<?php
/**
 * View: Agreement Edit/Create
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$id = (int) ($_GET['id'] ?? 0);
$is_new = empty($id);
$is_hub_embedded = sanitize_key($_GET['embedded'] ?? '') === 'hub';
$agreement = null;
$is_context_locked = false;
$has_financial_impact = false;
$can_edit_financial_fields = true;
$can_reschedule_installments = true;
$can_create_amendment = false;
$financial_status = 'open';
$lock_reasons = [];
$academic_year_end_date = class_exists('Olama_Reg_Agreement_Invoice') ? Olama_Reg_Agreement_Invoice::get_active_academic_year_end_date() : '';

if (!$is_new) {
    $agreement = Olama_Reg_Agreement::get($id);
    if (!$agreement) {
        wp_die(__('العقد غير موجود.', 'olama-registration'));
    }
    if (class_exists('Olama_Reg_Agreement_Policy')) {
        $financial_status = Olama_Reg_Agreement_Policy::get_financial_status($id);
        $financial_edit = Olama_Reg_Agreement_Policy::can_edit_financial_fields($id);
        $schedule_edit = Olama_Reg_Agreement_Policy::can_reschedule_installments($id);
        $amendment_create = Olama_Reg_Agreement_Policy::can_create_amendment($id);
        $can_edit_financial_fields = !is_wp_error($financial_edit);
        $can_reschedule_installments = !is_wp_error($schedule_edit);
        $can_create_amendment = !is_wp_error($amendment_create);
        $has_financial_impact = !$can_edit_financial_fields;
        $lock_reasons = Olama_Reg_Agreement_Policy::get_lock_reasons($id);
    } else {
        global $wpdb;
        $has_financial_impact = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}olama_invoices WHERE agreement_id = %d AND status != 'cancelled'",
            $id
        )) > 0;
        $can_edit_financial_fields = !$has_financial_impact;
        $can_reschedule_installments = !$has_financial_impact;
    }
    if ($has_financial_impact) {
        $is_context_locked = true;
    }
} else {
    // Defaults for new
    $payer_type = sanitize_text_field($_GET['payer_type'] ?? 'customer');
    $payer_uid = sanitize_text_field($_GET['payer_uid'] ?? '');
    $payer_id = '';
    $payer_name = '';

    if (!empty($payer_uid)) {
        global $wpdb;
        if ($payer_type === 'customer') {
            $payer_row = $wpdb->get_row($wpdb->prepare(
                "SELECT id, customer_name FROM {$wpdb->prefix}olama_customers WHERE customer_uid = %s OR id = %d LIMIT 1",
                $payer_uid,
                is_numeric($payer_uid) ? intval($payer_uid) : 0
            ));
            if ($payer_row) {
                $payer_id = $payer_row->id;
                $payer_name = $payer_row->customer_name;
                $is_context_locked = true;
            }
        } elseif ($payer_type === 'family') {
            $payer_row = $wpdb->get_row($wpdb->prepare(
                "SELECT family_uid, family_name FROM {$wpdb->prefix}olama_families WHERE family_uid = %s LIMIT 1",
                $payer_uid
            ));
            if ($payer_row) {
                $payer_id = $payer_row->family_uid;
                $payer_name = $payer_row->family_name;
                $is_context_locked = true;
            }
        }
    }

    $agreement = (object) [
        'id' => 0,
        'agreement_number' => __('تلقائي', 'olama-registration'),
        'payer_type' => $payer_type,
        'payer_id' => $payer_id,
        'participant_type' => ($payer_type === 'family') ? 'student' : 'child',
        'participant_id' => '',
        'activity_type' => '',
        'academic_year_id' => 0,
        'start_date' => current_time('Y-m-d'),
        'end_date' => $academic_year_end_date,
        'status' => 'draft',
        'notes' => '',
        'total_amount' => 0,
        'payer_name' => $payer_name,
        'participant_name' => '',
        'template_id' => 0,
    ];
}

$payer_children_json = '[]';
$payer_children = [];
if ($agreement && $agreement->payer_id) {
    global $wpdb;
    if ($agreement->payer_type === 'customer' && is_numeric($agreement->payer_id)) {
        $table = $wpdb->prefix . 'olama_customer_children';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, child_name AS text FROM {$table} WHERE customer_id = %d AND is_active = 1", (int) $agreement->payer_id));
        foreach ($rows as $r) {
            $payer_children[] = ['id' => (int) $r->id, 'text' => $r->text];
        }
    } elseif ($agreement->payer_type === 'family' && $agreement->payer_id) {
        $table = $wpdb->prefix . 'olama_students';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT student_uid AS id, student_name AS text FROM {$table} WHERE family_id = %s", $agreement->payer_id));
        foreach ($rows as $r) {
            $payer_children[] = ['id' => $r->id, 'text' => $r->text];
        }
    }
    $payer_children_json = wp_json_encode($payer_children);
}
?>
<script>
window.payerChildren = <?php echo $payer_children_json; ?>;
</script>
<div class="olama-reg-wrap os-wrap<?php echo $is_hub_embedded ? ' os-hub-embedded-form' : ''; ?>" id="os-agreement-app" data-id="<?php echo esc_attr($agreement->id); ?>" data-can-edit-financial="<?php echo esc_attr($can_edit_financial_fields ? '1' : '0'); ?>" data-can-reschedule="<?php echo esc_attr($can_reschedule_installments ? '1' : '0'); ?>" data-can-create-amendment="<?php echo esc_attr($can_create_amendment ? '1' : '0'); ?>" data-financial-status="<?php echo esc_attr($financial_status); ?>" data-embedded="<?php echo esc_attr($is_hub_embedded ? 'hub' : 'page'); ?>">
    <?php if (!$is_hub_embedded): ?>
    <div class="olama-reg-page-header">
        <div style="display:flex; align-items:center; gap:15px;">
            <h1 class="wp-heading-inline" style="margin:0;">
                <?php echo $is_new ? esc_html__('إضافة عقد جديد', 'olama-registration') : esc_html__('تعديل العقد', 'olama-registration') . ' #' . esc_html($agreement->agreement_number); ?>
            </h1>
            <span
                class="olama-reg-badge <?php echo $agreement->status === 'completed' ? 'olama-reg-badge--active' : ($agreement->status === 'draft' ? 'olama-reg-badge--warning' : 'olama-reg-badge--inactive'); ?>"
                id="os-agr-status-badge">
                <?php echo esc_html($agreement->status); ?>
            </span>
        </div>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration-agreements' ) ); ?>" class="olama-reg-back-btn">
            <span class="dashicons dashicons-arrow-right-alt2" style="margin-top:4px;"></span> <?php esc_html_e('العودة للقائمة', 'olama-registration'); ?>
        </a>
    </div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper wp-clearfix os-nav-tabs" style="margin-bottom: 20px;">
        <a href="#tab-header"
            class="nav-tab nav-tab-active"><?php esc_html_e('البيانات الأساسية', 'olama-registration'); ?></a>
        <a href="#tab-fees"
            class="nav-tab <?php echo $is_new ? 'os-disabled' : ''; ?>"><?php esc_html_e('الرسوم', 'olama-registration'); ?></a>
        <a href="#tab-clauses"
            class="nav-tab <?php echo $is_new ? 'os-disabled' : ''; ?>"><?php esc_html_e('البنود والشروط', 'olama-registration'); ?></a>
    </nav>

    <div class="os-tab-content active" id="tab-header">
        <div class="olama-reg-section">
            <form id="os-form-agreement-header" style="margin:0;">
                <input type="hidden" name="id" value="<?php echo esc_attr($agreement->id); ?>">
                <input type="hidden" name="template_id" value="<?php echo esc_attr($agreement->template_id); ?>">
                
                <h3 class="olama-reg-section-title"><?php esc_html_e('البيانات الأساسية', 'olama-registration'); ?></h3>
                <?php if (!$can_edit_financial_fields): ?>
                    <div class="notice notice-warning inline" style="padding:10px 14px; margin:0 0 14px;">
                        <p style="margin:0 0 8px;"><strong><?php esc_html_e('العقد مقفل مالياً للتعديل المباشر.', 'olama-registration'); ?></strong></p>
                        <?php if (!empty($lock_reasons)): ?>
                            <ul style="margin:0 18px 0 0; list-style:disc;">
                                <?php foreach ($lock_reasons as $reason): ?>
                                    <li><?php echo esc_html($reason); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if ($can_create_amendment && (current_user_can('manage_options') || current_user_can('olama_create_agreement_amendment'))): ?>
                            <p style="margin:12px 0 0;">
                                <button type="button" class="button button-primary" id="os-agr-create-amendment"><?php esc_html_e('تعديل مالي على العقد', 'olama-registration'); ?></button>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="olama-reg-grid">
                    <div class="olama-reg-field olama-reg-field--required">
                        <label><?php esc_html_e('نوع الجهة الدافعة', 'olama-registration'); ?></label>
                        <div style="margin-top:8px;">
                            <label style="margin-left: 20px;">
                                <input type="radio" name="payer_type" value="customer" <?php checked($agreement->payer_type, 'customer'); ?> <?php disabled($is_context_locked); ?>>
                                <?php esc_html_e('عميل (Walk-in)', 'olama-registration'); ?>
                            </label>
                            <label>
                                <input type="radio" name="payer_type" value="family" <?php checked($agreement->payer_type, 'family'); ?> <?php disabled($is_context_locked); ?>>
                                <?php esc_html_e('عائلة (مدرسة)', 'olama-registration'); ?>
                            </label>
                            <?php if ($is_context_locked): ?>
                                <input type="hidden" name="payer_type" value="<?php echo esc_attr($agreement->payer_type); ?>">
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="olama-reg-field olama-reg-field--required">
                        <label><?php esc_html_e('الجهة الدافعة', 'olama-registration'); ?></label>
                        <select name="payer_id" id="os-agr-payer" style="width: 100%;" required <?php disabled($is_context_locked); ?>>
                            <?php if ($agreement->payer_id): ?>
                                <option value="<?php echo esc_attr($agreement->payer_id); ?>" selected="selected">
                                    <?php echo esc_html($agreement->payer_name); ?></option>
                            <?php endif; ?>
                        </select>
                        <?php if ($is_context_locked): ?>
                            <input type="hidden" name="payer_id" value="<?php echo esc_attr($agreement->payer_id); ?>">
                        <?php endif; ?>
                    </div>

                    <div class="olama-reg-field olama-reg-field--required">
                        <label><?php esc_html_e('طبيعة العقد', 'olama-registration'); ?></label>
                        <select name="activity_type" id="os-agr-activity" style="width: 100%;" required <?php disabled($has_financial_impact); ?>>
                            <option value=""><?php esc_html_e('اختر طبيعة العقد', 'olama-registration'); ?></option>
                            <?php
                            $agreement_natures = get_option( 'olama_reg_agreement_natures', ['عقد مدرسة', 'عقد روضة', 'عقد نادي صيفي', 'رحلة مدرسية'] );
                            $agreement_nature_installments = get_option( 'olama_reg_agreement_nature_installments', [] );
                            if ( ! is_array( $agreement_nature_installments ) ) {
                                $agreement_nature_installments = [];
                            }
                            foreach ($agreement_natures as $nature) {
                                $has_installments = array_key_exists( $nature, $agreement_nature_installments ) ? ! empty( $agreement_nature_installments[ $nature ] ) : true;
                                echo '<option value="' . esc_attr($nature) . '" data-has-installments="' . ( $has_installments ? '1' : '0' ) . '" ' . selected($agreement->activity_type, $nature, false) . '>' . esc_html($nature) . '</option>';
                            }
                            // To support legacy values that are not in the current settings list
                            if (!empty($agreement->activity_type) && !in_array($agreement->activity_type, $agreement_natures)) {
                                $legacy_label = $agreement->activity_type;
                                if ($agreement->activity_type === 'kindergarten') $legacy_label = 'رياض الأطفال';
                                elseif ($agreement->activity_type === 'summer_club') $legacy_label = 'النادي الصيفي';
                                elseif ($agreement->activity_type === 'karate') $legacy_label = 'كاراتيه';
                                echo '<option value="' . esc_attr($agreement->activity_type) . '" selected>' . esc_html($legacy_label) . '</option>';
                            }
                            ?>
                        </select>
                        <?php if ($has_financial_impact): ?>
                            <input type="hidden" name="activity_type" value="<?php echo esc_attr($agreement->activity_type); ?>">
                        <?php endif; ?>
                    </div>

                    <div class="olama-reg-field olama-reg-field--required">
                        <label><?php esc_html_e('تاريخ البداية', 'olama-registration'); ?></label>
                        <input type="text" name="start_date" class="os-datepicker" value="<?php echo esc_attr($agreement->start_date); ?>" required style="width:100%;" <?php disabled($has_financial_impact); ?>>
                        <?php if ($has_financial_impact): ?>
                            <input type="hidden" name="start_date" value="<?php echo esc_attr($agreement->start_date); ?>">
                        <?php endif; ?>
                    </div>

                    <div class="olama-reg-field">
                        <label><?php esc_html_e('تاريخ النهاية', 'olama-registration'); ?> <span style="font-weight:normal; font-size:12px; color:#999;">(اختياري)</span></label>
                        <input type="text" name="end_date" class="os-datepicker" value="<?php echo esc_attr($agreement->end_date); ?>" style="width:100%;" <?php disabled($has_financial_impact); ?>>
                        <?php if ($has_financial_impact): ?>
                            <input type="hidden" name="end_date" value="<?php echo esc_attr($agreement->end_date); ?>">
                        <?php endif; ?>
                    </div>

                    <div class="olama-reg-field olama-reg-field--required">
                        <label><?php esc_html_e('حالة العقد', 'olama-registration'); ?></label>
                        <?php
                        $status_labels = [
                            'draft' => __('مسودة', 'olama-registration'),
                            'completed' => __('مكتمل', 'olama-registration'),
                            'cancelled' => __('ملغى', 'olama-registration'),
                        ];
                        ?>
                        <input type="text" value="<?php echo esc_attr($status_labels[$agreement->status] ?? $agreement->status); ?>" style="width:100%;" readonly>
                    </div>

                    <div class="olama-reg-field" style="grid-column: 1 / -1;">
                        <label><?php esc_html_e('ملاحظات', 'olama-registration'); ?></label>
                        <textarea name="notes" rows="4" style="width: 100%; border:1.5px solid #E0C090; border-radius:6px; padding:8px; font-family:inherit;"><?php echo esc_textarea($agreement->notes); ?></textarea>
                    </div>
                </div>
                
                <div class="olama-reg-form-actions" style="margin-top: 20px;">
                        <button type="submit" class="olama-reg-btn olama-reg-btn--primary" id="os-btn-save-header">
                            <span class="dashicons dashicons-saved"></span> <?php esc_html_e('حفظ البيانات', 'olama-registration'); ?>
                        </button>
                    <?php if (false): ?>
                        <div class="notice notice-warning inline" style="padding:10px; margin:0; width:100%;">
                            <p><?php esc_html_e('العقد مرتبط بمدفوعات/فواتير نشطة. لا يمكن تعديل البيانات الأساسية؛ يسمح فقط بإضافة بنود رسوم جديدة.', 'olama-registration'); ?></p>
                        </div>
                    <?php endif; ?>
                    <span class="spinner" style="float:none;"></span>
                </div>
            </form>
        </div>
    </div>

    <div class="os-tab-content" id="tab-fees" style="display:none;">
        <div class="olama-reg-section">
            <h3 class="olama-reg-section-title"><?php esc_html_e('جدول الرسوم المستحقة', 'olama-registration'); ?></h3>
            <?php if ($is_new): ?>
                <p style="padding:15px; color:#666;"><?php esc_html_e('الرجاء حفظ البيانات الأساسية أولاً.', 'olama-registration'); ?></p>
            <?php else: ?>
                <div class="olama-reg-table-wrap" style="padding: 15px;">
                    <table class="olama-reg-fin-table" id="os-agr-fees-table"
                        data-agr-id="<?php echo esc_attr($id); ?>">
                        <thead>
                            <tr>
                                <th style="width: 15%;"><?php esc_html_e('نوع العقد / نموذج العقد', 'olama-registration'); ?></th>
                                <th style="width: 15%;"><?php esc_html_e('المشترك', 'olama-registration'); ?></th>
                                <th style="width: 20%;"><?php esc_html_e('البيان', 'olama-registration'); ?></th>
                                <th style="width: 12%;"><?php esc_html_e('المبلغ', 'olama-registration'); ?></th>
                                <th style="width: 10%;"><?php esc_html_e('الخصم', 'olama-registration'); ?></th>
                                <th style="width: 10%;"><?php esc_html_e('الصافي', 'olama-registration'); ?></th>
                                <th style="width: 12%;"><?php esc_html_e('تاريخ الاستحقاق', 'olama-registration'); ?></th>
                                <th style="width: 8%;"><?php esc_html_e('الحالة', 'olama-registration'); ?></th>
                                <th style="width: 8%;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $templates = Olama_Reg_Billing_Fees::get_agreement_templates($agreement->activity_type);
                            $fees = Olama_Reg_Agreement_Fees::get_by_agreement($id);
                            if ($fees) {
                                foreach ($fees as $fee) {
                                    $is_locked = $has_financial_impact || in_array($fee->paid_status, ['invoiced', 'paid'], true);
                                    ?>
                                <tr data-fee-id="<?php echo esc_attr($fee->id); ?>">
                                    <td>
                                        <select name="fee_category" style="width:100%" class="os-agr-fee-template-select" <?php disabled($is_locked); ?>>
                                            <?php
                                            foreach ($templates as $tpl) {
                                                $total = 0;
                                                foreach ($tpl->items as $it) {
                                                    $total += (float) ($it['amount'] ?? 0);
                                                }
                                                // We store the template ID in fee_category to link it
                                                $selected = selected($fee->fee_category, $tpl->id, false);
                                                // Fallback for old textual categories: if name matches
                                                if (!$selected && $fee->fee_category === $tpl->template_name) {
                                                    $selected = 'selected="selected"';
                                                }
                                                echo '<option value="' . esc_attr($tpl->id) . '" data-name="' . esc_attr($tpl->template_name) . '" data-amount="' . esc_attr($total) . '" data-subject-type="' . esc_attr($tpl->subject_type ?? 'general') . '" data-subject-value="' . esc_attr($tpl->subject_value ?? '') . '" ' . $selected . '>' . esc_html($tpl->template_name) . '</option>';
                                            }
                                            // Keep custom value if not found
                                            if ($fee->fee_category !== 'general' && !is_numeric($fee->fee_category)) {
                                                echo '<option value="' . esc_attr($fee->fee_category) . '" selected>' . esc_html($fee->fee_category) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="child_id" style="width:100%" class="os-agr-fee-child-select" <?php disabled($is_locked); ?>>
                                            <option value=""><?php esc_html_e('اختر المشترك', 'olama-registration'); ?></option>
                                            <?php
                                            if (!empty($payer_children)) {
                                                foreach ($payer_children as $child) {
                                                    $selected = selected($fee->child_id, $child['id'], false);
                                                    echo '<option value="' . esc_attr($child['id']) . '" ' . $selected . '>' . esc_html($child['text']) . '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="label" value="<?php echo esc_attr($fee->label); ?>"
                                            style="width:100%" <?php disabled($is_locked); ?>>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="amount"
                                            value="<?php echo esc_attr($fee->amount); ?>" style="width:100%"
                                            class="os-agr-fee-calc" <?php disabled($is_locked); ?>>
                                        <?php 
                                        if (is_numeric($fee->fee_category) && $fee->fee_category > 0) {
                                            $row_template = Olama_Reg_Billing_Fees::get_template((int)$fee->fee_category);
                                            if ($row_template && !empty($row_template->items)) {
                                                echo '<div style="font-size:11px; margin-top:5px; padding:5px; background:#f9f9f9; border:1px solid #ddd; border-radius:3px;">';
                                                $total_items = 0;
                                                foreach ($row_template->items as $item) {
                                                    $desc = esc_html($item['description'] ?? '');
                                                    $amt = (float)($item['amount'] ?? 0);
                                                    $total_items += $amt;
                                                    echo "<div style='display:flex; justify-content:space-between;'><span>{$desc}:</span> <span>" . number_format($amt, 3) . "</span></div>";
                                                }
                                                echo '<div style="border-top:1px dashed #ccc; margin-top:4px; padding-top:4px; display:flex; justify-content:space-between;"><strong>' . esc_html__('الإجمالي:', 'olama-registration') . '</strong> <strong>' . number_format($total_items, 3) . '</strong></div>';
                                                echo '</div>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="discount"
                                            value="<?php echo esc_attr($fee->discount); ?>" style="width:100%"
                                            class="os-agr-fee-calc" <?php disabled($is_locked); ?>>
                                    </td>
                                    <td>
                                        <span
                                            class="os-agr-fee-net"><?php echo number_format((float) $fee->net_amount, 3); ?></span>
                                    </td>
                                    <td>
                                        <input type="text" name="due_date" value="<?php echo esc_attr($fee->due_date); ?>"
                                            style="width:100%" class="os-datepicker" <?php disabled($is_locked); ?>>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($fee->paid_status === 'paid' && $fee->invoice_id) {
                                            $payment_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}olama_payments WHERE invoice_id = %d LIMIT 1", $fee->invoice_id));
                                            if ($payment_id) {
                                                echo '<a href="' . esc_url(admin_url('admin.php?page=olama-registration-payments&action=print_receipt&id=' . $payment_id)) . '" target="_blank">' . esc_html__('مدفوع (السند)', 'olama-registration') . '</a>';
                                            } else {
                                                echo esc_html($fee->paid_status);
                                            }
                                        } else {
                                            echo esc_html($fee->paid_status); 
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (!$is_locked): ?>
                                            <button type="button"
                                                class="button button-small os-agr-save-fee"><?php esc_html_e('حفظ', 'olama-registration'); ?></button>
                                            <button type="button" class="button button-small os-agr-delete-fee"
                                                style="color:red;">X</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" style="text-align:left;">
                                <strong><?php esc_html_e('الإجمالي الكلي للعقد:', 'olama-registration'); ?></strong></td>
                            <td colspan="4"><strong><span
                                        id="os-agr-total-label"><?php echo number_format((float) $agreement->total_amount, 3); ?></span>
                                    JD</strong></td>
                        </tr>
                        <?php 
                        $actual_template_id = $agreement->template_id;
                        if (empty($actual_template_id) && !empty($fees)) {
                            foreach ($fees as $fee) {
                                if (is_numeric($fee->fee_category) && $fee->fee_category > 0) {
                                    $actual_template_id = (int)$fee->fee_category;
                                    break;
                                }
                            }
                        }

                        if (!empty($actual_template_id)) {
                            $fee_template = Olama_Reg_Billing_Fees::get_template($actual_template_id);
                            if ($fee_template) {
                                ?>
                                <tr>
                                    <td colspan="9" style="text-align:center; font-weight:normal; background-color: #f9f9f9;">
                                        <?php 
                                        $template_name = esc_html($fee_template->template_name);
                                        $total_amount_formatted = number_format((float) $agreement->total_amount, 3);
                                        echo sprintf(
                                            esc_html__('بناءً على نموذج الرسوم (%s): صافي %s دينار، ويُوزَّع على أقساط العقد.', 'olama-registration'),
                                            $template_name,
                                            $total_amount_formatted
                                        );
                                        ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                    </tfoot>
                </table>

                <?php
                $due_schedule = Olama_Reg_Agreement_Invoice::get_due_schedule($id);
                if (empty($due_schedule) && (float) $agreement->total_amount > 0) {
                    Olama_Reg_Agreement_Invoice::generate_default_due_schedule($id);
                    $due_schedule = Olama_Reg_Agreement_Invoice::get_due_schedule($id);
                }
                $due_total = 0.0;
                ?>

                <h3 class="olama-reg-section-title" style="margin-top:25px;"><?php esc_html_e('توزيع الاستحقاق', 'olama-registration'); ?></h3>
                <table class="olama-reg-fin-table" id="os-agr-due-table" data-agr-id="<?php echo esc_attr($id); ?>">
                    <thead>
                        <tr>
                            <th style="width:90px;"><?php esc_html_e('رقم القسط', 'olama-registration'); ?></th>
                            <th><?php esc_html_e('تاريخ الاستحقاق', 'olama-registration'); ?></th>
                            <th><?php esc_html_e('مبلغ القسط', 'olama-registration'); ?></th>
                            <th><?php esc_html_e('المدفوع', 'olama-registration'); ?></th>
                            <th><?php esc_html_e('المتبقي', 'olama-registration'); ?></th>
                            <th><?php esc_html_e('الحالة', 'olama-registration'); ?></th>
                            <th style="width:80px;"><?php esc_html_e('إجراءات', 'olama-registration'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($due_schedule as $line): ?>
                            <?php
                            $amount = (float) $line->amount_due;
                            $paid = (float) $line->amount_paid;
                            $remaining = max(0, $amount - $paid);
                            $due_total += $amount;
                            $line_locked = !$can_reschedule_installments || $paid > 0;
                            ?>
                            <tr>
                                <td class="os-agr-due-no"><?php echo esc_html($line->installment_no); ?></td>
                                <td><input type="text" class="os-datepicker os-agr-due-date" value="<?php echo esc_attr($line->due_date); ?>" style="width:100%;" <?php disabled($line_locked); ?>></td>
                                <td><input type="number" step="0.01" min="0.01" class="os-agr-due-amount" value="<?php echo esc_attr(number_format($amount, 2, '.', '')); ?>" style="width:100%;" <?php disabled($line_locked); ?>></td>
                                <td><?php echo esc_html(number_format($paid, 2)); ?></td>
                                <td><?php echo esc_html(number_format($remaining, 2)); ?></td>
                                <td><?php echo esc_html($line->status); ?></td>
                                <td>
                                    <?php if (!$line_locked): ?>
                                        <button type="button" class="button button-small os-agr-delete-due">X</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="7" style="background:#fafafa;">
                                <strong><?php esc_html_e('صافي العقد', 'olama-registration'); ?>:</strong>
                                <span id="os-agr-due-net"><?php echo esc_html(number_format((float) $agreement->total_amount, 2)); ?></span>
                                &nbsp; | &nbsp;
                                <strong><?php esc_html_e('مجموع الاستحقاقات', 'olama-registration'); ?>:</strong>
                                <span id="os-agr-due-total"><?php echo esc_html(number_format($due_total, 2)); ?></span>
                                &nbsp; | &nbsp;
                                <strong><?php esc_html_e('الفرق', 'olama-registration'); ?>:</strong>
                                <span id="os-agr-due-diff"><?php echo esc_html(number_format(((float) $agreement->total_amount) - $due_total, 2)); ?></span>
                                <div id="os-agr-due-warning" style="display:none; color:#b91c1c; margin-top:8px; font-weight:700;">
                                    <?php esc_html_e('مجموع الاستحقاقات لا يساوي صافي العقد. يرجى تعديل توزيع الاستحقاق قبل الحفظ.', 'olama-registration'); ?>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <div style="margin-top: 20px; display:flex; justify-content:space-between; align-items:center; gap:15px; flex-wrap:wrap;">
                    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                        <label style="display:flex; align-items:center; gap:8px; font-weight:700;">
                            <span><?php esc_html_e('عدد الأقساط', 'olama-registration'); ?></span>
                            <input type="number" id="os-agr-due-count" min="1" max="24" value="<?php echo esc_attr( count( $due_schedule ) ?: Olama_Reg_Agreement_Invoice::DEFAULT_INSTALLMENTS ); ?>" style="width:90px;" <?php disabled(!$can_reschedule_installments); ?>>
                        </label>
                        <button type="button" class="olama-reg-btn olama-reg-btn--secondary"
                            id="os-agr-add-fee-row"><span class="dashicons dashicons-plus" style="margin-top:4px;"></span> <?php esc_html_e('إضافة بند رسوم', 'olama-registration'); ?></button>
                        <button type="button" class="button" id="os-agr-add-due-row"><?php esc_html_e('إضافة قسط', 'olama-registration'); ?></button>
                        <button type="button" class="button" id="os-agr-regenerate-due"><?php esc_html_e('توليد التوزيع المقترح', 'olama-registration'); ?></button>
                        <button type="button" class="button button-primary" id="os-agr-save-due"><?php esc_html_e('حفظ توزيع الاستحقاق', 'olama-registration'); ?></button>
                    </div>
                    <?php
                    $has_unpaid = false;
                    if (!empty($fees)) {
                        foreach ($fees as $f) {
                            if ($f->paid_status === 'unpaid') {
                                $has_unpaid = true;
                                break;
                            }
                        }
                    }
                    ?>
                    <?php if ($agreement->status !== 'completed' && $agreement->status !== 'cancelled'): ?>
                        <button type="button" class="olama-reg-btn olama-reg-btn--primary" id="os-agr-complete-agreement">
                            <span class="dashicons dashicons-yes-alt" style="margin-top:4px;"></span> <?php esc_html_e('إكمال العقد وإنشاء الفاتورة', 'olama-registration'); ?>
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Hidden template for new row -->
                <table style="display:none;">
                    <tbody id="os-agr-fee-row-template">
                        <tr data-fee-id="0">
                            <td>
                                <select name="fee_category" style="width:100%" class="os-agr-fee-template-select">
                                    <?php
                                    foreach ($templates as $tpl) {
                                        $total = 0;
                                        foreach ($tpl->items as $it) {
                                            $total += (float) ($it['amount'] ?? 0);
                                        }
                                        echo '<option value="' . esc_attr($tpl->id) . '" data-name="' . esc_attr($tpl->template_name) . '" data-amount="' . esc_attr($total) . '" data-subject-type="' . esc_attr($tpl->subject_type ?? 'general') . '" data-subject-value="' . esc_attr($tpl->subject_value ?? '') . '">' . esc_html($tpl->template_name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                            <td>
                                <select name="child_id" style="width:100%" class="os-agr-fee-child-select">
                                    <option value=""><?php esc_html_e('اختر المشترك', 'olama-registration'); ?></option>
                                    <?php
                                    if (!empty($payer_children)) {
                                        foreach ($payer_children as $child) {
                                            echo '<option value="' . esc_attr($child['id']) . '">' . esc_html($child['text']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </td>
                            <td><input type="text" name="label" class="os-inline-input" style="width:100%"
                                    placeholder="<?php esc_attr_e('البيان', 'olama-registration'); ?>"></td>
                            <td><input type="number" step="0.01" name="amount" value="0.00"
                                    class="os-inline-input os-agr-fee-calc" style="width:100%"></td>
                            <td><input type="number" step="0.01" name="discount" value="0.00"
                                    class="os-inline-input os-agr-fee-calc" style="width:100%"></td>
                            <td><span class="os-agr-fee-net">0.000</span></td>
                            <td><input type="text" name="due_date" class="os-inline-input os-datepicker" style="width:100%">
                            </td>
                            <td>unpaid</td>
                            <td>
                                <button type="button"
                                    class="button button-small os-agr-save-fee"><?php esc_html_e('حفظ', 'olama-registration'); ?></button>
                                <button type="button" class="button button-small os-agr-delete-fee"
                                    style="color:red;">X</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="os-tab-content" id="tab-clauses" style="display:none;">
        <div class="olama-reg-section">
            <h3 class="olama-reg-section-title"><?php esc_html_e('بنود وشروط العقد', 'olama-registration'); ?></h3>
            <?php if ($is_new): ?>
                <p style="padding:15px; color:#666;"><?php esc_html_e('الرجاء حفظ البيانات الأساسية أولاً.', 'olama-registration'); ?></p>
            <?php else: ?>
                <div style="padding:15px;">
                    <div style="margin-bottom: 25px; display:flex; flex-direction:column; gap:10px; background:#f9f9f9; padding:20px; border-radius:8px; border:1px solid #eee;">
                        <label style="font-weight:700; color:#E8920A;"><?php esc_html_e('إضافة بند جديد', 'olama-registration'); ?></label>
                        <div style="display:flex; gap:15px; align-items:flex-start;">
                            <textarea id="os-agr-new-clause" rows="3" style="flex:1; border:1px solid #ddd; border-radius:6px; padding:8px;"
                                placeholder="<?php esc_attr_e('أدخل البند هنا...', 'olama-registration'); ?>"></textarea>
    
                            <div style="width:300px;">
                                <select id="os-agr-clause-bank-select" style="width:100%; margin-bottom:10px;">
                                    <option value=""><?php esc_html_e('-- اختر من البنود العامة --', 'olama-registration'); ?>
                                    </option>
                                    <?php
                                    $bank_clauses = Olama_Reg_Clause_Bank::get_active();
                                    foreach ($bank_clauses as $bc) {
                                        echo '<option value="' . esc_attr($bc->clause_text) . '">' . esc_html($bc->title) . '</option>';
                                    }
                                    ?>
                                </select>
                                <button type="button" class="olama-reg-btn olama-reg-btn--primary" id="os-agr-add-clause"
                                    data-agr-id="<?php echo esc_attr($id); ?>"
                                    style="width:100%;"><span class="dashicons dashicons-plus-alt2" style="margin-top:4px;"></span> <?php esc_html_e('إضافة البند', 'olama-registration'); ?></button>
                            </div>
                        </div>
                    </div>

                <ul id="os-agr-clauses-list" style="margin:0; padding:0; list-style:none;">
                    <?php
                    $clauses = Olama_Reg_Agreement_Clauses::get_by_agreement($id);
                    if ($clauses) {
                        foreach ($clauses as $clause) {
                            ?>
                            <li data-clause-id="<?php echo esc_attr($clause->id); ?>"
                                style="background:#fff; border:1px solid #e0c090; border-radius:6px; padding:15px; margin-bottom:10px; cursor:move; box-shadow:0 2px 5px rgba(0,0,0,0.02);">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                    <span class="dashicons dashicons-menu" style="color:#ccc; margin-left:10px; cursor:grab; margin-top:5px;"></span>
                                    <textarea class="os-agr-clause-text" style="flex-grow:1; margin-left:15px; border:1px solid #eee; border-radius:4px; padding:8px;"
                                        rows="2"><?php echo esc_textarea($clause->clause_text); ?></textarea>
                                    <div style="display:flex; flex-direction:column; gap:5px;">
                                        <button type="button" class="olama-reg-btn olama-reg-btn--primary os-agr-save-clause"
                                            style="padding:2px 10px; font-size:12px; min-height:28px;"><?php esc_html_e('حفظ', 'olama-registration'); ?></button>
                                        <button type="button" class="button button-small os-agr-delete-clause"
                                            style="color:#c62828; border-color:#ffcdd2; background:#fff;">X</button>
                                    </div>
                                </div>
                            </li>
                            <?php
                        }
                    }
                    ?>
                </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$is_new): ?>
    <div id="os-agr-amendment-modal" class="olama-reg-modal olama-reg-wrap os-agr-amendment-modal" style="display:none;">
        <div class="olama-reg-modal-dialog os-agr-amendment-dialog">
            <div class="olama-reg-modal-header">
                <h2 class="olama-reg-modal-title"><?php esc_html_e('تعديل مالي على العقد', 'olama-registration'); ?></h2>
                <button type="button" class="olama-reg-modal-close" id="os-agr-close-amendment-modal">&times;</button>
            </div>
            <form id="os-agr-amendment-form" style="margin:0;">
                <div class="olama-reg-modal-body os-agr-amendment-body">
                    <div class="olama-reg-grid">
                        <div class="olama-reg-field">
                            <label><?php esc_html_e('نوع التعديل', 'olama-registration'); ?></label>
                            <select name="amendment_type" id="os-agr-amendment-type" style="width:100%;">
                                <option value="correction_error"><?php esc_html_e('تصحيح خطأ', 'olama-registration'); ?></option>
                                <option value="discount_change"><?php esc_html_e('تعديل خصم', 'olama-registration'); ?></option>
                                <option value="increase_amount"><?php esc_html_e('زيادة مبلغ', 'olama-registration'); ?></option>
                                <option value="decrease_amount"><?php esc_html_e('خفض مبلغ', 'olama-registration'); ?></option>
                            </select>
                        </div>
                        <div class="olama-reg-field">
                            <label><?php esc_html_e('تاريخ السريان', 'olama-registration'); ?></label>
                            <input type="text" name="effective_date" id="os-agr-amendment-date" class="os-datepicker" value="<?php echo esc_attr(current_time('Y-m-d')); ?>">
                        </div>
                        <div class="olama-reg-field">
                            <label><?php esc_html_e('القيمة القديمة', 'olama-registration'); ?></label>
                            <input type="text" id="os-agr-amendment-old" readonly>
                        </div>
                        <div class="olama-reg-field">
                            <label><?php esc_html_e('القيمة الجديدة', 'olama-registration'); ?></label>
                            <input type="number" step="0.001" name="new_total" id="os-agr-amendment-new" value="">
                        </div>
                        <div class="olama-reg-field">
                            <label><?php esc_html_e('الفرق', 'olama-registration'); ?></label>
                            <input type="text" id="os-agr-amendment-diff" readonly>
                        </div>
                    </div>
                    <div class="olama-reg-field" style="margin-top:14px;">
                        <label><?php esc_html_e('سبب التعديل', 'olama-registration'); ?></label>
                        <textarea name="reason" id="os-agr-amendment-reason" rows="4" style="width:100%;"></textarea>
                    </div>
                    <div class="olama-reg-field" style="margin-top:14px;">
                        <label><?php esc_html_e('ملاحظات داخلية', 'olama-registration'); ?></label>
                        <textarea name="admin_notes" id="os-agr-amendment-notes" rows="3" style="width:100%;"></textarea>
                    </div>
                    <div class="notice notice-warning inline os-agr-amendment-confirm">
                        <p style="margin:0;"><?php esc_html_e('أفهم أن هذا التعديل سيؤثر على الرصيد المالي للعقد وسيتم تسجيله في سجل التدقيق ولا يمكن حذفه بعد الترحيل.', 'olama-registration'); ?></p>
                    </div>
                </div>
                <div class="os-agr-amendment-footer">
                    <button type="button" class="button" id="os-agr-preview-amendment"><?php esc_html_e('معاينة الأثر', 'olama-registration'); ?></button>
                    <button type="submit" class="olama-reg-btn olama-reg-btn--primary" id="os-agr-save-amendment"><?php esc_html_e('حفظ المسودة', 'olama-registration'); ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if (!$is_new): ?>
    <?php $amendments = class_exists('Olama_Reg_Agreement_Amendment') ? Olama_Reg_Agreement_Amendment::get_by_agreement($id) : []; ?>
    <div class="olama-reg-wrap os-wrap os-agr-amendments-wrap">
    <div class="olama-reg-section os-agr-amendments-section">
        <h3 class="olama-reg-section-title"><?php esc_html_e('سجل تعديلات العقد', 'olama-registration'); ?></h3>
        <div class="olama-reg-table-wrap">
            <table class="olama-reg-fin-table os-agr-amendments-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('رقم التعديل', 'olama-registration'); ?></th>
                        <th><?php esc_html_e('النوع', 'olama-registration'); ?></th>
                        <th><?php esc_html_e('الحالة', 'olama-registration'); ?></th>
                        <th><?php esc_html_e('تاريخ السريان', 'olama-registration'); ?></th>
                        <th><?php esc_html_e('القديم', 'olama-registration'); ?></th>
                        <th><?php esc_html_e('الجديد', 'olama-registration'); ?></th>
                        <th><?php esc_html_e('الفرق', 'olama-registration'); ?></th>
                        <th><?php esc_html_e('الإجراءات', 'olama-registration'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($amendments)): ?>
                        <?php foreach ($amendments as $amendment): ?>
                            <tr data-amendment-id="<?php echo esc_attr($amendment->id); ?>">
                                <td><strong><?php echo esc_html($amendment->amendment_no); ?></strong></td>
                                <td><?php echo esc_html($amendment->amendment_type); ?></td>
                                <td><?php echo esc_html($amendment->status); ?></td>
                                <td><?php echo esc_html($amendment->effective_date); ?></td>
                                <td><?php echo esc_html(number_format((float) $amendment->old_total, 3)); ?></td>
                                <td><?php echo esc_html(number_format((float) $amendment->new_total, 3)); ?></td>
                                <td><?php echo esc_html(number_format((float) $amendment->difference_amount, 3)); ?></td>
                                <td class="os-agr-amendments-actions">
                                    <?php if ($amendment->status === 'draft' && (current_user_can('manage_options') || current_user_can('olama_approve_agreement_amendment'))): ?>
                                        <button type="button" class="button button-small os-agr-approve-amendment"><?php esc_html_e('اعتماد', 'olama-registration'); ?></button>
                                    <?php endif; ?>
                                    <?php if ($amendment->status === 'approved' && (current_user_can('manage_options') || current_user_can('olama_post_agreement_amendment'))): ?>
                                        <button type="button" class="button button-small button-primary os-agr-post-amendment"><?php esc_html_e('ترحيل', 'olama-registration'); ?></button>
                                    <?php endif; ?>
                                    <?php if (in_array($amendment->status, ['draft', 'approved', 'pending_approval'], true) && (current_user_can('manage_options') || current_user_can('olama_approve_agreement_amendment'))): ?>
                                        <button type="button" class="button button-small os-agr-reject-amendment"><?php esc_html_e('رفض', 'olama-registration'); ?></button>
                                    <?php endif; ?>
                                    <?php if ($amendment->status !== 'posted' && (current_user_can('manage_options') || current_user_can('olama_cancel_financial_agreement'))): ?>
                                        <button type="button" class="button button-small os-agr-cancel-amendment"><?php esc_html_e('إلغاء', 'olama-registration'); ?></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8"><?php esc_html_e('لا توجد تعديلات مسجلة بعد.', 'olama-registration'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    </div>
<?php endif; ?>

<?php if (!$is_new): ?>
    <!-- Invoice Generation Modal -->
    <div id="os-agr-invoice-modal"
        style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:99999; backdrop-filter:blur(3px);">
        <div
            style="background:#fff; width:500px; margin:100px auto; padding:30px; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.2); border-top:4px solid #E8920A;">
            <h3 style="margin-top:0; color:#1a1a2e; font-family:'Tajawal', sans-serif;"><?php esc_html_e('إصدار فاتورة', 'olama-registration'); ?></h3>
            <p style="color:#666; margin-bottom:20px;"><?php esc_html_e('الرجاء تحديد الرسوم التي ترغب بفوترتها:', 'olama-registration'); ?></p>

            <form id="os-agr-invoice-form">
                <input type="hidden" name="agreement_id" value="<?php echo esc_attr($id); ?>">
                <table class="olama-reg-fin-table" style="margin-bottom:25px;">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="os-agr-inv-check-all" checked></th>
                            <th><?php esc_html_e('الرسم', 'olama-registration'); ?></th>
                            <th><?php esc_html_e('المبلغ', 'olama-registration'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Rows will be injected via JS -->
                    </tbody>
                </table>

                <div style="text-align:left; display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" class="button"
                        id="os-agr-close-invoice-modal"><?php esc_html_e('إلغاء', 'olama-registration'); ?></button>
                    <button type="submit" class="olama-reg-btn olama-reg-btn--primary" <?php disabled(!$has_unpaid); ?>><?php esc_html_e('تأكيد الإصدار', 'olama-registration'); ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
