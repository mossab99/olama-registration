<?php
/**
 * Payment recording and allocation against invoice installments.
 *
 * Tables used:
 *   {prefix}olama_payments
 *   {prefix}olama_invoice_installments
 *   {prefix}olama_invoices
 *   {prefix}olama_billing_audit
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Olama_Reg_Billing_Payment {

    private static function t( string $name ): string {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    // ── Record a payment ──────────────────────────────────────────────────────

    /**
     * Record a new payment and allocate it to installments.
     *
     * @param array $data {
     *   invoice_id, installment_id (optional), family_uid,
     *   payment_date, amount, method, reference, notes
     * }
     * @return int|WP_Error
     */
    public static function record( array $data ): int|\WP_Error {
        global $wpdb;

        $invoice_id = absint( $data['invoice_id'] ?? 0 );
        if ( ! $invoice_id ) {
            return new \WP_Error( 'missing_invoice', __( 'Invoice ID is required.', 'olama-registration' ) );
        }

        $amount = round( (float) ( $data['amount'] ?? 0 ), 2 );
        if ( $amount <= 0 ) {
            return new \WP_Error( 'invalid_amount', __( 'Payment amount must be greater than zero.', 'olama-registration' ) );
        }

        $family_uid = sanitize_text_field( $data['family_uid'] ?? '' );

        // Resolve family_uid from invoice if not supplied
        if ( ! $family_uid ) {
            $family_uid = (string) $wpdb->get_var( $wpdb->prepare(
                "SELECT family_uid FROM " . self::t( 'olama_invoices' ) . " WHERE id = %d",
                $invoice_id
            ) );
        }

        $payment_date = '';
        if ( ! empty( $data['payment_date'] ) ) {
            $raw = sanitize_text_field( $data['payment_date'] );
            $payment_date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ? $raw : date( 'Y-m-d' );
        } else {
            $payment_date = date( 'Y-m-d' );
        }

        $valid_methods = [ 'cash', 'bank_transfer', 'cheque', 'online' ];
        $method = sanitize_text_field( $data['method'] ?? 'cash' );
        if ( ! in_array( $method, $valid_methods, true ) ) $method = 'cash';

        $payload = [
            'invoice_id'     => $invoice_id,
            'installment_id' => absint( $data['installment_id'] ?? 0 ) ?: null,
            'family_uid'     => $family_uid,
            'payment_date'   => $payment_date,
            'amount'         => $amount,
            'method'         => $method,
            'reference'      => sanitize_text_field( $data['reference'] ?? '' ) ?: null,
            'received_by'    => get_current_user_id(),
            'notes'          => sanitize_textarea_field( $data['notes'] ?? '' ) ?: null,
        ];

        // ── Transaction: insert payment + update totals ────────────────────────
        $wpdb->query( 'START TRANSACTION' );

        $result = $wpdb->insert( self::t( 'olama_payments' ), $payload );

        if ( ! $result ) {
            $wpdb->query( 'ROLLBACK' );
            return new \WP_Error( 'db_error', $wpdb->last_error );
        }

        $payment_id = (int) $wpdb->insert_id;

        // Allocate to installments
        self::allocate_to_installments( $payment_id );

        // Recalculate invoice totals (also handles status auto-update)
        Olama_Reg_Billing_Invoice::recalculate_totals( $invoice_id );

        $wpdb->query( 'COMMIT' );

        // Audit
        self::log_audit( 'payment', $payment_id, 'created', null,
            self::get_payment_row( $payment_id ) );

        return $payment_id;
    }

    /**
     * Reverse a payment by inserting a negative counterpart and recalculating installments.
     */
    public static function reverse( int $id, string $notes = '' ): int|\WP_Error {
        global $wpdb;

        $payment = self::get_payment_row( $id );
        if ( ! $payment ) {
            return new \WP_Error( 'not_found', __( 'Payment not found.', 'olama-registration' ) );
        }

        if ( (float) $payment->amount <= 0 || $payment->method === 'reversal' ) {
            return new \WP_Error( 'already_reversed', __( 'لا يمكن عكس هذا السند.', 'olama-registration' ) );
        }

        // Check if already reversed
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . self::t( 'olama_payments' ) . " WHERE reference = %s",
            'REVERSAL-' . $id
        ) );
        if ( $exists ) {
            return new \WP_Error( 'already_reversed', __( 'السند معكوس مسبقاً.', 'olama-registration' ) );
        }

        $payload = [
            'invoice_id'     => $payment->invoice_id,
            'installment_id' => $payment->installment_id,
            'family_uid'     => $payment->family_uid,
            'payment_date'   => date( 'Y-m-d' ),
            'amount'         => -1 * (float) $payment->amount,
            'method'         => 'reversal',
            'reference'      => 'REVERSAL-' . $id,
            'received_by'    => get_current_user_id(),
            'notes'          => sanitize_textarea_field( $notes ) ?: __( 'عكس سند قبض رقم', 'olama-registration' ) . ' #' . $id,
        ];

        $wpdb->query( 'START TRANSACTION' );

        $result = $wpdb->insert( self::t( 'olama_payments' ), $payload );
        if ( ! $result ) {
            $wpdb->query( 'ROLLBACK' );
            return new \WP_Error( 'db_error', $wpdb->last_error );
        }
        $new_payment_id = (int) $wpdb->insert_id;

        // Reset all installments and recalculate
        self::reallocate_all_installments( (int) $payment->invoice_id );

        // Recalculate invoice totals
        Olama_Reg_Billing_Invoice::recalculate_totals( (int) $payment->invoice_id );

        $wpdb->query( 'COMMIT' );

        self::log_audit( 'payment', $id, 'reversed', $payment, self::get_payment_row( $id ) );

        return $new_payment_id;
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    public static function get_invoice_payments( int $invoice_id ): array {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT p.*, u.display_name AS received_by_name
             FROM " . self::t( 'olama_payments' ) . " p
             LEFT JOIN {$wpdb->users} u ON u.ID = p.received_by
             WHERE p.invoice_id = %d
             ORDER BY p.payment_date ASC, p.id ASC",
            $invoice_id
        ) ) ?: [];
    }

    public static function get_family_payments( string $family_uid, int $year_id ): array {
        global $wpdb;

        $params      = [ $family_uid ];
        $year_clause = '';
        if ( $year_id > 0 ) {
            $year_clause = ' AND i.academic_year_id = %d';
            $params[]    = $year_id;
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT p.*, i.invoice_number, u.display_name AS received_by_name
             FROM " . self::t( 'olama_payments' ) . " p
             LEFT JOIN " . self::t( 'olama_invoices' ) . " i ON i.id = p.invoice_id
             LEFT JOIN {$wpdb->users} u ON u.ID = p.received_by
             WHERE p.family_uid = %s {$year_clause}
             ORDER BY p.payment_date DESC, p.id DESC",
            ...$params
        ) ) ?: [];
    }

    // ── Installment allocation ────────────────────────────────────────────────

    /**
     * Allocate a payment to the oldest unpaid installments first.
     * Carries over remainder to subsequent installments.
     */
    public static function allocate_to_installments( int $payment_id ): void {
        global $wpdb;

        $payment = self::get_payment_row( $payment_id );
        if ( ! $payment ) return;

        $remaining = (float) $payment->amount;
        if ( $remaining <= 0 ) return;

        // Get unpaid/partial installments ordered oldest first
        $installments = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . self::t( 'olama_invoice_installments' ) . "
             WHERE invoice_id = %d
               AND status IN ('pending','partial','overdue')
             ORDER BY installment_no ASC",
            (int) $payment->invoice_id
        ) );

        if ( empty( $installments ) ) return;

        foreach ( $installments as $inst ) {
            if ( $remaining <= 0 ) break;

            $outstanding = (float) $inst->amount_due - (float) $inst->amount_paid;
            if ( $outstanding <= 0 ) continue;

            $apply = min( $remaining, $outstanding );
            $new_paid = round( (float) $inst->amount_paid + $apply, 2 );
            $remaining = round( $remaining - $apply, 2 );

            $new_status = 'partial';
            if ( $new_paid >= (float) $inst->amount_due ) {
                $new_status = 'paid';
            } elseif ( ! empty( $inst->due_date ) && $inst->due_date < date( 'Y-m-d' ) ) {
                $new_status = 'overdue';
            }

            $wpdb->update(
                self::t( 'olama_invoice_installments' ),
                [
                    'amount_paid' => $new_paid,
                    'status'      => $new_status,
                ],
                [ 'id' => (int) $inst->id ]
            );
        }
    }

    /**
     * Completely recalculates all installment allocations for an invoice.
     * Used when a payment is reversed to ensure accuracy.
     */
    public static function reallocate_all_installments( int $invoice_id ): void {
        global $wpdb;

        // 1. Reset all installments amount_paid = 0 and status based on due_date
        $wpdb->query( $wpdb->prepare(
            "UPDATE " . self::t( 'olama_invoice_installments' ) . " 
             SET amount_paid = 0, 
                 status = IF(due_date < CURDATE(), 'overdue', 'pending')
             WHERE invoice_id = %d",
            $invoice_id
        ) );

        // 2. Get net sum of all payments for this invoice
        $net_paid = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(amount) FROM " . self::t( 'olama_payments' ) . " WHERE invoice_id = %d",
            $invoice_id
        ) );

        if ( $net_paid <= 0 ) return;

        // 3. Re-apply to installments in order
        $installments = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . self::t( 'olama_invoice_installments' ) . "
             WHERE invoice_id = %d
             ORDER BY installment_no ASC",
            $invoice_id
        ) );

        $remaining = $net_paid;
        foreach ( $installments as $inst ) {
            if ( $remaining <= 0 ) break;

            $outstanding = (float) $inst->amount_due;
            if ( $outstanding <= 0 ) continue;

            $apply = min( $remaining, $outstanding );
            $new_paid = $apply;
            $remaining = round( $remaining - $apply, 2 );

            $new_status = 'partial';
            if ( $new_paid >= (float) $inst->amount_due ) {
                $new_status = 'paid';
            } elseif ( ! empty( $inst->due_date ) && $inst->due_date < date( 'Y-m-d' ) ) {
                $new_status = 'overdue';
            }

            $wpdb->update(
                self::t( 'olama_invoice_installments' ),
                [
                    'amount_paid' => $new_paid,
                    'status'      => $new_status,
                ],
                [ 'id' => (int) $inst->id ]
            );
        }
    }

    // ── Receipt data ──────────────────────────────────────────────────────────

    /**
     * Build an array of receipt data for printing / display.
     */
    public static function generate_receipt_data( int $payment_id ): array {
        global $wpdb;

        $payment = self::get_payment_row( $payment_id );
        if ( ! $payment ) return [];

        $invoice = Olama_Reg_Billing_Invoice::get_invoice( (int) $payment->invoice_id );

        $family = null;
        if ( $payment->family_uid ) {
            $family = $wpdb->get_row( $wpdb->prepare(
                "SELECT family_uid, father_first_name, father_family_name FROM {$wpdb->prefix}olama_families WHERE family_uid = %s",
                $payment->family_uid
            ) );
        }

        $received_by_name = '';
        if ( $payment->received_by ) {
            $user = get_userdata( (int) $payment->received_by );
            $received_by_name = $user ? $user->display_name : '';
        }

        return [
            'payment'          => $payment,
            'invoice'          => $invoice,
            'family'           => $family,
            'received_by_name' => $received_by_name,
            'generated_at'     => current_time( 'mysql' ),
        ];
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private static function get_payment_row( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::t( 'olama_payments' ) . " WHERE id = %d",
            $id
        ) ) ?: null;
    }

    private static function log_audit(
        string  $entity_type,
        int     $entity_id,
        string  $action,
        ?object $before,
        ?object $after
    ): void {
        global $wpdb;

        $wpdb->insert( self::t( 'olama_billing_audit' ), [
            'entity_type'  => sanitize_text_field( $entity_type ),
            'entity_id'    => $entity_id,
            'action'       => sanitize_text_field( $action ),
            'actor_id'     => get_current_user_id(),
            'before_state' => $before ? wp_json_encode( $before ) : null,
            'after_state'  => $after  ? wp_json_encode( $after )  : null,
            'ip_address'   => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
        ] );
    }
}
