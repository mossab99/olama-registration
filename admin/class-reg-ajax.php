<?php
/**
 * AJAX Endpoints
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

            // External Customer CRUD
            'olama_reg_search_external_customers',
            'olama_reg_get_external_customer',
            'olama_reg_add_external_customer',
            'olama_reg_update_external_customer',
            'olama_reg_delete_external_customer',

            // Child CRUD
            'olama_reg_get_external_customer_children',
            'olama_reg_add_child_to_customer',
            'olama_reg_update_child',
            'olama_reg_delete_child',

            // Agreements
            'olama_reg_agr_save_header',
            'olama_reg_agr_search_payer',
            'olama_reg_agr_get_participants',
            'olama_reg_agr_save_fee',
            'olama_reg_agr_delete_fee',
            'olama_reg_agr_add_clause',
            'olama_reg_agr_save_clause',
            'olama_reg_agr_delete_clause',
            'olama_reg_agr_reorder_clauses',
            'olama_reg_agr_generate_invoice',
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

    // ── External Customer CRUD ─────────────────────────────────────────────────

    public function ajax_search_external_customers(): void {
        $this->guard();
        global $wpdb;
        $q    = sanitize_text_field( $_POST['q'] ?? '' );
        $like = '%' . $wpdb->esc_like( $q ) . '%';

        $table  = $wpdb->prefix . 'olama_customers';
        $ctable = $wpdb->prefix . 'olama_customer_children';

        $customers = $wpdb->get_results( $wpdb->prepare(
            "SELECT DISTINCT c.id, c.customer_uid, c.customer_name, c.phone
             FROM {$table} c
             LEFT JOIN {$ctable} ch ON ch.customer_id = c.id AND ch.is_active = 1
             WHERE c.is_active = 1
               AND ( c.customer_name LIKE %s OR c.phone LIKE %s OR c.customer_uid LIKE %s OR ch.child_name LIKE %s )
             LIMIT 20",
            $like, $like, $like, $like
        ) );

        wp_send_json_success( [ 'customers' => $customers ] );
    }

    public function ajax_get_external_customer(): void {
        $this->guard();
        $customer_id = absint( $_POST['customer_id'] ?? 0 );

        if ( ! $customer_id ) {
            wp_send_json_error( [ 'message' => 'معرف العميل مطلوب.' ] );
        }

        $customer = Olama_Reg_Customer::get( $customer_id );
        if ( ! $customer ) {
            wp_send_json_error( [ 'message' => 'العميل غير موجود.' ] );
        }

        $children = Olama_Reg_Child::get_by_customer( $customer_id );

        wp_send_json_success( [
            'customer' => $customer,
            'children' => $children,
        ] );
    }

    public function ajax_add_external_customer(): void {
        $this->guard();

        $result = Olama_Reg_Customer::create( [
            'customer_name' => sanitize_text_field( $_POST['customer_name'] ?? $_POST['name'] ?? '' ),
            'phone'         => sanitize_text_field( $_POST['phone'] ?? '' ),
            'notes'         => sanitize_textarea_field( $_POST['notes'] ?? '' ),
        ] );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        $customer     = Olama_Reg_Customer::get( $result );
        $children_raw = json_decode( stripslashes( $_POST['children'] ?? '[]' ), true );
        $children     = [];

        if ( is_array( $children_raw ) ) {
            foreach ( $children_raw as $c ) {
                $child_id = Olama_Reg_Child::add( $result, [
                    'child_name' => $c['name'] ?? '',
                    'grade'      => $c['grade'] ?? '',
                ] );
                if ( ! is_wp_error( $child_id ) ) {
                    $children[] = Olama_Reg_Child::get( $child_id );
                }
            }
        }

        wp_send_json_success( [
            'message'      => 'تم إضافة العميل بنجاح.',
            'customer_id'  => $result,
            'customer_uid' => $customer->customer_uid ?? '',
            'customer'     => $customer,
            'children'     => $children,
        ] );
    }

    public function ajax_update_external_customer(): void {
        $this->guard();
        $customer_id = absint( $_POST['customer_id'] ?? 0 );

        if ( ! $customer_id ) {
            wp_send_json_error( [ 'message' => 'معرف العميل مطلوب.' ] );
        }

        $result = Olama_Reg_Customer::update( $customer_id, [
            'customer_name' => sanitize_text_field( $_POST['customer_name'] ?? $_POST['name'] ?? '' ),
            'phone'         => sanitize_text_field( $_POST['phone'] ?? '' ),
            'notes'         => sanitize_textarea_field( $_POST['notes'] ?? '' ),
        ] );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [
            'message'  => 'تم تحديث بيانات العميل.',
            'customer' => Olama_Reg_Customer::get( $customer_id ),
        ] );
    }

    public function ajax_delete_external_customer(): void {
        $this->guard();
        $customer_id = absint( $_POST['customer_id'] ?? 0 );

        if ( ! $customer_id ) {
            wp_send_json_error( [ 'message' => 'معرف العميل مطلوب.' ] );
        }

        $result = Olama_Reg_Customer::delete( $customer_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 'message' => 'تم حذف العميل بنجاح.' ] );
    }

    // ── Child CRUD ────────────────────────────────────────────────────────────

    public function ajax_get_external_customer_children(): void {
        $this->guard();
        $customer_id = absint( $_POST['customer_id'] ?? 0 );

        if ( ! $customer_id ) {
            wp_send_json_error( [ 'message' => 'معرف العميل مطلوب.' ] );
        }

        $children = Olama_Reg_Child::get_by_customer( $customer_id );
        wp_send_json_success( [ 'children' => $children ] );
    }

    public function ajax_add_child_to_customer(): void {
        $this->guard();
        $customer_id = absint( $_POST['customer_id'] ?? 0 );

        if ( ! $customer_id ) {
            wp_send_json_error( [ 'message' => 'معرف العميل مطلوب.' ] );
        }

        $result = Olama_Reg_Child::add( $customer_id, [
            'child_name' => sanitize_text_field( $_POST['child_name'] ?? $_POST['name'] ?? '' ),
            'grade'      => sanitize_text_field( $_POST['grade'] ?? '' ),
            'notes'      => sanitize_textarea_field( $_POST['notes'] ?? '' ),
        ] );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [
            'message' => 'تمت إضافة الابن بنجاح.',
            'child'   => Olama_Reg_Child::get( $result ),
        ] );
    }

    public function ajax_update_child(): void {
        $this->guard();
        $child_id = absint( $_POST['child_id'] ?? 0 );

        if ( ! $child_id ) {
            wp_send_json_error( [ 'message' => 'معرف الابن مطلوب.' ] );
        }

        $result = Olama_Reg_Child::update( $child_id, [
            'child_name' => sanitize_text_field( $_POST['child_name'] ?? $_POST['name'] ?? '' ),
            'grade'      => sanitize_text_field( $_POST['grade'] ?? '' ),
            'notes'      => sanitize_textarea_field( $_POST['notes'] ?? '' ),
        ] );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [
            'message' => 'تم تحديث بيانات الابن.',
            'child'   => Olama_Reg_Child::get( $child_id ),
        ] );
    }

    public function ajax_delete_child(): void {
        $this->guard();
        $child_id = absint( $_POST['child_id'] ?? 0 );

        if ( ! $child_id ) {
            wp_send_json_error( [ 'message' => 'معرف الابن مطلوب.' ] );
        }

        $result = Olama_Reg_Child::delete( $child_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 'message' => 'تم حذف الابن.' ] );
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
            "SELECT family_uid, family_name AS father_first_name, '' AS father_family_name
             FROM {$wpdb->prefix}olama_families
             WHERE family_uid LIKE %s OR family_name LIKE %s
             LIMIT 10",
            $like, $like
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
        $is_external  = ! empty( $_POST['is_external'] ) || ! empty( $_POST['is_external_customer'] );
        $ext_customer = absint( $_POST['ext_customer_id'] ?? 0 );

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
            if ( ! $ext_customer || ! $service_type || $amount <= 0 ) {
                wp_send_json_error( [ 'message' => __( 'بيانات غير مكتملة. تأكد من تحديد نوع الخدمة وقيمة الدفعة.', 'olama-registration' ) ] );
            }

            // Use CUST-uid as family_uid for external customers (not EXT-{id})
            $customer = Olama_Reg_Customer::get( $ext_customer );
            $family_uid = $customer ? $customer->customer_uid : ( 'CUST-' . str_pad( $ext_customer, 4, '0', STR_PAD_LEFT ) );

            // Children come as array of child IDs (from DB checkboxes)
            $child_ids_raw = isset( $_POST['child_ids'] ) && is_array( $_POST['child_ids'] )
                ? array_map( 'absint', $_POST['child_ids'] )
                : [];

            // Also handle newly typed children (quick-add during payment)
            $new_children_raw = json_decode( stripslashes( $_POST['new_children'] ?? '[]' ), true );
            if ( is_array( $new_children_raw ) ) {
                foreach ( $new_children_raw as $nc ) {
                    $nc_name = sanitize_text_field( $nc['name'] ?? '' );
                    if ( $nc_name ) {
                        $new_child_id = Olama_Reg_Child::add( $ext_customer, [
                            'child_name' => $nc_name,
                            'grade'      => sanitize_text_field( $nc['grade'] ?? '' ),
                        ] );
                        if ( ! is_wp_error( $new_child_id ) ) {
                            $child_ids_raw[] = $new_child_id;
                        }
                    }
                }
            }

            if ( ! empty( $child_ids_raw ) ) {
                foreach ( $child_ids_raw as $child_id ) {
                    $child = Olama_Reg_Child::get( $child_id );
                    if ( ! $child ) continue;
                    $items[] = [
                        'description'   => sprintf( '%s - %s', $service_type, $child->child_name ),
                        'quantity'      => 1,
                        'unit_price'    => $amount,
                        'ext_child_id'  => $child->id,
                    ];
                }
                $total_amount = max( 0, ( count( $items ) * $amount ) - $discount );
            } else {
                // Payment for the customer directly (no children)
                $items[] = [
                    'description' => $service_type,
                    'quantity'    => 1,
                    'unit_price'  => $amount,
                ];
                $total_amount = max( 0, $amount - $discount );
            }
        }

        $academic_year_id = 0;
        if ( class_exists( 'Olama_School_Academic' ) ) {
            $active_year = Olama_School_Academic::get_active_year();
            if ( $active_year ) {
                $academic_year_id = (int) $active_year->id;
            }
        }

        // 1. Create a single Invoice containing all items
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

        if ( $is_external ) {
            $invoice_data['ext_customer_id'] = $ext_customer;
            // If there is only one child, we can set ext_child_id for clearer reporting
            if ( count( $items ) === 1 && ! empty( $items[0]['ext_child_id'] ) ) {
                $invoice_data['ext_child_id'] = $items[0]['ext_child_id'];
            }
            $invoice_data['notes'] = 'رسوم خدمة: ' . $service_type;
        }

        $invoice_id = Olama_Reg_Billing_Invoice::create( $invoice_data );

        if ( is_wp_error( $invoice_id ) ) {
            wp_send_json_error( [ 'message' => $invoice_id->get_error_message() ] );
        }

        // 2. Record a single Payment
        $payment_id = Olama_Reg_Billing_Payment::record( [
            'family_uid'   => $family_uid,
            'invoice_id'   => $invoice_id,
            'amount'       => $total_amount,
            'payment_date' => date( 'Y-m-d' ),
            'method'       => $payment_meth,
            'notes'        => $is_external ? 'دفعة: ' . $service_type : 'دفعة مقبوضة عن خدمات إضافية: ' . $service_type,
        ] );

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

    // ── Agreements ────────────────────────────────────────────────────────────

    public function ajax_agr_save_header(): void {
        $this->guard();
        $id = (int) ( $_POST['id'] ?? 0 );
        
        $participant_id  = 0;
        $participant_ids = [];
        if ( isset( $_POST['participant_id'] ) ) {
            if ( is_array( $_POST['participant_id'] ) ) {
                $participant_ids = array_map( 'intval', $_POST['participant_id'] );
                $participant_id  = $participant_ids[0] ?? 0;
            } else {
                $participant_id  = (int) $_POST['participant_id'];
                $participant_ids = [ $participant_id ];
            }
        }

        $data = [
            'payer_type'       => sanitize_text_field( $_POST['payer_type'] ?? '' ),
            'payer_id'         => sanitize_text_field( $_POST['payer_id'] ?? '' ),
            'participant_type' => ( sanitize_text_field( $_POST['payer_type'] ?? '' ) === 'family' ) ? 'student' : 'child',
            'participant_id'   => $participant_id,
            'participant_ids'  => $participant_ids,
            'activity_type'    => sanitize_text_field( $_POST['activity_type'] ?? '' ),
            'start_date'       => sanitize_text_field( $_POST['start_date'] ?? '' ),
            'end_date'         => sanitize_text_field( $_POST['end_date'] ?? '' ),
            'notes'            => sanitize_textarea_field( $_POST['notes'] ?? '' ),
            'template_id'      => absint( $_POST['template_id'] ?? 0 ) ?: null,
        ];

        if ( empty( $data['end_date'] ) ) {
            $data['end_date'] = null; // Important for DB
        }

        if ( $id > 0 ) {
            $old = Olama_Reg_Agreement::get( $id );
            $old_template_id = $old ? $old->template_id : null;
            $old_participant_count = $old ? count( $old->participant_ids_array ) : 0;
            
            $result = Olama_Reg_Agreement::update( $id, $data );
            if ( $result ) {
                $new_participant_count = count( $participant_ids );
                if ( $data['template_id'] && ( $data['template_id'] != $old_template_id || $new_participant_count != $old_participant_count ) ) {
                    Olama_Reg_Agreement_Fees::apply_template_fees( $id, $data['template_id'] );
                }
                wp_send_json_success( [ 'message' => __( 'تم تحديث العقد.', 'olama-registration' ), 'id' => $id ] );
            }
        } else {
            $id = Olama_Reg_Agreement::create( $data );
            if ( $id ) {
                if ( $data['template_id'] ) {
                    Olama_Reg_Agreement_Fees::apply_template_fees( $id, $data['template_id'] );
                }
                wp_send_json_success( [ 'message' => __( 'تم إنشاء العقد.', 'olama-registration' ), 'id' => $id ] );
            }
        }

        wp_send_json_error( [ 'message' => __( 'حدث خطأ أثناء الحفظ.', 'olama-registration' ) ] );
    }

    public function ajax_agr_search_payer(): void {
        $this->guard();
        global $wpdb;
        $q          = sanitize_text_field( $_POST['q'] ?? '' );
        $payer_type = sanitize_text_field( $_POST['payer_type'] ?? 'customer' );
        $like       = '%' . $wpdb->esc_like( $q ) . '%';
        $results    = [];

        if ( $payer_type === 'customer' ) {
            $table = $wpdb->prefix . 'olama_customers';
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, customer_name AS text FROM {$table} WHERE customer_name LIKE %s OR phone LIKE %s LIMIT 15",
                $like, $like
            ) );
            foreach ( $rows as $r ) $results[] = [ 'id' => $r->id, 'text' => $r->text ];
        } elseif ( $payer_type === 'family' ) {
            $table = $wpdb->prefix . 'olama_families';
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT family_uid AS id, family_name AS text FROM {$table} WHERE family_name LIKE %s OR family_uid LIKE %s LIMIT 15",
                $like, $like
            ) );
            foreach ( $rows as $r ) $results[] = [ 'id' => $r->id, 'text' => $r->id . ' - ' . $r->text ];
        }

        wp_send_json_success( [ 'results' => $results ] );
    }

    public function ajax_agr_get_participants(): void {
        $this->guard();
        global $wpdb;
        $payer_type = sanitize_text_field( $_POST['payer_type'] ?? 'customer' );
        $payer_id   = sanitize_text_field( $_POST['payer_id'] ?? '' );
        $results    = [];

        if ( $payer_type === 'customer' && is_numeric( $payer_id ) ) {
            $table = $wpdb->prefix . 'olama_customer_children';
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, child_name AS text FROM {$table} WHERE customer_id = %d AND is_active = 1", (int) $payer_id ) );
            foreach ( $rows as $r ) $results[] = [ 'id' => $r->id, 'text' => $r->text ];
        } elseif ( $payer_type === 'family' && $payer_id ) {
            $table = $wpdb->prefix . 'olama_students';
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT student_uid AS id, student_name AS text FROM {$table} WHERE family_id = %s", $payer_id ) );
            foreach ( $rows as $r ) $results[] = [ 'id' => $r->id, 'text' => $r->text ];
        }

        wp_send_json_success( [ 'results' => $results ] );
    }

    public function ajax_agr_save_fee(): void {
        $this->guard();
        $id = (int) ( $_POST['id'] ?? 0 );
        $agreement_id = (int) ( $_POST['agreement_id'] ?? 0 );
        
        $data = [
            'fee_category' => sanitize_text_field( $_POST['fee_category'] ?? '' ),
            'label'        => sanitize_text_field( $_POST['label'] ?? '' ),
            'amount'       => (float) ( $_POST['amount'] ?? 0 ),
            'discount'     => (float) ( $_POST['discount'] ?? 0 ),
            'due_date'     => sanitize_text_field( $_POST['due_date'] ?? '' ),
        ];
        if ( empty( $data['due_date'] ) ) $data['due_date'] = null;

        if ( $id > 0 ) {
            $result = Olama_Reg_Agreement_Fees::update( $id, $data );
            if ( $result ) {
                wp_send_json_success( [ 'message' => __( 'تم تحديث الرسم.', 'olama-registration' ), 'total' => Olama_Reg_Agreement::get( $agreement_id )->total_amount ] );
            }
        } else {
            $data['agreement_id'] = $agreement_id;
            $new_id = Olama_Reg_Agreement_Fees::add( $agreement_id, $data );
            if ( $new_id ) {
                wp_send_json_success( [ 'message' => __( 'تمت إضافة الرسم.', 'olama-registration' ), 'id' => $new_id, 'total' => Olama_Reg_Agreement::get( $agreement_id )->total_amount ] );
            }
        }
        wp_send_json_error( [ 'message' => __( 'حدث خطأ أثناء حفظ الرسم.', 'olama-registration' ) ] );
    }

    public function ajax_agr_delete_fee(): void {
        $this->guard();
        $id = (int) ( $_POST['id'] ?? 0 );
        $agreement_id = (int) ( $_POST['agreement_id'] ?? 0 );
        
        if ( Olama_Reg_Agreement_Fees::delete( $id ) ) {
            wp_send_json_success( [ 'message' => __( 'تم حذف الرسم.', 'olama-registration' ), 'total' => Olama_Reg_Agreement::get( $agreement_id )->total_amount ] );
        }
        wp_send_json_error( [ 'message' => __( 'لا يمكن حذف هذا الرسم.', 'olama-registration' ) ] );
    }

    public function ajax_agr_add_clause(): void {
        $this->guard();
        $agreement_id = (int) ( $_POST['agreement_id'] ?? 0 );
        $text = sanitize_textarea_field( $_POST['clause_text'] ?? '' );
        
        $id = Olama_Reg_Agreement_Clauses::add( $agreement_id, $text );
        if ( $id ) {
            wp_send_json_success( [ 'message' => __( 'تمت الإضافة.', 'olama-registration' ), 'id' => $id ] );
        }
        wp_send_json_error( [ 'message' => __( 'حدث خطأ.', 'olama-registration' ) ] );
    }

    public function ajax_agr_save_clause(): void {
        $this->guard();
        $id = (int) ( $_POST['id'] ?? 0 );
        $text = sanitize_textarea_field( $_POST['clause_text'] ?? '' );
        
        if ( Olama_Reg_Agreement_Clauses::update( $id, $text ) ) {
            wp_send_json_success( [ 'message' => __( 'تم الحفظ.', 'olama-registration' ) ] );
        }
        wp_send_json_error( [ 'message' => __( 'حدث خطأ.', 'olama-registration' ) ] );
    }

    public function ajax_agr_delete_clause(): void {
        $this->guard();
        $id = (int) ( $_POST['id'] ?? 0 );
        
        if ( Olama_Reg_Agreement_Clauses::delete( $id ) ) {
            wp_send_json_success( [ 'message' => __( 'تم الحذف.', 'olama-registration' ) ] );
        }
        wp_send_json_error( [ 'message' => __( 'حدث خطأ.', 'olama-registration' ) ] );
    }

    public function ajax_agr_reorder_clauses(): void {
        $this->guard();
        $ordered_ids = isset( $_POST['ordered_ids'] ) && is_array( $_POST['ordered_ids'] ) ? array_map( 'intval', $_POST['ordered_ids'] ) : [];
        if ( ! empty( $ordered_ids ) ) {
            Olama_Reg_Agreement_Clauses::reorder( $ordered_ids );
            wp_send_json_success();
        }
        wp_send_json_error();
    }

    public function ajax_agr_generate_invoice(): void {
        $this->guard();
        $agreement_id = (int) ( $_POST['agreement_id'] ?? 0 );
        $fee_ids = isset( $_POST['fee_ids'] ) && is_array( $_POST['fee_ids'] ) ? array_map( 'intval', $_POST['fee_ids'] ) : [];

        if ( empty( $fee_ids ) ) {
            wp_send_json_error( [ 'message' => __( 'يجب تحديد رسم واحد على الأقل.', 'olama-registration' ) ] );
        }

        $result = Olama_Reg_Agreement_Invoice::generate_invoice( $agreement_id, $fee_ids );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 
            'message' => __( 'تم إصدار الفاتورة بنجاح.', 'olama-registration' ),
            'invoice_id' => $result 
        ] );
    }
}
