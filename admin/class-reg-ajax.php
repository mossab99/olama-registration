<?php
/**
 * AJAX Endpoints
 */

if (!defined('ABSPATH'))
    exit;

class Olama_Reg_Ajax
{

    public function __construct()
    {
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
            'olama_reg_agr_get_details',
            'olama_reg_agr_search_payer',
            'olama_reg_agr_get_participants',
            'olama_reg_agr_save_fee',
            'olama_reg_agr_delete_fee',
            'olama_reg_agr_add_clause',
            'olama_reg_agr_save_clause',
            'olama_reg_agr_delete_clause',
            'olama_reg_agr_reorder_clauses',
            'olama_reg_agr_generate_invoice',
            'olama_reg_agr_get_unpaid_fees',
            'olama_reg_reset_system',
        ];

        foreach ($actions as $action) {
            $method = 'ajax_' . str_replace('olama_reg_', '', $action);
            add_action('wp_ajax_' . $action, [$this, $method]);
        }

        // ── Customer Hub handlers (Phase 2 + 3 + 4) ─────────────────────────────
        add_action('wp_ajax_os_hub_search',       [$this, 'hub_search']);
        add_action('wp_ajax_os_hub_counts',       [$this, 'hub_counts']);
        add_action('wp_ajax_os_hub_tile',         [$this, 'hub_tile']);
        add_action('wp_ajax_os_hub_save_profile', [$this, 'hub_save_profile']);
        add_action('wp_ajax_os_hub_toggle_active',[$this, 'hub_toggle_active']);
        add_action('wp_ajax_os_hub_add_child',    [$this, 'hub_add_child']);
    }

    // ── Guard ─────────────────────────────────────────────────────────────────

    private function guard(): void
    {
        check_ajax_referer('olama_reg_nonce', 'nonce');
        if (
            !current_user_can('manage_options') &&
            !current_user_can('olama_manage_registration_families') &&
            !current_user_can('olama_manage_registration_students') &&
            !current_user_can('olama_manage_registration_fees') &&
            !current_user_can('olama_manage_registration_invoices') &&
            !current_user_can('olama_manage_registration_payments') &&
            !current_user_can('olama_manage_registration_reports')
        ) {
            wp_send_json_error(['message' => __('Unauthorized.', 'olama-registration')], 403);
        }
    }

    // ── Family ────────────────────────────────────────────────────────────────

    public function ajax_get_family(): void
    {
        $this->guard();

        $family_uid = sanitize_text_field($_POST['family_uid'] ?? '');
        $family = Olama_Reg_Family::get_family($family_uid);

        if (!$family) {
            wp_send_json_error(['message' => __('Family not found.', 'olama-registration')]);
        }

        $students = Olama_Reg_Student::get_family_students($family_uid);

        wp_send_json_success([
            'family' => $family,
            'students' => $students,
        ]);
    }

    // ── External Customer CRUD ─────────────────────────────────────────────────

    public function ajax_search_external_customers(): void
    {
        $this->guard();
        global $wpdb;
        $q = sanitize_text_field($_POST['q'] ?? '');
        $like = '%' . $wpdb->esc_like($q) . '%';

        $table = $wpdb->prefix . 'olama_customers';
        $ctable = $wpdb->prefix . 'olama_customer_children';

        $customers = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT c.id, c.customer_uid, c.customer_name, c.phone
             FROM {$table} c
             LEFT JOIN {$ctable} ch ON ch.customer_id = c.id AND ch.is_active = 1
             WHERE c.is_active = 1
               AND ( c.customer_name LIKE %s OR c.phone LIKE %s OR c.customer_uid LIKE %s OR ch.child_name LIKE %s )
             LIMIT 20",
            $like,
            $like,
            $like,
            $like
        ));

        wp_send_json_success(['customers' => $customers]);
    }

    public function ajax_get_external_customer(): void
    {
        $this->guard();
        $customer_id = absint($_POST['customer_id'] ?? 0);

        if (!$customer_id) {
            wp_send_json_error(['message' => 'معرف العميل مطلوب.']);
        }

        $customer = Olama_Reg_Customer::get($customer_id);
        if (!$customer) {
            wp_send_json_error(['message' => 'العميل غير موجود.']);
        }

        $children = Olama_Reg_Child::get_by_customer($customer_id);

        wp_send_json_success([
            'customer' => $customer,
            'children' => $children,
        ]);
    }

    public function ajax_add_external_customer(): void
    {
        $this->guard();

        $result = Olama_Reg_Customer::create([
            'customer_name' => sanitize_text_field($_POST['customer_name'] ?? $_POST['name'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        $customer = Olama_Reg_Customer::get($result);
        $children_raw = json_decode(stripslashes($_POST['children'] ?? '[]'), true);
        $children = [];

        if (is_array($children_raw)) {
            foreach ($children_raw as $c) {
                $child_id = Olama_Reg_Child::add($result, [
                    'child_name' => $c['name'] ?? '',
                    'grade' => $c['grade'] ?? '',
                ]);
                if (!is_wp_error($child_id)) {
                    $children[] = Olama_Reg_Child::get($child_id);
                }
            }
        }

        wp_send_json_success([
            'message' => 'تم إضافة العميل بنجاح.',
            'customer_id' => $result,
            'customer_uid' => $customer->customer_uid ?? '',
            'customer' => $customer,
            'children' => $children,
        ]);
    }

    public function ajax_update_external_customer(): void
    {
        $this->guard();
        $customer_id = absint($_POST['customer_id'] ?? 0);

        if (!$customer_id) {
            wp_send_json_error(['message' => 'معرف العميل مطلوب.']);
        }

        $result = Olama_Reg_Customer::update($customer_id, [
            'customer_name' => sanitize_text_field($_POST['customer_name'] ?? $_POST['name'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => 'تم تحديث بيانات العميل.',
            'customer' => Olama_Reg_Customer::get($customer_id),
        ]);
    }

    public function ajax_delete_external_customer(): void
    {
        $this->guard();
        $customer_id = absint($_POST['customer_id'] ?? 0);

        if (!$customer_id) {
            wp_send_json_error(['message' => 'معرف العميل مطلوب.']);
        }

        $result = Olama_Reg_Customer::delete($customer_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => 'تم حذف العميل بنجاح.']);
    }

    // ── Child CRUD ────────────────────────────────────────────────────────────

    public function ajax_get_external_customer_children(): void
    {
        $this->guard();
        $customer_id = absint($_POST['customer_id'] ?? 0);

        if (!$customer_id) {
            wp_send_json_error(['message' => 'معرف العميل مطلوب.']);
        }

        $children = Olama_Reg_Child::get_by_customer($customer_id);
        wp_send_json_success(['children' => $children]);
    }

    public function ajax_add_child_to_customer(): void
    {
        $this->guard();
        $customer_id = absint($_POST['customer_id'] ?? 0);

        if (!$customer_id) {
            wp_send_json_error(['message' => 'معرف العميل مطلوب.']);
        }

        $result = Olama_Reg_Child::add($customer_id, [
            'child_name' => sanitize_text_field($_POST['child_name'] ?? $_POST['name'] ?? ''),
            'grade' => sanitize_text_field($_POST['grade'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => 'تمت إضافة الابن بنجاح.',
            'child' => Olama_Reg_Child::get($result),
        ]);
    }

    public function ajax_update_child(): void
    {
        $this->guard();
        $child_id = absint($_POST['child_id'] ?? 0);

        if (!$child_id) {
            wp_send_json_error(['message' => 'معرف الابن مطلوب.']);
        }

        $result = Olama_Reg_Child::update($child_id, [
            'child_name' => sanitize_text_field($_POST['child_name'] ?? $_POST['name'] ?? ''),
            'grade' => sanitize_text_field($_POST['grade'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => 'تم تحديث بيانات الابن.',
            'child' => Olama_Reg_Child::get($child_id),
        ]);
    }

    public function ajax_delete_child(): void
    {
        $this->guard();
        $child_id = absint($_POST['child_id'] ?? 0);

        if (!$child_id) {
            wp_send_json_error(['message' => 'معرف الابن مطلوب.']);
        }

        $result = Olama_Reg_Child::delete($child_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => 'تم حذف الابن.']);
    }

    // ── Student ───────────────────────────────────────────────────────────────

    public function ajax_get_student(): void
    {
        $this->guard();

        $student_uid = sanitize_text_field($_POST['student_uid'] ?? '');
        $student = Olama_Reg_Student::get_student($student_uid);

        if (!$student) {
            wp_send_json_error(['message' => __('Student not found.', 'olama-registration')]);
        }

        global $wpdb;
        $active_year_id = 0;
        if (class_exists('Olama_School_Academic')) {
            $ay = Olama_School_Academic::get_active_year();
            if ($ay)
                $active_year_id = (int) $ay->id;
        }

        wp_send_json_success([
            'student' => $student,
            'photo_url' => Olama_Reg_Student::get_student_photo_url((int) ($student->photo_attachment_id ?? 0)),
        ]);
    }



    // ── Financial ─────────────────────────────────────────────────────────────

    public function ajax_save_financial_row(): void
    {
        $this->guard();

        $result = Olama_Reg_Financial::save_row($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        $family_uid = sanitize_text_field($_POST['family_uid'] ?? '');
        $academic_year_id = (int) ($_POST['academic_year_id'] ?? 0);

        wp_send_json_success([
            'message' => __('Row saved.', 'olama-registration'),
            'id' => $result,
            'totals' => Olama_Reg_Financial::get_totals($family_uid, $academic_year_id),
        ]);
    }

    public function ajax_delete_financial_row(): void
    {
        $this->guard();

        $id = (int) ($_POST['id'] ?? 0);
        $family_uid = sanitize_text_field($_POST['family_uid'] ?? '');
        $year_id = (int) ($_POST['academic_year_id'] ?? 0);

        Olama_Reg_Financial::delete_row($id);

        wp_send_json_success([
            'message' => __('Row deleted.', 'olama-registration'),
            'totals' => Olama_Reg_Financial::get_totals($family_uid, $year_id),
        ]);
    }

    public function ajax_get_financial(): void
    {
        $this->guard();

        $family_uid = sanitize_text_field($_POST['family_uid'] ?? '');
        $academic_year_id = (int) ($_POST['academic_year_id'] ?? 0);

        wp_send_json_success([
            'rows' => Olama_Reg_Financial::get_entitlements($family_uid, $academic_year_id),
            'totals' => Olama_Reg_Financial::get_totals($family_uid, $academic_year_id),
        ]);
    }

    // ── Search ────────────────────────────────────────────────────────────────

    public function ajax_search(): void
    {
        $this->guard();

        global $wpdb;
        $q = sanitize_text_field($_POST['q'] ?? '');
        $like = '%' . $wpdb->esc_like($q) . '%';

        $families = $wpdb->get_results($wpdb->prepare(
            "SELECT family_uid, family_name AS father_first_name, '' AS father_family_name
             FROM {$wpdb->prefix}olama_families
             WHERE family_uid LIKE %s OR family_name LIKE %s
             LIMIT 10",
            $like,
            $like
        ));

        $students = $wpdb->get_results($wpdb->prepare(
            "SELECT student_uid, student_name, national_id, family_id
             FROM {$wpdb->prefix}olama_students
             WHERE student_uid LIKE %s OR student_name LIKE %s OR national_id LIKE %s
             LIMIT 10",
            $like,
            $like,
            $like
        ));

        wp_send_json_success(['families' => $families, 'students' => $students]);
    }

    // ── Photo Upload ──────────────────────────────────────────────────────────

    public function ajax_upload_photo(): void
    {
        $this->guard();

        if (empty($_FILES['photo'])) {
            wp_send_json_error(['message' => __('No file received.', 'olama-registration')]);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload('photo', 0);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(['message' => $attachment_id->get_error_message()]);
        }

        wp_send_json_success([
            'attachment_id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
            'thumb' => wp_get_attachment_image_url($attachment_id, 'thumbnail'),
        ]);
    }

    // ── Billing - Fee Templates ──────────────────────────────────────────────

    public function ajax_save_fee_template(): void
    {
        $this->guard();
        $result = Olama_Reg_Billing_Fees::save_template($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Template saved successfully.', 'olama-registration'),
            'id' => $result,
        ]);
    }

    public function ajax_delete_fee_template(): void
    {
        $this->guard();
        $id = (int) ($_POST['id'] ?? 0);
        $result = Olama_Reg_Billing_Fees::delete_template($id);

        if (!$result) {
            wp_send_json_error(['message' => __('Could not delete template.', 'olama-registration')]);
        }

        wp_send_json_success(['message' => __('Template deleted successfully.', 'olama-registration')]);
    }

    // ── Billing - Invoices ────────────────────────────────────────────────────

    public function ajax_create_invoice(): void
    {
        $this->guard();
        $result = Olama_Reg_Billing_Invoice::create($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Invoice created successfully.', 'olama-registration'),
            'invoice_id' => $result,
        ]);
    }

    public function ajax_update_invoice(): void
    {
        $this->guard();
        $id = (int) ($_POST['id'] ?? 0);
        $result = Olama_Reg_Billing_Invoice::update($id, $_POST);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Invoice updated successfully.', 'olama-registration'),
        ]);
    }

    public function ajax_get_invoice(): void
    {
        $this->guard();
        $id = (int) ($_POST['id'] ?? 0);
        $invoice = Olama_Reg_Billing_Invoice::get_invoice($id);

        if (!$invoice) {
            wp_send_json_error(['message' => __('Invoice not found.', 'olama-registration')]);
        }

        if (class_exists('Olama_Reg_Billing_Payment')) {
            $invoice->payments = Olama_Reg_Billing_Payment::get_invoice_payments($id);
        } else {
            $invoice->payments = [];
        }

        wp_send_json_success(['invoice' => $invoice]);
    }

    public function ajax_cancel_invoice(): void
    {
        $this->guard();
        $id = (int) ($_POST['id'] ?? 0);
        $result = Olama_Reg_Billing_Invoice::cancel($id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('تم إلغاء الفاتورة بنجاح.', 'olama-registration'),
        ]);
    }

    // ── Billing - Payments ────────────────────────────────────────────────────

    public function ajax_record_payment(): void
    {
        $this->guard();
        $result = Olama_Reg_Billing_Payment::record($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Payment recorded successfully.', 'olama-registration'),
            'payment_id' => $result,
        ]);
    }

    public function ajax_reverse_payment(): void
    {
        $this->guard();
        $id = (int) ($_POST['id'] ?? 0);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $result = Olama_Reg_Billing_Payment::reverse($id, $notes);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('تم عكس السند بنجاح.', 'olama-registration'),
            'payment_id' => $result,
        ]);
    }

    public function ajax_get_receipt(): void
    {
        $this->guard();
        $payment_id = (int) ($_POST['id'] ?? 0);
        $data = Olama_Reg_Billing_Payment::generate_receipt_data($payment_id);

        if (empty($data)) {
            wp_send_json_error(['message' => __('Receipt data not found.', 'olama-registration')]);
        }

        wp_send_json_success($data);
    }

    // ── Billing - Family Billing Tab overlay ──────────────────────────────────

    public function ajax_get_family_billing(): void
    {
        $this->guard();
        $family_uid = sanitize_text_field($_POST['family_uid'] ?? '');
        $year_id = (int) ($_POST['academic_year_id'] ?? 0);

        if (!$family_uid) {
            wp_send_json_error(['message' => __('Family UID is required.', 'olama-registration')]);
        }

        // If it starts with CUST-, it's an external customer
        if (strpos($family_uid, 'CUST-') === 0) {
            global $wpdb;
            $customer_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}olama_customers WHERE customer_uid = %s LIMIT 1",
                $family_uid
            ));
            if ($customer_id) {
                $invoices = Olama_Reg_Billing_Invoice::get_customer_invoices($customer_id, $year_id);
                $payments = Olama_Reg_Billing_Payment::get_family_payments($family_uid, $year_id);
                $summary = Olama_Reg_Billing_Invoice::get_customer_invoice_summary($customer_id, $year_id);
            } else {
                $invoices = [];
                $payments = [];
                $summary = (object)[ 'total_invoiced' => 0, 'total_paid' => 0, 'balance' => 0 ];
            }
        } else {
            $invoices = Olama_Reg_Billing_Invoice::get_family_invoices($family_uid, $year_id);
            $payments = Olama_Reg_Billing_Payment::get_family_payments($family_uid, $year_id);
            $summary = Olama_Reg_Billing_Invoice::get_invoice_summary($family_uid, $year_id);
        }

        wp_send_json_success([
            'invoices' => $invoices,
            'payments' => $payments,
            'summary' => $summary,
        ]);
    }

    // ── Custom Payments ────────────────────────────────────────────────────────

    public function ajax_get_family_students(): void
    {
        $this->guard();
        $family_uid = sanitize_text_field($_POST['family_uid'] ?? '');
        if (!$family_uid) {
            wp_send_json_error(['message' => __('Missing family UID.', 'olama-registration')]);
        }

        $students = Olama_Reg_Student::get_family_students($family_uid);
        wp_send_json_success(['students' => $students]);
    }

    public function ajax_save_custom_payment(): void
    {
        $this->guard();
        global $wpdb;

        $family_uid = sanitize_text_field($_POST['family_uid'] ?? '');
        $student_uids = isset($_POST['student_uids']) && is_array($_POST['student_uids']) ? array_map('sanitize_text_field', $_POST['student_uids']) : [];
        $service_type = sanitize_text_field($_POST['service_type'] ?? '');
        $amount = (float) ($_POST['amount'] ?? 0);
        $discount = (float) ($_POST['discount'] ?? 0);
        $fee_template = absint($_POST['fee_template_id'] ?? 0);
        $payment_meth = sanitize_text_field($_POST['payment_method'] ?? 'cash');
        $is_external = !empty($_POST['is_external']) || !empty($_POST['is_external_customer']);
        $ext_customer = absint($_POST['ext_customer_id'] ?? 0);

        $linked_agreement_id = isset($_POST['linked_agreement_id']) ? (int) $_POST['linked_agreement_id'] : 0;
        $linked_fee_ids = isset($_POST['linked_fee_ids']) ? array_map('intval', explode(',', $_POST['linked_fee_ids'])) : [];
        
        $selected_fees = [];
        if ($linked_agreement_id && !empty($linked_fee_ids)) {
            $fees_db = Olama_Reg_Agreement_Fees::get_by_agreement($linked_agreement_id);
            if ($fees_db) {
                foreach ($fees_db as $f) {
                    if (in_array($f->id, $linked_fee_ids)) {
                        $selected_fees[] = $f;
                    }
                }
            }
        }

        $template_items = [];
        if ($fee_template) {
            $template_obj = Olama_Reg_Billing_Fees::get_template($fee_template);
            if ($template_obj && !empty($template_obj->items)) {
                $template_items = $template_obj->items;
            }
        }

        $items = [];
        $total_amount = 0;

        if (!$is_external) {
            if (!$family_uid || empty($student_uids) || !$service_type || $amount <= 0) {
                wp_send_json_error(['message' => __('بيانات غير مكتملة. تأكد من تحديد الطلاب وتحديد قيمة الدفعة.', 'olama-registration')]);
            }

            $per_child_discount = $discount;
            // Build line items
            foreach ($student_uids as $s_uid) {
                $student = Olama_Reg_Student::get_student($s_uid);
                $s_name = $student ? trim($student->student_name) : $s_uid;

                if (!empty($selected_fees)) {
                    foreach ($selected_fees as $f) {
                        $items[] = [
                            'description' => sprintf('%s - %s', $f->label ?: $f->fee_category, $s_name),
                            'quantity' => 1,
                            'unit_price' => (float)$f->amount,
                        ];
                    }
                } else if (!empty($template_items)) {
                    foreach ($template_items as $t) {
                        $t_desc = is_object($t) ? $t->description : $t['description'];
                        $t_amt = is_object($t) ? $t->amount : $t['amount'];
                        $items[] = [
                            'description' => sprintf('%s - %s', $t_desc, $s_name),
                            'quantity' => 1,
                            'unit_price' => (float)$t_amt,
                        ];
                    }
                } else {
                    $items[] = [
                        'description' => sprintf('%s - %s', $service_type, $s_name),
                        'quantity' => 1,
                        'unit_price' => $amount,
                    ];
                }
            }

            $num_children = count($student_uids);
            if (!empty($template_items) || !empty($selected_fees)) {
                $total_amount = $num_children * $amount;
            } else {
                $total_amount = max(0, $num_children * ($amount - $discount));
            }
            $discount_for_invoice = $num_children * $discount;
        } else {
            if (!$ext_customer || !$service_type || $amount <= 0) {
                wp_send_json_error(['message' => __('بيانات غير مكتملة. تأكد من تحديد نوع الخدمة وقيمة الدفعة.', 'olama-registration')]);
            }

            // Use CUST-uid as family_uid for external customers (not EXT-{id})
            $customer = Olama_Reg_Customer::get($ext_customer);
            $family_uid = $customer ? $customer->customer_uid : ('CUST-' . str_pad($ext_customer, 4, '0', STR_PAD_LEFT));

            // Children come as array of child IDs (from DB checkboxes)
            $child_ids_raw = isset($_POST['child_ids']) && is_array($_POST['child_ids'])
                ? array_map('absint', $_POST['child_ids'])
                : [];

            // Also handle newly typed children (quick-add during payment)
            $new_children_raw = json_decode(stripslashes($_POST['new_children'] ?? '[]'), true);
            if (is_array($new_children_raw)) {
                foreach ($new_children_raw as $nc) {
                    $nc_name = sanitize_text_field($nc['name'] ?? '');
                    if ($nc_name) {
                        $new_child_id = Olama_Reg_Child::add($ext_customer, [
                            'child_name' => $nc_name,
                            'grade' => sanitize_text_field($nc['grade'] ?? ''),
                        ]);
                        if (!is_wp_error($new_child_id)) {
                            $child_ids_raw[] = $new_child_id;
                        }
                    }
                }
            }

            if (!empty($child_ids_raw)) {
                foreach ($child_ids_raw as $child_id) {
                    $child = Olama_Reg_Child::get($child_id);
                    if (!$child)
                        continue;
                    if (!empty($selected_fees)) {
                        foreach ($selected_fees as $f) {
                            $items[] = [
                                'description' => sprintf('%s - %s', $f->label ?: $f->fee_category, $child->child_name),
                                'quantity' => 1,
                                'unit_price' => (float)$f->amount,
                                'ext_child_id' => $child->id,
                            ];
                        }
                    } else if (!empty($template_items)) {
                        foreach ($template_items as $t) {
                            $t_desc = is_object($t) ? $t->description : $t['description'];
                            $t_amt = is_object($t) ? $t->amount : $t['amount'];
                            $items[] = [
                                'description' => sprintf('%s - %s', $t_desc, $child->child_name),
                                'quantity' => 1,
                                'unit_price' => (float)$t_amt,
                                'ext_child_id' => $child->id,
                            ];
                        }
                    } else {
                        $items[] = [
                            'description' => sprintf('%s - %s', $service_type, $child->child_name),
                            'quantity' => 1,
                            'unit_price' => $amount,
                            'ext_child_id' => $child->id,
                        ];
                    }
                }
                $num_children = count($child_ids_raw);
                if (!empty($template_items) || !empty($selected_fees)) {
                    $total_amount = $num_children * $amount;
                } else {
                    $total_amount = max(0, $num_children * ($amount - $discount));
                }
                $discount_for_invoice = $num_children * $discount;
            } else {
                // Payment for the customer directly (no children)
                if (!empty($selected_fees)) {
                    foreach ($selected_fees as $f) {
                        $items[] = [
                            'description' => sprintf('%s', $f->label ?: $f->fee_category),
                            'quantity' => 1,
                            'unit_price' => (float)$f->amount,
                        ];
                    }
                } else if (!empty($template_items)) {
                    foreach ($template_items as $t) {
                        $t_desc = is_object($t) ? $t->description : $t['description'];
                        $t_amt = is_object($t) ? $t->amount : $t['amount'];
                        $items[] = [
                            'description' => sprintf('%s', $t_desc),
                            'quantity' => 1,
                            'unit_price' => (float)$t_amt,
                        ];
                    }
                } else {
                    $items[] = [
                        'description' => $service_type,
                        'quantity' => 1,
                        'unit_price' => $amount,
                    ];
                }
                if (!empty($template_items) || !empty($selected_fees)) {
                    $total_amount = $amount;
                } else {
                    $total_amount = max(0, $amount - $discount);
                }
                $discount_for_invoice = $discount;
            }
        }

        $academic_year_id = 0;
        if (class_exists('Olama_School_Academic')) {
            $active_year = Olama_School_Academic::get_active_year();
            if ($active_year) {
                $academic_year_id = (int) $active_year->id;
            }
        }



        // 1. Create a single Invoice containing all items
        $invoice_data = [
            'family_uid' => $family_uid,
            'academic_year_id' => $academic_year_id,
            'fee_template_id' => $fee_template ?: null,
            'issue_date' => date('Y-m-d'),
            'status' => 'issued',
            'notes' => 'رسوم خدمة إضافية: ' . $service_type,
            'items' => $items,
            'discount' => isset($discount_for_invoice) ? $discount_for_invoice : $discount,
            'linked_agreement_id' => $linked_agreement_id ?: null,
        ];

        if ($is_external) {
            $invoice_data['ext_customer_id'] = $ext_customer;
            // If there is only one child, we can set ext_child_id for clearer reporting
            if (count($items) === 1 && !empty($items[0]['ext_child_id'])) {
                $invoice_data['ext_child_id'] = $items[0]['ext_child_id'];
            }
            $invoice_data['notes'] = 'رسوم خدمة: ' . $service_type;
        }

        $invoice_id = Olama_Reg_Billing_Invoice::create($invoice_data);

        if (is_wp_error($invoice_id)) {
            wp_send_json_error(['message' => $invoice_id->get_error_message()]);
        }

        // 2. Record a single Payment
        $payment_id = Olama_Reg_Billing_Payment::record([
            'family_uid' => $family_uid,
            'invoice_id' => $invoice_id,
            'amount' => $total_amount,
            'payment_date' => date('Y-m-d'),
            'method' => $payment_meth,
            'notes' => $is_external ? 'دفعة: ' . $service_type : 'دفعة مقبوضة عن خدمات إضافية: ' . $service_type,
        ]);

        if (is_wp_error($payment_id)) {
            wp_send_json_error(['message' => $payment_id->get_error_message()]);
        }



        if ($linked_agreement_id && !empty($linked_fee_ids)) {
            $agreement = Olama_Reg_Agreement::get($linked_agreement_id);
            if ($agreement) {
                foreach ($linked_fee_ids as $fid) {
                    $wpdb->update(
                        $wpdb->prefix . 'olama_agreement_fees',
                        ['paid_status' => 'paid', 'invoice_id' => $invoice_id],
                        ['id' => $fid, 'agreement_id' => $linked_agreement_id]
                    );
                }

                $all_fees = Olama_Reg_Agreement_Fees::get_by_agreement($linked_agreement_id);
                $all_paid = true;
                if ($all_fees) {
                    foreach ($all_fees as $f) {
                        // After update, we need to check the updated state or just skip the ones we just updated
                        // The get_by_agreement will fetch new state if caching allows, but to be safe:
                        if (!in_array($f->id, $linked_fee_ids) && $f->paid_status === 'unpaid') {
                            $all_paid = false;
                            break;
                        }
                    }
                }

                if ($all_paid && $agreement->status === 'draft') {
                    $wpdb->update(
                        $wpdb->prefix . 'olama_agreements',
                        ['status' => 'active'],
                        ['id' => $linked_agreement_id]
                    );
                }
            }
        }

        wp_send_json_success([
            'message' => __('تم تسجيل الفاتورة وإصدار السند بنجاح.', 'olama-registration'),
            'invoice_id' => $invoice_id,
            'payment_id' => $payment_id,
        ]);
    }

    // ── Settlement Receipts ───────────────────────────────────────────────────

    public function ajax_create_settlement(): void
    {
        $this->guard();
        $result = Olama_Reg_Settlement::create_receipt($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('تم إنشاء إيصال التسوية بنجاح.', 'olama-registration'),
            'receipt_id' => $result,
        ]);
    }

    public function ajax_settle_receipt(): void
    {
        $this->guard();
        $id = (int) ($_POST['id'] ?? 0);
        $oracle_receipt = sanitize_text_field($_POST['oracle_receipt_number'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        $result = Olama_Reg_Settlement::settle_receipt($id, $oracle_receipt, $notes);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('تمت التسوية بنجاح.', 'olama-registration'),
        ]);
    }

    public function ajax_cancel_settlement(): void
    {
        $this->guard();
        $id = (int) ($_POST['id'] ?? 0);

        $result = Olama_Reg_Settlement::cancel_receipt($id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('تم إلغاء الإيصال بنجاح.', 'olama-registration'),
        ]);
    }

    // ── Agreements ────────────────────────────────────────────────────────────

    public function ajax_agr_save_header(): void
    {
        $this->guard();
        $id = (int) ($_POST['id'] ?? 0);

        $participant_id = 0;
        $participant_ids = [];
        if (isset($_POST['participant_id'])) {
            if (is_array($_POST['participant_id'])) {
                $participant_ids = array_map('intval', $_POST['participant_id']);
                $participant_id = $participant_ids[0] ?? 0;
            } else {
                $participant_id = (int) $_POST['participant_id'];
                $participant_ids = [$participant_id];
            }
        }

        $data = [
            'payer_type' => sanitize_text_field($_POST['payer_type'] ?? ''),
            'payer_id' => sanitize_text_field($_POST['payer_id'] ?? ''),
            'participant_type' => (sanitize_text_field($_POST['payer_type'] ?? '') === 'family') ? 'student' : 'child',
            'participant_id' => $participant_id,
            'participant_ids' => $participant_ids,
            'activity_type' => sanitize_text_field($_POST['activity_type'] ?? ''),
            'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date' => sanitize_text_field($_POST['end_date'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'template_id' => absint($_POST['template_id'] ?? 0) ?: null,
        ];

        if (isset($_POST['status'])) {
            $data['status'] = sanitize_text_field($_POST['status']);
        }

        if (empty($data['end_date'])) {
            $data['end_date'] = null; // Important for DB
        }

        if ($id > 0) {
            $old = Olama_Reg_Agreement::get($id);
            $old_template_id = $old ? $old->template_id : null;
            $old_participant_count = $old ? count($old->participant_ids_array) : 0;

            $result = Olama_Reg_Agreement::update($id, $data);
            if ($result) {
                $existing_fees = Olama_Reg_Agreement_Fees::get_by_agreement($id);
                if ($data['template_id'] && ($data['template_id'] != $old_template_id || empty($existing_fees))) {
                    Olama_Reg_Agreement_Fees::apply_template_fees($id, $data['template_id']);
                }
                $agreement = Olama_Reg_Agreement::get($id);
                wp_send_json_success([
                    'message'          => __('تم تحديث العقد.', 'olama-registration'),
                    'id'               => $id,
                    'agreement_number' => $agreement ? $agreement->agreement_number : '',
                ]);
            }
        } else {
            $id = Olama_Reg_Agreement::create($data);
            if ($id) {
                if ($data['template_id']) {
                    Olama_Reg_Agreement_Fees::apply_template_fees($id, $data['template_id']);
                }
                $agreement = Olama_Reg_Agreement::get($id);
                wp_send_json_success([
                    'message'          => __('تم إنشاء العقد.', 'olama-registration'),
                    'id'               => $id,
                    'agreement_number' => $agreement ? $agreement->agreement_number : '',
                ]);
            }
        }

        wp_send_json_error(['message' => __('حدث خطأ أثناء الحفظ.', 'olama-registration')]);
    }

    public function ajax_agr_get_details(): void
    {
        $this->guard();
        $id = (int) ($_POST['id'] ?? 0);
        $agreement = Olama_Reg_Agreement::get($id);
        if (!$agreement) {
            wp_send_json_error(['message' => __('العقد غير موجود.', 'olama-registration')]);
        }

        // Fetch fees
        $fees = Olama_Reg_Agreement_Fees::get_by_agreement($id);
        $fees_data = [];
        foreach ($fees as $f) {
            $fees_data[] = [
                'id' => $f->id,
                'fee_category' => $f->fee_category,
                'child_id' => $f->child_id,
                'label' => $f->label,
                'amount' => $f->amount,
                'discount' => $f->discount,
                'net_amount' => $f->net_amount,
                'due_date' => $f->due_date,
                'paid_status' => $f->paid_status,
                'invoice_id' => $f->invoice_id,
            ];
        }

        // Fetch clauses
        $clauses = Olama_Reg_Agreement_Clauses::get_by_agreement($id);
        $clauses_data = [];
        foreach ($clauses as $c) {
            $clauses_data[] = [
                'id' => $c->id,
                'clause_text' => $c->clause_text,
                'sort_order' => $c->sort_order,
            ];
        }

        wp_send_json_success([
            'agreement' => [
                'id' => $agreement->id,
                'agreement_number' => $agreement->agreement_number,
                'payer_type' => $agreement->payer_type,
                'payer_id' => $agreement->payer_id,
                'activity_type' => $agreement->activity_type,
                'start_date' => $agreement->start_date,
                'end_date' => $agreement->end_date,
                'status' => $agreement->status,
                'notes' => $agreement->notes,
                'template_id' => $agreement->template_id,
                'total_amount' => $agreement->total_amount,
            ],
            'fees' => $fees_data,
            'clauses' => $clauses_data,
        ]);
    }

    public function ajax_agr_search_payer(): void
    {
        $this->guard();
        global $wpdb;
        $q = sanitize_text_field($_POST['q'] ?? '');
        $payer_type = sanitize_text_field($_POST['payer_type'] ?? 'customer');
        $like = '%' . $wpdb->esc_like($q) . '%';
        $results = [];

        if ($payer_type === 'customer') {
            $table = $wpdb->prefix . 'olama_customers';
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, customer_name AS text FROM {$table} WHERE customer_name LIKE %s OR phone LIKE %s LIMIT 15",
                $like,
                $like
            ));
            foreach ($rows as $r)
                $results[] = ['id' => $r->id, 'text' => $r->text];
        } elseif ($payer_type === 'family') {
            $table = $wpdb->prefix . 'olama_families';
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT family_uid AS id, family_name AS text FROM {$table} WHERE family_name LIKE %s OR family_uid LIKE %s LIMIT 15",
                $like,
                $like
            ));
            foreach ($rows as $r)
                $results[] = ['id' => $r->id, 'text' => $r->id . ' - ' . $r->text];
        }

        wp_send_json_success(['results' => $results]);
    }

    public function ajax_agr_get_participants(): void
    {
        $this->guard();
        global $wpdb;
        $payer_type = sanitize_text_field($_POST['payer_type'] ?? 'customer');
        $payer_id = sanitize_text_field($_POST['payer_id'] ?? '');
        $results = [];

        if ($payer_type === 'customer' && $payer_id) {
            $customer_int_id = 0;
            if (is_numeric($payer_id)) {
                $customer_int_id = (int) $payer_id;
            } else {
                $customer_int_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}olama_customers WHERE customer_uid = %s LIMIT 1",
                    $payer_id
                ));
            }

            if ($customer_int_id) {
                $table = $wpdb->prefix . 'olama_customer_children';
                $rows = $wpdb->get_results($wpdb->prepare("SELECT id, child_name AS text FROM {$table} WHERE customer_id = %d AND is_active = 1", $customer_int_id));
                foreach ($rows as $r)
                    $results[] = ['id' => $r->id, 'text' => $r->text];
            }
        } elseif ($payer_type === 'family' && $payer_id) {
            $table = $wpdb->prefix . 'olama_students';
            $rows = $wpdb->get_results($wpdb->prepare("SELECT student_uid AS id, student_name AS text FROM {$table} WHERE family_id = %s", $payer_id));
            foreach ($rows as $r)
                $results[] = ['id' => $r->id, 'text' => $r->text];
        }

        wp_send_json_success(['results' => $results]);
    }

    public function ajax_agr_save_fee(): void
    {
        $this->guard();
        $id = (int) ($_POST['id'] ?? 0);
        $agreement_id = (int) ($_POST['agreement_id'] ?? 0);

        $data = [
            'child_id' => (isset($_POST['child_id']) && $_POST['child_id'] !== '') ? sanitize_text_field($_POST['child_id']) : null,
            'fee_category' => sanitize_text_field($_POST['fee_category'] ?? ''),
            'label' => sanitize_text_field($_POST['label'] ?? ''),
            'amount' => (float) ($_POST['amount'] ?? 0),
            'discount' => (float) ($_POST['discount'] ?? 0),
            'due_date' => sanitize_text_field($_POST['due_date'] ?? ''),
        ];
        if (empty($data['due_date']))
            $data['due_date'] = null;

        if ($id > 0) {
            $result = Olama_Reg_Agreement_Fees::update($id, $data);
            if ($result) {
                wp_send_json_success(['message' => __('تم تحديث الرسم.', 'olama-registration'), 'total' => Olama_Reg_Agreement::get($agreement_id)->total_amount]);
            }
        } else {
            $data['agreement_id'] = $agreement_id;
            $new_id = Olama_Reg_Agreement_Fees::add($agreement_id, $data);
            if ($new_id) {
                wp_send_json_success(['message' => __('تمت إضافة الرسم.', 'olama-registration'), 'id' => $new_id, 'total' => Olama_Reg_Agreement::get($agreement_id)->total_amount]);
            }
        }
        wp_send_json_error(['message' => __('حدث خطأ أثناء حفظ الرسم.', 'olama-registration')]);
    }

    public function ajax_agr_delete_fee(): void
    {
        $this->guard();
        $id = (int) ($_POST['id'] ?? 0);
        $agreement_id = (int) ($_POST['agreement_id'] ?? 0);

        if (Olama_Reg_Agreement_Fees::delete($id)) {
            wp_send_json_success(['message' => __('تم حذف الرسم.', 'olama-registration'), 'total' => Olama_Reg_Agreement::get($agreement_id)->total_amount]);
        }
        wp_send_json_error(['message' => __('لا يمكن حذف هذا الرسم.', 'olama-registration')]);
    }

    public function ajax_agr_add_clause(): void
    {
        $this->guard();
        $agreement_id = (int) ($_POST['agreement_id'] ?? 0);
        $text = sanitize_textarea_field($_POST['clause_text'] ?? '');

        $id = Olama_Reg_Agreement_Clauses::add($agreement_id, $text);
        if ($id) {
            wp_send_json_success(['message' => __('تمت الإضافة.', 'olama-registration'), 'id' => $id]);
        }
        wp_send_json_error(['message' => __('حدث خطأ.', 'olama-registration')]);
    }

    public function ajax_agr_save_clause(): void
    {
        $this->guard();
        $id = (int) ($_POST['id'] ?? 0);
        $text = sanitize_textarea_field($_POST['clause_text'] ?? '');

        if (Olama_Reg_Agreement_Clauses::update($id, $text)) {
            wp_send_json_success(['message' => __('تم الحفظ.', 'olama-registration')]);
        }
        wp_send_json_error(['message' => __('حدث خطأ.', 'olama-registration')]);
    }

    public function ajax_agr_delete_clause(): void
    {
        $this->guard();
        $id = (int) ($_POST['id'] ?? 0);

        if (Olama_Reg_Agreement_Clauses::delete($id)) {
            wp_send_json_success(['message' => __('تم الحذف.', 'olama-registration')]);
        }
        wp_send_json_error(['message' => __('حدث خطأ.', 'olama-registration')]);
    }

    public function ajax_agr_reorder_clauses(): void
    {
        $this->guard();
        $ordered_ids = isset($_POST['ordered_ids']) && is_array($_POST['ordered_ids']) ? array_map('intval', $_POST['ordered_ids']) : [];
        if (!empty($ordered_ids)) {
            Olama_Reg_Agreement_Clauses::reorder($ordered_ids);
            wp_send_json_success();
        }
        wp_send_json_error();
    }

    public function ajax_agr_generate_invoice(): void
    {
        $this->guard();
        $agreement_id = (int) ($_POST['agreement_id'] ?? 0);
        $fee_ids = isset($_POST['fee_ids']) && is_array($_POST['fee_ids']) ? array_map('intval', $_POST['fee_ids']) : [];

        if (empty($fee_ids)) {
            wp_send_json_error(['message' => __('يجب تحديد رسم واحد على الأقل.', 'olama-registration')]);
        }

        $result = Olama_Reg_Agreement_Invoice::generate_invoice($agreement_id, $fee_ids);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('تم إصدار الفاتورة بنجاح.', 'olama-registration'),
            'invoice_id' => $result
        ]);
    }

    public function ajax_agr_get_unpaid_fees(): void
    {
        $this->guard();
        $agreement_id = (int) ($_POST['agreement_id'] ?? 0);
        if (!$agreement_id) {
            wp_send_json_error(['message' => __('معرف العقد مطلوب.', 'olama-registration')]);
        }

        $fees = Olama_Reg_Agreement_Fees::get_by_agreement($agreement_id);
        $unpaid_fees = [];
        if ($fees) {
            foreach ($fees as $fee) {
                if ($fee->paid_status === 'unpaid') {
                    $unpaid_fees[] = $fee;
                }
            }
        }

        wp_send_json_success(['fees' => $unpaid_fees]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CUSTOMER HUB — Phase 2 Handlers
    // Nonce: 'os_hub_nonce'  (separate from main plugin nonce)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Separate guard for hub endpoints (uses os_hub_nonce).
     */
    private function hub_guard(): void
    {
        check_ajax_referer('os_hub_nonce', 'nonce');
        if (
            ! current_user_can('olama_manage_registration_families') &&
            ! current_user_can('manage_options')
        ) {
            wp_send_json_error(['message' => __('Unauthorized.', 'olama-registration')], 403);
        }
    }

    // ── os_hub_search ─────────────────────────────────────────────────────────

    /**
     * Unified search across families and external customers.
     *
     * POST: q (string, min 2), type ('family'|'external')
     * Returns: { results: [ { uid, name, phone, is_active, student_count|child_count } ] }
     */
    public function hub_search(): void
    {
        $this->hub_guard();

        $query = sanitize_text_field($_POST['q'] ?? '');
        $type  = sanitize_key($_POST['type'] ?? 'family');

        if (strlen($query) < 2) {
            wp_send_json_success(['results' => []]);
        }

        $results = ($type === 'family')
            ? $this->hub_search_families($query)
            : $this->hub_search_customers($query);

        wp_send_json_success(['results' => $results]);
    }

    private function hub_search_families(string $query): array
    {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($query) . '%';

        $sql = "SELECT
                    f.family_uid  AS uid,
                    f.family_name AS name,
                    COALESCE(f.father_mobile, f.mother_mobile, '') AS phone,
                    f.is_active,
                    COUNT(s.id)   AS student_count
                FROM {$wpdb->prefix}olama_families f
                LEFT JOIN {$wpdb->prefix}olama_students s
                    ON s.family_id = f.family_uid AND s.is_active = 1
                WHERE f.family_name   LIKE %s
                   OR f.family_uid    LIKE %s
                   OR f.father_mobile LIKE %s
                   OR f.mother_mobile LIKE %s
                GROUP BY f.id
                ORDER BY f.family_name
                LIMIT 20";

        return $wpdb->get_results(
            $wpdb->prepare($sql, $like, $like, $like, $like)
        ) ?: [];
    }

    private function hub_search_customers(string $query): array
    {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($query) . '%';

        $sql = "SELECT
                    c.customer_uid AS uid,
                    c.customer_name AS name,
                    COALESCE(c.phone, '') AS phone,
                    c.is_active,
                    COUNT(ch.id) AS child_count,
                    c.id AS internal_id
                FROM {$wpdb->prefix}olama_customers c
                LEFT JOIN {$wpdb->prefix}olama_customer_children ch
                    ON ch.customer_id = c.id AND ch.is_active = 1
                WHERE c.customer_name LIKE %s
                   OR c.customer_uid  LIKE %s
                   OR c.phone         LIKE %s
                GROUP BY c.id
                ORDER BY c.customer_name
                LIMIT 20";

        return $wpdb->get_results(
            $wpdb->prepare($sql, $like, $like, $like)
        ) ?: [];
    }

    // ── os_hub_counts ─────────────────────────────────────────────────────────

    /**
     * Batch-load badge counts for all 8 tiles in one round-trip.
     *
     * POST: uid (string), type ('family'|'external'), year (int, optional)
     * Returns: { counts: { profile, agreements, invoices, payments,
     *                      children, financial, history, settlements } }
     */
    public function hub_counts(): void
    {
        $this->hub_guard();

        $uid  = sanitize_text_field($_POST['uid']  ?? '');
        $type = sanitize_key($_POST['type'] ?? 'family');
        $year = (int) ($_POST['year'] ?? 0);

        if (! $year && class_exists('Olama_School_Academic')) {
            $active = Olama_School_Academic::get_active_year();
            $year   = $active ? (int) $active->id : 0;
        }

        global $wpdb;

        if ($type === 'family') {
            // Check if family exists
            $family_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}olama_families WHERE family_uid = %s",
                $uid
            ));
            if (!$family_exists) {
                wp_send_json_error([
                    'code'    => 'not_found',
                    'message' => __('العائلة المحددة غير موجودة في النظام أو تم حذفها.', 'olama-registration'),
                ]);
            }

            // Agreements
            $agreements = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}olama_agreements
                  WHERE payer_type = 'family' AND payer_id = %s",
                $uid
            ));

            // Invoices (current year or all)
            $inv_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}olama_invoices
                         WHERE family_uid = %s AND status != 'cancelled'";
            $inv_args = [$uid];
            if ($year) {
                $inv_sql .= ' AND academic_year_id = %d';
                $inv_args[] = $year;
            }
            $invoices = (int) $wpdb->get_var($wpdb->prepare($inv_sql, $inv_args));

            // Payments
            $pay_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}olama_payments p
                         INNER JOIN {$wpdb->prefix}olama_invoices i ON i.id = p.invoice_id
                         WHERE p.family_uid = %s";
            $pay_args = [$uid];
            if ($year) {
                $pay_sql .= ' AND i.academic_year_id = %d';
                $pay_args[] = $year;
            }
            $payments = (int) $wpdb->get_var($wpdb->prepare($pay_sql, $pay_args));

            // Children (students)
            $children = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}olama_students
                  WHERE family_id = %s AND is_active = 1",
                $uid
            ));

            // History / audit
            $history = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}olama_billing_audit
                  WHERE entity_type = 'family' AND entity_id = %s",
                $uid
            ));

            // Settlements (families only)
            $settle_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}olama_settlement_receipts
                            WHERE family_id = %s";
            $settle_args = [$uid];
            if ($year) {
                // The table may store year via dates; check if academic_year_id column exists
                $cols = $wpdb->get_col("DESCRIBE {$wpdb->prefix}olama_settlement_receipts", 0);
                if (in_array('academic_year_id', $cols)) {
                    $settle_sql .= ' AND academic_year_id = %d';
                    $settle_args[] = $year;
                }
            }
            $settlements = (int) $wpdb->get_var($wpdb->prepare($settle_sql, $settle_args));

            // Financial summary (just 1 record flag — shown as null badge)
            $financial = null;

        } else {
            // External customer — resolve internal id from UID
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}olama_customers WHERE customer_uid = %s LIMIT 1",
                $uid
            ));
            if (!$customer) {
                wp_send_json_error([
                    'code'    => 'not_found',
                    'message' => __('العميل المحدد غير موجود في النظام أو تم حذفه.', 'olama-registration'),
                ]);
            }
            $cid = (int) $customer->id;

            $agreements = $cid ? (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}olama_agreements
                  WHERE payer_type = 'customer' AND payer_id = %d",
                $cid
            )) : 0;

            $invoices = $cid ? (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}olama_invoices
                  WHERE ext_customer_id = %d AND status != 'cancelled'",
                $cid
            )) : 0;

            $payments = $cid ? (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}olama_payments p
                  INNER JOIN {$wpdb->prefix}olama_invoices i ON i.id = p.invoice_id
                  WHERE i.ext_customer_id = %d",
                $cid
            )) : 0;

            $children = $cid ? (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}olama_customer_children
                  WHERE customer_id = %d AND is_active = 1",
                $cid
            )) : 0;

            $history      = 0; // Phase 5 will query audit for external
            $financial    = null;
            $settlements  = null; // Not applicable for external customers
        }

        wp_send_json_success([
            'counts' => [
                'profile'     => 1, // Always has a profile
                'agreements'  => $agreements,
                'invoices'    => $invoices,
                'payments'    => $payments,
                'children'    => $children,
                'financial'   => $financial,
                'history'     => $history,
                'settlements' => $settlements,
            ],
            // Also return mini financial summary for identity header
            'financial_mini' => ($type === 'family')
                ? $this->hub_financial_mini($uid, $year)
                : null,
        ]);
    }

    /**
     * Returns minimal financial data for the identity header.
     */
    private function hub_financial_mini(string $family_uid, int $year): array
    {
        global $wpdb;
        $args = [$family_uid];
        $year_clause = '';
        if ($year) {
            $year_clause = ' AND academic_year_id = %d';
            $args[] = $year;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COALESCE(SUM(total),       0) AS total_billed,
                COALESCE(SUM(amount_paid), 0) AS total_paid,
                COALESCE(SUM(balance),     0) AS total_balance
             FROM {$wpdb->prefix}olama_invoices
             WHERE family_uid = %s
               AND status != 'cancelled'
               {$year_clause}",
            $args
        ));

        return [
            'total_billed'  => $row ? (float) $row->total_billed  : 0,
            'total_paid'    => $row ? (float) $row->total_paid    : 0,
            'total_balance' => $row ? (float) $row->total_balance : 0,
        ];
    }

    // ── os_hub_tile ───────────────────────────────────────────────────────────

    /**
     * Unified tile content loader — single endpoint.
     *
     * POST: tile (string), uid (string), type ('family'|'external'), year (int)
     * Returns: { html: '<string>', meta: {...} }
     */
    public function hub_tile(): void
    {
        $this->hub_guard();

        $tile = sanitize_key($_POST['tile'] ?? '');
        $uid  = sanitize_text_field($_POST['uid']  ?? '');
        $type = sanitize_key($_POST['type'] ?? 'family');
        $year = (int) ($_POST['year'] ?? 0);

        if (! $year && class_exists('Olama_School_Academic')) {
            $active = Olama_School_Academic::get_active_year();
            $year   = $active ? (int) $active->id : 0;
        }

        // Settlement tile only for families
        if ($tile === 'settlements' && $type !== 'family') {
            wp_send_json_error(['message' => __('هذا القسم للعائلات فقط.', 'olama-registration')]);
        }

        $handlers = [
            'profile'     => [$this, 'hub_tile_profile'],
            'agreements'  => [$this, 'hub_tile_agreements'],
            'invoices'    => [$this, 'hub_tile_invoices'],
            'payments'    => [$this, 'hub_tile_payments'],
            'children'    => [$this, 'hub_tile_children'],
            'financial'   => [$this, 'hub_tile_financial'],
            'history'     => [$this, 'hub_tile_history'],
            'settlements' => [$this, 'hub_tile_settlements'],
        ];

        if (! isset($handlers[$tile]) || ! is_callable($handlers[$tile])) {
            wp_send_json_error(['message' => __('Unknown tile.', 'olama-registration')]);
        }

        $data = call_user_func($handlers[$tile], $uid, $type, $year);
        wp_send_json_success([
            'html' => $data['html'] ?? '',
            'meta' => $data['meta'] ?? [],
        ]);
    }

    // ── Tile: Profile ─────────────────────────────────────────────────────────

    private function hub_tile_profile(string $uid, string $type, int $year): array
    {
        if ($type === 'family') {
            $row = Olama_Reg_Family::get_family($uid);
            if (! $row) {
                return ['html' => $this->hub_empty_state(__('العائلة غير موجودة.', 'olama-registration'))];
            }

            $is_active    = (bool) $row->is_active;
            $status_badge = $is_active
                ? '<span class="os-hub-badge os-hub-badge--green">' . __('نشط', 'olama-registration') . '</span>'
                : '<span class="os-hub-badge os-hub-badge--gray">'  . __('غير نشط', 'olama-registration') . '</span>';

            // ── Data view ──────────────────────────────────────────────────
            $html  = '<div class="os-hub-profile-wrap" data-uid="' . esc_attr($uid) . '" data-type="family">';

            // Action bar
            $html .= '<div class="os-hub-profile-actions">';
            $html .= '<button type="button" class="button os-hub-edit-btn" id="os-hub-profile-edit-btn">';
            $html .= '<span class="dashicons dashicons-edit" aria-hidden="true"></span> ' . __('تعديل', 'olama-registration');
            $html .= '</button>';
            $html .= ' <button type="button" class="button os-hub-toggle-active-btn" data-active="' . ($is_active ? '1' : '0') . '">';
            $html .= '<span class="dashicons ' . ($is_active ? 'dashicons-lock' : 'dashicons-unlock') . '" aria-hidden="true"></span> ';
            $html .= $is_active ? __('تعطيل', 'olama-registration') : __('تفعيل', 'olama-registration');
            $html .= '</button>';
            $html .= '</div>'; // .os-hub-profile-actions

            // Read-only view
            $html .= '<table class="os-hub-data-table widefat fixed striped os-hub-profile-view">';
            $html .= '<tbody>';
            $html .= $this->hub_tr(__('رقم الملف', 'olama-registration'),  esc_html($row->family_uid));
            $html .= $this->hub_tr(__('اسم العائلة', 'olama-registration'), esc_html($row->family_name));
            $html .= $this->hub_tr(__('هاتف الأب', 'olama-registration'),  esc_html($row->father_mobile ?? '—'));
            $html .= $this->hub_tr(__('هاتف الأم', 'olama-registration'),  esc_html($row->mother_mobile ?? '—'));
            $html .= $this->hub_tr(__('العنوان', 'olama-registration'),    esc_html($row->address ?? '—'));
            $html .= $this->hub_tr(__('الحالة', 'olama-registration'),     $status_badge);
            $html .= '</tbody></table>';

            // Inline edit form (hidden by default)
            $html .= '<form class="os-hub-profile-form" id="os-hub-profile-form" style="display:none;" novalidate>';
            $html .= '<input type="hidden" name="uid"  value="' . esc_attr($uid) . '">';
            $html .= '<input type="hidden" name="type" value="family">';
            $html .= '<table class="os-hub-data-table widefat fixed">';
            $html .= '<tbody>';
            $html .= '<tr><th>' . __('اسم العائلة', 'olama-registration') . '</th><td>';
            $html .= '<input type="text" name="family_name" class="widefat" required value="' . esc_attr($row->family_name) . '">';
            $html .= '</td></tr>';
            $html .= '<tr><th>' . __('هاتف الأب', 'olama-registration') . '</th><td>';
            $html .= '<input type="text" name="father_mobile" class="widefat" value="' . esc_attr($row->father_mobile ?? '') . '">';
            $html .= '</td></tr>';
            $html .= '<tr><th>' . __('هاتف الأم', 'olama-registration') . '</th><td>';
            $html .= '<input type="text" name="mother_mobile" class="widefat" value="' . esc_attr($row->mother_mobile ?? '') . '">';
            $html .= '</td></tr>';
            $html .= '<tr><th>' . __('العنوان', 'olama-registration') . '</th><td>';
            $html .= '<input type="text" name="address" class="widefat" value="' . esc_attr($row->address ?? '') . '">';
            $html .= '</td></tr>';
            $html .= '</tbody></table>';
            $html .= '<div class="os-hub-form-actions">';
            $html .= '<button type="submit" class="button button-primary os-hub-form-save">';
            $html .= '<span class="dashicons dashicons-saved" aria-hidden="true"></span> ' . __('حفظ التغييرات', 'olama-registration');
            $html .= '</button>';
            $html .= ' <button type="button" class="button os-hub-form-cancel">' . __('إلغاء', 'olama-registration') . '</button>';
            $html .= '</div>';
            $html .= '</form>';

            // Toast placeholder
            $html .= '<div class="os-hub-profile-toast" aria-live="polite"></div>';
            $html .= '</div>'; // .os-hub-profile-wrap

        } else {
            global $wpdb;
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}olama_customers WHERE customer_uid = %s LIMIT 1",
                $uid
            ));
            if (! $row) {
                return ['html' => $this->hub_empty_state(__('العميل غير موجود.', 'olama-registration'))];
            }

            $is_active    = (bool) $row->is_active;
            $status_badge = $is_active
                ? '<span class="os-hub-badge os-hub-badge--green">' . __('نشط', 'olama-registration') . '</span>'
                : '<span class="os-hub-badge os-hub-badge--gray">'  . __('غير نشط', 'olama-registration') . '</span>';

            $html  = '<div class="os-hub-profile-wrap" data-uid="' . esc_attr($uid) . '" data-type="external">';

            // Action bar
            $html .= '<div class="os-hub-profile-actions">';
            $html .= '<button type="button" class="button os-hub-edit-btn" id="os-hub-profile-edit-btn">';
            $html .= '<span class="dashicons dashicons-edit" aria-hidden="true"></span> ' . __('تعديل', 'olama-registration');
            $html .= '</button>';
            $html .= ' <button type="button" class="button os-hub-toggle-active-btn" data-active="' . ($is_active ? '1' : '0') . '">';
            $html .= '<span class="dashicons ' . ($is_active ? 'dashicons-lock' : 'dashicons-unlock') . '" aria-hidden="true"></span> ';
            $html .= $is_active ? __('تعطيل', 'olama-registration') : __('تفعيل', 'olama-registration');
            $html .= '</button>';
            $html .= '</div>';

            // Read-only view
            $html .= '<table class="os-hub-data-table widefat fixed striped os-hub-profile-view">';
            $html .= '<tbody>';
            $html .= $this->hub_tr(__('رقم العميل', 'olama-registration'), esc_html($row->customer_uid));
            $html .= $this->hub_tr(__('الاسم', 'olama-registration'),      esc_html($row->customer_name));
            $html .= $this->hub_tr(__('الهاتف', 'olama-registration'),     esc_html($row->phone ?? '—'));
            $html .= $this->hub_tr(__('الملاحظات', 'olama-registration'),  esc_html($row->notes ?? '—'));
            $html .= $this->hub_tr(__('الحالة', 'olama-registration'),     $status_badge);
            $html .= '</tbody></table>';

            // Inline edit form
            $html .= '<form class="os-hub-profile-form" id="os-hub-profile-form" style="display:none;" novalidate>';
            $html .= '<input type="hidden" name="uid"  value="' . esc_attr($uid) . '">';
            $html .= '<input type="hidden" name="type" value="external">';
            $html .= '<table class="os-hub-data-table widefat fixed">';
            $html .= '<tbody>';
            $html .= '<tr><th>' . __('الاسم', 'olama-registration') . '</th><td>';
            $html .= '<input type="text" name="customer_name" class="widefat" required value="' . esc_attr($row->customer_name) . '">';
            $html .= '</td></tr>';
            $html .= '<tr><th>' . __('الهاتف', 'olama-registration') . '</th><td>';
            $html .= '<input type="text" name="phone" class="widefat" value="' . esc_attr($row->phone ?? '') . '">';
            $html .= '</td></tr>';
            $html .= '<tr><th>' . __('الملاحظات', 'olama-registration') . '</th><td>';
            $html .= '<textarea name="notes" class="widefat" rows="3">' . esc_textarea($row->notes ?? '') . '</textarea>';
            $html .= '</td></tr>';
            $html .= '</tbody></table>';
            $html .= '<div class="os-hub-form-actions">';
            $html .= '<button type="submit" class="button button-primary os-hub-form-save">';
            $html .= '<span class="dashicons dashicons-saved" aria-hidden="true"></span> ' . __('حفظ التغييرات', 'olama-registration');
            $html .= '</button>';
            $html .= ' <button type="button" class="button os-hub-form-cancel">' . __('إلغاء', 'olama-registration') . '</button>';
            $html .= '</div>';
            $html .= '</form>';

            $html .= '<div class="os-hub-profile-toast" aria-live="polite"></div>';
            $html .= '</div>';
        }

        return ['html' => $html];
    }

    // ── Tile: Agreements ──────────────────────────────────────────────────────

    private function hub_tile_agreements(string $uid, string $type, int $year): array
    {
        global $wpdb;
        $payer_type = ($type === 'family') ? 'family' : 'customer';
        $payer_id   = $uid;

        // For external customers, resolve internal id
        if ($type === 'external') {
            $c = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}olama_customers WHERE customer_uid = %s LIMIT 1",
                $uid
            ));
            $payer_id = $c ? (string) $c->id : $uid;
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, a.agreement_number, a.activity_type, a.status,
                    a.start_date, a.end_date, a.total_amount
             FROM {$wpdb->prefix}olama_agreements a
             WHERE a.payer_type = %s AND a.payer_id = %s
             ORDER BY a.id DESC",
            $payer_type, $payer_id
        ));

        if (! $rows) {
            $html = $this->hub_empty_state(
                __('لا توجد عقود مسجلة', 'olama-registration'),
                'dashicons-media-document'
            );
            return ['html' => $html];
        }

        $html  = '<table class="os-hub-data-table widefat fixed striped">';
        $html .= '<thead><tr>';
        $html .= '<th>' . __('رقم العقد', 'olama-registration') . '</th>';
        $html .= '<th>' . __('الطلاب المشتركين', 'olama-registration') . '</th>';
        $html .= '<th>' . __('طبيعة العقد', 'olama-registration') . '</th>';
        $html .= '<th>' . __('الحالة', 'olama-registration') . '</th>';
        $html .= '<th>' . __('الفواتير المرتبطة', 'olama-registration') . '</th>';
        $html .= '<th>' . __('اجمالي العقد', 'olama-registration') . '</th>';
        $html .= '<th>' . __('المبلغ المحصل', 'olama-registration') . '</th>';
        $html .= '<th>' . __('المبلغ المتبقي', 'olama-registration') . '</th>';
        $html .= '<th>' . __('الإجراءات', 'olama-registration') . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $r) {
            $agreement = Olama_Reg_Agreement::get($r->id);
            if (!$agreement) continue;

            $status_map = [
                'draft'     => ['label' => __('مسودة', 'olama-registration'),    'cls' => 'os-hub-badge--gray'],
                'active'    => ['label' => __('نشط', 'olama-registration'),      'cls' => 'os-hub-badge--green'],
                'completed' => ['label' => __('مكتمل', 'olama-registration'),    'cls' => 'os-hub-badge--blue'],
                'cancelled' => ['label' => __('ملغى', 'olama-registration'),     'cls' => 'os-hub-badge--red'],
            ];
            $s = $status_map[$r->status] ?? ['label' => esc_html($r->status), 'cls' => 'os-hub-badge--gray'];

            // Linked invoices
            $invoices = $wpdb->get_results($wpdb->prepare(
                "SELECT id, invoice_number, status, balance FROM {$wpdb->prefix}olama_invoices WHERE agreement_id = %d",
                $r->id
            ));
            $invoice_links = [];
            if (!empty($invoices)) {
                foreach ($invoices as $inv) {
                    $payment_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}olama_payments WHERE invoice_id = %d LIMIT 1", $inv->id));
                    if ($payment_id) {
                        $url = admin_url('admin.php?page=olama-registration-payments&action=print_receipt&id=' . $payment_id);
                        $invoice_links[] = '<a href="' . esc_url($url) . '" target="_blank">' . esc_html($inv->invoice_number) . '</a>';
                    } else {
                        $invoice_links[] = esc_html($inv->invoice_number);
                    }
                }
            }
            $invoices_str = !empty($invoice_links) ? implode('<br>', $invoice_links) : '—';

            // Collected and remaining
            $collected = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(amount_paid) FROM {$wpdb->prefix}olama_invoices WHERE agreement_id = %d AND status != 'cancelled'",
                $r->id
            ));
            $remaining = max(0, (float)$r->total_amount - $collected);

            $print_url = admin_url('admin.php?page=olama-registration-agreements&action=print&id=' . $r->id);

            // Actions list
            $actions_html = '<a href="#" class="button button-small os-hub-edit-agreement" data-id="' . esc_attr($r->id) . '" style="margin-left: 4px;">' . __('تعديل', 'olama-registration') . '</a>';
            $actions_html .= '<a href="' . esc_url($print_url) . '" target="_blank" class="button button-small" style="margin-left: 4px;">' . __('طباعة', 'olama-registration') . '</a>';

            // View / Pay Invoice buttons section on a new line (ALWAYS shown if linked invoices exist)
            if (!empty($invoices)) {
                $actions_html .= '<div style="margin-top: 6px; padding-top: 6px; border-top: 1px dashed #cbd5e1; display: flex; flex-direction: column; gap: 4px;">';
                foreach ($invoices as $inv) {
                    // View Invoice button (always)
                    $actions_html .= '<button type="button" class="button button-small olama-reg-view-invoice-btn" data-id="' . esc_attr($inv->id) . '" title="' . esc_attr__('عرض الفاتورة', 'olama-registration') . '" style="background:#0284c7; border-color:#0284c7; color:#fff; display: block; width: 100%; text-align: center; justify-content: center; font-size: 11px; margin-bottom: 2px;">' . sprintf(__('عرض %s', 'olama-registration'), $inv->invoice_number) . '</button>';
                    
                    // Pay button (only if unpaid/partially paid)
                    if ((float)$inv->balance > 0 && $inv->status !== 'cancelled' && $inv->status !== 'draft') {
                        $actions_html .= '<button type="button" class="button button-small os-hub-pay-invoice-btn" data-id="' . esc_attr($inv->id) . '" title="' . esc_attr__('دفع الفاتورة', 'olama-registration') . '" style="background:#16a34a; border-color:#16a34a; color:#fff; display: block; width: 100%; text-align: center; justify-content: center; font-size: 11px; margin-bottom: 4px;">' . sprintf(__('دفع %s', 'olama-registration'), $inv->invoice_number) . '</button>';
                    }
                }
                $actions_html .= '</div>';
            }

            $has_invoices = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}olama_invoices WHERE agreement_id = %d AND status != 'cancelled'",
                $r->id
            )) > 0;

            if (!$has_invoices) {
                $cancel_url = admin_url('admin.php?page=olama-registration-agreements&action=cancel&id=' . $r->id . '&redirect_to=hub');
                $actions_html .= '<a href="' . esc_url($cancel_url) . '" class="button button-small" style="color:#d63638; text-decoration:none;" onclick="return confirm(\'' . esc_attr__('هل أنت متأكد من إلغاء وحذف هذا العقد؟', 'olama-registration') . '\');">' . __('إلغاء', 'olama-registration') . '</a>';
            }

            $html .= '<tr>';
            $html .= '<td><code>' . esc_html($r->agreement_number) . '</code></td>';
            $html .= '<td><strong>' . esc_html($agreement->participant_name) . '</strong></td>';
            $html .= '<td>' . esc_html($r->activity_type) . '</td>';
            $html .= '<td><span class="os-hub-badge ' . $s['cls'] . '">' . $s['label'] . '</span></td>';
            $html .= '<td>' . $invoices_str . '</td>';
            $html .= '<td dir="ltr">' . number_format((float)$r->total_amount, 3) . ' <small>JD</small></td>';
            $html .= '<td dir="ltr" style="color:#16a34a; font-weight:700;">' . number_format($collected, 3) . ' <small>JD</small></td>';
            $remaining_color = $remaining > 0 ? '#e8920a' : '#16a34a';
            $html .= '<td dir="ltr" style="color:' . $remaining_color . '; font-weight:700;">' . number_format($remaining, 3) . ' <small>JD</small></td>';
            $html .= '<td>' . $actions_html . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return [
            'html' => $html,
            'meta' => ['count' => count($rows)],
        ];
    }

    // ── Tile: Invoices ────────────────────────────────────────────────────────

    private function hub_tile_invoices(string $uid, string $type, int $year): array
    {
        global $wpdb;

        if ($type === 'family') {
            $where  = 'i.family_uid = %s AND (i.ext_customer_id IS NULL OR i.ext_customer_id = 0)';
            $args   = [$uid];
        } else {
            $c = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}olama_customers WHERE customer_uid = %s LIMIT 1",
                $uid
            ));
            $cid    = $c ? (int) $c->id : 0;
            $where  = 'i.ext_customer_id = %d';
            $args   = [$cid];
        }

        if ($year) {
            $where .= ' AND i.academic_year_id = %d';
            $args[] = $year;
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT i.id, i.invoice_number, i.status, i.subtotal, i.discount, i.amount_paid, i.balance, i.notes,
                        s.student_name, y.year_name
                 FROM {$wpdb->prefix}olama_invoices i
                 LEFT JOIN {$wpdb->prefix}olama_students s ON s.student_uid = i.student_uid
                 LEFT JOIN {$wpdb->prefix}olama_academic_years y ON y.id = i.academic_year_id
                 WHERE {$where}
                 ORDER BY i.id DESC
                 LIMIT 50",
                $args
            )
        );

        $new_invoice_url = admin_url('admin.php?page=olama-registration-invoices&action=new&type=' . $type . '&uid=' . $uid);

        if (! $rows) {
            $html = $this->hub_empty_state(
                __('لا توجد فواتير مسجلة', 'olama-registration') . ($wpdb->last_error ? (' (خطأ قاعدة البيانات: ' . esc_html($wpdb->last_error) . ')') : ''),
                'dashicons-media-text'
            );
            $html .= '<div class="os-hub-tile-footer"><a href="' . esc_url($new_invoice_url) . '" class="button button-primary">' . __('إصدار فاتورة جديدة', 'olama-registration') . '</a></div>';
            return ['html' => $html];
        }

        $status_map = [
            'issued'    => ['label' => __('صادرة', 'olama-registration'),   'cls' => 'os-hub-badge--blue'],
            'partially_paid' => ['label' => __('مدفوعة جزئياً', 'olama-registration'), 'cls' => 'os-hub-badge--orange'],
            'paid'      => ['label' => __('مدفوعة', 'olama-registration'),  'cls' => 'os-hub-badge--green'],
            'overdue'   => ['label' => __('متأخرة', 'olama-registration'),   'cls' => 'os-hub-badge--red'],
            'cancelled' => ['label' => __('ملغاة', 'olama-registration'),   'cls' => 'os-hub-badge--gray'],
        ];

        $total_balance = 0;
        $html  = '<table class="os-hub-data-table widefat fixed striped">';
        $html .= '<thead><tr>';
        $html .= '<th>' . __('رقم الفاتورة', 'olama-registration') . '</th>';
        $html .= '<th>' . __('الطالب المستهدف', 'olama-registration') . '</th>';
        $html .= '<th>' . __('العام الدراسي', 'olama-registration') . '</th>';
        $html .= '<th>' . __('طبيعة الخدمة', 'olama-registration') . '</th>';
        $html .= '<th>' . __('أصل الفاتورة', 'olama-registration') . '</th>';
        $html .= '<th>' . __('الخصم الممنوح', 'olama-registration') . '</th>';
        $html .= '<th>' . __('المدفوع', 'olama-registration') . '</th>';
        $html .= '<th>' . __('الرصيد', 'olama-registration') . '</th>';
        $html .= '<th>' . __('الحالة', 'olama-registration') . '</th>';
        $html .= '<th style="width:180px;">' . __('الإجراءات', 'olama-registration') . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $r) {
            $s  = $status_map[$r->status] ?? ['label' => esc_html($r->status), 'cls' => 'os-hub-badge--gray'];
            $total_balance += (float) $r->balance;

            $print_url = admin_url('admin.php?page=olama-registration-invoices&action=print&id=' . $r->id);
            $student_disp = $r->student_name ? esc_html($r->student_name) : '—';
            $year_disp = $r->year_name ? esc_html($r->year_name) : '—';
            
            $service_disp = '—';
            if (!empty($r->notes)) {
                $clean_notes = trim($r->notes);
                if (preg_match('/^(?:طبيعة الخدمة|رسوم خدمة|رسوم خدمة إضافية):\s*(.+)$/mu', $clean_notes, $matches)) {
                    $service_disp = esc_html(trim($matches[1]));
                } else {
                    $first_line = explode("\n", $clean_notes)[0];
                    if (strpos($first_line, 'طبيعة الخدمة:') !== false) {
                        $service_disp = esc_html(trim(str_replace('طبيعة الخدمة:', '', $first_line)));
                    } elseif (strpos($first_line, 'رسوم خدمة:') !== false) {
                        $service_disp = esc_html(trim(str_replace('رسوم خدمة:', '', $first_line)));
                    } elseif (strpos($first_line, 'رسوم خدمة إضافية:') !== false) {
                        $service_disp = esc_html(trim(str_replace('رسوم خدمة إضافية:', '', $first_line)));
                    }
                }
            }

            $html .= '<tr>';
            $html .= '<td><code>' . esc_html($r->invoice_number) . '</code></td>';
            $html .= '<td>' . $student_disp . '</td>';
            $html .= '<td>' . $year_disp . '</td>';
            $html .= '<td>' . $service_disp . '</td>';
            $html .= '<td dir="ltr">' . number_format((float)$r->subtotal, 2) . '</td>';
            $html .= '<td dir="ltr" style="color:#ef4444;">' . number_format((float)$r->discount, 2) . '</td>';
            $html .= '<td dir="ltr" style="color:#16a34a; font-weight:700;">' . number_format((float)$r->amount_paid, 2) . '</td>';
            $html .= '<td dir="ltr"><strong>' . number_format((float)$r->balance, 2) . '</strong></td>';
            $html .= '<td><span class="os-hub-badge ' . $s['cls'] . '">' . $s['label'] . '</span></td>';
            $html .= '<td>';
            
            // Display (عرض)
            if (!($type === 'customer' && $r->status === 'partial')) {
                $html .= '<button type="button" class="button button-small olama-reg-view-invoice-btn" data-id="' . esc_attr($r->id) . '" title="عرض تفاصيل الفاتورة">' . __('عرض', 'olama-registration') . '</button> ';
            }
            
            // Pay (دفع)
            if ((float)$r->balance > 0 && $r->status !== 'cancelled' && $r->status !== 'draft') {
                $html .= '<button type="button" class="button button-small button-primary os-hub-pay-invoice-btn" data-id="' . esc_attr($r->id) . '" title="' . esc_attr__('دفع الفاتورة', 'olama-registration') . '" style="background:#16a34a; border-color:#16a34a; color:#fff; margin-left: 2px;">' . __('دفع', 'olama-registration') . '</button> ';
            }
            
            // Print (طباعة)
            $html .= '<a href="' . esc_url($print_url) . '" target="_blank" class="button button-small" title="طباعة الفاتورة">' . __('طباعة', 'olama-registration') . '</a> ';
            
            // Cancel (إلغاء)
            if ($r->status !== 'cancelled' && (float)$r->amount_paid == 0) {
                $html .= '<button type="button" class="button button-small olama-reg-cancel-invoice-btn" data-id="' . esc_attr($r->id) . '" title="إلغاء الفاتورة" style="color:#dc2626;">' . __('إلغاء', 'olama-registration') . '</button>';
            }
            
            $html .= '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<div class="os-hub-tile-footer">';
        $html .= '<strong>' . __('إجمالي الرصيد المستحق: ', 'olama-registration') . '</strong>';
        $html .= '<span dir="ltr">' . number_format($total_balance, 2) . ' <small>د.أ</small></span>';
        $html .= '</div>';

        return [
            'html' => $html,
            'meta' => ['count' => count($rows), 'balance' => $total_balance],
        ];
    }

    // ── Tile: Payments ────────────────────────────────────────────────────────

    private function hub_tile_payments(string $uid, string $type, int $year): array
    {
        global $wpdb;

        if ($type === 'family') {
            $join_where = 'p.family_uid = %s';
            $args       = [$uid];
        } else {
            $c = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}olama_customers WHERE customer_uid = %s LIMIT 1",
                $uid
            ));
            $cid        = $c ? (int) $c->id : 0;
            $join_where = 'i.ext_customer_id = %d';
            $args       = [$cid];
        }

        if ($year) {
            $join_where .= ' AND i.academic_year_id = %d';
            $args[] = $year;
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.id, p.invoice_id, p.payment_date, p.amount, p.method, p.reference,
                        p.received_by, p.notes, p.family_uid,
                        i.invoice_number, p.amount AS payment_amount,
                        u.display_name AS received_by_name
                 FROM {$wpdb->prefix}olama_payments p
                 INNER JOIN {$wpdb->prefix}olama_invoices i ON i.id = p.invoice_id
                 LEFT JOIN {$wpdb->users} u ON u.ID = p.received_by
                 WHERE {$join_where}
                 ORDER BY p.id DESC
                 LIMIT 50",
                $args
            )
        );

        if (! $rows) {
            return ['html' => $this->hub_empty_state(
                __('لا توجد سندات قبض مسجلة', 'olama-registration'),
                'dashicons-money-alt'
            )];
        }

        // Get payer name (family name or customer name) for the active dashboard profile
        $payer_name = '';
        if ($type === 'family') {
            $payer_name = $wpdb->get_var($wpdb->prepare(
                "SELECT family_name FROM {$wpdb->prefix}olama_families WHERE family_uid = %s",
                $uid
            ));
        } else {
            $payer_name = $wpdb->get_var($wpdb->prepare(
                "SELECT customer_name FROM {$wpdb->prefix}olama_customers WHERE customer_uid = %s",
                $uid
            ));
        }
        if (!$payer_name) {
            $payer_name = $uid;
        }

        $reversed_ids = [];
        foreach ($rows as $row_pay) {
            if (strpos($row_pay->reference ?? '', 'REVERSAL-') === 0) {
                $reversed_ids[] = (int) str_replace('REVERSAL-', '', $row_pay->reference);
            }
        }

        $method_cfg = [
            'cash'          => ['label' => __('نقدي', 'olama-registration'),          'class' => 'reg-method-pill--cash'],
            'bank_transfer' => ['label' => __('تحويل بنكي', 'olama-registration'),    'class' => 'reg-method-pill--transfer'],
            'cheque'        => ['label' => __('شيك بنكي', 'olama-registration'),       'class' => 'reg-method-pill--cheque'],
            'online'        => ['label' => __('دفع إلكتروني', 'olama-registration'),   'class' => 'reg-method-pill--online'],
            'reversal'      => ['label' => __('عكس قيد', 'olama-registration'),       'class' => 'reg-method-pill--reversal'],
        ];

        $total_paid = 0;
        $html  = '<table class="olama-reg-fin-table widefat fixed striped" style="width:100%; border-collapse:collapse; font-size:13px;">';
        $html .= '<thead><tr>';
        $html .= '<th style="width:70px;">' . __('رقم السند', 'olama-registration') . '</th>';
        $html .= '<th>' . __('تاريخ القبض', 'olama-registration') . '</th>';
        $html .= '<th>' . __('رقم الفاتورة', 'olama-registration') . '</th>';
        $html .= '<th>' . __('رقم الملف', 'olama-registration') . '</th>';
        $html .= '<th>' . __('ولي الأمر', 'olama-registration') . '</th>';
        $html .= '<th>' . __('القيمة المقبوضة', 'olama-registration') . '</th>';
        $html .= '<th>' . __('طريقة الدفع', 'olama-registration') . '</th>';
        $html .= '<th>' . __('رقم المرجع / الشيك', 'olama-registration') . '</th>';
        $html .= '<th>' . __('المستلم', 'olama-registration') . '</th>';
        $html .= '<th style="width:80px; text-align:center;">' . __('إيصال', 'olama-registration') . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $r) {
            $total_paid += (float) $r->amount;
            $m = $method_cfg[$r->method] ?? ['label' => $r->method, 'class' => 'reg-method-pill--cash'];
            $is_reversal = (float) $r->amount < 0;

            $print_url = admin_url('admin.php?page=olama-registration-payments&action=print_receipt&id=' . (int) $r->id);

            $html .= '<tr' . ($is_reversal ? ' class="os-hub-row--reversal"' : '') . '>';
            $html .= '<td><span style="font-weight:700; color:var(--reg-text-muted);">#' . (int) $r->id . '</span></td>';
            $html .= '<td style="color:var(--reg-text-muted);">' . esc_html($r->payment_date) . '</td>';
            $html .= '<td><strong>' . esc_html($r->invoice_number ?: '—') . '</strong></td>';
            $html .= '<td><span class="olama-reg-uid-badge">' . esc_html($r->family_uid) . '</span></td>';
            $html .= '<td>' . esc_html($payer_name) . '</td>';
            $html .= '<td style="' . ($is_reversal ? 'color:#d63638;' : 'color:var(--reg-success);') . ' font-weight:800; font-size:15px;" dir="ltr">';
            $html .= number_format((float)$r->amount, 2) . '</td>';
            $html .= '<td>';
            $html .= '<span class="reg-method-pill ' . esc_attr($m['class']) . '">';
            $html .= esc_html($m['label']);
            $html .= '</span>';
            $html .= '</td>';
            $html .= '<td>' . esc_html($r->reference ?: '—') . '</td>';
            $html .= '<td>' . esc_html($r->received_by_name ?: '—') . '</td>';
            $html .= '<td style="text-align:center; white-space:nowrap;">';
            $html .= '<a href="' . esc_url($print_url) . '" target="_blank" class="button button-small" title="' . esc_attr__('طباعة سند القبض', 'olama-registration') . '" style="margin-left: 2px;">';
            $html .= '<span class="dashicons dashicons-printer"></span>';
            $html .= '</a>';
            if ((float) $r->amount > 0 && $r->method !== 'reversal') {
                $is_already_reversed = in_array((int) $r->id, $reversed_ids, true);
                if ($is_already_reversed) {
                    $html .= '<button type="button" class="button button-small" disabled style="opacity:0.5; cursor:not-allowed;" title="' . esc_attr__('هذا السند معكوس مسبقاً', 'olama-registration') . '">';
                    $html .= '<span class="dashicons dashicons-undo"></span>';
                    $html .= '</button>';
                } else {
                    $html .= '<button type="button" class="button button-small olama-reg-reverse-payment-btn" data-id="' . esc_attr($r->id) . '" title="' . esc_attr__('عكس السند', 'olama-registration') . '" style="color:#c62828;">';
                    $html .= '<span class="dashicons dashicons-undo"></span>';
                    $html .= '</button>';
                }
            }
            $html .= '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<div class="os-hub-tile-footer">';
        $html .= '<strong>' . __('إجمالي المدفوعات: ', 'olama-registration') . '</strong>';
        $html .= '<span dir="ltr">' . number_format($total_paid, 2) . ' <small>د.أ</small></span>';
        $html .= '</div>';

        return [
            'html' => $html,
            'meta' => ['count' => count($rows), 'total' => $total_paid],
        ];
    }

    // ── Tile: Children / Students ─────────────────────────────────────────────

    private function hub_tile_children(string $uid, string $type, int $year): array
    {
        global $wpdb;

        if ($type === 'family') {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT student_uid AS uid, student_name AS name,
                        '' AS grade, '' AS section, is_active
                 FROM {$wpdb->prefix}olama_students
                 WHERE family_id = %s
                 ORDER BY sequence_in_family, student_name",
                $uid
            ));
            $label_uid  = __('رقم الطالب', 'olama-registration');
            $label_name = __('اسم الطالب', 'olama-registration');
        } else {
            $c = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}olama_customers WHERE customer_uid = %s LIMIT 1",
                $uid
            ));
            $cid  = $c ? (int) $c->id : 0;
            $rows = $cid ? $wpdb->get_results($wpdb->prepare(
                "SELECT child_uid AS uid, child_name AS name, grade, '' AS section, is_active
                 FROM {$wpdb->prefix}olama_customer_children
                 WHERE customer_id = %d
                 ORDER BY child_name",
                $cid
            )) : [];
            $label_uid  = __('رقم الابن', 'olama-registration');
            $label_name = __('اسم الابن', 'olama-registration');
        }

        if (! $rows) {
            $empty = $this->hub_empty_state(
                __('لا يوجد طلاب / أبناء مسجلون', 'olama-registration'),
                'dashicons-groups'
            );
            if ($type === 'external') {
                $empty .= $this->hub_add_child_form($uid);
            }
            return ['html' => $empty];
        }

        $html  = '<table class="os-hub-data-table widefat fixed striped">';
        $html .= '<thead><tr>';
        $html .= "<th>{$label_uid}</th><th>{$label_name}</th>";
        $html .= '<th>' . __('الصف', 'olama-registration') . '</th>';
        $html .= '<th>' . __('الحالة', 'olama-registration') . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $r) {
            $html .= '<tr>';
            $html .= '<td><code>' . esc_html($r->uid) . '</code></td>';
            $html .= '<td>' . esc_html($r->name) . '</td>';
            $html .= '<td>' . esc_html($r->grade ?: '—') . '</td>';
            $html .= '<td>' . ($r->is_active
                ? '<span class="os-hub-badge os-hub-badge--green">' . __('نشط', 'olama-registration') . '</span>'
                : '<span class="os-hub-badge os-hub-badge--gray">'  . __('غير نشط', 'olama-registration') . '</span>') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        // For external customers: show Add Child form below the table
        if ($type === 'external') {
            $html .= $this->hub_add_child_form($uid);
        }

        return ['html' => $html, 'meta' => ['count' => count($rows)]];
    }

    /**
     * Renders the inline "Add Child" form HTML for external customers.
     */
    private function hub_add_child_form(string $uid): string
    {
        $html  = '<div class="os-hub-add-child-wrap" data-uid="' . esc_attr($uid) . '">';
        $html .= '<div class="os-hub-tile-footer">';
        $html .= '<button type="button" class="button" id="os-hub-add-child-btn">';
        $html .= '<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ';
        $html .= __('إضافة ابن', 'olama-registration');
        $html .= '</button>';
        $html .= '</div>';
        $html .= '<form class="os-hub-add-child-form" id="os-hub-add-child-form" style="display:none;" novalidate>';
        $html .= '<input type="hidden" name="uid" value="' . esc_attr($uid) . '">';
        $html .= '<table class="os-hub-data-table widefat fixed"><tbody>';
        $html .= '<tr><th>' . __('اسم الابن', 'olama-registration') . ' <span style="color:#d63638;">*</span></th><td>';
        $html .= '<input type="text" name="child_name" id="os-hub-child-name-input" class="widefat" required placeholder="' . esc_attr__('أدخل اسم الابن...', 'olama-registration') . '">';
        $html .= '</td></tr>';
        $html .= '<tr><th>' . __('الصف / المرحلة', 'olama-registration') . '</th><td>';
        $html .= '<input type="text" name="grade" class="widefat" placeholder="' . esc_attr__('اختياري...', 'olama-registration') . '">';
        $html .= '</td></tr>';
        $html .= '</tbody></table>';
        $html .= '<div class="os-hub-form-actions">';
        $html .= '<button type="submit" class="button button-primary os-hub-add-child-submit">';
        $html .= '<span class="dashicons dashicons-saved" aria-hidden="true"></span> ' . __('حفظ', 'olama-registration');
        $html .= '</button>';
        $html .= ' <button type="button" class="button os-hub-add-child-cancel">' . __('إلغاء', 'olama-registration') . '</button>';
        $html .= '</div>';
        $html .= '</form>';
        $html .= '<div class="os-hub-add-child-toast" aria-live="polite"></div>';
        $html .= '</div>';
        return $html;
    }

    // ── Tile: Financial Summary ────────────────────────────────────────────────

    private function hub_tile_financial(string $uid, string $type, int $year): array
    {
        global $wpdb;

        if ($type !== 'family') {
            // External customers: simple invoice summary
            $c   = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}olama_customers WHERE customer_uid = %s LIMIT 1",
                $uid
            ));
            $cid = $c ? (int) $c->id : 0;

            $summary = $cid ? $wpdb->get_row($wpdb->prepare(
                "SELECT
                     COALESCE(SUM(total), 0) AS billed,
                     COALESCE(SUM(amount_paid), 0) AS paid,
                     COALESCE(SUM(balance), 0) AS balance
                 FROM {$wpdb->prefix}olama_invoices
                 WHERE ext_customer_id = %d AND status != 'cancelled'",
                $cid
            )) : null;

            $billed  = $summary ? (float) $summary->billed  : 0;
            $paid    = $summary ? (float) $summary->paid    : 0;
            $balance = $summary ? (float) $summary->balance : 0;
        } else {
            // Family: year-scoped
            $args = [$uid];
            $yc   = '';
            if ($year) { $yc = 'AND academic_year_id = %d'; $args[] = $year; }

            $summary = $wpdb->get_row($wpdb->prepare(
                "SELECT
                     COALESCE(SUM(total), 0)       AS billed,
                     COALESCE(SUM(amount_paid), 0) AS paid,
                     COALESCE(SUM(balance), 0)     AS balance
                 FROM {$wpdb->prefix}olama_invoices
                 WHERE family_uid = %s
                   AND (ext_customer_id IS NULL OR ext_customer_id = 0)
                   AND status != 'cancelled' {$yc}",
                $args
            ));

            $billed  = $summary ? (float) $summary->billed  : 0;
            $paid    = $summary ? (float) $summary->paid    : 0;
            $balance = $summary ? (float) $summary->balance : 0;
        }

        $paid_pct    = $billed > 0 ? round($paid / $billed * 100, 1) : 0;
        $balance_pct = $billed > 0 ? round($balance / $billed * 100, 1) : 0;

        $html  = '<div class="os-hub-financial-summary">';

        // Stat cards
        $html .= '<div class="os-hub-fin-cards">';
        $html .= $this->hub_fin_card(__('إجمالي المفوتر', 'olama-registration'), $billed, 'os-hub-badge--blue');
        $html .= $this->hub_fin_card(__('إجمالي المدفوع', 'olama-registration'), $paid,    'os-hub-badge--green');
        $html .= $this->hub_fin_card(__('الرصيد المستحق', 'olama-registration'), $balance, $balance > 0 ? 'os-hub-badge--red' : 'os-hub-badge--green');
        $html .= '</div>';

        // Progress bar
        if ($billed > 0) {
            $html .= '<div class="os-hub-progress-wrap">';
            $html .= '<div class="os-hub-progress-label">';
            $html .= '<span>' . __('نسبة السداد', 'olama-registration') . '</span>';
            $html .= '<span dir="ltr">' . $paid_pct . '%</span>';
            $html .= '</div>';
            $html .= '<div class="os-hub-progress">';
            $html .= '<div class="os-hub-progress__bar" style="width:' . $paid_pct . '%;"></div>';
            $html .= '</div>';
            $html .= '</div>';
        }

        // Footer CTA: New Invoice
        $new_invoice_url = admin_url('admin.php?page=olama-registration-invoices&action=new');
        if ($type === 'family') {
            $new_invoice_url .= '&family_uid=' . urlencode($uid);
        } else {
            $new_invoice_url .= '&ext_customer_uid=' . urlencode($uid);
        }

        $html .= '<div class="os-hub-tile-footer">';
        $html .= '<a href="' . esc_url($new_invoice_url) . '" class="button button-primary button-small">';
        $html .= '<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ';
        $html .= __('إنشاء فاتورة جديدة', 'olama-registration');
        $html .= '</a>';
        $html .= '</div>';

        $html .= '</div>';

        return [
            'html' => $html,
            'meta' => ['billed' => $billed, 'paid' => $paid, 'balance' => $balance],
        ];
    }

    private function hub_fin_card(string $label, float $amount, string $cls): string
    {
        return '<div class="os-hub-fin-card">'
             . '<div class="os-hub-fin-card__label">' . esc_html($label) . '</div>'
             . '<div class="os-hub-fin-card__amount ' . $cls . '" dir="ltr">'
             . number_format($amount, 2) . ' <small>د.أ</small>'
             . '</div>'
             . '</div>';
    }

    // ── Tile: History & Audit ─────────────────────────────────────────────────

    private function hub_tile_history(string $uid, string $type, int $year): array
    {
        global $wpdb;

        // Action label map (Arabic)
        $action_labels = [
            'invoice_created'   => __('إنشاء فاتورة',      'olama-registration'),
            'invoice_updated'   => __('تعديل فاتورة',      'olama-registration'),
            'invoice_cancelled' => __('إلغاء فاتورة',      'olama-registration'),
            'payment_received'  => __('تسجيل دفعة',        'olama-registration'),
            'payment_reversed'  => __('عكس دفعة',          'olama-registration'),
            'agreement_created' => __('إنشاء عقد',           'olama-registration'),
            'agreement_updated' => __('تعديل عقد',           'olama-registration'),
            'profile_updated'   => __('تعديل بيانات',       'olama-registration'),
            'status_changed'    => __('تغيير الحالة',       'olama-registration'),
            'child_added'       => __('إضافة ابن',           'olama-registration'),
        ];

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, action, entity_type, entity_id, actor_id, created_at, notes
             FROM {$wpdb->prefix}olama_billing_audit
             WHERE entity_id = %s
             ORDER BY id DESC
             LIMIT 100",
            $uid
        ));

        if (! $rows) {
            return ['html' => $this->hub_empty_state(
                __('لا توجد سجلات تدقيق', 'olama-registration'),
                'dashicons-backup'
            )];
        }

        // Pre-fetch user display names (batch, avoid N+1)
        $actor_ids = array_unique(array_filter(array_column($rows, 'actor_id')));
        $user_names = [];
        foreach ($actor_ids as $actor_id) {
            $u = get_userdata((int) $actor_id);
            $user_names[$actor_id] = $u ? $u->display_name : __('مجهول', 'olama-registration');
        }

        $html  = '<table class="os-hub-data-table widefat fixed striped">';
        $html .= '<thead><tr>';
        $html .= '<th>' . __('الإجراء', 'olama-registration') . '</th>';
        $html .= '<th>' . __('المستخدم', 'olama-registration') . '</th>';
        $html .= '<th>' . __('التاريخ', 'olama-registration') . '</th>';
        $html .= '<th>' . __('ملاحظات', 'olama-registration') . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $r) {
            $action_label = $action_labels[$r->action] ?? esc_html($r->action);
            $actor_name   = $user_names[$r->actor_id] ?? __('نظام', 'olama-registration');
            $date         = $r->created_at
                ? wp_date('Y/m/d H:i', strtotime($r->created_at))
                : '—';

            $html .= '<tr>';
            $html .= '<td><span class="os-hub-badge os-hub-badge--blue">' . esc_html($action_label) . '</span></td>';
            $html .= '<td>' . esc_html($actor_name) . '</td>';
            $html .= '<td dir="ltr" style="white-space:nowrap;">' . esc_html($date) . '</td>';
            $html .= '<td>' . esc_html($r->notes ?? '—') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return ['html' => $html, 'meta' => ['count' => count($rows)]];
    }

    // ── Tile: Settlement Receipts (families only) ─────────────────────────────

    private function hub_tile_settlements(string $uid, string $type, int $year): array
    {
        if ($type !== 'family') {
            return ['html' => $this->hub_empty_state(
                __('إيصالات التسوية للعائلات المسجلة فقط', 'olama-registration'),
                'dashicons-bank'
            )];
        }

        global $wpdb;
        $args  = [$uid];
        $yc    = '';

        if ($year) {
            $cols = $wpdb->get_col("DESCRIBE {$wpdb->prefix}olama_settlement_receipts", 0);
            if (in_array('academic_year_id', $cols)) {
                $yc    = ' AND academic_year_id = %d';
                $args[] = $year;
            }
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.id, r.receipt_number, r.family_id, r.payment_category, r.original_amount,
                    r.settled_amount, r.remaining_balance, r.payment_method,
                    r.oracle_receipt_number, r.settlement_date, r.status, r.created_at,
                    f.family_name AS father_first_name, '' AS father_family_name
             FROM {$wpdb->prefix}olama_settlement_receipts r
             LEFT JOIN {$wpdb->prefix}olama_families f ON f.family_uid = r.family_id
             WHERE r.family_id = %s {$yc}
             ORDER BY r.id DESC
             LIMIT 50",
            $args
        ));

        if (! $rows) {
            return ['html' => $this->hub_empty_state(
                __('لا توجد إيصالات تسوية لهذه العائلة', 'olama-registration'),
                'dashicons-bank'
            )];
        }

        $status_map = [
            'pending_settlement' => ['label' => __('بانتظار التسوية', 'olama-registration'), 'cls' => 'os-hub-badge--orange'],
            'settled'            => ['label' => __('تمت التسوية', 'olama-registration'), 'cls' => 'os-hub-badge--green'],
            'cancelled'          => ['label' => __('ملغي', 'olama-registration'),    'cls' => 'os-hub-badge--gray'],
        ];

        $html  = '<table class="os-hub-data-table widefat fixed striped">';
        $html .= '<thead><tr>';
        $html .= '<th style="width: 120px;">' . __('رقم الإيصال', 'olama-registration') . '</th>';
        $html .= '<th style="width: 100px;">' . __('رقم العائلة', 'olama-registration') . '</th>';
        $html .= '<th>' . __('اسم العائلة', 'olama-registration') . '</th>';
        $html .= '<th>' . __('قائمة الخدمات', 'olama-registration') . '</th>';
        $html .= '<th>' . __('المبلغ', 'olama-registration') . '</th>';
        $html .= '<th>' . __('المتبقي', 'olama-registration') . '</th>';
        $html .= '<th>' . __('التاريخ', 'olama-registration') . '</th>';
        $html .= '<th>' . __('الحالة', 'olama-registration') . '</th>';
        $html .= '<th style="width: 200px;">' . __('إجراءات', 'olama-registration') . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $r) {
            $s = $status_map[$r->status] ?? ['label' => esc_html($r->status), 'cls' => 'os-hub-badge--gray'];
            $family_name = trim(($r->father_first_name ?? '') . ' ' . ($r->father_family_name ?? ''));
            $date = date_i18n(get_option('date_format'), strtotime($r->created_at));

            $style = '';
            if ($r->status === 'pending_settlement') {
                $style = ' style="background-color: #fff8e5;"';
            }

            $html .= '<tr class="status-' . esc_attr($r->status) . '"' . $style . '>';
            $html .= '<td><strong>' . esc_html($r->receipt_number) . '</strong></td>';
            $html .= '<td><span class="olama-reg-uid-badge">' . esc_html($r->family_id) . '</span></td>';
            $html .= '<td>' . esc_html($family_name) . '</td>';
            $html .= '<td>' . esc_html($r->payment_category) . '</td>';
            $html .= '<td dir="ltr">' . number_format((float)$r->original_amount, 2) . '</td>';
            $html .= '<td dir="ltr">' . number_format((float)$r->remaining_balance, 2) . '</td>';
            $html .= '<td>' . esc_html($date) . '</td>';
            $html .= '<td><span class="os-hub-badge ' . $s['cls'] . '">' . $s['label'] . '</span></td>';
            
            $html .= '<td>';
            $print_url = admin_url('admin.php?page=olama-registration-settlements&action=print&id=' . (int)$r->id);
            $html .= '<a href="' . esc_url($print_url) . '" target="_blank" class="button button-small">' . __('طباعة', 'olama-registration') . '</a> ';
            
            if ($r->status === 'pending_settlement') {
                $html .= '<button type="button" class="button button-small button-primary btn-settle-receipt" data-id="' . (int)$r->id . '" data-amount="' . number_format((float)$r->original_amount, 2) . '">' . __('تسوية', 'olama-registration') . '</button> ';
                $html .= '<button type="button" class="button button-small btn-cancel-receipt" data-id="' . (int)$r->id . '" style="color:#d63638; border-color:#d63638;">' . __('إلغاء', 'olama-registration') . '</button>';
            }
            $html .= '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return ['html' => $html, 'meta' => ['count' => count($rows)]];
    }

    // ── HTML Helpers ──────────────────────────────────────────────────────────

    // ══════════════════════════════════════════════════════════════════════════
    // PHASE 4: Add Child (External Customers)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Add a child record to an external customer.
     *
     * POST: uid (customer_uid), child_name, grade
     */
    public function hub_add_child(): void
    {
        $this->hub_guard();

        $uid        = sanitize_text_field($_POST['uid']        ?? '');
        $child_name = sanitize_text_field($_POST['child_name'] ?? '');
        $grade      = sanitize_text_field($_POST['grade']      ?? '');

        if (! $uid) {
            wp_send_json_error(['message' => __('معرف العميل غير صالح.', 'olama-registration')]);
        }

        if (! $child_name) {
            wp_send_json_error(['message' => __('اسم الابن مطلوب.', 'olama-registration')]);
        }

        global $wpdb;

        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}olama_customers WHERE customer_uid = %s LIMIT 1",
            $uid
        ));

        if (! $customer) {
            wp_send_json_error(['message' => __('العميل غير موجود.', 'olama-registration')]);
        }

        // Generate a simple child UID
        $child_uid = 'C-' . strtoupper(substr(md5($uid . $child_name . time()), 0, 8));

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'olama_customer_children',
            [
                'customer_id' => (int) $customer->id,
                'child_uid'   => $child_uid,
                'child_name'  => $child_name,
                'grade'       => $grade,
                'is_active'   => 1,
            ]
        );

        if (! $inserted) {
            wp_send_json_error(['message' => __('حدث خطأ أثناء الحفظ.', 'olama-registration')]);
        }

        wp_send_json_success([
            'message'    => __('تمت إضافة الابن بنجاح.', 'olama-registration'),
            'child_name' => $child_name,
            'child_uid'  => $child_uid,
            'grade'      => $grade,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PHASE 3: Interactive Profile Actions
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Save profile edits for family or external customer.
     *
     * POST: uid, type ('family'|'external'), + field values
     */
    public function hub_save_profile(): void
    {
        $this->hub_guard();

        $uid  = sanitize_text_field($_POST['uid']  ?? '');
        $type = sanitize_key($_POST['type'] ?? 'family');

        if (! $uid) {
            wp_send_json_error(['message' => __('معرف غير صالح.', 'olama-registration')]);
        }

        global $wpdb;

        if ($type === 'family') {
            $data = [
                'family_name'   => sanitize_text_field($_POST['family_name']   ?? ''),
                'father_mobile' => sanitize_text_field($_POST['father_mobile'] ?? ''),
                'mother_mobile' => sanitize_text_field($_POST['mother_mobile'] ?? ''),
                'address'       => sanitize_text_field($_POST['address']       ?? ''),
            ];

            if (empty($data['family_name'])) {
                wp_send_json_error(['message' => __('اسم العائلة مطلوب.', 'olama-registration')]);
            }

            $updated = $wpdb->update(
                $wpdb->prefix . 'olama_families',
                $data,
                ['family_uid' => $uid]
            );

            if ($updated === false) {
                wp_send_json_error(['message' => __('حدث خطأ أثناء الحفظ.', 'olama-registration')]);
            }

            $row = Olama_Reg_Family::get_family($uid);
            wp_send_json_success([
                'message' => __('تم تحديث بيانات العائلة.', 'olama-registration'),
                'name'    => $row ? $row->family_name : $data['family_name'],
            ]);

        } else {
            // External customer — resolve by UID
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}olama_customers WHERE customer_uid = %s LIMIT 1",
                $uid
            ));

            if (! $customer) {
                wp_send_json_error(['message' => __('العميل غير موجود.', 'olama-registration')]);
            }

            $data = [
                'customer_name' => sanitize_text_field($_POST['customer_name'] ?? ''),
                'phone'         => sanitize_text_field($_POST['phone']         ?? ''),
                'notes'         => sanitize_textarea_field($_POST['notes']     ?? ''),
            ];

            if (empty($data['customer_name'])) {
                wp_send_json_error(['message' => __('اسم العميل مطلوب.', 'olama-registration')]);
            }

            $updated = $wpdb->update(
                $wpdb->prefix . 'olama_customers',
                $data,
                ['id' => $customer->id]
            );

            if ($updated === false) {
                wp_send_json_error(['message' => __('حدث خطأ أثناء الحفظ.', 'olama-registration')]);
            }

            wp_send_json_success([
                'message' => __('تم تحديث بيانات العميل.', 'olama-registration'),
                'name'    => $data['customer_name'],
            ]);
        }
    }

    /**
     * Toggle active/inactive status for family or customer.
     *
     * POST: uid, type ('family'|'external'), active (0|1)
     */
    public function hub_toggle_active(): void
    {
        $this->hub_guard();

        $uid    = sanitize_text_field($_POST['uid']  ?? '');
        $type   = sanitize_key($_POST['type']   ?? 'family');
        $active = (int) ($_POST['active'] ?? 1) ? 0 : 1; // toggle: flip the incoming value

        if (! $uid) {
            wp_send_json_error(['message' => __('معرف غير صالح.', 'olama-registration')]);
        }

        global $wpdb;

        if ($type === 'family') {
            $updated = $wpdb->update(
                $wpdb->prefix . 'olama_families',
                ['is_active' => $active],
                ['family_uid' => $uid]
            );
        } else {
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}olama_customers WHERE customer_uid = %s LIMIT 1",
                $uid
            ));

            $updated = $customer ? $wpdb->update(
                $wpdb->prefix . 'olama_customers',
                ['is_active' => $active],
                ['id' => $customer->id]
            ) : false;
        }

        if ($updated === false) {
            wp_send_json_error(['message' => __('حدث خطأ أثناء تغيير الحالة.', 'olama-registration')]);
        }

        wp_send_json_success([
            'message' => $active
                ? __('تم تفعيل الملف.', 'olama-registration')
                : __('تم تعطيل الملف.', 'olama-registration'),
            'is_active' => $active,
        ]);
    }

    // ── HTML Helpers ──────────────────────────────────────────────────────────

    private function hub_empty_state(string $message, string $icon = 'dashicons-info-outline'): string
    {
        return '<div class="os-hub-notice">'
             . '<span class="dashicons ' . esc_attr($icon) . '" aria-hidden="true"></span>'
             . '<p class="os-hub-notice__title">' . esc_html($message) . '</p>'
             . '</div>';
    }

    private function hub_tr(string $label, string $value): string
    {
        return '<tr><th scope="row" style="width:160px;">' . esc_html($label) . '</th>'
             . '<td>' . $value . '</td></tr>';
    }

    public function ajax_reset_system(): void
    {
        $this->guard();

        // Safety keyword lock
        $confirm = sanitize_text_field($_POST['confirm_reset'] ?? '');
        if (strtoupper($confirm) !== 'RESET') {
            wp_send_json_error(['message' => 'يرجى كتابة كلمة التأكيد RESET بشكل صحيح للمتابعة.']);
        }

        global $wpdb;
        $cleared = [];

        // Disable foreign key checks temporarily if needed, though they are not set on these tables typically
        $wpdb->query("SET FOREIGN_KEY_CHECKS = 0;");

        // 1. Transactional & Financial Data
        if (!empty($_POST['reset_transactions'])) {
            $tables = [
                'olama_invoices',
                'olama_invoice_items',
                'olama_invoice_installments',
                'olama_payments',
                'olama_billing_audit',
                'olama_settlement_receipts',
                'olama_reg_financial'
            ];
            foreach ($tables as $t) {
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}{$t}");
            }
            $cleared[] = 'البيانات المالية والمعاملات والسندات والقيود الاستحقاقية';
        }

        // 2. Agreements Data
        if (!empty($_POST['reset_agreements'])) {
            $tables = [
                'olama_agreements',
                'olama_agreement_fees',
                'olama_agreement_clauses'
            ];
            foreach ($tables as $t) {
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}{$t}");
            }
            $cleared[] = 'بيانات العقود بنوعيها والرسوم والبنود المرتبطة بها';
        }

        // 3. Customers Data
        if (!empty($_POST['reset_customers'])) {
            $tables = [
                'olama_customers',
                'olama_customer_children'
            ];
            foreach ($tables as $t) {
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}{$t}");
            }
            $cleared[] = 'بيانات جهات الاتصال والعملاء الإضافيين وأولادهم';
        }

        // 4. Configuration Templates
        if (!empty($_POST['reset_templates'])) {
            $tables = [
                'olama_fee_templates',
                'olama_agreement_templates',
                'olama_agreement_template_fees',
                'olama_agreement_template_clauses',
                'olama_agreement_clause_bank'
            ];
            foreach ($tables as $t) {
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}{$t}");
            }
            $cleared[] = 'قوالب الرسوم، قوالب العقود، وبنك الشروط';
        }

        $wpdb->query("SET FOREIGN_KEY_CHECKS = 1;");

        if (empty($cleared)) {
            wp_send_json_error(['message' => 'لم يتم اختيار أي بيانات لمسحها.']);
        }

        wp_send_json_success([
            'message' => 'تم تهيئة النظام ومسح البيانات المحددة بنجاح: <br>• ' . implode('<br>• ', $cleared),
        ]);
    }

}

