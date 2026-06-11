<?php
/**
 * Shared original admin form host for the customer hub.
 *
 * The hub loads first-party admin forms into this shell instead of keeping
 * dashboard-only copies of those workflows.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="os-hub-original-form-modal" class="olama-reg-modal olama-reg-wrap" style="display:none; position:fixed; z-index:99999; inset:0; overflow:auto; background-color:rgba(26,26,46,0.4); backdrop-filter:blur(4px);">
    <div class="olama-reg-modal-dialog" style="max-width:1250px; width:95%; margin:32px auto;">
        <div class="olama-reg-modal-header">
            <h2 class="olama-reg-modal-title" id="os-hub-original-form-title">
                <span class="dashicons dashicons-admin-generic"></span>
                <?php esc_html_e( 'نموذج الخدمة', 'olama-registration' ); ?>
            </h2>
            <button type="button" class="olama-reg-modal-close" aria-label="<?php esc_attr_e( 'إغلاق', 'olama-registration' ); ?>">&times;</button>
        </div>
        <div class="olama-reg-modal-body" id="os-hub-original-form-content" style="padding:0; max-height:calc(100vh - 150px); overflow:auto;">
            <div class="os-hub-modal-loading" style="padding:32px; text-align:center;">
                <span class="spinner is-active" style="float:none;"></span>
            </div>
        </div>
    </div>
</div>
