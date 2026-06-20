<?php
/**
 * Hub Stage 2 — Customer Lookup Panel
 *
 * Search input shell. AJAX wiring happens in Phase 2 (os-hub.js SearchModule).
 * The panel shows:
 *   - Back link to Stage 1
 *   - Current type badge
 *   - Search input + button
 *   - Results listbox (populated by JS)
 *   - Recent lookups section (populated from localStorage by JS)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="os-hub-lookup-screen">

    <!-- Back navigation -->
    <div class="os-hub-lookup-screen__nav" style="display:flex; justify-content:space-between; align-items:center; width:100%;">
        <div style="display:flex; gap:12px; align-items:center;">
            <button type="button"
                    class="button os-hub-back-btn"
                    id="os-hub-back-to-type"
                    aria-label="<?php esc_attr_e( 'العودة لاختيار نوع العميل', 'olama-registration' ); ?>">
                <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
                <?php _e( 'تغيير نوع العميل', 'olama-registration' ); ?>
            </button>

            <span class="os-hub-type-badge" id="os-hub-current-type-badge" aria-live="polite">
                <!-- Populated by JS: shows "عائلة" or "عميل خارجي" badge -->
            </span>
        </div>

        <button type="button"
                id="family_btn_add_new"
                class="button button-primary os-hub-add-family-btn"
                style="display:none; background:#2563eb; border-color:#2563eb; color:#fff; align-items:center; gap:6px; font-weight:bold; height:34px; padding:0 16px; border-radius:6px; border:none; cursor:pointer;">
            <span class="dashicons dashicons-plus" style="font-size:16px; width:16px; height:16px; margin-top:3px;"></span>
            <?php _e( 'إضافة عائلة جديدة', 'olama-registration' ); ?>
        </button>

        <!-- Add New Customer Button (Visible only when currentType is 'external') -->
        <button type="button"
                id="cust_btn_add_new"
                class="button button-primary os-hub-add-cust-btn"
                style="display:none; background:#d97706; border-color:#d97706; color:#fff; align-items:center; gap:6px; font-weight:bold; height:34px; padding:0 16px; border-radius:6px; border:none; cursor:pointer;">
            <span class="dashicons dashicons-plus" style="font-size:16px; width:16px; height:16px; margin-top:3px;"></span>
            <?php _e( 'إضافة عميل جديد', 'olama-registration' ); ?>
        </button>
    </div>

    <!-- Search form -->
    <div class="os-hub-search-wrap">
        <div class="os-hub-search-box" role="search">
            <span class="dashicons dashicons-search os-hub-search-icon" aria-hidden="true"></span>
            <input type="search"
                   id="os-hub-search-input"
                   class="os-hub-search-input"
                   placeholder="<?php esc_attr_e( 'ابحث باسم العائلة أو رقم الملف أو رقم الهاتف...', 'olama-registration' ); ?>"
                   autocomplete="off"
                   aria-label="<?php esc_attr_e( 'البحث عن عميل', 'olama-registration' ); ?>"
                   aria-controls="os-hub-results-list"
                   aria-autocomplete="list"
                   dir="rtl" />
            <button type="button"
                    id="os-hub-search-btn"
                    class="button button-primary os-hub-search-btn"
                    aria-label="<?php esc_attr_e( 'بحث', 'olama-registration' ); ?>">
                <?php _e( 'بحث', 'olama-registration' ); ?>
            </button>
        </div>

        <!-- Search status / spinner -->
        <div class="os-hub-search-status" id="os-hub-search-status" aria-live="polite" role="status">
            <!-- Populated by JS: spinner, "no results", error messages -->
        </div>

        <!-- Results list -->
        <ul id="os-hub-results-list"
            class="os-hub-results"
            role="listbox"
            aria-label="<?php esc_attr_e( 'نتائج البحث', 'olama-registration' ); ?>"
            tabindex="-1">
            <!-- Populated by JS -->
        </ul>
    </div><!-- .os-hub-search-wrap -->

    <!-- Recent lookups (populated from localStorage by JS) -->
    <div class="os-hub-recent" id="os-hub-recent-section" aria-label="<?php esc_attr_e( 'آخر عمليات البحث', 'olama-registration' ); ?>">
        <h3 class="os-hub-recent__title">
            <span class="dashicons dashicons-backup" aria-hidden="true"></span>
            <?php _e( 'آخر عمليات البحث', 'olama-registration' ); ?>
        </h3>
        <ul class="os-hub-recent__list" id="os-hub-recent-list" role="list">
            <!-- Populated by JS from localStorage -->
        </ul>
    </div>

</div><!-- .os-hub-lookup-screen -->

<div id="family-modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:99999; justify-content:center; align-items:flex-start; overflow-y:auto; padding:32px 12px;" dir="rtl">
    <div style="background:#fff; width:min(640px, 96vw); border-radius:8px; box-shadow:0 20px 60px rgba(15,23,42,0.28); overflow:hidden; margin:auto;">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; background:#1f2937; color:#fff; padding:16px 20px;">
            <h2 style="margin:0; font-size:18px; color:#fff; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-groups"></span>
                <?php _e( 'إضافة عائلة جديدة', 'olama-registration' ); ?>
            </h2>
            <button type="button" id="family-modal-close" class="button-link" style="color:#fff; font-size:24px; text-decoration:none; line-height:1;">&times;</button>
        </div>

        <form id="family-modal-form" style="padding:20px;">
            <div id="family-modal-notice" style="display:none; margin-bottom:14px; padding:10px 12px; border-radius:6px; font-weight:700;"></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label for="family-modal-uid" style="display:block; font-weight:700; margin-bottom:6px;"><?php _e( 'رقم العائلة', 'olama-registration' ); ?></label>
                    <input type="text" id="family-modal-uid" name="family_uid" class="widefat" placeholder="<?php esc_attr_e( 'اتركه فارغاً للترقيم التلقائي', 'olama-registration' ); ?>">
                </div>
                <div>
                    <label for="family-modal-name" style="display:block; font-weight:700; margin-bottom:6px;"><?php _e( 'اسم العائلة', 'olama-registration' ); ?></label>
                    <input type="text" id="family-modal-name" name="family_name" class="widefat" required>
                </div>
                <div>
                    <label for="family-modal-father-mobile" style="display:block; font-weight:700; margin-bottom:6px;"><?php _e( 'جوال الأب', 'olama-registration' ); ?></label>
                    <input type="text" id="family-modal-father-mobile" name="father_mobile" class="widefat">
                </div>
                <div>
                    <label for="family-modal-mother-mobile" style="display:block; font-weight:700; margin-bottom:6px;"><?php _e( 'جوال الأم', 'olama-registration' ); ?></label>
                    <input type="text" id="family-modal-mother-mobile" name="mother_mobile" class="widefat">
                </div>
            </div>
            <div style="margin-bottom:18px;">
                <label for="family-modal-address" style="display:block; font-weight:700; margin-bottom:6px;"><?php _e( 'العنوان', 'olama-registration' ); ?></label>
                <textarea id="family-modal-address" name="address" class="widefat" rows="3"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-start; gap:8px;">
                <button type="submit" class="button button-primary" id="family-modal-save"><?php _e( 'حفظ العائلة', 'olama-registration' ); ?></button>
                <button type="button" class="button" id="family-modal-cancel"><?php _e( 'إلغاء', 'olama-registration' ); ?></button>
            </div>
        </form>
    </div>
</div>
