<?php
/**
 * View: Agreement Edit/Create
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$id = (int) ( $_GET['id'] ?? 0 );
$is_new = empty( $id );
$agreement = null;

if ( ! $is_new ) {
    $agreement = Olama_Reg_Agreement::get( $id );
    if ( ! $agreement ) {
        wp_die( __( 'العقد غير موجود.', 'olama-registration' ) );
    }
} else {
    // Defaults for new
    $agreement = (object) [
        'id'               => 0,
        'agreement_number' => __( 'تلقائي', 'olama-registration' ),
        'payer_type'       => 'customer',
        'payer_id'         => '',
        'participant_type' => 'child',
        'participant_id'   => '',
        'activity_type'    => '',
        'academic_year_id' => 0,
        'start_date'       => current_time( 'Y-m-d' ),
        'end_date'         => '',
        'status'           => 'draft',
        'notes'            => '',
        'total_amount'     => 0,
        'payer_name'       => '',
        'participant_name' => '',
        'template_id'      => 0,
    ];
}

?>
<div class="wrap os-wrap" id="os-agreement-app" data-id="<?php echo esc_attr( $agreement->id ); ?>">
    <h1 class="wp-heading-inline">
        <?php echo $is_new ? esc_html__( 'إضافة عقد جديد', 'olama-registration' ) : esc_html__( 'تعديل العقد', 'olama-registration' ) . ' #' . esc_html( $agreement->agreement_number ); ?>
    </h1>
    
    <span class="os-badge <?php echo $agreement->status === 'active' ? 'badge-success' : ( $agreement->status === 'draft' ? 'badge-warning' : 'badge-secondary' ); ?>" id="os-agr-status-badge">
        <?php echo esc_html( $agreement->status ); ?>
    </span>

    <hr class="wp-header-end">

    <h2 class="nav-tab-wrapper os-nav-tabs">
        <a href="#tab-header" class="nav-tab nav-tab-active"><?php esc_html_e( 'البيانات الأساسية', 'olama-registration' ); ?></a>
        <a href="#tab-fees" class="nav-tab <?php echo $is_new ? 'os-disabled' : ''; ?>"><?php esc_html_e( 'الرسوم', 'olama-registration' ); ?></a>
        <a href="#tab-clauses" class="nav-tab <?php echo $is_new ? 'os-disabled' : ''; ?>"><?php esc_html_e( 'البنود والشروط', 'olama-registration' ); ?></a>
    </h2>

    <div class="os-tab-content active" id="tab-header">
        <div class="os-card">
            <form id="os-form-agreement-header">
                <input type="hidden" name="id" value="<?php echo esc_attr( $agreement->id ); ?>">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label><?php esc_html_e( 'نوع الجهة الدافعة', 'olama-registration' ); ?></label></th>
                        <td>
                            <label>
                                <input type="radio" name="payer_type" value="customer" <?php checked( $agreement->payer_type, 'customer' ); ?>>
                                <?php esc_html_e( 'عميل (Walk-in)', 'olama-registration' ); ?>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="payer_type" value="family" <?php checked( $agreement->payer_type, 'family' ); ?>>
                                <?php esc_html_e( 'عائلة (مدرسة)', 'olama-registration' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e( 'الجهة الدافعة', 'olama-registration' ); ?></label></th>
                        <td>
                            <select name="payer_id" id="os-agr-payer" style="width: 100%; max-width: 400px;" required>
                                <?php if ( $agreement->payer_id ) : ?>
                                    <option value="<?php echo esc_attr( $agreement->payer_id ); ?>" selected="selected"><?php echo esc_html( $agreement->payer_name ); ?></option>
                                <?php endif; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e( 'المشترك', 'olama-registration' ); ?></label></th>
                        <td>
                            <select name="participant_id[]" id="os-agr-participant" style="width: 100%; max-width: 400px;" required multiple="multiple">
                                <?php
                                if ( ! empty( $agreement->participant_ids_array ) ) {
                                    foreach ( $agreement->participant_ids_array as $pid ) {
                                        if ( $pid > 0 && ! empty( $agreement->participant_names[$pid] ) ) {
                                            echo '<option value="' . esc_attr( $pid ) . '" selected>' . esc_html( $agreement->participant_names[$pid] ) . '</option>';
                                        }
                                    }
                                } elseif ( $agreement->participant_id > 0 && ! empty( $agreement->participant_name ) ) {
                                    echo '<option value="' . esc_attr( $agreement->participant_id ) . '" selected>' . esc_html( $agreement->participant_name ) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e( 'نوع النشاط', 'olama-registration' ); ?></label></th>
                        <td>
                            <select name="activity_type" id="os-agr-activity" style="width: 100%; max-width: 400px;" required>
                                <option value=""><?php esc_html_e( 'اختر النشاط', 'olama-registration' ); ?></option>
                                <option value="kindergarten" <?php selected( $agreement->activity_type, 'kindergarten' ); ?>><?php esc_html_e( 'رياض الأطفال', 'olama-registration' ); ?></option>
                                <option value="summer_club" <?php selected( $agreement->activity_type, 'summer_club' ); ?>><?php esc_html_e( 'النادي الصيفي', 'olama-registration' ); ?></option>
                                <option value="karate" <?php selected( $agreement->activity_type, 'karate' ); ?>><?php esc_html_e( 'كاراتيه', 'olama-registration' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e( 'تاريخ البداية', 'olama-registration' ); ?></label></th>
                        <td>
                            <input type="text" name="start_date" class="os-datepicker" value="<?php echo esc_attr( $agreement->start_date ); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e( 'تاريخ النهاية', 'olama-registration' ); ?></label></th>
                        <td>
                            <input type="text" name="end_date" class="os-datepicker" value="<?php echo esc_attr( $agreement->end_date ); ?>">
                            <p class="description"><?php esc_html_e( 'اختياري', 'olama-registration' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e( 'ملاحظات', 'olama-registration' ); ?></label></th>
                        <td>
                            <textarea name="notes" rows="4" style="width: 100%; max-width: 600px;"><?php echo esc_textarea( $agreement->notes ); ?></textarea>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary" id="os-btn-save-header">
                        <?php esc_html_e( 'حفظ البيانات', 'olama-registration' ); ?>
                    </button>
                    <span class="spinner"></span>
                </p>
            </form>
        </div>
    </div>

    <div class="os-tab-content" id="tab-fees" style="display:none;">
        <div class="os-card">
            <?php if ( $is_new ) : ?>
                <p><?php esc_html_e( 'الرجاء حفظ البيانات الأساسية أولاً.', 'olama-registration' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped" id="os-agr-fees-table" data-agr-id="<?php echo esc_attr( $id ); ?>">
                    <thead>
                        <tr>
                            <th style="width: 15%;"><?php esc_html_e( 'نوع الرسم', 'olama-registration' ); ?></th>
                            <th style="width: 25%;"><?php esc_html_e( 'البيان', 'olama-registration' ); ?></th>
                            <th style="width: 15%;"><?php esc_html_e( 'المبلغ', 'olama-registration' ); ?></th>
                            <th style="width: 10%;"><?php esc_html_e( 'الخصم', 'olama-registration' ); ?></th>
                            <th style="width: 10%;"><?php esc_html_e( 'الصافي', 'olama-registration' ); ?></th>
                            <th style="width: 15%;"><?php esc_html_e( 'تاريخ الاستحقاق', 'olama-registration' ); ?></th>
                            <th style="width: 10%;"><?php esc_html_e( 'الحالة', 'olama-registration' ); ?></th>
                            <th style="width: 10%;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $templates = Olama_Reg_Billing_Fees::get_templates();
                        $fees = Olama_Reg_Agreement_Fees::get_by_agreement( $id );
                        if ( $fees ) {
                            foreach ( $fees as $fee ) {
                                $is_locked = in_array( $fee->paid_status, [ 'invoiced', 'paid' ], true );
                                ?>
                                <tr data-fee-id="<?php echo esc_attr( $fee->id ); ?>">
                                    <td>
                                        <select name="fee_category" style="width:100%" class="os-agr-fee-template-select" <?php disabled( $is_locked ); ?>>
                                            <option value="general" data-amount="0"><?php esc_html_e( 'عام', 'olama-registration' ); ?></option>
                                            <?php
                                            foreach ( $templates as $tpl ) {
                                                $total = 0;
                                                foreach ( $tpl->items as $it ) {
                                                    $total += (float) ( $it['amount'] ?? 0 );
                                                }
                                                // We store the template ID in fee_category to link it
                                                $selected = selected( $fee->fee_category, $tpl->id, false );
                                                // Fallback for old textual categories: if name matches
                                                if ( ! $selected && $fee->fee_category === $tpl->template_name ) {
                                                    $selected = 'selected="selected"';
                                                }
                                                echo '<option value="' . esc_attr( $tpl->id ) . '" data-name="' . esc_attr( $tpl->template_name ) . '" data-amount="' . esc_attr( $total ) . '" ' . $selected . '>' . esc_html( $tpl->template_name ) . '</option>';
                                            }
                                            // Keep custom value if not found
                                            if ( $fee->fee_category !== 'general' && ! is_numeric( $fee->fee_category ) ) {
                                                echo '<option value="' . esc_attr( $fee->fee_category ) . '" selected>' . esc_html( $fee->fee_category ) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="label" value="<?php echo esc_attr( $fee->label ); ?>" style="width:100%" <?php disabled( $is_locked ); ?>>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="amount" value="<?php echo esc_attr( $fee->amount ); ?>" style="width:100%" class="os-agr-fee-calc" <?php disabled( $is_locked ); ?>>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="discount" value="<?php echo esc_attr( $fee->discount ); ?>" style="width:100%" class="os-agr-fee-calc" <?php disabled( $is_locked ); ?>>
                                    </td>
                                    <td>
                                        <span class="os-agr-fee-net"><?php echo number_format( (float) $fee->net_amount, 3 ); ?></span>
                                    </td>
                                    <td>
                                        <input type="text" name="due_date" value="<?php echo esc_attr( $fee->due_date ); ?>" style="width:100%" class="os-datepicker" <?php disabled( $is_locked ); ?>>
                                    </td>
                                    <td>
                                        <?php echo esc_html( $fee->paid_status ); ?>
                                    </td>
                                    <td>
                                        <?php if ( ! $is_locked ) : ?>
                                            <button type="button" class="button button-small os-agr-save-fee"><?php esc_html_e( 'حفظ', 'olama-registration' ); ?></button>
                                            <button type="button" class="button button-small os-agr-delete-fee" style="color:red;">X</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" style="text-align:left;"><strong><?php esc_html_e( 'الإجمالي:', 'olama-registration' ); ?></strong></td>
                            <td colspan="4"><strong><span id="os-agr-total-label"><?php echo number_format( (float) $agreement->total_amount, 3 ); ?></span> JD</strong></td>
                        </tr>
                    </tfoot>
                </table>
                <div style="margin-top: 15px;">
                    <button type="button" class="button" id="os-agr-add-fee-row"><?php esc_html_e( 'إضافة بند رسوم', 'olama-registration' ); ?></button>
                    <?php if ( $agreement->status === 'active' ) : ?>
                        <button type="button" class="button button-primary" id="os-agr-open-invoice-modal" style="float:left;"><?php esc_html_e( 'إصدار فاتورة بالرسوم', 'olama-registration' ); ?></button>
                    <?php endif; ?>
                </div>

                <!-- Hidden template for new row -->
                <table style="display:none;">
                    <tbody id="os-agr-fee-row-template">
                        <tr data-fee-id="0">
                            <td>
                                <select name="fee_category" style="width:100%" class="os-agr-fee-template-select">
                                    <option value="general" data-amount="0"><?php esc_html_e( 'عام', 'olama-registration' ); ?></option>
                                    <?php
                                    foreach ( $templates as $tpl ) {
                                        $total = 0;
                                        foreach ( $tpl->items as $it ) {
                                            $total += (float) ( $it['amount'] ?? 0 );
                                        }
                                        echo '<option value="' . esc_attr( $tpl->id ) . '" data-name="' . esc_attr( $tpl->template_name ) . '" data-amount="' . esc_attr( $total ) . '">' . esc_html( $tpl->template_name ) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                            <td><input type="text" name="label" class="os-inline-input" style="width:100%" placeholder="<?php esc_attr_e( 'البيان', 'olama-registration' ); ?>"></td>
                            <td><input type="number" step="0.01" name="amount" value="0.00" class="os-inline-input os-agr-fee-calc" style="width:100%"></td>
                            <td><input type="number" step="0.01" name="discount" value="0.00" class="os-inline-input os-agr-fee-calc" style="width:100%"></td>
                            <td><span class="os-agr-fee-net">0.000</span></td>
                            <td><input type="text" name="due_date" class="os-inline-input os-datepicker" style="width:100%"></td>
                            <td>unpaid</td>
                            <td>
                                <button type="button" class="button button-small os-agr-save-fee"><?php esc_html_e( 'حفظ', 'olama-registration' ); ?></button>
                                <button type="button" class="button button-small os-agr-delete-fee" style="color:red;">X</button>
                            </td>
                        </tr>
                    </tbody>
                </table>

            <?php endif; ?>
        </div>
    </div>
    
    <div class="os-tab-content" id="tab-clauses" style="display:none;">
        <div class="os-card">
            <?php if ( $is_new ) : ?>
                <p><?php esc_html_e( 'الرجاء حفظ البيانات الأساسية أولاً.', 'olama-registration' ); ?></p>
            <?php else : ?>
                <div style="margin-bottom: 20px;">
                    <div style="display:flex; gap:10px; align-items:flex-start;">
                        <textarea id="os-agr-new-clause" rows="3" style="width:100%; max-width:500px;" placeholder="<?php esc_attr_e( 'أدخل البند هنا...', 'olama-registration' ); ?>"></textarea>
                        
                        <div>
                            <select id="os-agr-clause-bank-select" style="max-width:300px;">
                                <option value=""><?php esc_html_e( '-- اختر من البنود العامة --', 'olama-registration' ); ?></option>
                                <?php
                                $bank_clauses = Olama_Reg_Clause_Bank::get_active();
                                foreach ( $bank_clauses as $bc ) {
                                    echo '<option value="' . esc_attr( $bc->clause_text ) . '">' . esc_html( $bc->title ) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <button type="button" class="button button-secondary" id="os-agr-add-clause" data-agr-id="<?php echo esc_attr( $id ); ?>" style="margin-top:10px;"><?php esc_html_e( 'إضافة بند', 'olama-registration' ); ?></button>
                </div>
                
                <ul id="os-agr-clauses-list" style="margin:0; padding:0; list-style:none;">
                    <?php
                    $clauses = Olama_Reg_Agreement_Clauses::get_by_agreement( $id );
                    if ( $clauses ) {
                        foreach ( $clauses as $clause ) {
                            ?>
                            <li data-clause-id="<?php echo esc_attr( $clause->id ); ?>" style="background:#fff; border:1px solid #ccd0d4; padding:10px; margin-bottom:5px; cursor:move;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span class="dashicons dashicons-menu" style="color:#ccc; margin-left:10px;"></span>
                                    <textarea class="os-agr-clause-text" style="flex-grow:1; margin-left:10px;" rows="2"><?php echo esc_textarea( $clause->clause_text ); ?></textarea>
                                    <button type="button" class="button button-small os-agr-save-clause" style="margin-right:5px;"><?php esc_html_e( 'حفظ', 'olama-registration' ); ?></button>
                                    <button type="button" class="button button-small os-agr-delete-clause" style="color:red; margin-right:5px;">X</button>
                                </div>
                            </li>
                            <?php
                        }
                    }
                    ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ( ! $is_new && $agreement->status === 'active' ) : ?>
<!-- Invoice Generation Modal -->
<div id="os-agr-invoice-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:99999;">
    <div style="background:#fff; width:500px; margin:100px auto; padding:20px; border-radius:5px; box-shadow:0 3px 10px rgba(0,0,0,0.2);">
        <h3><?php esc_html_e( 'إصدار فاتورة', 'olama-registration' ); ?></h3>
        <p><?php esc_html_e( 'الرجاء تحديد الرسوم التي ترغب بفوترتها:', 'olama-registration' ); ?></p>
        
        <form id="os-agr-invoice-form">
            <input type="hidden" name="agreement_id" value="<?php echo esc_attr( $id ); ?>">
            <table class="wp-list-table widefat striped" style="margin-bottom:15px;">
                <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox" id="os-agr-inv-check-all" checked></th>
                        <th><?php esc_html_e( 'الرسم', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'المبلغ', 'olama-registration' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $has_unpaid = false;
                    if ( ! empty( $fees ) ) {
                        foreach ( $fees as $fee ) {
                            if ( $fee->paid_status === 'unpaid' ) {
                                $has_unpaid = true;
                                ?>
                                <tr>
                                    <td><input type="checkbox" name="fee_ids[]" value="<?php echo esc_attr( $fee->id ); ?>" class="os-agr-inv-check" checked></td>
                                    <td><?php echo esc_html( $fee->label ?: $fee->fee_category ); ?></td>
                                    <td><?php echo number_format( (float) $fee->net_amount, 3 ); ?></td>
                                </tr>
                                <?php
                            }
                        }
                    }
                    if ( ! $has_unpaid ) {
                        echo '<tr><td colspan="3">' . esc_html__( 'لا يوجد رسوم غير مفوترة.', 'olama-registration' ) . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
            
            <div style="text-align:left;">
                <button type="button" class="button" id="os-agr-close-invoice-modal"><?php esc_html_e( 'إلغاء', 'olama-registration' ); ?></button>
                <button type="submit" class="button button-primary" <?php disabled( ! $has_unpaid ); ?>><?php esc_html_e( 'تأكيد الإصدار', 'olama-registration' ); ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
