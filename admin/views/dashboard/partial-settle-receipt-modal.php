<?php
/**
 * Dashboard modal for reconciling a settlement receipt.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>

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
