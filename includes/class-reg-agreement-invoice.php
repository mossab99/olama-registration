<?php
/**
 * Agreement accounting workflow: agreement -> invoice -> due schedule -> receipts.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Olama_Reg_Agreement_Invoice {

    private static function t( string $name ): string {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    public static function get_due_schedule( int $agreement_id ): array {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . self::t( 'olama_invoice_installments' ) . "
             WHERE agreement_id = %d
             ORDER BY installment_no ASC, id ASC",
            $agreement_id
        ) ) ?: [];
    }

    public static function save_due_schedule( int $agreement_id, array $lines, int $invoice_id = 0 ): bool|\WP_Error {
        global $wpdb;

        $agreement = Olama_Reg_Agreement::get( $agreement_id );
        if ( ! $agreement ) {
            return new \WP_Error( 'not_found', __( 'العقد غير موجود.', 'olama-registration' ) );
        }

        $paid_total = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(amount_paid), 0) FROM " . self::t( 'olama_invoices' ) . " WHERE agreement_id = %d AND status != 'cancelled'",
            $agreement_id
        ) );
        if ( $paid_total > 0 ) {
            return new \WP_Error( 'schedule_locked', __( 'لا يمكن تعديل توزيع الاستحقاق بعد تسجيل مدفوعات على الفاتورة.', 'olama-registration' ) );
        }

        $clean = [];
        $no = 1;
        foreach ( $lines as $line ) {
            $due_date = self::sanitize_date( $line['due_date'] ?? '' );
            $amount = round( (float) ( $line['amount'] ?? $line['amount_due'] ?? 0 ), 2 );

            if ( ! $due_date || $amount <= 0 ) {
                continue;
            }

            $clean[] = [
                'installment_no' => $no++,
                'due_date'       => $due_date,
                'amount_due'     => $amount,
            ];
        }

        if ( empty( $clean ) ) {
            $start = self::sanitize_date( $agreement->start_date ?: current_time( 'Y-m-d' ) );
            $clean[] = [
                'installment_no' => 1,
                'due_date'       => $start ?: current_time( 'Y-m-d' ),
                'amount_due'     => round( (float) $agreement->total_amount, 2 ),
            ];
        }

        $wpdb->delete( self::t( 'olama_invoice_installments' ), [ 'agreement_id' => $agreement_id ] );

        foreach ( $clean as $line ) {
            $wpdb->insert( self::t( 'olama_invoice_installments' ), [
                'invoice_id'      => $invoice_id,
                'agreement_id'    => $agreement_id,
                'installment_no'  => $line['installment_no'],
                'due_date'        => $line['due_date'],
                'amount_due'      => $line['amount_due'],
                'amount_paid'     => 0.00,
                'status'          => self::initial_due_status( $line['due_date'] ),
            ] );
        }

        return true;
    }

    public static function generate_default_due_schedule( int $agreement_id ): bool|\WP_Error {
        $agreement = Olama_Reg_Agreement::get( $agreement_id );
        if ( ! $agreement ) {
            return new \WP_Error( 'not_found', __( 'العقد غير موجود.', 'olama-registration' ) );
        }

        $fees = Olama_Reg_Agreement_Fees::get_by_agreement( $agreement_id );
        $template_id = 0;
        foreach ( $fees as $fee ) {
            if ( is_numeric( $fee->fee_category ) && (int) $fee->fee_category > 0 ) {
                $template_id = (int) $fee->fee_category;
                break;
            }
        }

        $count = 1;
        if ( $template_id ) {
            $template = Olama_Reg_Billing_Fees::get_template( $template_id );
            if ( $template && ( $template->subject_type ?? '' ) === 'agreement' ) {
                $count = max( 1, (int) ( $template->installments ?? 1 ) );
            }
        }

        $total = round( (float) $agreement->total_amount, 2 );
        $start = self::sanitize_date( $agreement->start_date ?: current_time( 'Y-m-d' ) );
        $base = $count > 0 ? floor( ( $total / $count ) * 100 ) / 100 : $total;
        $lines = [];
        $allocated = 0.0;

        for ( $i = 1; $i <= $count; $i++ ) {
            $date = new \DateTime( $start ?: current_time( 'Y-m-d' ) );
            $date->modify( '+' . ( $i - 1 ) . ' month' );
            $amount = ( $i === $count ) ? round( $total - $allocated, 2 ) : $base;
            $allocated = round( $allocated + $amount, 2 );

            $lines[] = [
                'due_date' => $date->format( 'Y-m-d' ),
                'amount'   => $amount,
            ];
        }

        return self::save_due_schedule( $agreement_id, $lines );
    }

    public static function validate_completion( int $agreement_id ): true|\WP_Error {
        global $wpdb;

        $agreement = Olama_Reg_Agreement::get( $agreement_id );
        if ( ! $agreement ) {
            return new \WP_Error( 'not_found', __( 'العقد غير موجود.', 'olama-registration' ) );
        }

        $errors = [];
        if ( empty( $agreement->payer_id ) ) {
            $errors[] = __( 'يجب اختيار الجهة الدافعة.', 'olama-registration' );
        }
        if ( empty( $agreement->activity_type ) ) {
            $errors[] = __( 'يجب اختيار طبيعة العقد.', 'olama-registration' );
        }
        if ( empty( $agreement->start_date ) ) {
            $errors[] = __( 'تاريخ بداية العقد مطلوب.', 'olama-registration' );
        }
        if ( empty( $agreement->end_date ) ) {
            $errors[] = __( 'تاريخ نهاية العقد مطلوب.', 'olama-registration' );
        }
        if ( ! empty( $agreement->start_date ) && ! empty( $agreement->end_date ) && $agreement->end_date < $agreement->start_date ) {
            $errors[] = __( 'تاريخ نهاية العقد لا يمكن أن يكون قبل تاريخ البداية.', 'olama-registration' );
        }

        $fees = Olama_Reg_Agreement_Fees::get_by_agreement( $agreement_id );
        if ( empty( $fees ) ) {
            $errors[] = __( 'يجب إضافة بند رسوم واحد على الأقل.', 'olama-registration' );
        }
        if ( round( (float) $agreement->total_amount, 2 ) <= 0 ) {
            $errors[] = __( 'صافي العقد يجب أن يكون أكبر من صفر.', 'olama-registration' );
        }

        $clauses_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::t( 'olama_agreement_clauses' ) . " WHERE agreement_id = %d AND TRIM(clause_text) != ''",
            $agreement_id
        ) );
        if ( $clauses_count < 1 ) {
            $errors[] = __( 'يجب اختيار بند أو شرط واحد على الأقل.', 'olama-registration' );
        }

        $schedule = self::get_due_schedule( $agreement_id );
        if ( empty( $schedule ) ) {
            $errors[] = __( 'يجب إنشاء توزيع الاستحقاق قبل إكمال العقد.', 'olama-registration' );
        } else {
            $due_total = round( array_sum( array_map( static fn( $line ) => (float) $line->amount_due, $schedule ) ), 2 );
            $agreement_total = round( (float) $agreement->total_amount, 2 );
            if ( abs( $due_total - $agreement_total ) > 0.009 ) {
                $errors[] = __( 'مجموع الاستحقاقات لا يساوي صافي العقد. يرجى تعديل توزيع الاستحقاق قبل الحفظ.', 'olama-registration' );
            }
        }

        if ( $errors ) {
            return new \WP_Error( 'completion_validation_failed', implode( "\n", $errors ) );
        }

        return true;
    }

    public static function complete_agreement( int $agreement_id ): bool|\WP_Error {
        global $wpdb;

        $validation = self::validate_completion( $agreement_id );
        if ( is_wp_error( $validation ) ) {
            Olama_Reg_Agreement::update( $agreement_id, [ 'status' => 'draft' ] );
            return $validation;
        }

        $wpdb->query( 'START TRANSACTION' );
        $invoice_id = self::generate_invoice( $agreement_id );

        if ( is_wp_error( $invoice_id ) ) {
            $wpdb->query( 'ROLLBACK' );
            Olama_Reg_Agreement::update( $agreement_id, [ 'status' => 'draft' ] );
            return $invoice_id;
        }

        $wpdb->update(
            self::t( 'olama_invoice_installments' ),
            [ 'invoice_id' => (int) $invoice_id ],
            [ 'agreement_id' => $agreement_id ]
        );

        $fees = Olama_Reg_Agreement_Fees::get_by_agreement( $agreement_id );
        foreach ( $fees as $fee ) {
            Olama_Reg_Agreement_Fees::mark_invoiced( (int) $fee->id, (int) $invoice_id );
        }

        Olama_Reg_Agreement::update( $agreement_id, [ 'status' => 'completed' ] );
        $wpdb->query( 'COMMIT' );

        return true;
    }

    public static function generate_invoice( int $agreement_id, array $fee_ids = [] ): int|\WP_Error {
        global $wpdb;

        $agreement = Olama_Reg_Agreement::get( $agreement_id );
        if ( ! $agreement ) {
            return new \WP_Error( 'not_found', __( 'العقد غير موجود.', 'olama-registration' ) );
        }

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::t( 'olama_invoices' ) . " WHERE agreement_id = %d AND status != 'cancelled' ORDER BY id ASC LIMIT 1",
            $agreement_id
        ) );

        if ( $existing && (float) $existing->amount_paid > 0 ) {
            return new \WP_Error( 'invoice_has_payments', __( 'لا يمكن تعديل فاتورة مرتبطة بمدفوعات. يلزم إجراء تسوية أو إشعار دائن.', 'olama-registration' ) );
        }

        $fees = Olama_Reg_Agreement_Fees::get_by_agreement( $agreement_id );
        if ( empty( $fees ) ) {
            return new \WP_Error( 'no_fees', __( 'لا توجد رسوم صالحة للفوترة.', 'olama-registration' ) );
        }

        $items = [];
        foreach ( $fees as $fee ) {
            $items[] = [
                'description' => $fee->label ?: __( 'رسوم عقد', 'olama-registration' ),
                'quantity'    => 1,
                'unit_price'  => (float) $fee->net_amount,
            ];
        }

        $year_id = (int) ( $agreement->academic_year_id ?? 0 );
        if ( ! $year_id && class_exists( 'Olama_School_Academic' ) ) {
            $active_year = Olama_School_Academic::get_active_year();
            if ( $active_year ) {
                $year_id = (int) $active_year->id;
            }
        }
        if ( ! $year_id ) {
            return new \WP_Error( 'missing_year', __( 'لا يوجد عام دراسي محدد للعقد.', 'olama-registration' ) );
        }

        $invoice_data = [
            'academic_year_id'    => $year_id,
            'issue_date'          => current_time( 'Y-m-d' ),
            'status'              => 'issued',
            'notes'               => sprintf( __( 'فاتورة من العقد رقم: %s', 'olama-registration' ), $agreement->agreement_number ),
            'items'               => $items,
            'discount'            => 0,
            'linked_agreement_id' => $agreement->id,
            'agreement_id'        => $agreement->id,
        ];

        if ( $agreement->payer_type === 'customer' ) {
            $invoice_data['ext_customer_id'] = absint( $agreement->payer_id );
            $customer = Olama_Reg_Customer::get( (int) $agreement->payer_id );
            $invoice_data['family_uid'] = $customer ? $customer->customer_uid : 'CUST-' . str_pad( (string) $agreement->payer_id, 4, '0', STR_PAD_LEFT );
            if ( $agreement->participant_type === 'child' ) {
                $invoice_data['ext_child_id'] = absint( $agreement->participant_id );
            }
        } else {
            $invoice_data['family_uid'] = (string) $agreement->payer_id;
            if ( $agreement->participant_type === 'student' ) {
                $invoice_data['student_uid'] = (string) $agreement->participant_id;
            }
        }

        if ( $existing ) {
            $updated = Olama_Reg_Billing_Invoice::update( (int) $existing->id, $invoice_data );
            if ( is_wp_error( $updated ) ) {
                return $updated;
            }
            return (int) $existing->id;
        }

        return Olama_Reg_Billing_Invoice::create( $invoice_data );
    }

    public static function cancel_agreement( int $agreement_id ): bool|\WP_Error {
        global $wpdb;

        $invoice = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::t( 'olama_invoices' ) . " WHERE agreement_id = %d AND status != 'cancelled' ORDER BY id ASC LIMIT 1",
            $agreement_id
        ) );

        if ( $invoice && (float) $invoice->amount_paid > 0 ) {
            return new \WP_Error( 'has_payments', __( 'لا يمكن إلغاء العقد مباشرة لوجود مدفوعات مرتبطة بالفاتورة.', 'olama-registration' ) );
        }

        if ( $invoice ) {
            $cancelled = Olama_Reg_Billing_Invoice::cancel( (int) $invoice->id );
            if ( is_wp_error( $cancelled ) ) {
                return $cancelled;
            }
        }

        return Olama_Reg_Agreement::update( $agreement_id, [ 'status' => 'cancelled' ] );
    }

    public static function get_active_academic_year_end_date(): string {
        global $wpdb;

        if ( ! class_exists( 'Olama_School_Academic' ) ) {
            return '';
        }

        $active_year = Olama_School_Academic::get_active_year();
        if ( ! $active_year || empty( $active_year->id ) ) {
            return '';
        }

        foreach ( [ 'end_date', 'year_end_date', 'date_end' ] as $field ) {
            if ( ! empty( $active_year->{$field} ) ) {
                return self::sanitize_date( $active_year->{$field} );
            }
        }

        $columns = $wpdb->get_col( "DESCRIBE {$wpdb->prefix}olama_academic_years", 0 );
        foreach ( [ 'end_date', 'year_end_date', 'date_end' ] as $field ) {
            if ( in_array( $field, (array) $columns, true ) ) {
                $date = $wpdb->get_var( $wpdb->prepare(
                    "SELECT {$field} FROM {$wpdb->prefix}olama_academic_years WHERE id = %d",
                    (int) $active_year->id
                ) );
                if ( $date ) {
                    return self::sanitize_date( $date );
                }
            }
        }

        return '';
    }

    private static function initial_due_status( string $due_date ): string {
        return ( $due_date < current_time( 'Y-m-d' ) ) ? 'overdue' : 'unpaid';
    }

    private static function sanitize_date( string $val ): string {
        $raw = sanitize_text_field( $val );
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ? $raw : '';
    }
}
