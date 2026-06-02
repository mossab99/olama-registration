<?php
/**
 * Agreement Print View
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('Unauthorized', 'olama-registration'));
}

$id = (int) ($_GET['id'] ?? 0);
$agreement = Olama_Reg_Agreement::get($id);

if (!$agreement) {
    echo '<p>' . esc_html__('العقد غير موجود.', 'olama-registration') . '</p>';
    return;
}

$fees = Olama_Reg_Agreement_Fees::get_by_agreement($id);
$clauses = Olama_Reg_Agreement_Clauses::get_by_agreement($id);

// Get Payer Info
$payer_name = $agreement->payer_name;
$payer_phone = '';
if ($agreement->payer_type === 'customer') {
    $cust = Olama_Reg_Customer::get($agreement->payer_id);
    if ($cust)
        $payer_phone = $cust->phone;
} else {
    global $wpdb;
    $family = $wpdb->get_row($wpdb->prepare("SELECT father_mobile, mother_mobile FROM {$wpdb->prefix}olama_families WHERE family_uid = %s", $agreement->payer_id));
    if ($family)
        $payer_phone = $family->father_mobile ?: $family->mother_mobile;
}

// Ensure JS/CSS only target this page for printing
?>
<style>
    body { font-family: 'Tajawal', sans-serif; margin: 40px; color: #1a1a2e; background: #f9f9f9; }
    .print-wrap { max-width: 800px; margin: 0 auto; background: #fff; border: 1px solid #e0c090; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(232,146,10,0.1); }
    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; border-bottom: 2px solid #E8920A; padding-bottom: 20px; }
    .header-table td { vertical-align: top; }
    .logo-title { font-size: 24px; font-weight: 800; color: #E8920A; }
    .invoice-title { font-size: 24px; font-weight: 800; text-align: left; color: #1a1a2e; }
    .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
    .meta-table td { padding: 8px; border-bottom: 1px solid #f0f0f0; }
    .label { font-weight: 700; color: #6B7280; width: 150px; }
    .section-title { font-size: 18px; font-weight: 800; color: #E8920A; border-bottom: 1px solid #eee; padding-bottom: 8px; margin-bottom: 15px; margin-top: 30px; }
    .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
    .items-table th { background: #FFF3E0; color: #C4780A; font-weight: 700; padding: 10px; border: 1px solid #e0c090; text-align: right; }
    .items-table td { padding: 10px; border: 1px solid #eee; }
    .totals-table { width: 300px; margin-right: auto; margin-left: 0; border-collapse: collapse; }
    .totals-table td { padding: 8px; border-bottom: 1px dashed #e0c090; }
    .totals-table tr.grand-total td { font-weight: 800; font-size: 16px; color: #E8920A; border-bottom: 2px solid #E8920A; }
    .signatures { margin-top: 60px; display: flex; justify-content: space-between; }
    .signatures div { width: 40%; text-align: center; border-top: 1px dashed #ccc; padding-top: 15px; font-weight: 700; color: #6B7280; }
    .no-print { text-align: center; margin-bottom: 20px; }
    
    @media print {
        body { margin: 0; background: none; }
        .print-wrap { border: none; box-shadow: none; padding: 0; max-width: 100%; }
        .no-print, #adminmenuwrap, #adminmenuback, #wpadminbar, #wpfooter, .notice, .update-nag { display: none !important; }
        #wpcontent { margin-left: 0 !important; margin-right: 0 !important; padding: 0 !important; }
        @page { margin: 1cm; }
    }
</style>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;800&display=swap" rel="stylesheet">

<div class="wrap">
    <div class="no-print">
        <a href="<?php echo esc_url(admin_url('admin.php?page=olama-registration-agreements&action=edit&id=' . $id)); ?>"
            class="button">&laquo; <?php esc_html_e('العودة للعقد', 'olama-registration'); ?></a>
        <button type="button" class="button button-primary" onclick="window.print();" style="margin-right:10px;">
            <span class="dashicons dashicons-printer" style="margin-top:4px;"></span>
            <?php esc_html_e('طباعة العقد', 'olama-registration'); ?>
        </button>
    </div>

    <div class="print-wrap" id="os-print-area" dir="rtl">
        <table class="header-table">
            <tr>
                <td>
                    <?php 
                    $school_settings = get_option('olama_school_settings', []);
                    $school_name = !empty($school_settings['school_name_ar']) ? $school_settings['school_name_ar'] : __('نظام علماء', 'olama-registration');
                    ?>
                    <div class="logo-title"><?php echo esc_html($school_name); ?></div>
                    <div style="font-size: 11px; color:#6B7280; margin-top:4px;"><?php esc_html_e('عقد تسجيل / نشاط', 'olama-registration'); ?></div>
                </td>
                <td style="text-align: left;">
                    <div class="invoice-title"><?php echo esc_html($agreement->activity_type); ?></div>
                    <div style="font-weight: 700; color: #6B7280; margin-top:4px;"><?php echo esc_html($agreement->agreement_number); ?></div>
                </td>
            </tr>
        </table>

        <table class="meta-table">
            <tr>
                <td class="label"><?php esc_html_e('الطرف الثاني (الجهة الدافعة):', 'olama-registration'); ?></td>
                <td><?php echo esc_html($payer_name); ?> <?php echo $payer_phone ? ' ( ' . esc_html($payer_phone) . ' ) ' : ''; ?></td>
                <td class="label"><?php esc_html_e('تاريخ الإصدار:', 'olama-registration'); ?></td>
                <td><?php echo esc_html(date('Y-m-d', strtotime($agreement->created_at))); ?></td>
            </tr>
            <tr>
                <td class="label"><?php esc_html_e('تاريخ البداية:', 'olama-registration'); ?></td>
                <td><?php echo esc_html($agreement->start_date); ?></td>
                <td class="label"><?php esc_html_e('تاريخ النهاية:', 'olama-registration'); ?></td>
                <td><?php echo esc_html($agreement->end_date ?: '—'); ?></td>
            </tr>
            <tr>
                <td class="label"><?php esc_html_e('المشترك:', 'olama-registration'); ?></td>
                <td><strong><?php echo esc_html($agreement->participant_name); ?></strong></td>
                <td class="label"><?php esc_html_e('الحالة:', 'olama-registration'); ?></td>
                <td><strong><?php echo esc_html($agreement->status); ?></strong></td>
            </tr>
        </table>

        <div class="os-print-section">
            <div class="section-title"><?php esc_html_e('الرسوم المستحقة', 'olama-registration'); ?></div>
            <?php 
            if ($fees): 
                $grouped_fees = [];
                foreach ($fees as $fee) {
                    $child_id = $fee->child_id ?: 0;
                    if (!isset($grouped_fees[$child_id])) {
                        $grouped_fees[$child_id] = [
                            'child_name' => $child_id ? Olama_Reg_Agreement::resolve_participant_name($agreement->participant_type, (string)$child_id) : esc_html__('رسوم عامة / بدون تحديد مشترك', 'olama-registration'),
                            'items' => []
                        ];
                    }
                    $grouped_fees[$child_id]['items'][] = $fee;
                }

                foreach ($grouped_fees as $child_id => $group):
                    ?>
                    <h4 class="child-fees-title" style="margin-top: 20px; font-size:16px; color:#1a1a2e; border-right: 3px solid #E8920A; padding-right: 8px;">
                        <?php echo esc_html($group['child_name']); ?>
                    </h4>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('الرقم', 'olama-registration'); ?></th>
                                <th><?php esc_html_e('البيان', 'olama-registration'); ?></th>
                                <th><?php esc_html_e('تاريخ الاستحقاق', 'olama-registration'); ?></th>
                                <th><?php esc_html_e('المبلغ (الأساسي)', 'olama-registration'); ?></th>
                                <th><?php esc_html_e('الخصم', 'olama-registration'); ?></th>
                                <th><?php esc_html_e('المبلغ (الصافي)', 'olama-registration'); ?></th>
                                <th><?php esc_html_e('رقم الفاتورة', 'olama-registration'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $i = 1;
                            $child_net_total = 0;
                            foreach ($group['items'] as $fee):
                                $child_net_total += (float)$fee->net_amount;
                                ?>
                                <tr>
                                    <td style="text-align:center;"><?php echo $i++; ?></td>
                                    <td><?php echo esc_html($fee->label ?: $fee->fee_category); ?></td>
                                    <td><?php echo esc_html($fee->due_date ?: '-'); ?></td>
                                    <td style="text-align:left; vertical-align:top;">
                                        <?php 
                                        $showed_breakdown = false;
                                        if (is_numeric($fee->fee_category) && $fee->fee_category > 0) {
                                            $row_template = Olama_Reg_Billing_Fees::get_template((int)$fee->fee_category);
                                            if ($row_template && !empty($row_template->items)) {
                                                $showed_breakdown = true;
                                                echo '<div style="font-size:12px; margin-bottom:5px;">';
                                                foreach ($row_template->items as $item) {
                                                    $desc = esc_html($item['description'] ?? '');
                                                    $amt = number_format((float)($item['amount'] ?? 0), 3);
                                                    echo "<div style='display:flex; justify-content:space-between;'><span>{$desc}:</span> <span>{$amt}</span></div>";
                                                }
                                                echo '<div style="border-top:1px dashed #ccc; margin-top:4px; padding-top:4px; display:flex; justify-content:space-between;"><strong>' . esc_html__('الإجمالي:', 'olama-registration') . '</strong> <strong>' . number_format((float) $fee->amount, 3) . '</strong></div>';
                                                echo '</div>';
                                            }
                                        }
                                        if (!$showed_breakdown) {
                                            echo number_format((float) $fee->amount, 3); 
                                        }
                                        ?>
                                    </td>
                                    <td style="text-align:left;"><?php echo number_format((float) $fee->discount, 3); ?></td>
                                    <td style="text-align:left;"><?php echo number_format((float) $fee->net_amount, 3); ?></td>
                                    <td style="text-align:center;">
                                        <?php 
                                        if ($fee->paid_status === 'paid' && $fee->invoice_id) {
                                            global $wpdb;
                                            $invoice_num = $wpdb->get_var($wpdb->prepare("SELECT invoice_number FROM {$wpdb->prefix}olama_invoices WHERE id = %d", $fee->invoice_id));
                                            echo esc_html($invoice_num ?: '-');
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="5" style="text-align:left;">
                                    <?php esc_html_e('إجمالي رسوم المشترك:', 'olama-registration'); ?></th>
                                <th style="text-align:left;">
                                    <?php echo number_format($child_net_total, 3) . ' JD'; ?>
                                </th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                <?php endforeach; ?>

                <!-- Final aggregated total amount due for the entire agreement -->
                <table class="totals-table" style="margin-top: 20px;">
                    <tr class="grand-total">
                        <td style="font-weight:800;"><?php esc_html_e('الإجمالي الكلي للعقد:', 'olama-registration'); ?></td>
                        <td style="text-align:left; font-weight:800;"><?php echo number_format((float) $agreement->total_amount, 3); ?> JD</td>
                    </tr>
                    <?php 
                    $actual_template_id = $agreement->template_id;
                    if (empty($actual_template_id) && !empty($fees)) {
                        // Try to find template id from fees
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
                                <td colspan="2" style="text-align:center; font-weight:normal; background-color: #f9f9f9; border:none; padding:10px;">
                                    <?php 
                                    $template_name = esc_html($fee_template->template_name);
                                    $installments = (int)$fee_template->installments;
                                    $total_amount_formatted = number_format((float) $agreement->total_amount, 3);
                                    
                                    if ($installments > 1) {
                                        echo sprintf(
                                            esc_html__('بناءً على نموذج الرسوم (%s): مبلغ %s دينار مقسمة على %d دفعات.', 'olama-registration'),
                                            $template_name,
                                            $total_amount_formatted,
                                            $installments
                                        );
                                    } else {
                                        echo sprintf(
                                            esc_html__('بناءً على نموذج الرسوم (%s)', 'olama-registration'),
                                            $template_name
                                        );
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </table>
            <?php else: ?>
                <p><?php esc_html_e('لا يوجد رسوم مسجلة لهذا العقد.', 'olama-registration'); ?></p>
            <?php endif; ?>
        </div>

        <div class="os-print-section">
            <div class="section-title"><?php esc_html_e('البنود والشروط', 'olama-registration'); ?></div>
            <?php if ($clauses): ?>
                <ol style="padding-right: 20px;">
                    <?php foreach ($clauses as $clause): ?>
                        <li style="margin-bottom: 10px;"><?php echo nl2br(esc_html($clause->clause_text)); ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p><?php esc_html_e('لا يوجد بنود مسجلة لهذا العقد.', 'olama-registration'); ?></p>
            <?php endif; ?>
        </div>

        <div class="signatures">
            <div>
                <strong><?php esc_html_e('الطرف الأول', 'olama-registration'); ?></strong><br>
                <?php esc_html_e('( التوقيع / الختم )', 'olama-registration'); ?>
            </div>
            <div>
                <strong><?php esc_html_e('الطرف الثاني', 'olama-registration'); ?></strong><br>
                <?php esc_html_e('( التوقيع )', 'olama-registration'); ?>
            </div>
        </div>
    </div>
</div>