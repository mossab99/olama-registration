<?php
/**
 * Admin View: Settlement Receipts
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Handle filters
$filter_status = sanitize_text_field( $_GET['filter_status'] ?? '' );
$filter_family = sanitize_text_field( $_GET['filter_family'] ?? '' );

$args = [];
if ( $filter_status ) $args['status'] = $filter_status;
if ( $filter_family ) $args['family_id'] = $filter_family;

$receipts = Olama_Reg_Settlement::get_receipts( $args );
?>

<div class="wrap olama-reg-wrap" dir="rtl">
    <div class="olama-reg-page-header">
        <h1>
            <span class="dashicons dashicons-clipboard"></span>
            <?php esc_html_e( 'إيصالات التسوية', 'olama-registration' ); ?>
        </h1>
        <div style="display:flex; gap:12px; align-items:center;">
            <button type="button" class="olama-reg-btn olama-reg-btn--primary" id="btn-new-settlement">
                <span class="dashicons dashicons-plus"></span>
                <?php esc_html_e( 'إضافة إيصال جديد', 'olama-registration' ); ?>
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="olama-reg-filter-bar">
        <form method="get" class="olama-reg-filter-form">
            <input type="hidden" name="page" value="olama-registration-settlements">
            
            <div class="olama-reg-filter-group">
                <label><?php esc_html_e( 'الحالة', 'olama-registration' ); ?></label>
                <select name="filter_status" class="olama-reg-filter-input">
                    <option value=""><?php esc_html_e( 'الكل', 'olama-registration' ); ?></option>
                    <option value="pending_settlement" <?php selected( $filter_status, 'pending_settlement' ); ?>><?php esc_html_e( 'بانتظار التسوية', 'olama-registration' ); ?></option>
                    <option value="settled" <?php selected( $filter_status, 'settled' ); ?>><?php esc_html_e( 'تمت التسوية', 'olama-registration' ); ?></option>
                    <option value="cancelled" <?php selected( $filter_status, 'cancelled' ); ?>><?php esc_html_e( 'ملغي', 'olama-registration' ); ?></option>
                </select>
            </div>

            <div class="olama-reg-filter-group">
                <label><?php esc_html_e( 'رقم العائلة', 'olama-registration' ); ?></label>
                <input type="text" name="filter_family" value="<?php echo esc_attr( $filter_family ); ?>" placeholder="رقم العائلة" class="olama-reg-filter-input">
            </div>

            <div class="olama-reg-filter-group">
                <button type="submit" class="olama-reg-btn olama-reg-btn--primary">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e( 'تصفية', 'olama-registration' ); ?>
                </button>
                <a href="?page=olama-registration-settlements" class="olama-reg-btn">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e( 'مسح', 'olama-registration' ); ?>
                </a>
            </div>
        </form>
    </div>

    <!-- List -->
    <div class="olama-reg-section">
        <h3 class="olama-reg-section-title">
            <span class="dashicons dashicons-list-view"></span>
            <?php esc_html_e( 'قائمة إيصالات التسوية', 'olama-registration' ); ?>
        </h3>
        <div class="olama-reg-table-wrap">
            <table class="olama-reg-fin-table">
        <thead>
            <tr>
                <th style="width: 120px;"><?php esc_html_e( 'رقم الإيصال', 'olama-registration' ); ?></th>
                <th style="width: 100px;"><?php esc_html_e( 'رقم العائلة', 'olama-registration' ); ?></th>
                <th><?php esc_html_e( 'اسم العائلة', 'olama-registration' ); ?></th>
                <th><?php esc_html_e( 'قائمة الخدمات', 'olama-registration' ); ?></th>
                <th><?php esc_html_e( 'المبلغ', 'olama-registration' ); ?></th>
                <th><?php esc_html_e( 'المتبقي', 'olama-registration' ); ?></th>
                <th><?php esc_html_e( 'التاريخ', 'olama-registration' ); ?></th>
                <th><?php esc_html_e( 'الحالة', 'olama-registration' ); ?></th>
                <th style="width: 250px;"><?php esc_html_e( 'إجراءات', 'olama-registration' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $receipts ) ) : ?>
                <tr><td colspan="9"><?php esc_html_e( 'لا توجد إيصالات.', 'olama-registration' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $receipts as $receipt ) : ?>
                    <tr class="status-<?php echo esc_attr( $receipt->status ); ?>" <?php if ( $receipt->status === 'pending_settlement' ) echo 'style="background-color: #fff8e5;"'; ?>>
                        <td><strong><?php echo esc_html( $receipt->receipt_number ); ?></strong></td>
                        <td><span class="olama-reg-uid-badge"><?php echo esc_html( $receipt->family_id ); ?></span></td>
                        <td><?php echo esc_html( trim( ($receipt->father_first_name ?? '') . ' ' . ($receipt->father_family_name ?? '') ) ); ?></td>
                        <td><?php echo esc_html( $receipt->payment_category ); ?></td>
                        <td><?php echo number_format( $receipt->original_amount, 2 ); ?></td>
                        <td><?php echo number_format( $receipt->remaining_balance, 2 ); ?></td>
                        <td><?php echo esc_html( date_i18n( get_option('date_format'), strtotime($receipt->created_at) ) ); ?></td>
                        <td>
                            <?php
                            if ( $receipt->status === 'pending_settlement' ) echo '<span style="color:#d63638;font-weight:bold;">بانتظار التسوية</span>';
                            elseif ( $receipt->status === 'settled' ) echo '<span style="color:#00a32a;font-weight:bold;">تمت التسوية</span>';
                            elseif ( $receipt->status === 'cancelled' ) echo '<span style="color:#8c8f94;">ملغي</span>';
                            else echo esc_html( Olama_Reg_Status_Labels::label( $receipt->status, 'settlement' ) );
                            ?>
                        </td>
                        <td>
                            <a href="?page=olama-registration-settlements&action=print&id=<?php echo $receipt->id; ?>" target="_blank" class="button button-small"><?php esc_html_e( 'طباعة', 'olama-registration' ); ?></a>
                            
                            <?php if ( $receipt->status === 'pending_settlement' ) : ?>
                                <button type="button" class="button button-small button-primary btn-settle-receipt" data-id="<?php echo $receipt->id; ?>" data-amount="<?php echo $receipt->original_amount; ?>"><?php esc_html_e( 'تسوية', 'olama-registration' ); ?></button>
                                <button type="button" class="button button-small btn-cancel-receipt" data-id="<?php echo $receipt->id; ?>" style="color: #d63638; border-color: #d63638;"><?php esc_html_e( 'إلغاء', 'olama-registration' ); ?></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: New Receipt -->
<div id="modal-new-settlement" class="olama-reg-modal olama-reg-wrap" style="display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(26,26,46,0.4); backdrop-filter:blur(4px);" dir="rtl">
    <div class="olama-reg-modal-dialog" style="max-width:550px; width:90%;">
        <div class="olama-reg-modal-header">
            <h2 class="olama-reg-modal-title">
                <span class="dashicons dashicons-clipboard"></span>
                <?php esc_html_e( 'إنشاء إيصال تسوية جديد', 'olama-registration' ); ?>
            </h2>
            <button type="button" class="olama-reg-modal-close btn-close-modal">&times;</button>
        </div>
        <form id="form-new-settlement">
            <div class="olama-reg-modal-body">
                <div class="olama-reg-section" style="border:none; box-shadow:none; margin:0; padding:0;">
                    <div class="olama-reg-grid" style="grid-template-columns: 1fr; gap:16px;">
                        <div class="olama-reg-field olama-reg-field--required">
                            <label><?php esc_html_e( 'العائلة', 'olama-registration' ); ?></label>
                            <select name="family_id" class="olama-reg-family-search" style="width:100%;" required>
                                <option value="">-- ابحث بالاسم أو رقم العائلة --</option>
                            </select>
                        </div>
                        <div class="olama-reg-field olama-reg-field--required">
                            <label><?php esc_html_e( 'قائمة الخدمات', 'olama-registration' ); ?></label>
                            <select name="payment_category" required style="width:100%;">
                                <option value=""><?php esc_html_e( '-- اختر --', 'olama-registration' ); ?></option>
                                <?php
                                $custom_services = get_option('olama_reg_custom_services', ['دوسية', 'نشاط', 'مواصلات', 'امتحان إضافي']);
                                foreach ($custom_services as $srv) {
                                    echo '<option value="' . esc_attr($srv) . '">' . esc_html($srv) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="olama-reg-field olama-reg-field--required">
                            <label><?php esc_html_e( 'المبلغ', 'olama-registration' ); ?></label>
                            <input type="number" name="original_amount" step="0.01" min="0.01" required style="width:100%;">
                        </div>
                        <div class="olama-reg-field olama-reg-field--required">
                            <label><?php esc_html_e( 'طريقة الدفع', 'olama-registration' ); ?></label>
                            <select name="payment_method" style="width:100%;">
                                <option value="cash">نقدي (Cash)</option>
                                <option value="card">بطاقة (Card)</option>
                                <option value="transfer">تحويل بنكي (Transfer)</option>
                            </select>
                        </div>
                        <div class="olama-reg-field">
                            <label><?php esc_html_e( 'ملاحظات', 'olama-registration' ); ?></label>
                            <textarea name="notes" rows="3" style="width:100%;"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="olama-reg-form-actions">
                <button type="submit" class="olama-reg-btn olama-reg-btn--primary"><?php esc_html_e( 'حفظ وإصدار الإيصال', 'olama-registration' ); ?></button>
                <button type="button" class="button button-large btn-close-modal"><?php esc_html_e( 'إغلاق', 'olama-registration' ); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Settle Receipt -->
<div id="modal-settle-receipt" class="olama-reg-modal olama-reg-wrap" style="display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(26,26,46,0.4); backdrop-filter:blur(4px);" dir="rtl">
    <div class="olama-reg-modal-dialog" style="max-width:550px; width:90%;">
        <div class="olama-reg-modal-header">
            <h2 class="olama-reg-modal-title">
                <span class="dashicons dashicons-clipboard"></span>
                <?php esc_html_e( 'تسوية الإيصال في النظام', 'olama-registration' ); ?>
            </h2>
            <button type="button" class="olama-reg-modal-close btn-close-modal">&times;</button>
        </div>
        <form id="form-settle-receipt">
            <div class="olama-reg-modal-body">
                <input type="hidden" name="id" id="settle-receipt-id">
                <div class="olama-reg-section" style="border:none; box-shadow:none; margin:0; padding:0;">
                    <div class="olama-reg-grid" style="grid-template-columns: 1fr; gap:16px;">
                        <div class="olama-reg-field">
                            <label><?php esc_html_e( 'مبلغ التسوية', 'olama-registration' ); ?></label>
                            <input type="text" id="settle-amount-display" readonly style="background:#f1f1f1; width:100%;">
                        </div>
                        <div class="olama-reg-field olama-reg-field--required">
                            <label><?php esc_html_e( 'رقم الإيصال (أوراكل)', 'olama-registration' ); ?></label>
                            <input type="text" name="oracle_receipt_number" required style="width:100%;">
                        </div>
                        <div class="olama-reg-field">
                            <label><?php esc_html_e( 'ملاحظات المحاسب', 'olama-registration' ); ?></label>
                            <textarea name="notes" rows="3" style="width:100%;"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="olama-reg-form-actions">
                <button type="submit" class="olama-reg-btn olama-reg-btn--primary"><?php esc_html_e( 'تأكيد التسوية', 'olama-registration' ); ?></button>
                <button type="button" class="button button-large btn-close-modal"><?php esc_html_e( 'إغلاق', 'olama-registration' ); ?></button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Hide modals initially (prevent flash on some browsers if CSS takes time)
    $('.olama-reg-modal').hide();

    // Open New Receipt Modal
    $('#btn-new-settlement').on('click', function(e) {
        e.preventDefault();
        $('#modal-new-settlement').css('display', 'flex').hide().fadeIn();
        
        // Initialize Select2 with AJAX
        if ($.fn.select2) {
            var $select = $('.olama-reg-family-search');
            if (!$select.hasClass('select2-hidden-accessible')) {
                $select.select2({
                    dir: 'rtl',
                    width: '100%',
                    dropdownParent: $('#modal-new-settlement'),
                    ajax: {
                        url: typeof ajaxurl !== 'undefined' ? ajaxurl : (typeof olamaReg !== 'undefined' ? olamaReg.ajaxurl : ''),
                        dataType: 'json',
                        delay: 250,
                        type: 'POST',
                        data: function (params) {
                            return {
                                action: 'olama_reg_search',
                                nonce: typeof olamaReg !== 'undefined' ? olamaReg.nonce : '',
                                q: params.term
                            };
                        },
                        processResults: function (data) {
                            if (data.success && data.data.families) {
                                return {
                                    results: data.data.families.map(function(f) {
                                        const name1 = f.father_first_name || '';
                                        const name2 = f.father_family_name || '';
                                        const fullName = (name1 + ' ' + name2).trim() || 'بدون اسم';
                                        return {
                                            id: f.family_uid,
                                            text: `${fullName} (${f.family_uid})`
                                        };
                                    })
                                };
                            }
                            return { results: [] };
                        },
                        cache: true
                    },
                    placeholder: 'ابحث بالاسم أو رقم العائلة...',
                    minimumInputLength: 2
                });
            }
        }
    });

    // Close Modals
    $('.btn-close-modal').on('click', function() {
        $(this).closest('.olama-reg-modal').fadeOut();
    });

    // Submit New Receipt
    $('#form-new-settlement').on('submit', function(e) {
        e.preventDefault();
        var data = $(this).serialize() + '&action=olama_reg_create_settlement&nonce=' + olamaReg.nonce;
        var btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).text('جاري الحفظ...');

        $.post(olamaReg.ajaxurl, data, function(res) {
            if (res.success) {
                alert(res.data.message);
                window.location.reload();
            } else {
                alert(res.data.message || 'حدث خطأ');
                btn.prop('disabled', false).text('حفظ وإصدار الإيصال');
            }
        });
    });

    // Open Settle Modal
    $('.btn-settle-receipt').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var amount = $(this).data('amount');
        $('#settle-receipt-id').val(id);
        $('#settle-amount-display').val(amount);
        $('#modal-settle-receipt').css('display', 'flex').hide().fadeIn();
    });

    // Submit Settlement
    $('#form-settle-receipt').on('submit', function(e) {
        e.preventDefault();
        var data = $(this).serialize() + '&action=olama_reg_settle_receipt&nonce=' + olamaReg.nonce;
        var btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).text('جاري الحفظ...');

        $.post(olamaReg.ajaxurl, data, function(res) {
            if (res.success) {
                alert(res.data.message);
                window.location.reload();
            } else {
                alert(res.data.message || 'حدث خطأ');
                btn.prop('disabled', false).text('تأكيد التسوية');
            }
        });
    });

    // Cancel Receipt
    $('.btn-cancel-receipt').on('click', function(e) {
        e.preventDefault();
        if (!confirm('هل أنت متأكد من إلغاء هذا الإيصال؟')) return;

        var id = $(this).data('id');
        $.post(olamaReg.ajaxurl, {
            action: 'olama_reg_cancel_settlement',
            nonce: olamaReg.nonce,
            id: id
        }, function(res) {
            if (res.success) {
                alert(res.data.message);
                window.location.reload();
            } else {
                alert(res.data.message || 'حدث خطأ');
            }
        });
    });
});
</script>
