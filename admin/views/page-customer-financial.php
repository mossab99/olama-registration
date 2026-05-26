<?php
/**
 * External Customer Financial Settlement View — Premium Redesign
 * Displays all invoices, payments, and balances for the customer and their children.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$customer = Olama_Reg_Customer::get( $customer_id );
if ( ! $customer ) {
    wp_die( esc_html__( 'العميل غير موجود.', 'olama-registration' ) );
}

$customer_uid = $customer->customer_uid ?: ( 'CUST-' . str_pad( $customer->id, 4, '0', STR_PAD_LEFT ) );

// Academic years for dropdown filter
$academic_years = [];
if ( class_exists( 'Olama_School_Academic' ) ) {
    $academic_years = (array) ( Olama_School_Academic::get_years() ?: [] );
}

// Resolve selected academic year ID
$active_year_id = 0;
if ( isset( $_GET['academic_year_id'] ) ) {
    $active_year_id = (int) $_GET['academic_year_id'];
} else if ( class_exists( 'Olama_School_Academic' ) ) {
    $ay = Olama_School_Academic::get_active_year();
    if ( $ay ) $active_year_id = (int) $ay->id;
}

// Get customer data
$children = Olama_Reg_Child::get_by_customer( $customer_id );
$invoices = Olama_Reg_Billing_Invoice::get_customer_invoices( $customer_id, $active_year_id );
$payments = Olama_Reg_Billing_Payment::get_family_payments( $customer_uid, $active_year_id );
$summary  = Olama_Reg_Billing_Invoice::get_customer_invoice_summary( $customer_id, $active_year_id );
?>

<div class="olama-reg-page-header">
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration-contacts&view=customers' ) ); ?>"
       class="olama-reg-back-btn">← <?php esc_html_e( 'العودة للقائمة', 'olama-registration' ); ?></a>
    <h1>
        <?php esc_html_e( 'ملف العميل المالي', 'olama-registration' ); ?>
        <span class="olama-reg-uid-badge olama-reg-uid-badge--lg"><?php echo esc_html( $customer_uid ); ?></span>
    </h1>
</div>

<!-- Hero Customer Details Card -->
<div class="olama-reg-uid-hero" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); margin-bottom: 24px;">
    <span class="dashicons dashicons-businessman" style="font-size:36px;width:36px;height:36px;color:rgba(255,255,255,0.9);flex-shrink:0;"></span>
    <div style="flex:1;">
        <span class="olama-reg-uid-hero-label"><?php esc_html_e( 'اسم العميل الخارجي', 'olama-registration' ); ?></span>
        <div class="olama-reg-uid-hero-value" style="font-size: 22px; font-weight: 800; color: #fff;">
            <?php echo esc_html( $customer->customer_name ); ?>
        </div>
        <div class="olama-reg-uid-hero-meta" style="color: #94a3b8; font-size: 13px; margin-top: 4px;">
            <?php if ( $customer->phone ): ?>
                <span class="dashicons dashicons-phone" style="font-size:14px;width:14px;height:14px;vertical-align:middle;color:#a855f7;"></span> 
                <span dir="ltr"><?php echo esc_html( $customer->phone ); ?></span>
                &middot;
            <?php endif; ?>
            <?php printf( esc_html__( '%d ابن/ابنة', 'olama-registration' ), count( $children ) ); ?>
            <?php if ( $customer->notes ): ?>
                &middot;
                <span class="dashicons dashicons-testimonial" style="font-size:14px;width:14px;height:14px;vertical-align:middle;color:#3b82f6;"></span>
                <span><?php echo esc_html( $customer->notes ); ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="olama-reg-form-wrapper" id="olama-reg-form-wrapper" style="box-shadow: 0 4px 20px rgba(0,0,0,0.05); border-radius: 14px;">

    <!-- Toolbar -->
    <div class="olama-reg-fin-toolbar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; padding-bottom:16px; border-bottom:1px solid #f1f5f9;">
        <div class="olama-reg-field olama-reg-field--inline" style="gap:12px; align-items:center;">
            <label style="font-weight:700; white-space:nowrap; color:var(--reg-text-muted); font-size:13px;">
                <?php esc_html_e( 'العام الدراسي:', 'olama-registration' ); ?>
            </label>
            <select id="olama-reg-customer-fin-year" style="min-width:180px; padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e1;">
                <option value="0"><?php esc_html_e( 'جميع السنوات', 'olama-registration' ); ?></option>
                <?php foreach ( $academic_years as $ay ): ?>
                    <option value="<?php echo esc_attr( $ay->id ); ?>"
                            <?php selected( $ay->id, $active_year_id ); ?>>
                        <?php echo esc_html( $ay->year_name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration-custom-payments&ext_customer_id=' . $customer_id ) ); ?>" class="olama-reg-btn olama-reg-btn--primary" style="display:flex; align-items:center; gap:6px;">
            <span class="dashicons dashicons-plus"></span>
            <?php esc_html_e( 'إصدار دفعة/فاتورة مخصصة', 'olama-registration' ); ?>
        </a>
    </div>

    <!-- Summary Dashboard Cards -->
    <div class="olama-reg-dashboard-cards" style="display: flex; gap: 20px; margin-bottom: 30px;">
        <div class="olama-reg-stat-card" style="flex:1; background:#fff; padding:20px; border-radius:12px; border:1px solid #e2e8f0; text-align:center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);">
            <h4 style="margin:0; color:#64748b; font-size:14px; font-weight:600;"><?php esc_html_e('إجمالي الفواتير', 'olama-registration'); ?></h4>
            <div style="font-size:28px; font-weight:800; color:#0f172a; margin-top:10px;">
                <?php echo number_format( (float)($summary->total_invoiced ?? 0), 2 ); ?>
            </div>
        </div>
        <div class="olama-reg-stat-card" style="flex:1; background:#fff; padding:20px; border-radius:12px; border:1px solid #e2e8f0; text-align:center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);">
            <h4 style="margin:0; color:#64748b; font-size:14px; font-weight:600;"><?php esc_html_e('إجمالي المحصل', 'olama-registration'); ?></h4>
            <div style="font-size:28px; font-weight:800; color:#10b981; margin-top:10px;">
                <?php echo number_format( (float)($summary->total_paid ?? 0), 2 ); ?>
            </div>
        </div>
        <div class="olama-reg-stat-card" style="flex:1; background:#fff; padding:20px; border-radius:12px; border:1px solid #e2e8f0; text-align:center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);">
            <h4 style="margin:0; color:#64748b; font-size:14px; font-weight:600;"><?php esc_html_e('الذمم المستحقة', 'olama-registration'); ?></h4>
            <div style="font-size:28px; font-weight:800; color:#ef4444; margin-top:10px;">
                <?php echo number_format( (float)($summary->balance ?? 0), 2 ); ?>
            </div>
        </div>
    </div>

    <!-- Invoices List -->
    <div class="olama-reg-invoices-list">
        <?php if ( empty( $invoices ) ): ?>
            <div class="olama-reg-empty-state" style="padding: 40px 20px; text-align: center; border: 1px dashed #cbd5e1; border-radius: 12px; background: #fafaf9;">
                <span class="dashicons dashicons-media-document" style="font-size: 48px; width: 48px; height: 48px; color: #cbd5e1; margin-bottom: 12px;"></span>
                <p style="color:#64748b; font-size:14px; margin:0;"><?php esc_html_e( 'لا توجد فواتير مسجلة لهذا العميل.', 'olama-registration' ); ?></p>
            </div>
        <?php else: ?>
            <?php foreach ( $invoices as $inv ): 
                // Filter payments for this invoice
                $inv_payments = array_filter( $payments, fn($p) => (int)$p->invoice_id === (int)$inv->id );
                
                // Status Badge Logic
                $status_colors = [
                    'draft'     => ['bg' => '#f1f5f9', 'text' => '#475569', 'label' => 'مسودة'],
                    'issued'    => ['bg' => '#e0f2fe', 'text' => '#0369a1', 'label' => 'صادرة'],
                    'partial'   => ['bg' => '#fef3c7', 'text' => '#b45309', 'label' => 'جزئية'],
                    'paid'      => ['bg' => '#dcfce7', 'text' => '#15803d', 'label' => 'مدفوعة'],
                    'overdue'   => ['bg' => '#fee2e2', 'text' => '#b91c1c', 'label' => 'متأخرة'],
                    'cancelled' => ['bg' => '#f3f4f6', 'text' => '#374151', 'label' => 'ملغاة'],
                ];
                $st = $status_colors[ $inv->status ] ?? $status_colors['draft'];
            ?>
            <div class="olama-reg-invoice-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; margin-bottom:20px; overflow:hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.01);">
                
                <!-- Invoice Header -->
                <div style="padding:20px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; background:#fafaf9;">
                    <div>
                        <div style="font-weight:700; color:#0f172a; font-size:16px;">
                            <?php echo esc_html( $inv->invoice_number ); ?>
                        </div>
                        <div style="color:#64748b; font-size:13px; margin-top:5px;">
                            <?php echo esc_html( $inv->issue_date ); ?> 
                            <?php if($inv->due_date): ?> &middot; <?php esc_html_e('تاريخ الاستحقاق:', 'olama-registration'); ?> <?php echo esc_html($inv->due_date); ?><?php endif; ?>
                            <?php if($inv->notes): ?> &middot; <span style="color:var(--reg-primary); font-weight:600;"><?php echo esc_html($inv->notes); ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div style="text-align:left;">
                        <span style="display:inline-block; padding:4px 12px; border-radius:999px; font-size:12px; font-weight:600; background:<?php echo $st['bg']; ?>; color:<?php echo $st['text']; ?>;">
                            <?php echo esc_html( $st['label'] ); ?>
                        </span>
                        <div style="font-size:20px; font-weight:800; color:#0f172a; margin-top:5px;">
                            <?php echo number_format( (float)$inv->total, 2 ); ?> د.أ
                        </div>
                    </div>
                </div>

                <!-- Payments Nested List -->
                <div style="padding:20px;">
                    <?php if ( empty( $inv_payments ) ): ?>
                        <p style="color:#94a3b8; font-size:13px; margin:0; text-align:center; padding:10px 0;">
                            <?php esc_html_e( 'لم يتم تسجيل أي دفعات لهذه الفاتورة بعد.', 'olama-registration' ); ?>
                        </p>
                    <?php else: ?>
                        <h5 style="margin:0 0 15px 0; color:#475569; font-size:14px; font-weight:700;">
                            <span class="dashicons dashicons-money-alt" style="font-size:16px; margin-top:0; color:#10b981; vertical-align:middle; margin-left:4px;"></span>
                            <?php esc_html_e( 'سجل الدفعات', 'olama-registration' ); ?>
                        </h5>
                        <table style="width:100%; border-collapse:collapse; font-size:13px;">
                            <thead>
                                <tr style="border-bottom:1px solid #e2e8f0; background: #f8fafc;">
                                    <th style="padding:10px 8px; text-align:right; color:#64748b; font-weight:600;"><?php esc_html_e('رقم السند', 'olama-registration'); ?></th>
                                    <th style="padding:10px 8px; text-align:right; color:#64748b; font-weight:600;"><?php esc_html_e('تاريخ القبض', 'olama-registration'); ?></th>
                                    <th style="padding:10px 8px; text-align:right; color:#64748b; font-weight:600;"><?php esc_html_e('طريقة الدفع', 'olama-registration'); ?></th>
                                    <th style="padding:10px 8px; text-align:left; color:#64748b; font-weight:600;"><?php esc_html_e('المبلغ', 'olama-registration'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $inv_payments as $pay ): 
                                    $method_label = match($pay->method) {
                                        'cash' => 'نقدي', 'bank_transfer' => 'حوالة', 'cheque' => 'شيك', 'online' => 'إلكتروني', default => 'أخرى'
                                    };
                                ?>
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:10px 8px;">#<?php echo esc_html($pay->id); ?></td>
                                    <td style="padding:10px 8px;"><?php echo esc_html($pay->payment_date); ?></td>
                                    <td style="padding:10px 8px;">
                                        <span style="display:inline-block; background:#f0fdf4; color:#166534; padding:2px 8px; border-radius:4px; font-size:11px;">
                                            <?php echo esc_html($method_label); ?>
                                        </span>
                                        <?php if($pay->reference): ?>
                                            <div style="font-size:11px; color:#94a3b8; margin-top:2px;"><?php echo esc_html($pay->reference); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 8px; text-align:left; font-weight:700; color:#10b981; display:flex; justify-content:space-between; align-items:center;">
                                        <span><?php echo number_format((float)$pay->amount, 2); ?> د.أ</span>
                                        <?php if ( (float)$pay->amount > 0 && $pay->method !== 'reversal' ): ?>
                                            <button class="button button-small olama-reg-reverse-payment-btn"
                                                    data-id="<?php echo esc_attr( $pay->id ); ?>"
                                                    title="<?php esc_attr_e( 'عكس السند', 'olama-registration' ); ?>"
                                                    style="color:#c62828; border:none; background:none; padding:0; cursor:pointer; margin-right:8px;">
                                                <span class="dashicons dashicons-undo" style="font-size:16px; width:16px; height:16px;"></span>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <!-- Invoice Footer Actions -->
                <div style="padding:12px 20px; background:#f8fafc; border-top:1px solid #f1f5f9; display:flex; justify-content:flex-end; gap:10px;">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration-invoices&action=view&id=' . $inv->id ) ); ?>" class="olama-reg-btn" style="background:#fff; border:1px solid #cbd5e1; color:#475569; display:inline-flex; align-items:center; gap:4px; text-decoration:none;">
                        <span class="dashicons dashicons-visibility"></span> <?php esc_html_e('عرض الفاتورة', 'olama-registration'); ?>
                    </a>
                    <button type="button" class="olama-reg-btn olama-reg-btn--primary olama-reg-pay-invoice-trigger"
                            data-id="<?php echo esc_attr( $inv->id ); ?>"
                            data-no="<?php echo esc_attr( $inv->invoice_number ); ?>"
                            data-bal="<?php echo esc_attr( $inv->balance ); ?>"
                            data-family="<?php echo esc_attr( $customer_uid ); ?>"
                            style="display:inline-flex; align-items:center; gap:4px; text-decoration:none; cursor:pointer;">
                        <span class="dashicons dashicons-plus"></span> <?php esc_html_e('تسجيل دفعة', 'olama-registration'); ?>
                    </button>
                    <?php if ( (float)$inv->amount_paid == 0 && $inv->status !== 'cancelled' ): ?>
                        <button class="olama-reg-btn olama-reg-cancel-invoice-btn" data-id="<?php echo esc_attr( $inv->id ); ?>" style="background:#fff; border:1px solid #fca5a5; color:#dc2626; display:inline-flex; align-items:center; gap:4px; cursor:pointer;">
                            <span class="dashicons dashicons-dismiss"></span> <?php esc_html_e('إلغاء الفاتورة', 'olama-registration'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- Year Select Page Reload Handler -->
<script>
jQuery(document).ready(function($) {
    $('#olama-reg-customer-fin-year').on('change', function() {
        const yearId = $(this).val();
        const url = new URL(window.location.href);
        if (parseInt(yearId) > 0) {
            url.searchParams.set('academic_year_id', yearId);
        } else {
            url.searchParams.delete('academic_year_id');
        }
        window.location.href = url.toString();
    });
});
</script>
