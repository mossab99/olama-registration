<?php
/**
 * Settings View
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Handle save
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['olama_reg_settings_nonce'] ) && wp_verify_nonce( $_POST['olama_reg_settings_nonce'], 'save_settings' ) ) {
    if ( isset( $_POST['custom_services'] ) ) {
        // Sanitize array of strings
        $services = array_map( 'sanitize_text_field', (array) $_POST['custom_services'] );
        // Remove empty
        $services = array_filter( $services, 'strlen' );
        update_option( 'olama_reg_custom_services', array_values( $services ) );
    }

    if ( isset( $_POST['agreement_natures'] ) ) {
        $natures = array_map( 'sanitize_text_field', (array) $_POST['agreement_natures'] );
        $natures = array_filter( $natures, 'strlen' );
        update_option( 'olama_reg_agreement_natures', array_values( $natures ) );
    }
    
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'تم حفظ الإعدادات بنجاح.', 'olama-registration' ) . '</p></div>';
}

$services = get_option( 'olama_reg_custom_services', ['دوسية', 'نشاط', 'مواصلات', 'امتحان إضافي'] );
$agreement_natures = get_option( 'olama_reg_agreement_natures', ['عقد مدرسة', 'عقد روضة', 'عقد نادي صيفي', 'رحلة مدرسية'] );
?>

<div class="wrap olama-reg-wrap">
    <div class="olama-reg-header">
        <h1><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'إعدادات التسجيل', 'olama-registration' ); ?></h1>
    </div>

    <div class="olama-reg-box" style="max-width: 800px; margin-top: 20px;">
        <h2 class="nav-tab-wrapper wp-clearfix os-nav-tabs" style="margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 0;">
            <a href="#tab-services" class="nav-tab nav-tab-active" style="margin-bottom: -1px; background: #fff; border-bottom-color: #fff; color: var(--reg-primary); font-weight: 700;">الخدمات الإضافية</a>
            <a href="#tab-agreement-natures" class="nav-tab" style="margin-bottom: -1px;">طبيعة العقد</a>
        </h2>

        <form method="post" action="">
            <?php wp_nonce_field( 'save_settings', 'olama_reg_settings_nonce' ); ?>
            
            <div class="os-tab-content active" id="tab-services">
                <p style="color: var(--reg-text-muted); margin-bottom: 20px;">
                    قم بإدارة قائمة الخدمات الإضافية التي تظهر عند إنشاء دفعات مخصصة (مثل المواصلات، الأنشطة، الخ).
                </p>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label>قائمة الخدمات</label></th>
                            <td>
                                <div id="olama-reg-services-list">
                                    <?php foreach ( $services as $srv ): ?>
                                        <div class="olama-reg-service-row" style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">
                                            <input type="text" name="custom_services[]" value="<?php echo esc_attr( $srv ); ?>" class="regular-text" required />
                                            <button type="button" class="button olama-reg-remove-service" style="color: #dc2626; border-color: #dc2626;"><span class="dashicons dashicons-trash" style="margin-top: 3px;"></span></button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="button" id="olama-reg-add-service" style="margin-top: 10px;">
                                    <span class="dashicons dashicons-plus-alt2" style="margin-top: 3px;"></span> إضافة خدمة جديدة
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="os-tab-content" id="tab-agreement-natures" style="display:none;">
                <p style="color: var(--reg-text-muted); margin-bottom: 20px;">
                    قم بإدارة قائمة طبيعة العقد التي تظهر في شاشة إنشاء عقد جديد.
                </p>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label>طبيعة العقد</label></th>
                            <td>
                                <div id="olama-reg-natures-list">
                                    <?php foreach ( $agreement_natures as $nature ): ?>
                                        <div class="olama-reg-nature-row" style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">
                                            <input type="text" name="agreement_natures[]" value="<?php echo esc_attr( $nature ); ?>" class="regular-text" required />
                                            <button type="button" class="button olama-reg-remove-nature" style="color: #dc2626; border-color: #dc2626;"><span class="dashicons dashicons-trash" style="margin-top: 3px;"></span></button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="button" id="olama-reg-add-nature" style="margin-top: 10px;">
                                    <span class="dashicons dashicons-plus-alt2" style="margin-top: 3px;"></span> إضافة طبيعة عقد جديدة
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="submit">
                <button type="submit" class="button button-primary" style="background: var(--reg-primary); border-color: var(--reg-primary);">
                    حفظ التغييرات
                </button>
            </p>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Tabs switching logic
    $('.os-nav-tabs .nav-tab').on('click', function(e) {
        e.preventDefault();
        $('.os-nav-tabs .nav-tab').removeClass('nav-tab-active').css({'background': '', 'border-bottom-color': '', 'color': '', 'font-weight': ''});
        $(this).addClass('nav-tab-active').css({'background': '#fff', 'border-bottom-color': '#fff', 'color': 'var(--reg-primary)', 'font-weight': '700'});
        
        $('.os-tab-content').hide().removeClass('active');
        $($(this).attr('href')).show().addClass('active');
    });

    $('#olama-reg-add-service').on('click', function() {
        const row = `
            <div class="olama-reg-service-row" style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">
                <input type="text" name="custom_services[]" value="" class="regular-text" required placeholder="اسم الخدمة..." />
                <button type="button" class="button olama-reg-remove-service" style="color: #dc2626; border-color: #dc2626;"><span class="dashicons dashicons-trash" style="margin-top: 3px;"></span></button>
            </div>
        `;
        $('#olama-reg-services-list').append(row);
    });

    $(document).on('click', '.olama-reg-remove-service', function() {
        if ( $('.olama-reg-service-row').length > 1 ) {
            $(this).closest('.olama-reg-service-row').remove();
        } else {
            alert('يجب أن تحتوي القائمة على خدمة واحدة على الأقل.');
        }
    });

    $('#olama-reg-add-nature').on('click', function() {
        const row = `
            <div class="olama-reg-nature-row" style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">
                <input type="text" name="agreement_natures[]" value="" class="regular-text" required placeholder="طبيعة العقد..." />
                <button type="button" class="button olama-reg-remove-nature" style="color: #dc2626; border-color: #dc2626;"><span class="dashicons dashicons-trash" style="margin-top: 3px;"></span></button>
            </div>
        `;
        $('#olama-reg-natures-list').append(row);
    });

    $(document).on('click', '.olama-reg-remove-nature', function() {
        if ( $('.olama-reg-nature-row').length > 1 ) {
            $(this).closest('.olama-reg-nature-row').remove();
        } else {
            alert('يجب أن تحتوي القائمة على قيمة واحدة على الأقل.');
        }
    });
});
</script>
