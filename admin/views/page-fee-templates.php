<?php
/**
 * Fee Templates Management View — Premium Redesign
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$action = sanitize_text_field( $_GET['action'] ?? '' );
$id     = absint( $_GET['id'] ?? 0 );

$template = null;
if ( $action === 'edit' && $id ) {
    $template = Olama_Reg_Billing_Fees::get_template( $id );
}

$templates = Olama_Reg_Billing_Fees::get_templates();

// Get grades for dropdown
$grades = [];
if ( class_exists( 'Olama_School_Grade' ) ) {
    $grades = Olama_School_Grade::get_grades();
}
?>
<div class="wrap olama-reg-wrap" dir="rtl">

    <!-- ── PAGE HEADER ──────────────────────────────────────────────────── -->
    <div class="olama-reg-page-header">
        <?php if ( $action ): ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration-fees' ) ); ?>"
               class="olama-reg-back-btn">
                <span class="dashicons dashicons-arrow-right-alt"></span>
                <?php esc_html_e( 'العودة للقائمة', 'olama-registration' ); ?>
            </a>
        <?php endif; ?>
        <h1>
            <span class="dashicons dashicons-clipboard"></span>
            <?php if ( $action === 'edit' ): ?>
                <?php esc_html_e( 'تعديل نموذج الرسوم', 'olama-registration' ); ?>
            <?php elseif ( $action === 'add' ): ?>
                <?php esc_html_e( 'نموذج رسوم جديد', 'olama-registration' ); ?>
            <?php else: ?>
                <?php esc_html_e( 'نماذج الرسوم الدراسية', 'olama-registration' ); ?>
            <?php endif; ?>
        </h1>
        <?php if ( ! $action ): ?>
            <a href="<?php echo esc_url( add_query_arg( [ 'action' => 'add' ], admin_url( 'admin.php?page=olama-registration-fees' ) ) ); ?>"
               class="olama-reg-btn olama-reg-btn--primary">
                <span class="dashicons dashicons-plus"></span>
                <?php esc_html_e( 'إضافة نموذج جديد', 'olama-registration' ); ?>
            </a>
        <?php endif; ?>
    </div>

    <!-- Notice area -->
    <div id="olama-reg-notice" class="olama-reg-notice" style="display:none;"></div>

    <?php if ( ! $action ): ?>
        <!-- ── LIST VIEW ──────────────────────────────────────────────────── -->
        <div class="olama-reg-section">
            <h3 class="olama-reg-section-title">
                <span class="dashicons dashicons-list-view"></span>
                <?php esc_html_e( 'النماذج المتاحة', 'olama-registration' ); ?>
            </h3>
            <div class="olama-reg-table-wrap">
                <table class="olama-reg-fin-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'اسم النموذج', 'olama-registration' ); ?></th>
                            <th><?php esc_html_e( 'الصف المستهدف', 'olama-registration' ); ?></th>
                            <th style="text-align:center;"><?php esc_html_e( 'عدد الأقساط', 'olama-registration' ); ?></th>
                            <th><?php esc_html_e( 'إجمالي قيمة النموذج', 'olama-registration' ); ?></th>
                            <th style="text-align:center; width:100px;"><?php esc_html_e( 'الخيارات', 'olama-registration' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $templates ) ): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="olama-reg-empty-state">
                                        <span class="dashicons dashicons-clipboard"></span>
                                        <p><?php esc_html_e( 'لا يوجد نماذج رسوم مضافة حتى الآن.', 'olama-registration' ); ?></p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ( $templates as $tpl ):
                                $total_val = 0;
                                foreach ( $tpl->items as $item ) {
                                    $total_val += (float) ( $item['amount'] ?? 0 );
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong style="font-size:14px;"><?php echo esc_html( $tpl->template_name ); ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $grade_name = '<span class="olama-reg-badge olama-reg-badge--inactive">' . esc_html__( 'عام', 'olama-registration' ) . '</span>';
                                        if ( ! empty( $tpl->grade_id ) ) {
                                            foreach ( $grades as $g ) {
                                                if ( (string)$g->id === (string)$tpl->grade_id ) {
                                                    $grade_name = '<span class="olama-reg-badge olama-reg-badge--info">' . esc_html( $g->grade_name ) . '</span>';
                                                    break;
                                                }
                                            }
                                        }
                                        echo wp_kses_post( $grade_name );
                                        ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="olama-reg-uid-badge" style="background:linear-gradient(135deg,#5C6BC0,#3949AB);letter-spacing:0.5px;">
                                            <?php echo esc_html( $tpl->installments ); ?> قسط
                                        </span>
                                    </td>
                                    <td class="olama-reg-balance-cell">
                                        <strong><?php echo esc_html( number_format( $total_val, 2 ) ); ?></strong>
                                    </td>
                                    <td style="text-align:center;">
                                        <a href="<?php echo esc_url( add_query_arg( [ 'action' => 'edit', 'id' => $tpl->id ], admin_url( 'admin.php?page=olama-registration-fees' ) ) ); ?>"
                                           class="button button-small" title="<?php esc_attr_e( 'تعديل', 'olama-registration' ); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </a>
                                        &nbsp;
                                        <button class="button button-small olama-reg-delete-fee-template-btn"
                                                data-id="<?php echo esc_attr( $tpl->id ); ?>"
                                                title="<?php esc_attr_e( 'حذف', 'olama-registration' ); ?>"
                                                style="color:#C62828; border-color:#FFCDD2; background:#FFF5F5;">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- ── CREATE/EDIT VIEW ───────────────────────────────────────────── -->
        <div class="olama-reg-form-wrapper">
            <form id="olama-reg-fee-template-form" method="post">
                <input type="hidden" name="id" value="<?php echo esc_attr( $template ? $template->id : 0 ); ?>">

                <!-- Section 1: Basic Info -->
                <div class="olama-reg-section" style="margin:0; border-radius:0; border-left:none; border-right:none; border-top:none;">
                    <h3 class="olama-reg-section-title">
                        <span class="dashicons dashicons-info-outline"></span>
                        <?php esc_html_e( 'معلومات النموذج الأساسية', 'olama-registration' ); ?>
                    </h3>
                    <div class="olama-reg-grid">
                        <div class="olama-reg-field olama-reg-field--required">
                            <label for="template_name"><?php esc_html_e( 'اسم نموذج الرسوم', 'olama-registration' ); ?></label>
                            <input type="text" id="template_name" name="template_name"
                                   value="<?php echo esc_attr( $template ? $template->template_name : '' ); ?>"
                                   placeholder="<?php esc_attr_e( 'مثال: رسوم الصف الأول الابتدائي', 'olama-registration' ); ?>"
                                   required>
                        </div>
                        <div class="olama-reg-field">
                            <label for="grade_id"><?php esc_html_e( 'الصف المستهدف (اختياري)', 'olama-registration' ); ?></label>
                            <select id="grade_id" name="grade_id">
                                <option value=""><?php esc_html_e( 'عام — يطبق على جميع الصفوف', 'olama-registration' ); ?></option>
                                <?php foreach ( $grades as $g ): ?>
                                    <option value="<?php echo esc_attr( $g->id ); ?>" <?php selected( $template ? $template->grade_id : '', $g->id ); ?>>
                                        <?php echo esc_html( $g->grade_name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="olama-reg-field">
                            <label for="installments"><?php esc_html_e( 'عدد الأقساط الافتراضي', 'olama-registration' ); ?></label>
                            <input type="number" id="installments" name="installments"
                                   min="1" max="12"
                                   value="<?php echo esc_attr( $template ? $template->installments : 1 ); ?>">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Fee Items -->
                <div class="olama-reg-section" style="margin:0; border-radius:0; border:none; border-top:1px solid var(--reg-border);">
                    <h3 class="olama-reg-section-title">
                        <span class="dashicons dashicons-list-view"></span>
                        <?php esc_html_e( 'تفاصيل بنود الرسوم', 'olama-registration' ); ?>
                    </h3>
                    <div class="olama-reg-section-body" style="padding:0;">
                        <div class="olama-reg-table-wrap">
                            <table class="olama-reg-fin-table" id="olama-reg-fee-items-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'الوصف / البند', 'olama-registration' ); ?></th>
                                        <th style="width:180px;"><?php esc_html_e( 'القيمة', 'olama-registration' ); ?></th>
                                        <th style="width:60px; text-align:center;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ( $template && ! empty( $template->items ) ): ?>
                                        <?php foreach ( $template->items as $idx => $item ): ?>
                                            <tr>
                                                <td>
                                                    <input type="text"
                                                           name="items[<?php echo esc_attr( $idx ); ?>][description]"
                                                           value="<?php echo esc_attr( $item['description'] ); ?>"
                                                           class="olama-reg-inline-input"
                                                           required
                                                           placeholder="<?php esc_attr_e( 'مثال: رسوم التسجيل، رسوم الباص...', 'olama-registration' ); ?>">
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01"
                                                           name="items[<?php echo esc_attr( $idx ); ?>][amount]"
                                                           value="<?php echo esc_attr( $item['amount'] ); ?>"
                                                           class="olama-reg-inline-input olama-reg-fee-amount-input"
                                                           required>
                                                </td>
                                                <td style="text-align:center;">
                                                    <button type="button" class="olama-reg-btn-danger olama-reg-remove-fee-row-btn"
                                                            title="<?php esc_attr_e( 'حذف البند', 'olama-registration' ); ?>">
                                                        <span class="dashicons dashicons-trash"></span>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td>
                                                <input type="text" name="items[0][description]"
                                                       class="olama-reg-inline-input"
                                                       required
                                                       placeholder="<?php esc_attr_e( 'مثال: رسوم التسجيل، رسوم الباص...', 'olama-registration' ); ?>">
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" name="items[0][amount]"
                                                       value="0.00"
                                                       class="olama-reg-inline-input olama-reg-fee-amount-input"
                                                       required>
                                            </td>
                                            <td style="text-align:center;">
                                                <button type="button" class="olama-reg-btn-danger olama-reg-remove-fee-row-btn"
                                                        title="<?php esc_attr_e( 'حذف البند', 'olama-registration' ); ?>">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="olama-reg-fin-totals">
                                        <td>
                                            <strong><?php esc_html_e( 'الإجمالي الكلي للنموذج:', 'olama-registration' ); ?></strong>
                                        </td>
                                        <td colspan="2">
                                            <strong id="olama-reg-fee-total-label"
                                                    style="font-size:16px; color:var(--reg-primary-dark);">0.00</strong>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="olama-reg-form-actions-inline">
                            <button type="button" class="olama-reg-btn olama-reg-btn--secondary" id="olama-reg-add-fee-row-btn">
                                <span class="dashicons dashicons-plus"></span>
                                <?php esc_html_e( 'إضافة بند آخر', 'olama-registration' ); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="olama-reg-form-actions">
                    <button type="submit" class="olama-reg-btn olama-reg-btn--primary" id="olama-reg-save-fee-template-btn">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e( 'حفظ النموذج', 'olama-registration' ); ?>
                    </button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration-fees' ) ); ?>"
                       class="olama-reg-btn olama-reg-btn--ghost">
                        <span class="dashicons dashicons-no-alt"></span>
                        <?php esc_html_e( 'إلغاء', 'olama-registration' ); ?>
                    </a>
                </div>
            </form>
        </div>
    <?php endif; ?>

</div>
