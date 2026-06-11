<?php
/**
 * Immutable cash/bank movement ledger helpers.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Olama_Reg_Cash_Bank_Movement {

    private static function t( string $name ): string {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    public static function record_receipt_movement( int $payment_id ): int|\WP_Error {
        $payment = self::get_payment( $payment_id );
        if ( ! $payment ) {
            return new \WP_Error( 'payment_not_found', __( 'Payment not found.', 'olama-registration' ) );
        }

        if ( (string) ( $payment->status ?? 'posted' ) !== 'posted' || (float) $payment->amount <= 0 ) {
            return new \WP_Error( 'payment_not_posted', __( 'Only posted positive payments create receipt movements.', 'olama-registration' ) );
        }

        $account_id = (int) ( $payment->account_id ?? 0 );
        if ( ! $account_id ) {
            $account_id = self::get_default_account_id_for_method( (string) $payment->method );
        }

        if ( ! $account_id ) {
            return new \WP_Error( 'missing_account', __( 'No financial account is available for this receipt.', 'olama-registration' ) );
        }

        return self::record_movement( [
            'account_id'      => $account_id,
            'cash_session_id' => (int) ( $payment->cash_session_id ?? 0 ) ?: null,
            'movement_type'   => 'receipt',
            'source_type'     => 'payment',
            'source_id'       => $payment_id,
            'direction'       => 'in',
            'amount'          => (float) $payment->amount,
            'movement_date'   => $payment->payment_date ?: current_time( 'Y-m-d' ),
            'created_by'      => (int) ( $payment->received_by ?? get_current_user_id() ),
            'notes'           => self::payment_note( $payment ),
        ] );
    }

    public static function record_reversal_movement( int $reversal_payment_id, int $original_payment_id ): int|\WP_Error {
        $reversal = self::get_payment( $reversal_payment_id );
        $original = self::get_payment( $original_payment_id );
        if ( ! $reversal || ! $original ) {
            return new \WP_Error( 'payment_not_found', __( 'Payment not found.', 'olama-registration' ) );
        }

        $account_id = (int) ( $reversal->account_id ?? $original->account_id ?? 0 );
        if ( ! $account_id ) {
            $account_id = self::get_default_account_id_for_method( (string) $original->method );
        }

        if ( ! $account_id ) {
            return new \WP_Error( 'missing_account', __( 'No financial account is available for this reversal.', 'olama-registration' ) );
        }

        return self::record_movement( [
            'account_id'      => $account_id,
            'cash_session_id' => (int) ( $reversal->cash_session_id ?? $original->cash_session_id ?? 0 ) ?: null,
            'movement_type'   => 'receipt_reversal',
            'source_type'     => 'payment',
            'source_id'       => $reversal_payment_id,
            'direction'       => 'out',
            'amount'          => abs( (float) $reversal->amount ),
            'movement_date'   => $reversal->payment_date ?: current_time( 'Y-m-d' ),
            'created_by'      => (int) ( $reversal->received_by ?? get_current_user_id() ),
            'notes'           => self::payment_note( $reversal ),
        ] );
    }

    public static function record_transfer( int $from_account_id, int $to_account_id, float $amount ): int|\WP_Error {
        return new \WP_Error( 'not_implemented', __( 'Cash/bank transfers are scheduled for the transfer workflow phase.', 'olama-registration' ) );
    }

    public static function get_account_balance( int $account_id, ?string $date_to = null ): float {
        global $wpdb;

        $account = $wpdb->get_row( $wpdb->prepare(
            "SELECT opening_balance FROM " . self::t( 'olama_financial_accounts' ) . " WHERE id = %d",
            $account_id
        ) );
        if ( ! $account ) {
            return 0.0;
        }

        $where = 'account_id = %d AND status = %s';
        $params = [ $account_id, 'posted' ];
        if ( $date_to ) {
            $where .= ' AND movement_date <= %s';
            $params[] = $date_to;
        }

        $net = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0)
             FROM " . self::t( 'olama_cash_bank_movements' ) . "
             WHERE {$where}",
            ...$params
        ) );

        return round( (float) $account->opening_balance + $net, 2 );
    }

    public static function get_movements( array $filters = [] ): array {
        global $wpdb;

        $where = [ '1=1' ];
        $params = [];
        if ( ! empty( $filters['account_id'] ) ) {
            $where[] = 'm.account_id = %d';
            $params[] = absint( $filters['account_id'] );
        }
        if ( ! empty( $filters['date_from'] ) ) {
            $where[] = 'm.movement_date >= %s';
            $params[] = sanitize_text_field( $filters['date_from'] );
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where[] = 'm.movement_date <= %s';
            $params[] = sanitize_text_field( $filters['date_to'] );
        }

        $sql = "SELECT m.*, a.account_code, a.account_name, a.type AS account_type
                FROM " . self::t( 'olama_cash_bank_movements' ) . " m
                LEFT JOIN " . self::t( 'olama_financial_accounts' ) . " a ON a.id = m.account_id
                WHERE " . implode( ' AND ', $where ) . "
                ORDER BY m.movement_date DESC, m.id DESC";

        return $params ? ( $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) ) ?: [] ) : ( $wpdb->get_results( $sql ) ?: [] );
    }

    public static function get_default_account_id_for_method( string $method ): int {
        $map = [
            'cash'          => 'cash',
            'bank_transfer' => 'bank',
            'cheque'        => 'cheque_clearing',
            'online'        => 'electronic',
        ];

        return self::get_default_account_id( $map[ $method ] ?? 'cash' );
    }

    public static function get_default_account_id( string $type ): int {
        global $wpdb;

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . self::t( 'olama_financial_accounts' ) . "
             WHERE type = %s AND is_active = 1
             ORDER BY is_default DESC, id ASC
             LIMIT 1",
            $type
        ) );
    }

    private static function record_movement( array $data ): int|\WP_Error {
        global $wpdb;

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . self::t( 'olama_cash_bank_movements' ) . "
             WHERE source_type = %s AND source_id = %d AND movement_type = %s",
            $data['source_type'],
            (int) $data['source_id'],
            $data['movement_type']
        ) );
        if ( $existing ) {
            return (int) $existing;
        }

        if ( ! self::acquire_number_lock() ) {
            return new \WP_Error( 'number_lock_failed', __( 'Could not reserve a movement number. Please try again.', 'olama-registration' ) );
        }

        $payload = [
            'movement_no'     => self::generate_movement_no(),
            'account_id'      => (int) $data['account_id'],
            'cash_session_id' => $data['cash_session_id'] ?? null,
            'movement_type'   => sanitize_key( $data['movement_type'] ),
            'source_type'     => sanitize_key( $data['source_type'] ),
            'source_id'       => (int) $data['source_id'],
            'direction'       => $data['direction'] === 'out' ? 'out' : 'in',
            'amount'          => round( abs( (float) $data['amount'] ), 2 ),
            'movement_date'   => sanitize_text_field( $data['movement_date'] ?? current_time( 'Y-m-d' ) ),
            'status'          => sanitize_key( $data['status'] ?? 'posted' ),
            'created_by'      => (int) ( $data['created_by'] ?? get_current_user_id() ),
            'notes'           => sanitize_textarea_field( $data['notes'] ?? '' ) ?: null,
        ];

        $result = $wpdb->insert( self::t( 'olama_cash_bank_movements' ), $payload );
        self::release_number_lock();

        if ( ! $result ) {
            return new \WP_Error( 'db_error', $wpdb->last_error );
        }

        return (int) $wpdb->insert_id;
    }

    private static function get_payment( int $payment_id ): ?object {
        global $wpdb;

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::t( 'olama_payments' ) . " WHERE id = %d",
            $payment_id
        ) ) ?: null;
    }

    private static function payment_note( object $payment ): string {
        $receipt_no = method_exists( 'Olama_Reg_Billing_Payment', 'get_receipt_number' )
            ? Olama_Reg_Billing_Payment::get_receipt_number( $payment )
            : '#' . (int) $payment->id;

        return sprintf( 'Receipt %s / invoice %d', $receipt_no, (int) $payment->invoice_id );
    }

    private static function acquire_number_lock(): bool {
        global $wpdb;
        $locked = $wpdb->get_var( "SELECT GET_LOCK('olama_reg_movement_number', 5)" );

        return (string) $locked === '1';
    }

    private static function release_number_lock(): void {
        global $wpdb;
        $wpdb->get_var( "SELECT RELEASE_LOCK('olama_reg_movement_number')" );
    }

    private static function generate_movement_no(): string {
        global $wpdb;

        $year = current_time( 'Y' );
        $base = 'MOV-' . $year . '-';
        $latest = (string) $wpdb->get_var( $wpdb->prepare(
            "SELECT movement_no
             FROM " . self::t( 'olama_cash_bank_movements' ) . "
             WHERE movement_no LIKE %s
             ORDER BY movement_no DESC
             LIMIT 1",
            $wpdb->esc_like( $base ) . '%'
        ) );

        $next = 1;
        if ( preg_match( '/^' . preg_quote( $base, '/' ) . '(\d+)$/', $latest, $matches ) ) {
            $next = (int) $matches[1] + 1;
        }

        return $base . str_pad( (string) $next, 5, '0', STR_PAD_LEFT );
    }
}
