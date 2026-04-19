<?php
/**
 * Student List Page
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$table = new Olama_Reg_Student_Table();
$table->prepare_items();
?>
<div class="wrap olama-reg-wrap" dir="rtl">

    <div class="olama-reg-page-header">
        <h1 class="wp-heading-inline">
            <span class="dashicons dashicons-groups"></span>
            <?php esc_html_e( 'سجل الطلاب', 'olama-registration' ); ?>
        </h1>
    </div>

    <div id="olama-reg-notice" class="olama-reg-notice" style="display:none;"></div>

    <form method="get">
        <input type="hidden" name="page" value="olama-registration-students">
        <?php $table->search_box( __( 'بحث باسم الطالب أو رقمه', 'olama-registration' ), 'student-search' ); ?>
    </form>

    <form method="post">
        <?php $table->display(); ?>
    </form>

</div>
