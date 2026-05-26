<?php
/**
 * Hub Stage 3 — Service Hub Panel Shell
 *
 * Phase 4 additions:
 *   - Academic Year selector dropdown in identity header
 *   - Quick action links (New Invoice, New Agreement)
 *   - Shortcut number hints (1–8) on tile buttons
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$tiles = [
    'profile'     => [
        'icon'  => 'dashicons-admin-users',
        'color' => '#2271b1',
        'label' => __( 'بيانات العميل', 'olama-registration' ),
        'desc'  => __( 'المعلومات الأساسية والتواصل', 'olama-registration' ),
        'always'=> true,
    ],
    'agreements'  => [
        'icon'  => 'dashicons-media-document',
        'color' => '#00a32a',
        'label' => __( 'العقود', 'olama-registration' ),
        'desc'  => __( 'عقود الخدمات والاتفاقيات', 'olama-registration' ),
        'always'=> true,
    ],
    'invoices'    => [
        'icon'  => 'dashicons-media-text',
        'color' => '#d63638',
        'label' => __( 'الفواتير', 'olama-registration' ),
        'desc'  => __( 'الفواتير المالية والمستحقات', 'olama-registration' ),
        'always'=> true,
    ],
    'payments'    => [
        'icon'  => 'dashicons-money-alt',
        'color' => '#3582c4',
        'label' => __( 'السندات والمدفوعات', 'olama-registration' ),
        'desc'  => __( 'سجلات السداد والإيصالات', 'olama-registration' ),
        'always'=> true,
    ],
    'children'    => [
        'icon'  => 'dashicons-groups',
        'color' => '#7b68ee',
        'label' => __( 'الطلاب والأبناء', 'olama-registration' ),
        'desc'  => __( 'الطلاب المسجلون والأبناء', 'olama-registration' ),
        'always'=> true,
    ],
    'financial'   => [
        'icon'  => 'dashicons-chart-bar',
        'color' => '#d09b00',
        'label' => __( 'الملخص المالي', 'olama-registration' ),
        'desc'  => __( 'الإجماليات والتقرير السنوي', 'olama-registration' ),
        'always'=> true,
    ],
    'history'     => [
        'icon'  => 'dashicons-backup',
        'color' => '#646970',
        'label' => __( 'سجل العمليات', 'olama-registration' ),
        'desc'  => __( 'تدقيق الحركات المالية', 'olama-registration' ),
        'always'=> true,
    ],
    'settlements' => [
        'icon'  => 'dashicons-bank',
        'color' => '#1a3a5c',
        'label' => __( 'إيصالات التسوية', 'olama-registration' ),
        'desc'  => __( 'التسوية السنوية ونظام Oracle', 'olama-registration' ),
        'always'=> false, // families only — JS hides/shows
    ],
];
?>
<div class="os-hub-service-screen">

    <!-- ── Identity Header ─────────────────────────────────────────────────── -->
    <div class="os-hub-identity" id="os-hub-identity" aria-live="polite">

        <div class="os-hub-identity__avatar">
            <span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
        </div>

        <div class="os-hub-identity__info">
            <div class="os-hub-identity__name" id="os-hub-identity-name">—</div>
            <div class="os-hub-identity__meta" id="os-hub-identity-meta">—</div>
        </div>

        <!-- Phase 4: Quick action links (shown after customer loads) -->
        <div class="os-hub-identity__quick-actions" id="os-hub-quick-actions"
             style="display:none;" role="toolbar"
             aria-label="<?php esc_attr_e( 'إجراءات سريعة', 'olama-registration' ); ?>">
            <a href="#"
               id="os-hub-qaction-invoice"
               class="button button-primary button-small os-hub-qaction os-hub-qaction--family-only"
               title="<?php esc_attr_e( 'فاتورة جديدة', 'olama-registration' ); ?>"
               aria-label="<?php esc_attr_e( 'إنشاء فاتورة جديدة', 'olama-registration' ); ?>"
               style="display:none;">
                <span class="dashicons dashicons-media-text" aria-hidden="true"></span>
                <?php _e( 'فاتورة', 'olama-registration' ); ?>
            </a>
            <a href="#"
               id="os-hub-qaction-agreement"
               class="button button-small os-hub-qaction"
               title="<?php esc_attr_e( 'عقد جديد', 'olama-registration' ); ?>"
               aria-label="<?php esc_attr_e( 'إنشاء عقد جديد', 'olama-registration' ); ?>">
                <span class="dashicons dashicons-media-document" aria-hidden="true"></span>
                <?php _e( 'عقد', 'olama-registration' ); ?>
            </a>
            <a href="#"
               id="os-hub-qaction-payment"
               class="button button-small os-hub-qaction"
               title="<?php esc_attr_e( 'تسجيل دفعة', 'olama-registration' ); ?>"
               aria-label="<?php esc_attr_e( 'تسجيل دفعة جديدة', 'olama-registration' ); ?>">
                <span class="dashicons dashicons-money-alt" aria-hidden="true"></span>
                <?php _e( 'دفعة', 'olama-registration' ); ?>
            </a>
            <a href="#"
               id="os-hub-qaction-settlement"
               class="button button-small os-hub-qaction os-hub-qaction--family-only"
               title="<?php esc_attr_e( 'تسوية جديدة', 'olama-registration' ); ?>"
               aria-label="<?php esc_attr_e( 'إنشاء تسوية جديدة', 'olama-registration' ); ?>"
               style="display:none;">
                <span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
                <?php _e( 'تسوية', 'olama-registration' ); ?>
            </a>
            <a href="#"
               id="os-hub-qaction-custom-payment"
               class="button button-small os-hub-qaction"
               title="<?php esc_attr_e( 'دفعة مخصصة', 'olama-registration' ); ?>"
               aria-label="<?php esc_attr_e( 'إصدار دفعة مخصصة', 'olama-registration' ); ?>">
                <span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
                <?php _e( 'دفعة مخصصة', 'olama-registration' ); ?>
            </a>
        </div>

        <!-- Phase 4: Academic Year Selector -->
        <div class="os-hub-identity__year-wrap" id="os-hub-year-wrap" style="display:none;">
            <label for="os-hub-year-select" class="screen-reader-text">
                <?php _e( 'السنة الدراسية', 'olama-registration' ); ?>
            </label>
            <select id="os-hub-year-select"
                    class="os-hub-year-select"
                    aria-label="<?php esc_attr_e( 'تصفية حسب السنة الدراسية', 'olama-registration' ); ?>">
                <!-- Populated by JS from HUB_DATA.academicYears -->
            </select>
        </div>

        <!-- Back button -->
        <div class="os-hub-identity__actions">
            <button type="button"
                    class="button os-hub-back-btn"
                    id="os-hub-back-to-search"
                    aria-label="<?php esc_attr_e( 'العودة للبحث', 'olama-registration' ); ?>">
                <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
                <?php _e( 'رجوع', 'olama-registration' ); ?>
            </button>
        </div>

    </div><!-- .os-hub-identity -->

    <!-- ── Keyboard shortcut legend (Phase 4) ────────────────────────────── -->
    <div class="os-hub-shortcuts-legend" id="os-hub-shortcuts-legend" aria-hidden="true" style="display:none;">
        <span class="dashicons dashicons-keyboard" aria-hidden="true"></span>
        <?php _e( 'اختصارات: ', 'olama-registration' ); ?>
        <kbd>1</kbd>–<kbd>8</kbd> <?php _e( 'للقسم', 'olama-registration' ); ?> &nbsp;
        <kbd>Esc</kbd> <?php _e( 'رجوع', 'olama-registration' ); ?>
    </div>

    <!-- ── Tile Grid ───────────────────────────────────────────────────────── -->
    <div class="os-hub-tile-grid" id="os-hub-tile-grid" role="list">

        <?php
        $tile_num = 1;
        foreach ( $tiles as $tile_id => $tile ) :
            $panel_id = 'os-hub-tile-panel-' . $tile_id;
        ?>
        <div class="os-hub-tile-item<?php echo $tile['always'] ? '' : ' os-hub-tile-item--family-only os-hub-tile-item--hidden'; ?>"
             role="listitem"
             data-tile="<?php echo esc_attr( $tile_id ); ?>_wrapper"
             data-tile-id="<?php echo esc_attr( $tile_id ); ?>">

            <button type="button"
                    class="os-hub-tile"
                    id="os-hub-tile-btn-<?php echo esc_attr( $tile_id ); ?>"
                    aria-expanded="false"
                    aria-controls="<?php echo esc_attr( $panel_id ); ?>"
                    data-tile="<?php echo esc_attr( $tile_id ); ?>"
                    data-shortcut="<?php echo $tile_num; ?>"
                    title="<?php echo esc_attr( $tile['label'] ) . ' (' . $tile_num . ')'; ?>"
                    style="--tile-color: <?php echo esc_attr( $tile['color'] ); ?>;">

                <span class="os-hub-tile__icon-wrap" aria-hidden="true">
                    <span class="dashicons <?php echo esc_attr( $tile['icon'] ); ?>"></span>
                </span>

                <span class="os-hub-tile__text">
                    <span class="os-hub-tile__label"><?php echo esc_html( $tile['label'] ); ?></span>
                    <span class="os-hub-tile__desc"><?php echo esc_html( $tile['desc'] ); ?></span>
                </span>

                <span class="os-hub-tile__badge"
                      id="os-hub-tile-badge-<?php echo esc_attr( $tile_id ); ?>"
                      aria-label="<?php esc_attr_e( 'العدد', 'olama-registration' ); ?>"
                      style="display:none;">0</span>

                <!-- Phase 4: keyboard shortcut hint -->
                <span class="os-hub-tile__shortcut" aria-hidden="true"><?php echo $tile_num; ?></span>

                <span class="os-hub-tile__chevron dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>

            </button><!-- .os-hub-tile -->

            <div class="os-hub-tile-panel"
                 id="<?php echo esc_attr( $panel_id ); ?>"
                 aria-hidden="true"
                 role="region"
                 aria-label="<?php echo esc_attr( $tile['label'] ); ?>"
                 style="--tile-color: <?php echo esc_attr( $tile['color'] ); ?>;">

                <div class="os-hub-tile-panel__inner">
                    <div class="os-hub-tile-panel__loading" style="display:none;" aria-live="polite">
                        <span class="os-hub-spinner" aria-hidden="true"></span>
                        <span><?php _e( 'جارٍ التحميل...', 'olama-registration' ); ?></span>
                    </div>
                    <div class="os-hub-tile-panel__content"></div>
                </div>

            </div><!-- .os-hub-tile-panel -->

        </div><!-- .os-hub-tile-item -->
        <?php
            $tile_num++;
        endforeach;
        ?>

    </div><!-- .os-hub-tile-grid -->

</div><!-- .os-hub-service-screen -->
