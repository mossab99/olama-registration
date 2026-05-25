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
                Olama_Reg_Agreement_Templates::save_template_relations( $new_id, $_POST['fees'] ?? [], $_POST['clauses'] ?? [] );
                wp_redirect( admin_url( 'admin.php?page=olama-registration-agreements&tab=templates&action=edit&id=' . $new_id . '&updated=1' ) );
                exit;
            }
        } else {
            global $wpdb;
            $wpdb->update( $wpdb->prefix . 'olama_agreement_templates', $data, [ 'id' => $id ] );
            Olama_Reg_Agreement_Templates::save_template_relations( $id, $_POST['fees'] ?? [], $_POST['clauses'] ?? [] );
            wp_redirect( admin_url( 'admin.php?page=olama-registration-agreements&tab=templates&action=edit&id=' . $id . '&updated=1' ) );
            exit;
        }
    }

    $bank_clauses = [];
    if ( class_exists( 'Olama_Reg_Clause_Bank' ) ) {
        $bank_clauses = Olama_Reg_Clause_Bank::get_all();
    }

    $fee_templates = [];
    if ( class_exists( 'Olama_Reg_Billing_Fees' ) ) {
        $fee_templates = Olama_Reg_Billing_Fees::get_templates();
    }

    ?>
    <div class="olama-reg-wrap">
        <div class="olama-reg-page-header">
            <h1 class="wp-heading-inline" style="margin:0;"><?php echo $is_new ? esc_html__( 'إضافة نموذج جديد', 'olama-registration' ) : esc_html__( 'تعديل النموذج', 'olama-registration' ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration-agreements&tab=templates' ) ); ?>" class="olama-reg-back-btn">
                <span class="dashicons dashicons-arrow-right-alt2" style="margin-top:4px;"></span> <?php esc_html_e( 'العودة للقائمة', 'olama-registration' ); ?>
            </a>
        </div>

        <nav class="nav-tab-wrapper wp-clearfix" style="margin-bottom: 20px;">
            <a href="?page=olama-registration-agreements&tab=agreements" class="nav-tab">
                <?php esc_html_e( 'العقود', 'olama-registration' ); ?>
            </a>
            <a href="?page=olama-registration-agreements&tab=templates" class="nav-tab nav-tab-active">
                <?php esc_html_e( 'نماذج العقود', 'olama-registration' ); ?>
            </a>
            <a href="?page=olama-registration-agreements&tab=clauses" class="nav-tab">
                <?php esc_html_e( 'بنود العقود العامة', 'olama-registration' ); ?>
            </a>
        </nav>

        <?php if ( isset( $_GET['updated'] ) ) : ?>
            <div class="olama-reg-notice olama-reg-notice--success is-dismissible"><p style="margin:0;"><?php esc_html_e( 'تم الحفظ بنجاح.', 'olama-registration' ); ?></p></div>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field( 'save_agr_template', 'template_nonce' ); ?>
            
            <div class="olama-reg-section">
                <h3 class="olama-reg-section-title"><?php esc_html_e('البيانات الأساسية', 'olama-registration'); ?></h3>
                <div class="olama-reg-grid">
                    <div class="olama-reg-field">
                        <label><?php esc_html_e( 'الاسم', 'olama-registration' ); ?></label>
                        <input type="text" name="name" value="<?php echo esc_attr( $template->name ); ?>" required>
                    </div>
                    <div class="olama-reg-field">
                        <label><?php esc_html_e( 'النشاط', 'olama-registration' ); ?></label>
                        <select name="activity_type" required>
                            <option value=""><?php esc_html_e( 'اختر النشاط', 'olama-registration' ); ?></option>
                            <option value="kindergarten" <?php selected( $template->activity_type, 'kindergarten' ); ?>><?php esc_html_e( 'رياض الأطفال', 'olama-registration' ); ?></option>
                            <option value="summer_club" <?php selected( $template->activity_type, 'summer_club' ); ?>><?php esc_html_e( 'النادي الصيفي', 'olama-registration' ); ?></option>
                            <option value="karate" <?php selected( $template->activity_type, 'karate' ); ?>><?php esc_html_e( 'كاراتيه', 'olama-registration' ); ?></option>
                        </select>
                    </div>
                    <div class="olama-reg-field olama-reg-field--wide">
                        <label><?php esc_html_e( 'الوصف', 'olama-registration' ); ?></label>
                        <textarea name="description" rows="3"><?php echo esc_textarea( $template->description ); ?></textarea>
                    </div>
                    <div class="olama-reg-field olama-reg-field--checkbox">
                        <label>
                            <input type="checkbox" name="is_active" value="1" <?php checked( $template->is_active, 1 ); ?>>
                            <?php esc_html_e( 'مفعّل', 'olama-registration' ); ?>
                        </label>
                    </div>
                </div>
            </div>

            <div class="olama-reg-section">
                <h3 class="olama-reg-section-title"><?php esc_html_e( 'الرسوم الافتراضية', 'olama-registration' ); ?></h3>
                <div class="olama-reg-table-wrap" style="padding:15px;">
                    <table class="olama-reg-fin-table" id="fees-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'فئة الرسم', 'olama-registration' ); ?></th>
                                <th><?php esc_html_e( 'البيان', 'olama-registration' ); ?></th>
                                <th><?php esc_html_e( 'المبلغ', 'olama-registration' ); ?></th>
                                <th><?php esc_html_e( 'الخصم', 'olama-registration' ); ?></th>
                                <th style="width:80px;"><?php esc_html_e( 'إزالة', 'olama-registration' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $fees = $is_new ? [] : Olama_Reg_Agreement_Templates::get_fees( $id );
                            foreach ( $fees as $fee ) :
                            ?>
                            <tr>
                                <td>
                                    <select name="fees[category][]" class="olama-reg-inline-input" style="width:100%" required>
                                        <option value="general"><?php esc_html_e('عام', 'olama-registration'); ?></option>
                                        <?php
                                        foreach ( $fee_templates as $tpl ) {
                                            $selected = selected( $fee->fee_category, $tpl->id, false );
                                            if ( ! $selected && $fee->fee_category === $tpl->template_name ) {
                                                $selected = 'selected="selected"';
                                            }
                                            echo '<option value="' . esc_attr( $tpl->id ) . '" ' . $selected . '>' . esc_html( $tpl->template_name ) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td><input type="text" name="fees[label][]" value="<?php echo esc_attr( $fee->label ); ?>" class="olama-reg-inline-input" required></td>
                                <td><input type="number" name="fees[amount][]" value="<?php echo esc_attr( $fee->amount ); ?>" step="0.001" class="olama-reg-inline-input" required></td>
                                <td><input type="number" name="fees[discount][]" value="<?php echo esc_attr( $fee->discount ); ?>" step="0.001" class="olama-reg-inline-input"></td>
                                <td><button type="button" class="button button-small remove-row" style="color:red;">X</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top: 15px;">
                        <button type="button" class="olama-reg-btn olama-reg-btn--secondary" id="add-fee-row"><span class="dashicons dashicons-plus" style="margin-top:4px;"></span> <?php esc_html_e( 'إضافة رسم', 'olama-registration' ); ?></button>
                    </div>
                </div>
            </div>

            <div class="olama-reg-section">
                <h3 class="olama-reg-section-title"><?php esc_html_e( 'البنود الافتراضية', 'olama-registration' ); ?></h3>
                <div class="olama-reg-table-wrap" style="padding:15px;">
                    <table class="olama-reg-fin-table" id="clauses-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'نص البند', 'olama-registration' ); ?></th>
                                <th style="width:80px;"><?php esc_html_e( 'إزالة', 'olama-registration' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $clauses = $is_new ? [] : Olama_Reg_Agreement_Templates::get_clauses( $id );
                            foreach ( $clauses as $clause ) :
                            ?>
                            <tr>
                                <td><textarea name="clauses[]" rows="2" style="width:100%; border:1px solid #ddd; border-radius:4px; padding:8px;" required><?php echo esc_textarea( $clause->clause_text ); ?></textarea></td>
                                <td><button type="button" class="button button-small remove-row" style="color:red;">X</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 20px; display:flex; gap:10px; align-items:flex-start;">
                        <button type="button" class="olama-reg-btn olama-reg-btn--secondary" id="add-clause-row"><span class="dashicons dashicons-plus" style="margin-top:4px;"></span> <?php esc_html_e( 'إضافة بند فارغ', 'olama-registration' ); ?></button>
                        <div style="display:flex; gap:10px; align-items:center; background:#f9f9f9; padding:5px 10px; border-radius:6px; border:1px solid #eee;">
                            <select id="clause-bank-select" style="min-width:250px;">
                                <option value=""><?php esc_html_e( 'اختر من بنود العقود العامة...', 'olama-registration' ); ?></option>
                                <?php foreach ( $bank_clauses as $bc ) : ?>
                                    <option value="<?php echo esc_attr( $bc->clause_text ); ?>"><?php echo esc_html( $bc->title ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="olama-reg-btn olama-reg-btn--primary" id="add-bank-clause" style="padding:6px 12px;"><?php esc_html_e( 'إدراج', 'olama-registration' ); ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="olama-reg-form-actions">
                <button type="submit" class="olama-reg-btn olama-reg-btn--primary" name="submit">
                    <span class="dashicons dashicons-saved" style="margin-top:4px;"></span> <?php esc_html_e( 'حفظ التغييرات', 'olama-registration' ); ?>
                </button>
            </div>
        </form>

        <script>
        jQuery(document).ready(function($) {
            var feeOptions = '<option value="general"><?php echo esc_js( __("عام", "olama-registration") ); ?></option>';
            <?php foreach ( $fee_templates as $tpl ) : ?>
                feeOptions += '<option value="<?php echo esc_attr( $tpl->id ); ?>"><?php echo esc_js( $tpl->template_name ); ?></option>';
            <?php endforeach; ?>

            $('#add-fee-row').on('click', function() {
                var row = '<tr>' +
                    '<td><select name="fees[category][]" class="olama-reg-inline-input" style="width:100%" required>' + feeOptions + '</select></td>' +
                    '<td><input type="text" name="fees[label][]" class="olama-reg-inline-input" required></td>' +
                    '<td><input type="number" name="fees[amount][]" value="0" step="0.001" class="olama-reg-inline-input" required></td>' +
                    '<td><input type="number" name="fees[discount][]" value="0" step="0.001" class="olama-reg-inline-input"></td>' +
                    '<td><button type="button" class="button button-small remove-row" style="color:red;">X</button></td>' +
                    '</tr>';
                $('#fees-table tbody').append(row);
            });

            $('#add-clause-row').on('click', function() {
                var row = '<tr>' +
                    '<td><textarea name="clauses[]" rows="2" style="width:100%; border:1px solid #ddd; border-radius:4px; padding:8px;" required></textarea></td>' +
                    '<td><button type="button" class="button button-small remove-row" style="color:red;">X</button></td>' +
                    '</tr>';
                $('#clauses-table tbody').append(row);
            });

            $('#add-bank-clause').on('click', function() {
                var text = $('#clause-bank-select').val();
                if ( text ) {
                    var row = '<tr>' +
                        '<td><textarea name="clauses[]" rows="2" style="width:100%; border:1px solid #ddd; border-radius:4px; padding:8px;" required>' + text + '</textarea></td>' +
                        '<td><button type="button" class="button button-small remove-row" style="color:red;">X</button></td>' +
                        '</tr>';
                    $('#clauses-table tbody').append(row);
                    $('#clause-bank-select').val('');
                }
            });

            $(document).on('click', '.remove-row', function() {
                $(this).closest('tr').remove();
            });
        });
        </script>
    </div>
    <?php
} else {
    // List view
    $templates = Olama_Reg_Agreement_Templates::get_list();
    ?>
    <div class="olama-reg-wrap">
        <div class="olama-reg-page-header">
            <h1 class="wp-heading-inline" style="margin:0;"><?php esc_html_e( 'نماذج وبنود العقود', 'olama-registration' ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration-agreements&tab=templates&action=new' ) ); ?>" class="olama-reg-btn olama-reg-btn--primary">
                <span class="dashicons dashicons-plus-alt2" style="margin-top:4px;"></span> <?php esc_html_e( 'إضافة نموذج جديد', 'olama-registration' ); ?>
            </a>
        </div>

        <nav class="nav-tab-wrapper wp-clearfix" style="margin-bottom: 20px;">
            <a href="?page=olama-registration-agreements&tab=agreements" class="nav-tab">
                <?php esc_html_e( 'العقود', 'olama-registration' ); ?>
            </a>
            <a href="?page=olama-registration-agreements&tab=templates" class="nav-tab nav-tab-active">
                <?php esc_html_e( 'نماذج العقود', 'olama-registration' ); ?>
            </a>
            <a href="?page=olama-registration-agreements&tab=clauses" class="nav-tab">
                <?php esc_html_e( 'بنود العقود العامة', 'olama-registration' ); ?>
            </a>
        </nav>

        <div class="olama-reg-section">
            <div class="olama-reg-table-wrap">
                <table class="olama-reg-fin-table">
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
                                        <strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration-agreements&tab=templates&action=edit&id=' . $t->id ) ); ?>" style="color:#1565C0; text-decoration:none;"><?php echo esc_html( $t->name ); ?></a></strong>
                                    </td>
                                    <td><?php echo esc_html( $t->activity_type ); ?></td>
                                    <td><?php echo esc_html( $t->description ); ?></td>
                                    <td><?php echo $t->is_active ? '<span class="olama-reg-badge olama-reg-badge--active">' . __( 'مفعّل', 'olama-registration' ) . '</span>' : '<span class="olama-reg-badge olama-reg-badge--inactive">' . __( 'غير مفعّل', 'olama-registration' ) . '</span>'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}
