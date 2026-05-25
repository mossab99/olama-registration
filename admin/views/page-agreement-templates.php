<?php
/**
 * Agreement Templates UI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'Unauthorized', 'olama-registration' ) );
}

$action = sanitize_text_field( $_GET['action'] ?? '' );
$id     = (int) ( $_GET['id'] ?? 0 );

if ( $action === 'edit' || $action === 'new' ) {
    $is_new = empty( $id );
    $template = $is_new ? (object) [
        'id'            => 0,
        'activity_type' => '',
        'name'          => '',
        'description'   => '',
        'is_active'     => 1,
    ] : Olama_Reg_Agreement_Templates::get( $id );

    if ( ! $template ) {
        wp_die( __( 'النموذج غير موجود.', 'olama-registration' ) );
    }

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['template_nonce'] ) && wp_verify_nonce( $_POST['template_nonce'], 'save_agr_template' ) ) {
        $data = [
            'activity_type' => sanitize_text_field( $_POST['activity_type'] ?? '' ),
            'name'          => sanitize_text_field( $_POST['name'] ?? '' ),
            'description'   => sanitize_textarea_field( $_POST['description'] ?? '' ),
            'is_active'     => isset( $_POST['is_active'] ) ? 1 : 0,
        ];
        
        if ( $is_new ) {
            $new_id = Olama_Reg_Agreement_Templates::create( $data );
            if ( ! is_wp_error( $new_id ) ) {
                wp_redirect( admin_url( 'admin.php?page=olama-registration-agreement-templates&action=edit&id=' . $new_id . '&updated=1' ) );
                exit;
            }
        } else {
            global $wpdb;
            $wpdb->update( $wpdb->prefix . 'olama_agreement_templates', $data, [ 'id' => $id ] );
            wp_redirect( admin_url( 'admin.php?page=olama-registration-agreement-templates&action=edit&id=' . $id . '&updated=1' ) );
            exit;
        }
    }

    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php echo $is_new ? esc_html__( 'إضافة نموذج جديد', 'olama-registration' ) : esc_html__( 'تعديل النموذج', 'olama-registration' ); ?></h1>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration-agreement-templates' ) ); ?>" class="page-title-action"><?php esc_html_e( 'العودة للقائمة', 'olama-registration' ); ?></a>
        <hr class="wp-header-end">

        <?php if ( isset( $_GET['updated'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'تم الحفظ بنجاح.', 'olama-registration' ); ?></p></div>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field( 'save_agr_template', 'template_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label><?php esc_html_e( 'الاسم', 'olama-registration' ); ?></label></th>
                    <td><input type="text" name="name" value="<?php echo esc_attr( $template->name ); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label><?php esc_html_e( 'النشاط', 'olama-registration' ); ?></label></th>
                    <td>
                        <select name="activity_type" required>
                            <option value=""><?php esc_html_e( 'اختر النشاط', 'olama-registration' ); ?></option>
                            <option value="kindergarten" <?php selected( $template->activity_type, 'kindergarten' ); ?>><?php esc_html_e( 'رياض الأطفال', 'olama-registration' ); ?></option>
                            <option value="summer_club" <?php selected( $template->activity_type, 'summer_club' ); ?>><?php esc_html_e( 'النادي الصيفي', 'olama-registration' ); ?></option>
                            <option value="karate" <?php selected( $template->activity_type, 'karate' ); ?>><?php esc_html_e( 'كاراتيه', 'olama-registration' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label><?php esc_html_e( 'الوصف', 'olama-registration' ); ?></label></th>
                    <td><textarea name="description" rows="3" class="regular-text"><?php echo esc_textarea( $template->description ); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label><?php esc_html_e( 'الحالة', 'olama-registration' ); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="is_active" value="1" <?php checked( $template->is_active, 1 ); ?>>
                            <?php esc_html_e( 'مفعّل', 'olama-registration' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'حفظ التغييرات', 'olama-registration' ) ); ?>
        </form>

        <?php if ( ! $is_new ) : ?>
            <hr>
            <h3><?php esc_html_e( 'هذه الميزة (الرسوم والبنود الافتراضية) قيد التطوير حالياً، وسيتم استكمال واجهتها في المرحلة القادمة.', 'olama-registration' ); ?></h3>
        <?php endif; ?>
    </div>
    <?php
} else {
    // List view
    $templates = Olama_Reg_Agreement_Templates::get_list();
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e( 'نماذج العقود', 'olama-registration' ); ?></h1>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration-agreement-templates&action=new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'إضافة جديد', 'olama-registration' ); ?></a>
        <hr class="wp-header-end">

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'الاسم', 'olama-registration' ); ?></th>
                    <th><?php esc_html_e( 'النشاط', 'olama-registration' ); ?></th>
                    <th><?php esc_html_e( 'الوصف', 'olama-registration' ); ?></th>
                    <th><?php esc_html_e( 'الحالة', 'olama-registration' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $templates ) ) : ?>
                    <tr><td colspan="4"><?php esc_html_e( 'لا يوجد نماذج.', 'olama-registration' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $templates as $t ) : ?>
                        <tr>
                            <td>
                                <strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration-agreement-templates&action=edit&id=' . $t->id ) ); ?>"><?php echo esc_html( $t->name ); ?></a></strong>
                            </td>
                            <td><?php echo esc_html( $t->activity_type ); ?></td>
                            <td><?php echo esc_html( $t->description ); ?></td>
                            <td><?php echo $t->is_active ? __( 'مفعّل', 'olama-registration' ) : __( 'غير مفعّل', 'olama-registration' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
