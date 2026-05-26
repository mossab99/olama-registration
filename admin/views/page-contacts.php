<?php
/**
 * Contacts Dashboard (Tabs for Families, Students, Customers)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$active_view = sanitize_text_field( $_GET['view'] ?? 'families' );
?>
<div class="wrap olama-reg-wrap" dir="rtl">

    <h1 class="wp-heading-inline" style="margin-bottom: 20px;">
        <span class="dashicons dashicons-id-alt"></span>
        <?php esc_html_e( 'جهات الاتصال', 'olama-registration' ); ?>
    </h1>

    <h2 class="nav-tab-wrapper olama-reg-tabs" style="margin-bottom: 20px;">
        <a href="?page=olama-registration-contacts&view=families" class="nav-tab <?php echo $active_view === 'families' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-groups" style="line-height:1.5;"></span> العائلات
        </a>
        <a href="?page=olama-registration-contacts&view=students" class="nav-tab <?php echo $active_view === 'students' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-welcome-learn-more" style="line-height:1.5;"></span> الطلاب
        </a>
        <a href="?page=olama-registration-contacts&view=customers" class="nav-tab <?php echo $active_view === 'customers' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-businessman" style="line-height:1.5;"></span> العملاء الخارجيين
        </a>
    </h2>

    <div class="olama-reg-tab-content active" style="padding: 10px 0;">
        <?php
        if ( $active_view === 'students' ) {
            include OLAMA_REG_PATH . 'admin/views/page-student-list.php';
        } elseif ( $active_view === 'customers' ) {
            include OLAMA_REG_PATH . 'admin/views/page-customers.php';
        } else {
            include OLAMA_REG_PATH . 'admin/views/page-family-list.php';
        }
        ?>
    </div>

</div>
