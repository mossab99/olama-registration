<?php
/**
 * AJAX Endpoints — all 13 actions in one class
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Olama_Reg_Ajax {

    public function __construct() {
        $actions = [
            'olama_reg_get_family',
            'olama_reg_get_student',

            'olama_reg_save_financial_row',
            'olama_reg_delete_financial_row',
            'olama_reg_get_financial',
            'olama_reg_search',
            'olama_reg_upload_photo',
            'olama_reg_save_fee_template',
            'olama_reg_delete_fee_template',
            'olama_reg_create_invoice',
            'olama_reg_update_invoice',
            'olama_reg_get_invoice',
            'olama_reg_cancel_invoice',
            'olama_reg_record_payment',
            'olama_reg_reverse_payment',
            'olama_reg_get_receipt',
            'olama_reg_get_family_billing',
            'olama_reg_get_family_students',
            'olama_reg_save_custom_payment',
            'olama_reg_create_settlement',
            'olama_reg_settle_receipt',
            'olama_reg_cancel_settlement',
            'olama_reg_create_external_customer',
        ];

        foreach ( $actions as $action ) {
            $method = 'ajax_' . str_replace( 'olama_reg_', '', $action );
            add_action( 'wp_ajax_' . $action, [ $this, $method ] );
        }
    }

    // ── Guard ─────────────────────────────────────────────────────────────────

    private function guard(): void {
        check_ajax_referer( 'olama_reg_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'olama-registration' ) ], 403 );
        }
    }

    // ── Family ────────────────────────────────────────────────────────────────

    public function ajax_get_family(): void {
        $this->guard();

        $family_uid = sanitize_text_field( $_POST['family_uid'] ?? '' );
        $family     = Olama_Reg_Family::get_family( $family_uid );

        if ( ! $family ) {
            wp_send_json_error( [ 'message' => __( 'Family not found.', 'olama-registration' ) ] );
        }

        $students = Olama_Reg_Student::get_family_students( $family_uid );

        wp_send_json_success( [
            'family'   => $family,
            'students' => $students,
        ] );
    }

    public function ajax_create_external_customer(): void {
        try {
            $this->guard();

            $name  = sanitize_text_field( $_POST['name'] ?? '' );
            $phone = sanitize_text_field( $_POST['phone'] ?? '' );

            if ( empty( $name ) || empty( $phone ) ) {
                wp_send_json_error( [ 'message' => __( 'Name and Phone are required.', 'olama-registration' ) ] );
            }

            global $wpdb;
            $table = $wpdb->prefix . 'olama_families';

            // Check if phone exists in father_mobile or mother_mobile
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT family_uid, family_name FROM {$table} WHERE father_mobile = %s OR mother_mobile = %s LIMIT 1",
                $phone, $phone
            ) );

            $children = json_decode( stripslashes( $_POST['children'] ?? '[]' ), true );
            $student_uids = [];

            if ( $existing ) {
                // Prevent linking to internal families
                if ( strpos( $existing->family_name, '[Ext]' ) === false && strpos( $existing->family_name, '(External)' ) === false ) {
                    wp_send_json_error( [ 'message' => __( 'رقم الهاتف هذا مستخدم بالفعل لعائلة مسجلة مسبقاً. يرجى استخدام نافذة (عائلة مسجلة) أو إدخال رقم مختلف.', 'olama-registration' ) ] );
                }

                $family_uid = $existing->family_uid;
                $is_new = false;
            } else {
                // Generate a new family UID
                if (!class_exists('Olama_Reg_ID_Generator')) {
                    wp_send_json_error( [ 'message' => 'Id generator class not found' ] );
                }
                $family_uid = Olama_Reg_ID_Generator::next_family_uid();

                if ( ! $family_uid ) {
                    wp_send_json_error( [ 'message' => __( 'Could not generate family UID.', 'olama-registration' ) ] );
                }

                // Insert new external customer
                $inserted = $wpdb->insert(
                    $table,
                    [
                        'family_uid'         => $family_uid,
                        'family_name'        => $name . ' [Ext]',
                        'father_first_name'  => $name,
                        'father_mobile'      => $phone,
                    ],
                    [ '%s', '%s', '%s', '%s' ]
                );

                if ( ! $inserted ) {
                    wp_send_json_error( [ 'message' => 'Insert Family Error: ' . $wpdb->last_error ] );
                }
                $is_new = true;
            }

            // Process children
            if ( is_array( $children ) && ! empty( $children ) ) {
                $students_table = $wpdb->prefix . 'olama_students';
                foreach ( $children as $child ) {
                    $child_name = sanitize_text_field( $child['name'] ?? '' );
                    if ( empty( $child_name ) ) continue;
                    
                    $child_grade = sanitize_text_field( $child['grade'] ?? '' );
                    $full_child_name = $child_name . ' [Ext]';

                    // Check if this exact external child already exists for this family
                    $existing_child = $wpdb->get_row( $wpdb->prepare(
                        "SELECT student_uid FROM {$students_table} WHERE family_id = %s AND student_name = %s LIMIT 1",
                        $family_uid, $full_child_name
                    ) );

                    if ( $existing_child ) {
                        $student_uids[] = $existing_child->student_uid;
                    } else {
                        $new_student_uid = uniqid( 'ext_stu_' );
                        $inserted_student = $wpdb->insert(
                            $students_table,
                            [
                                'student_uid'  => $new_student_uid,
                                'family_id'    => $family_uid,
                                'student_name' => $full_child_name,
                                'national_id'  => $child_grade,
                                'is_active'    => 1,
                            ],
                            [ '%s', '%s', '%s', '%s', '%d' ]
                        );
                        if (!$inserted_student) {
                            wp_send_json_error( [ 'message' => 'Insert Student Error: ' . $wpdb->last_error ] );
                        }
                        $student_uids[] = $new_student_uid;
                    }
                }
            }

            wp_send_json_success( [
                'message'      => __( 'External customer processed successfully.', 'olama-registration' ),
                'family_uid'   => $family_uid,
                'student_uids' => $student_uids,
                'is_new'       => $is_new,
            ] );
        } catch (\Throwable $e) {
            wp_send_json_error([ 'message' => 'Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() ]);
        }
    }

    // ── Student ───────────────────────────────────────────────────────────────

    public function ajax_get_student(): void {
        $this->guard();

        $student_uid = sanitize_text_field( $_POST['student_uid'] ?? '' );
        $student     = Olama_Reg_Student::get_student( $student_uid );

        if ( ! $student ) {
            wp_send_json_error( [ 'message' => __( 'Student not found.', 'olama-registration' ) ] );
        }

        global $wpdb;
        $active_year_id = 0;
        if ( class_exists( 'Olama_School_Academic' ) ) {
            $ay = Olama_School_Academic::get_active_year();
            if ( $ay ) $active_year_id = (int) $ay->id;
        }

        wp_send_json_success( [
            'student'   => $student,
            'photo_url' => Olama_Reg_Student::get_student_photo_url( (int) ( $student->photo_attachment_id ?? 0 ) ),
        ] );
    }



    // ── Financial ─────────────────────────────────────────────────────────────

    public function ajax_save_financial_row(): void {
        $this->guard();

        $result = Olama_Reg_Financial::save_row( $_POST );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        $family_uid       = sanitize_text_field( $_POST['family_uid'] ?? '' );
        $academic_year_id = (int) ( $_POST['academic_year_id'] ?? 0 );

        wp_send_json_success( [
            'message' => __( 'Row saved.', 'olama-registration' ),
            'id'      => $result,
            'totals'  => Olama_Reg_Financial::get_totals( $family_uid, $academic_year_id ),
        ] );
    }

    public function ajax_delete_financial_row(): void {
        $this->guard();

        $id         = (int) ( $_POST['id'] ?? 0 );
        $family_uid = sanitize_text_field( $_POST['family_uid'] ?? '' );
        $year_id    = (int) ( $_POST['academic_year_id'] ?? 0 );

        Olama_Reg_Financial::delete_row( $id );

        wp_send_json_success( [
            'message' => __( 'Row deleted.', 'olama-registration' ),
            'totals'  => Olama_Reg_Financial::get_totals( $family_uid, $year_id ),
        ] );
    }

    public function ajax_get_financial(): void {
        $this->guard();

        $family_uid       = sanitize_text_field( $_POST['family_uid'] ?? '' );
        $academic_year_id = (int) ( $_POST['academic_year_id'] ?? 0 );

        wp_send_json_success( [
            'rows'   => Olama_Reg_Financial::get_entitlements( $family_uid, $academic_year_id ),
            'totals' => Olama_Reg_Financial::get_totals( $family_uid, $academic_year_id ),
        ] );
    }

    // ── Search ────────────────────────────────────────────────────────────────

    public function ajax_search(): void {
        $this->guard();

        global $wpdb;
        $q      = sanitize_text_field( $_POST['q'] ?? '' );
        $like   = '%' . $wpdb->esc_like( $q ) . '%';

        $families = $wpdb->get_results( $wpdb->prepare(
            "SELECT family_uid, father_first_name, father_family_name, is_active
             FROM {$wpdb->prefix}olama_families
             WHERE family_uid LIKE %s OR father_first_name LIKE %s OR father_family_name LIKE %s
             LIMIT 10",
            $like, $like, $like
        ) );

        $students = $wpdb->get_results( $wpdb->prepare(
            "SELECT student_uid, student_name, national_id, family_id
             FROM {$wpdb->prefix}olama_students
             WHERE student_uid LIKE %s OR student_name LIKE %s OR national_id LIKE %s
             LIMIT 10",
            $like, $like, $like
        ) );

        wp_send_json_success( [ 'families' => $families, 'students' => $students ] );
    }

    // ── Photo Upload ──────────────────────────────────────────────────────────

    public function ajax_upload_photo(): void {
        $this->guard();

        if ( empty( $_FILES['photo'] ) ) {
            wp_send_json_error( [ 'message' => __( 'No file received.', 'olama-registration' ) ] );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload( 'photo', 0 );

        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( [ 'message' => $attachment_id->get_error_message() ] );
        }

        wp_send_json_success( [
            'attachment_id' => $attachment_id,
            'url'           => wp_get_attachment_url( $attachment_id ),
            'thumb'         => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
        ] );
    }

    // ── Billing - Fee Templates ──────────────────────────────────────────────

    public function ajax_save_fee_template(): void {
        $this->guard();
        $result = Olama_Reg_Billing_Fees::save_template( $_POST );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [
            'message' => __( 'Template saved successfully.', 'olama-registration' ),
            'id'      => $result,
        ] );
    }

    public function ajax_delete_fee_template(): void {
        $this->guard();
        $id     = (int) ( $_POST['id'] ?? 0 );
        $result = Olama_Reg_Billing_Fees::delete_template( $id );

        if ( ! $result ) {
            wp_send_json_error( [ 'message' => __( 'Could not delete template.', 'olama-registration' ) ] );
        }

        wp_send_json_success( [ 'message' => __( 'Template deleted successfully.', 'olama-registration' ) ] );
    }

    // ── Billing - Invoices ────────────────────────────────────────────────────

    public function ajax_create_invoice(): void {
        $this->guard();
        $result = Olama_Reg_Billing_Invoice::create( $_POST );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [
            'message'    => __( 'Invoice created successfully.', 'olama-registration' ),
            'invoice_id' => $result,
        ] );
    }

    public function ajax_update_invoice(): void {
        $this->guard();
        $id     = (int) ( $_POST['id'] ?? 0 );
        $result = Olama_Reg_Billing_Invoice::update( $id, $_POST );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [
            'message' => __( 'Invoice updated successfully.', 'olama-registration' ),
        ] );
    }

    public function ajax_get_invoice(): void {
        $this->guard();
        $id      = (int) ( $_POST['id'] ?? 0 );
        $invoice = Olama_Reg_Billing_Invoice::get_invoice( $id );

        if ( ! $invoice ) {
            wp_send_json_error( [ 'message' => __( 'Invoice not found.', 'olama-registration' ) ] );
        }

        wp_send_json_success( [ 'invoice' => $invoice ] );
    }

    public function ajax_cancel_invoice(): void {
        $this->guard();
        $id     = (int) ( $_POST['id'] ?? 0 );
        $result = Olama_Reg_Billing_Invoice::cancel( $id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [
            'message' => __( 'تم إلغاء الفاتورة بنجاح.', 'olama-registration' ),
        ] );
    }

    // ── Billing - Payments ────────────────────────────────────────────────────

    public function ajax_record_payment(): void {
        $this->guard();
        $result = Olama_Reg_Billing_Payment::record( $_POST );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [
            'message'    => __( 'Payment recorded successfully.', 'olama-registration' ),
            'payment_id' => $result,
        ] );
    }

    public function ajax_reverse_payment(): void {
        $this->guard();
        $id     = (int) ( $_POST['id'] ?? 0 );
        $notes  = sanitize_textarea_field( $_POST['notes'] ?? '' );
        $result = Olama_Reg_Billing_Payment::reverse( $id, $notes );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [
            'message'    => __( 'تم عكس السند بنجاح.', 'olama-registration' ),
            'payment_id' => $result,
        ] );
    }

    public function ajax_get_receipt(): void {
        $this->guard();
        $payment_id = (int) ( $_POST['id'] ?? 0 );
        $data       = Olama_Reg_Billing_Payment::generate_receipt_data( $payment_id );

        if ( empty( $data ) ) {
            wp_send_json_error( [ 'message' => __( 'Receipt data not found.', 'olama-registration' ) ] );
        }

        wp_send_json_success( $data );
    }

    // ── Billing - Family Billing Tab overlay ──────────────────────────────────

    public function ajax_get_family_billing(): void {
        $this->guard();
        $family_uid = sanitize_text_field( $_POST['family_uid'] ?? '' );
        $year_id    = (int) ( $_POST['academic_year_id'] ?? 0 );

        if ( ! $family_uid ) {
            wp_send_json_error( [ 'message' => __( 'Family UID is required.', 'olama-registration' ) ] );
        }

        $invoices = Olama_Reg_Billing_Invoice::get_family_invoices( $family_uid, $year_id );
        $payments = Olama_Reg_Billing_Payment::get_family_payments( $family_uid, $year_id );
        $summary  = Olama_Reg_Billing_Invoice::get_invoice_summary( $family_uid, $year_id );

        wp_send_json_success( [
            'invoices' => $invoices,
            'payments' => $payments,
            'summary'  => $summary,
        ] );
    }

    // ── Custom Payments ────────────────────────────────────────────────────────

    public function ajax_get_family_students(): void {
        $this->guard();
        $family_uid = sanitize_text_field( $_POST['family_uid'] ?? '' );
        if ( ! $family_uid ) {
            wp_send_json_error( [ 'message' => __( 'Missing family UID.', 'olama-registration' ) ] );
        }
        
        $students = Olama_Reg_Student::get_family_students( $family_uid );
        wp_send_json_success( [ 'students' => $students ] );
    }

    public function ajax_save_custom_payment(): void {
        $this->guard();
        global $wpdb;

        $family_uid   = sanitize_text_field( $_POST['family_uid'] ?? '' );
        $student_uids = isset( $_POST['student_uids'] ) && is_array( $_POST['student_uids'] ) ? array_map( 'sanitize_text_field', $_POST['student_uids'] ) : [];
        $service_type = sanitize_text_field( $_POST['service_type'] ?? '' );
        $amount       = (float) ( $_POST['amount'] ?? 0 );
        $discount     = (float) ( $_POST['discount'] ?? 0 );
        $fee_template = absint( $_POST['fee_template_id'] ?? 0 );
        $payment_meth = sanitize_text_field( $_POST['payment_method'] ?? 'cash' );
        $is_external  = ! empty( $_POST['is_external'] );

        $items = [];
        $total_amount = 0;

        if ( ! $is_external ) {
            if ( ! $family_uid || empty( $student_uids ) || ! $service_type || $amount <= 0 ) {
                wp_send_json_error( [ 'message' => __( 'بيانات غير مكتملة. تأكد من تحديد الطلاب وتحديد قيمة الدفعة.', 'olama-registration' ) ] );
            }

            // Build line items
            foreach ( $student_uids as $s_uid ) {
                $student = Olama_Reg_Student::get_student( $s_uid );
                $s_name  = $student ? trim( $student->student_name ) : $s_uid;
                
                $items[] = [
                    'description' => sprintf( '%s - %s', $service_type, $s_name ),
                    'quantity'    => 1,
                    'unit_price'  => $amount,
                ];
            }

            $total_amount = max( 0, ( count( $student_uids ) * $amount ) - $discount );
        } else {
            if ( ! $family_uid || ! $service_type || $amount <= 0 ) {
                wp_send_json_error( [ 'message' => __( 'بيانات غير مكتملة. تأكد من تحديد نوع الخدمة وقيمة الدفعة.', 'olama-registration' ) ] );
            }
            $items[] = [
                'description' => $service_type,
                'quantity'    => 1,
                'unit_price'  => $amount,
            ];
            $total_amount = max( 0, $amount - $discount );
        }

        $academic_year_id = 0;
        if ( class_exists( 'Olama_School_Academic' ) ) {
            $active_year = Olama_School_Academic::get_active_year();
            if ( $active_year ) {
                $academic_year_id = (int) $active_year->id;
            }
        }

        // 1. Create Invoice
        $invoice_data = [
            'family_uid'       => $family_uid,
            'academic_year_id' => $academic_year_id,
            'fee_template_id'  => $fee_template ?: null,
            'issue_date'       => date( 'Y-m-d' ),
            'status'           => 'issued',
            'notes'            => 'رسوم خدمة إضافية: ' . $service_type,
            'items'            => $items,
            'discount'         => $discount,
        ];

        $invoice_id = Olama_Reg_Billing_Invoice::create( $invoice_data );

        if ( is_wp_error( $invoice_id ) ) {
            wp_send_json_error( [ 'message' => $invoice_id->get_error_message() ] );
        }

        // 2. Register Payment
        $payment_data = [
            'family_uid'   => $family_uid,
            'invoice_id'   => $invoice_id,
            'amount'       => $total_amount,
            'payment_date' => date( 'Y-m-d' ),
            'method'       => $payment_meth,
            'reference'    => '',
            'notes'        => 'دفعة مقبوضة عن خدمات إضافية: ' . $service_type,
        ];

        $payment_id = Olama_Reg_Billing_Payment::record( $payment_data );

        if ( is_wp_error( $payment_id ) ) {
            wp_send_json_error( [ 'message' => $payment_id->get_error_message() ] );
        }

        wp_send_json_success( [
            'message'    => __( 'تم تسجيل الفاتورة وإصدار السند بنجاح.', 'olama-registration' ),
            'invoice_id' => $invoice_id,
            'payment_id' => $payment_id,
        ] );
    }

    // ── Settlement Receipts ───────────────────────────────────────────────────

    public function ajax_create_settlement(): void {
        $this->guard();
        $result = Olama_Reg_Settlement::create_receipt( $_POST );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [
            'message'    => __( 'تم إنشاء إيصال التسوية بنجاح.', 'olama-registration' ),
            'receipt_id' => $result,
        ] );
    }

    public function ajax_settle_receipt(): void {
        $this->guard();
        $id = (int) ( $_POST['id'] ?? 0 );
        $oracle_receipt = sanitize_text_field( $_POST['oracle_receipt_number'] ?? '' );
        $notes = sanitize_textarea_field( $_POST['notes'] ?? '' );

        $result = Olama_Reg_Settlement::settle_receipt( $id, $oracle_receipt, $notes );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [
            'message' => __( 'تمت التسوية بنجاح.', 'olama-registration' ),
        ] );
    }

    public function ajax_cancel_settlement(): void {
        $this->guard();
        $id = (int) ( $_POST['id'] ?? 0 );

        $result = Olama_Reg_Settlement::cancel_receipt( $id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [
            'message' => __( 'تم إلغاء الإيصال بنجاح.', 'olama-registration' ),
        ] );
    }
}
