<?php
/**
 * Dashboard modal for creating settlement receipts.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$custom_services = get_option( 'olama_reg_custom_services', ['دوسية', 'نشاط', 'مواصلات', 'امتحان إضافي'] );
?>

<div id="olama-reg-settlement-modal" class="olama-reg-modal olama-reg-wrap" style="display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(26,26,46,0.4); backdrop-filter:blur(4px);">
    <div class="olama-reg-modal-dialog" style="max-width:550px; width:90%;">
        <div class="olama-reg-modal-header">
            <h2 class="olama-reg-modal-title">
                <span class="dashicons dashicons-clipboard"></span>
                <?php esc_html_e( 'إنشاء إيصال تسوية جديد', 'olama-registration' ); ?>
            </h2>
            <button type="button" class="olama-reg-modal-close">&times;</button>
        </div>

        <form id="olama-reg-settlement-form" style="margin:0;">
            <div class="olama-reg-modal-body">
                <input type="hidden" name="family_id" id="settlement_family_id">

                <div class="olama-reg-section">
                    <h3 class="olama-reg-section-title"><?php esc_html_e( 'تفاصيل إيصال التسوية', 'olama-registration' ); ?></h3>
                    <div class="olama-reg-grid" style="grid-template-columns: 1fr; gap:16px; padding:16px;">
                        <div class="olama-reg-field olama-reg-field--required">
                            <label for="settlement_family_search"><?php esc_html_e( 'العائلة', 'olama-registration' ); ?></label>
                            <select id="settlement_family_search" style="width:100%;" required></select>
                        </div>

                        <div class="olama-reg-field olama-reg-field--required">
                            <label for="settlement_category"><?php esc_html_e( 'قائمة الخدمات', 'olama-registration' ); ?></label>
                            <select id="settlement_category" name="payment_category" required style="width:100%;">
                                <option value=""><?php esc_html_e( '-- اختر --', 'olama-registration' ); ?></option>
                                <?php foreach ($custom_services as $srv) : ?>
                                    <option value="<?php echo esc_attr($srv); ?>"><?php echo esc_html($srv); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="olama-reg-field olama-reg-field--required">
                            <label for="settlement_amount"><?php esc_html_e( 'المبلغ (د.أ)', 'olama-registration' ); ?></label>
                            <input type="number" id="settlement_amount" name="original_amount" step="0.01" min="0.01" required style="width:100%; border: 1.5px solid #E0C090; border-radius: 6px; padding: 8px;">
                        </div>

                        <div class="olama-reg-field olama-reg-field--required">
                            <label for="settlement_method"><?php esc_html_e( 'طريقة الدفع', 'olama-registration' ); ?></label>
                            <select id="settlement_method" name="payment_method" style="width:100%;">
                                <option value="cash"><?php esc_html_e( 'نقدي (Cash)', 'olama-registration' ); ?></option>
                                <option value="card"><?php esc_html_e( 'بطاقة (Card)', 'olama-registration' ); ?></option>
                                <option value="transfer"><?php esc_html_e( 'تحويل بنكي (Transfer)', 'olama-registration' ); ?></option>
                            </select>
                        </div>

                        <div class="olama-reg-field">
                            <label for="settlement_notes"><?php esc_html_e( 'ملاحظات', 'olama-registration' ); ?></label>
                            <textarea id="settlement_notes" name="notes" rows="3" style="width:100%; border:1.5px solid #E0C090; border-radius:6px; padding:8px; font-family:inherit;" placeholder="<?php esc_attr_e( 'أدخل أي ملاحظات إضافية هنا...', 'olama-registration' ); ?>"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="olama-reg-form-actions">
                <button type="submit" class="olama-reg-btn olama-reg-btn--primary" id="olama-reg-save-settlement-btn">
                    <?php esc_html_e( 'حفظ وإصدار الإيصال', 'olama-registration' ); ?>
                </button>
                <button type="button" class="button button-large olama-reg-modal-close"><?php esc_html_e( 'إلغاء', 'olama-registration' ); ?></button>
            </div>
        </form>
    </div>
</div>
