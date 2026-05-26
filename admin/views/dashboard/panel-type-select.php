<?php
/**
 * Hub Stage 1 — Type Selection Panel
 *
 * Presents two card buttons:
 *   - Family (Enrolled)    → keyboard shortcut: F
 *   - Individual (Walk-in) → keyboard shortcut: E
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="os-hub-type-screen">

    <div class="os-hub-type-screen__header">
        <h2 class="os-hub-type-screen__title">
            <?php _e( 'حدّد نوع العميل', 'olama-registration' ); ?>
        </h2>
        <p class="os-hub-type-screen__subtitle">
            <?php _e( 'اختر نوع العميل للبدء بالبحث وإدارة الخدمات', 'olama-registration' ); ?>
        </p>
    </div>

    <div class="os-hub-type-cards" role="group" aria-label="<?php esc_attr_e( 'نوع العميل', 'olama-registration' ); ?>">

        <!-- Family Card -->
        <div class="os-hub-type-card os-hub-type-card--family"
             role="button"
             tabindex="0"
             aria-pressed="false"
             data-type="family"
             id="os-hub-type-family"
             title="<?php esc_attr_e( 'اختصار: F', 'olama-registration' ); ?>">
            <div class="os-hub-type-card__icon-wrap">
                <span class="dashicons dashicons-groups" aria-hidden="true"></span>
            </div>
            <div class="os-hub-type-card__body">
                <span class="os-hub-type-card__label"><?php _e( 'عائلة مسجّلة', 'olama-registration' ); ?></span>
                <span class="os-hub-type-card__desc"><?php _e( 'عائلات الطلاب المسجلين في المدرسة', 'olama-registration' ); ?></span>
            </div>
            <kbd class="os-hub-type-card__shortcut" aria-hidden="true">F</kbd>
        </div>

        <!-- Walk-in / External Customer Card -->
        <div class="os-hub-type-card os-hub-type-card--external"
             role="button"
             tabindex="0"
             aria-pressed="false"
             data-type="external"
             id="os-hub-type-external"
             title="<?php esc_attr_e( 'اختصار: E', 'olama-registration' ); ?>">
            <div class="os-hub-type-card__icon-wrap">
                <span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
            </div>
            <div class="os-hub-type-card__body">
                <span class="os-hub-type-card__label"><?php _e( 'عميل خارجي', 'olama-registration' ); ?></span>
                <span class="os-hub-type-card__desc"><?php _e( 'عملاء المراجعة والخدمات الخارجية', 'olama-registration' ); ?></span>
            </div>
            <kbd class="os-hub-type-card__shortcut" aria-hidden="true">E</kbd>
        </div>

    </div><!-- .os-hub-type-cards -->

    <p class="os-hub-type-screen__hint">
        <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
        <?php _e( 'يمكنك الضغط على <kbd>F</kbd> للعائلات أو <kbd>E</kbd> للعملاء الخارجيين', 'olama-registration' ); ?>
    </p>

</div><!-- .os-hub-type-screen -->
