<?php
/**
 * Central rules for agreement financial locks and amendment eligibility.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Olama_Reg_Agreement_Policy {

    private static function t( string $name ): string {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    public static function get_linked_invoice( int $agreement_id ): ?object {
        global $wpdb;

        $invoice = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::t( 'olama_invoices' ) . "
             WHERE agreement_id = %d AND status != 'cancelled'
             ORDER BY id ASC
             LIMIT 1",
            $agreement_id
        ) );

        if ( $invoice ) {
            return $invoice;
        }

        $agreement = class_exists( 'Olama_Reg_Agreement' ) ? Olama_Reg_Agreement::get( $agreement_id ) : null;
        if ( ! $agreement ) {
            return null;
        }

        $customer_uid = '';
        if ( (string) $agreement->payer_type === 'customer' && ! empty( $agreement->payer_id ) ) {
            $customer_uid = (string) $wpdb->get_var( $wpdb->prepare(
                "SELECT customer_uid FROM " . self::t( 'olama_customers' ) . " WHERE id = %d LIMIT 1",
                (int) $agreement->payer_id
            ) );
        }

        $note_like = '%' . $wpdb->esc_like( (string) $agreement->agreement_number ) . '%';

        if ( (string) $agreement->payer_type === 'customer' ) {
            $invoice = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM " . self::t( 'olama_invoices' ) . "
                 WHERE status != 'cancelled'
                   AND notes LIKE %s
                   AND (
                        ext_customer_id = %d
                        OR ( %s != '' AND family_uid = %s )
                   )
                 ORDER BY id ASC
                 LIMIT 1",
                $note_like,
                (int) $agreement->payer_id,
                $customer_uid,
                $customer_uid
            ) );
        } else {
            $invoice = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM " . self::t( 'olama_invoices' ) . "
                 WHERE status != 'cancelled'
                   AND notes LIKE %s
                   AND family_uid = %s
                 ORDER BY id ASC
                 LIMIT 1",
                $note_like,
                (string) $agreement->payer_id
            ) );
        }

        return $invoice ?: null;
    }

    public static function get_linked_invoice_id( int $agreement_id ): int {
        $invoice = self::get_linked_invoice( $agreement_id );
        return $invoice ? (int) $invoice->id : 0;
    }

    public static function has_invoice( int $agreement_id ): bool {
        return self::get_linked_invoice_id( $agreement_id ) > 0;
    }

    public static function has_installments( int $agreement_id ): bool {
        global $wpdb;

        $invoice_id = self::get_linked_invoice_id( $agreement_id );
        if ( $invoice_id <= 0 ) {
            return false;
        }

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::t( 'olama_invoice_installments' ) . "
             WHERE invoice_id = %d OR (agreement_id = %d AND invoice_id > 0)",
            $invoice_id,
            $agreement_id
        ) );

        return $count > 0;
    }

    public static function has_paid_installments( int $agreement_id ): bool {
        global $wpdb;

        $invoice_id = self::get_linked_invoice_id( $agreement_id );
        $where = 'agreement_id = %d AND (amount_paid > 0 OR status IN ("partial", "partially_paid", "paid"))';
        $args = [ $agreement_id ];

        if ( $invoice_id > 0 ) {
            $where = '(agreement_id = %d OR invoice_id = %d) AND (amount_paid > 0 OR status IN ("partial", "partially_paid", "paid"))';
            $args = [ $agreement_id, $invoice_id ];
        }

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::t( 'olama_invoice_installments' ) . " WHERE {$where}",
            ...$args
        ) );

        return $count > 0;
    }

    public static function has_payments( int $agreement_id ): bool {
        global $wpdb;

        $invoice_id = self::get_linked_invoice_id( $agreement_id );
        if ( $invoice_id <= 0 ) {
            return false;
        }

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::t( 'olama_payments' ) . "
             WHERE invoice_id = %d AND status NOT IN ('cancelled', 'failed', 'void')",
            $invoice_id
        ) );

        return $count > 0;
    }

    public static function has_payment_allocations( int $agreement_id ): bool {
        global $wpdb;

        $invoice_id = self::get_linked_invoice_id( $agreement_id );
        if ( $invoice_id <= 0 ) {
            return false;
        }

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*)
             FROM " . self::t( 'olama_payment_allocations' ) . " pa
             INNER JOIN " . self::t( 'olama_invoice_installments' ) . " ii ON ii.id = pa.installment_id
             WHERE pa.invoice_id = %d OR ii.agreement_id = %d",
            $invoice_id,
            $agreement_id
        ) );

        return $count > 0;
    }

    public static function has_active_adjustments( int $agreement_id ): bool {
        global $wpdb;

        $invoice_id = self::get_linked_invoice_id( $agreement_id );
        if ( $invoice_id <= 0 ) {
            return false;
        }

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::t( 'olama_invoice_adjustments' ) . "
             WHERE invoice_id = %d AND status = 'issued'",
            $invoice_id
        ) );

        return $count > 0;
    }

    public static function has_closed_cash_session( int $agreement_id ): bool {
        global $wpdb;

        $invoice_id = self::get_linked_invoice_id( $agreement_id );
        if ( $invoice_id <= 0 ) {
            return false;
        }

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*)
             FROM " . self::t( 'olama_payments' ) . " p
             INNER JOIN " . self::t( 'olama_cash_sessions' ) . " cs ON cs.id = p.cash_session_id
             WHERE p.invoice_id = %d
               AND p.cash_session_id IS NOT NULL
               AND (cs.status IN ('closed', 'reviewed') OR cs.closed_at IS NOT NULL OR cs.reviewed_at IS NOT NULL)",
            $invoice_id
        ) );

        return $count > 0;
    }

    public static function is_financially_locked( int $agreement_id ): bool {
        return self::has_invoice( $agreement_id )
            || self::has_installments( $agreement_id )
            || self::has_payments( $agreement_id )
            || self::has_payment_allocations( $agreement_id )
            || self::has_active_adjustments( $agreement_id )
            || self::has_closed_cash_session( $agreement_id );
    }

    public static function get_financial_status( int $agreement_id ): string {
        $agreement = class_exists( 'Olama_Reg_Agreement' ) ? Olama_Reg_Agreement::get( $agreement_id ) : null;
        $invoice = self::get_linked_invoice( $agreement_id );

        if ( $agreement && $agreement->status === 'cancelled' && self::is_financially_locked( $agreement_id ) ) {
            return 'cancelled_with_financial_impact';
        }
        if ( self::has_closed_cash_session( $agreement_id ) || self::has_payment_allocations( $agreement_id ) || self::has_active_adjustments( $agreement_id ) ) {
            return 'financially_locked';
        }
        if ( $invoice && round( (float) $invoice->balance, 2 ) <= 0 ) {
            return 'paid';
        }
        if ( $invoice && (float) $invoice->amount_paid > 0 ) {
            return 'partially_paid';
        }
        if ( $invoice ) {
            return 'invoiced';
        }

        return 'open';
    }

    public static function get_lock_reasons( int $agreement_id ): array {
        $reasons = [];

        if ( self::has_invoice( $agreement_id ) ) {
            $reasons[] = __( 'تم إصدار فاتورة لهذا العقد.', 'olama-registration' );
        }
        if ( self::has_payments( $agreement_id ) ) {
            $reasons[] = __( 'يوجد دفعات مسجلة على فاتورة العقد.', 'olama-registration' );
        }
        if ( self::has_payment_allocations( $agreement_id ) || self::has_paid_installments( $agreement_id ) ) {
            $reasons[] = __( 'يوجد تسديد أو تخصيص على أقساط العقد.', 'olama-registration' );
        }
        if ( self::has_active_adjustments( $agreement_id ) ) {
            $reasons[] = __( 'يوجد إشعار دائن أو مدين فعال مرتبط بالفاتورة.', 'olama-registration' );
        }
        if ( self::has_closed_cash_session( $agreement_id ) ) {
            $reasons[] = __( 'يوجد دفعات ضمن صندوق مغلق أو مراجع.', 'olama-registration' );
        }

        return array_values( array_unique( $reasons ) );
    }

    public static function can_edit_admin_fields( int $agreement_id ): true|\WP_Error {
        return true;
    }

    public static function can_edit_financial_fields( int $agreement_id ): true|\WP_Error {
        if ( self::is_financially_locked( $agreement_id ) ) {
            return new \WP_Error(
                'agreement_financially_locked',
                self::build_error_message( __( 'لا يمكن تعديل بيانات العقد المالية مباشرة بعد وجود أثر مالي.', 'olama-registration' ), $agreement_id )
            );
        }

        return true;
    }

    public static function can_create_amendment( int $agreement_id ): true|\WP_Error {
        if ( ! class_exists( 'Olama_Reg_Agreement' ) || ! Olama_Reg_Agreement::get( $agreement_id ) ) {
            return new \WP_Error( 'agreement_not_found', __( 'العقد غير موجود.', 'olama-registration' ) );
        }
        if ( ! self::has_invoice( $agreement_id ) ) {
            return new \WP_Error( 'agreement_not_invoiced', __( 'يمكن تعديل مسودة العقد مباشرة قبل إصدار الفاتورة.', 'olama-registration' ) );
        }

        return true;
    }

    public static function can_reschedule_installments( int $agreement_id ): true|\WP_Error {
        if (
            self::has_payments( $agreement_id )
            || self::has_payment_allocations( $agreement_id )
            || self::has_paid_installments( $agreement_id )
            || self::has_closed_cash_session( $agreement_id )
        ) {
            return new \WP_Error(
                'agreement_schedule_locked',
                self::build_error_message( __( 'لا يمكن تعديل توزيع الاستحقاق بعد بدء التحصيل أو وجود قيود مالية مرتبطة.', 'olama-registration' ), $agreement_id )
            );
        }

        return true;
    }

    public static function can_cancel_agreement( int $agreement_id ): true|\WP_Error {
        if (
            self::has_payments( $agreement_id )
            || self::has_payment_allocations( $agreement_id )
            || self::has_active_adjustments( $agreement_id )
            || self::has_closed_cash_session( $agreement_id )
        ) {
            return new \WP_Error(
                'agreement_cancel_locked',
                self::build_error_message( __( 'لا يمكن إلغاء العقد مباشرة بعد وجود دفعات أو قيود مالية. استخدم إجراء تعديل/تسوية.', 'olama-registration' ), $agreement_id )
            );
        }

        return true;
    }

    private static function build_error_message( string $prefix, int $agreement_id ): string {
        $reasons = self::get_lock_reasons( $agreement_id );
        if ( empty( $reasons ) ) {
            return $prefix;
        }

        return $prefix . ' ' . implode( ' ', $reasons );
    }
}
