<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$action = sanitize_text_field( $_POST['action'] ?? $_GET['action'] ?? '' );
$id     = (int) ( $_GET['id'] ?? 0 );

// Handle form submission
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['clause_nonce'] ) ) {
    if ( wp_verify_nonce( $_POST['clause_nonce'], 'save_clause' ) ) {
        $post_id = (int) $_POST['id'];
        $data = [
            'title'       => $_POST['title'],
            'clause_text' => $_POST['clause_text'],
            'is_active'   => isset( $_POST['is_active'] ) ? 1 : 0,
        ];
        
        if ( $post_id > 0 ) {
            Olama_Reg_Clause_Bank::update( $post_id, $data );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'تم تحديث البند بنجاح.', 'olama-registration' ) . '</p></div>';
        } else {
            Olama_Reg_Clause_Bank::add( $data );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'تمت إضافة البند بنجاح.', 'olama-registration' ) . '</p></div>';
        }
    }
}

// Handle delete
if ( $action === 'delete' && $id > 0 && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_clause_' . $id ) ) {
    Olama_Reg_Clause_Bank::delete( $id );
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'تم حذف البند.', 'olama-registration' ) . '</p></div>';
}

$edit_mode = false;
$edit_clause = null;
if ( $action === 'edit' && $id > 0 ) {
    $edit_mode = true;
    $edit_clause = Olama_Reg_Clause_Bank::get( $id );
}

$clauses = Olama_Reg_Clause_Bank::get_all();
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'بنود العقود العامة', 'olama-registration' ); ?></h1>
    <?php if ( $edit_mode ) : ?>
        <a href="?page=olama-registration-clause-bank" class="page-title-action"><?php esc_html_e( 'إضافة جديد', 'olama-registration' ); ?></a>
    <?php endif; ?>
    <hr class="wp-header-end">

    <div id="col-container" class="wp-clearfix">
        <div id="col-left">
            <div class="col-wrap">
                <div class="form-wrap">
                    <h2><?php echo $edit_mode ? esc_html__( 'تعديل البند', 'olama-registration' ) : esc_html__( 'إضافة بند جديد', 'olama-registration' ); ?></h2>
                    <form method="post" action="?page=olama-registration-clause-bank">
                        <?php wp_nonce_field( 'save_clause', 'clause_nonce' ); ?>
                        <input type="hidden" name="id" value="<?php echo $edit_mode ? esc_attr( $edit_clause->id ) : '0'; ?>">
                        
                        <div class="form-field form-required">
                            <label for="title"><?php esc_html_e( 'عنوان البند', 'olama-registration' ); ?></label>
                            <input type="text" name="title" id="title" value="<?php echo $edit_mode ? esc_attr( $edit_clause->title ) : ''; ?>" required>
                            <p><?php esc_html_e( 'اسم يسهل التعرف عليه (مثل: شرط الانسحاب)', 'olama-registration' ); ?></p>
                        </div>

                        <div class="form-field form-required">
                            <label for="clause_text"><?php esc_html_e( 'نص البند', 'olama-registration' ); ?></label>
                            <textarea name="clause_text" id="clause_text" rows="5" required><?php echo $edit_mode ? esc_textarea( $edit_clause->clause_text ) : ''; ?></textarea>
                            <p><?php esc_html_e( 'نص الشرط الذي سيظهر في العقد.', 'olama-registration' ); ?></p>
                        </div>

                        <div class="form-field">
                            <label for="is_active">
                                <input type="checkbox" name="is_active" id="is_active" value="1" <?php checked( ! $edit_mode || $edit_clause->is_active ); ?>>
                                <?php esc_html_e( 'مفعل', 'olama-registration' ); ?>
                            </label>
                        </div>

                        <?php submit_button( $edit_mode ? __( 'تحديث', 'olama-registration' ) : __( 'إضافة', 'olama-registration' ), 'primary', 'submit', false ); ?>
                        <?php if ( $edit_mode ) : ?>
                            <a href="?page=olama-registration-clause-bank" class="button"><?php esc_html_e( 'إلغاء', 'olama-registration' ); ?></a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div id="col-right">
            <div class="col-wrap">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="column-primary"><?php esc_html_e( 'العنوان', 'olama-registration' ); ?></th>
                            <th><?php esc_html_e( 'النص', 'olama-registration' ); ?></th>
                            <th><?php esc_html_e( 'الحالة', 'olama-registration' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $clauses ) ) : ?>
                            <tr>
                                <td colspan="3"><?php esc_html_e( 'لا يوجد بنود مسجلة.', 'olama-registration' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $clauses as $c ) : ?>
                                <tr>
                                    <td class="column-primary" data-colname="<?php esc_attr_e( 'العنوان', 'olama-registration' ); ?>">
                                        <strong>
                                            <a href="?page=olama-registration-clause-bank&action=edit&id=<?php echo $c->id; ?>">
                                                <?php echo esc_html( $c->title ); ?>
                                            </a>
                                        </strong>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="?page=olama-registration-clause-bank&action=edit&id=<?php echo $c->id; ?>">
                                                    <?php esc_html_e( 'تحرير', 'olama-registration' ); ?>
                                                </a> |
                                            </span>
                                            <span class="delete">
                                                <a href="<?php echo wp_nonce_url( '?page=olama-registration-clause-bank&action=delete&id=' . $c->id, 'delete_clause_' . $c->id ); ?>" onclick="return confirm('<?php esc_attr_e( 'تأكيد الحذف؟', 'olama-registration' ); ?>');" class="submitdelete" style="color:#a00;">
                                                    <?php esc_html_e( 'حذف', 'olama-registration' ); ?>
                                                </a>
                                            </span>
                                        </div>
                                        <button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>
                                    </td>
                                    <td data-colname="<?php esc_attr_e( 'النص', 'olama-registration' ); ?>">
                                        <?php echo wp_trim_words( $c->clause_text, 15 ); ?>
                                    </td>
                                    <td data-colname="<?php esc_attr_e( 'الحالة', 'olama-registration' ); ?>">
                                        <?php if ( $c->is_active ) : ?>
                                            <span style="color:green;"><?php esc_html_e( 'مفعل', 'olama-registration' ); ?></span>
                                        <?php else : ?>
                                            <span style="color:red;"><?php esc_html_e( 'معطل', 'olama-registration' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
