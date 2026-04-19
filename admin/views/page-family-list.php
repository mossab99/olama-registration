<?php
/**
 * Family List Page
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$action     = sanitize_text_field( $_GET['action']     ?? '' );
$family_uid = sanitize_text_field( $_GET['family_uid'] ?? '' );
$active_tab = sanitize_text_field( $_GET['tab']        ?? 'family' );

// Load family for edit mode
$family = null;
if ( $action === 'edit' && $family_uid ) {
    $family = Olama_Reg_Family::get_family( $family_uid );
}

$table = new Olama_Reg_Family_Table();
$table->prepare_items();
?>
<div class="wrap olama-reg-wrap" dir="rtl">

    <?php if ( ! $action ): ?>
    <!-- ── LIST VIEW ─────────────────────────────────────────────── -->
    <div class="olama-reg-page-header">
        <h1 class="wp-heading-inline">
            <span class="dashicons dashicons-id-alt"></span>
            <?php esc_html_e( 'العائلات المسجلة', 'olama-registration' ); ?>
        </h1>
        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'olama-registration', 'action' => 'edit' ], admin_url( 'admin.php' ) ) ); ?>"
           class="page-title-action olama-reg-btn olama-reg-btn--primary">
            + <?php esc_html_e( 'إضافة عائلة جديدة', 'olama-registration' ); ?>
        </a>
    </div>

    <!-- Notice area -->
    <div id="olama-reg-notice" class="olama-reg-notice" style="display:none;"></div>

    <!-- Search Form -->
    <form method="get" class="olama-reg-search-form">
        <input type="hidden" name="page" value="olama-registration">
        <?php $table->search_box( __( 'بحث', 'olama-registration' ), 'olama-reg-search' ); ?>
    </form>

    <?php $table->views(); ?>

    <form method="post">
        <?php $table->display(); ?>
    </form>

    <?php else: ?>
    <!-- ── EDIT / CREATE VIEW ────────────────────────────────────── -->

    <div class="olama-reg-page-header">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration' ) ); ?>"
           class="olama-reg-back-btn">← <?php esc_html_e( 'العودة للقائمة', 'olama-registration' ); ?></a>
        <h1>
            <?php if ( $family ): ?>
                <?php esc_html_e( 'ملف العائلة', 'olama-registration' ); ?>
                <span class="olama-reg-uid-badge olama-reg-uid-badge--lg"><?php echo esc_html( $family->family_uid ); ?></span>
            <?php else: ?>
                <?php esc_html_e( 'عائلة جديدة', 'olama-registration' ); ?>
                <span class="olama-reg-uid-badge olama-reg-uid-badge--lg olama-reg-uid-pending"><?php esc_html_e( 'سيتم تعيينه تلقائياً', 'olama-registration' ); ?></span>
            <?php endif; ?>
        </h1>
    </div>

    <!-- Notice area -->
    <div id="olama-reg-notice" class="olama-reg-notice" style="display:none;"></div>

    <?php include OLAMA_REG_PATH . 'admin/views/partial-family-form.php'; ?>
    <?php endif; ?>

</div><!-- .olama-reg-wrap -->
