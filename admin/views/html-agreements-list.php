<?php
/**
 * View: Agreements List
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once OLAMA_REG_PATH . 'admin/class-reg-agreement-table.php';

$table = new Olama_Reg_Agreement_Table();
$table->prepare_items();

?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'العقود', 'olama-registration' ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration-agreements&action=new' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'إضافة عقد جديد', 'olama-registration' ); ?>
    </a>
    <hr class="wp-header-end">

    <div class="os-card" style="margin-top: 20px;">
        <form method="get">
            <input type="hidden" name="page" value="olama-registration-agreements">
            
            <div class="alignleft actions">
                <select name="status">
                    <option value=""><?php esc_html_e( 'جميع الحالات', 'olama-registration' ); ?></option>
                    <option value="draft" <?php selected( $_REQUEST['status'] ?? '', 'draft' ); ?>><?php esc_html_e( 'مسودة', 'olama-registration' ); ?></option>
                    <option value="active" <?php selected( $_REQUEST['status'] ?? '', 'active' ); ?>><?php esc_html_e( 'فعال', 'olama-registration' ); ?></option>
                    <option value="completed" <?php selected( $_REQUEST['status'] ?? '', 'completed' ); ?>><?php esc_html_e( 'مكتمل', 'olama-registration' ); ?></option>
                    <option value="cancelled" <?php selected( $_REQUEST['status'] ?? '', 'cancelled' ); ?>><?php esc_html_e( 'ملغي', 'olama-registration' ); ?></option>
                </select>
                
                <input type="text" name="activity_type" placeholder="<?php esc_attr_e( 'النشاط (مثل النادي الصيفي)', 'olama-registration' ); ?>" value="<?php echo esc_attr( wp_unslash( $_REQUEST['activity_type'] ?? '' ) ); ?>" />
                
                <input type="submit" class="button" value="<?php esc_attr_e( 'تصفية', 'olama-registration' ); ?>">
            </div>
            
            <?php
            $table->display();
            ?>
        </form>
    </div>
</div>
