<?php
/**
 * Custom Payments View — DB-backed children checkboxes + quick-add child
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$services      = get_option( 'olama_reg_custom_services', ['دوسية', 'نشاط', 'مواصلات', 'امتحان إضافي'] );
$fee_templates = Olama_Reg_Billing_Fees::get_templates();

$linked_agreement = null;
if ( isset($_GET['from_agreement']) && !empty($_GET['fee_ids']) ) {
    $agr_id = (int) $_GET['from_agreement'];
    $fee_ids = array_map('intval', explode(',', $_GET['fee_ids']));
    
    $agreement = Olama_Reg_Agreement::get($agr_id);
    if ( $agreement ) {
        $fees = Olama_Reg_Agreement_Fees::get_by_agreement($agr_id);
        
        $total_amount = 0;
        $total_discount = 0;
        $selected_fee_children = [];
        
        foreach ( $fees as $f ) {
            if ( in_array( $f->id, $fee_ids ) ) {
                $total_amount += (float) $f->amount;
                $total_discount += (float) $f->discount;
                if ( !empty($f->child_id) ) {
                    $selected_fee_children[] = is_numeric($f->child_id) ? (int)$f->child_id : $f->child_id;
                }
            }
        }
        $selected_fee_children = array_unique($selected_fee_children);
        
        $linked_agreement = [
            'id' => $agreement->id,
            'number' => $agreement->agreement_number,
            'payer_type' => $agreement->payer_type, // 'customer' or 'family'
            'payer_id' => $agreement->payer_id,
            'payer_name' => $agreement->payer_name,
            'participants' => !empty($selected_fee_children) ? $selected_fee_children : $agreement->participant_ids_array, // array of child IDs or UIDs
            'activity_type' => $agreement->activity_type,
            'amount' => $total_amount,
            'discount' => $total_discount,
            'fee_ids' => $fee_ids,
        ];
    }
}
?>

<div class="wrap olama-reg-wrap">
    <div class="olama-reg-header">
        <h1><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'دفعات مخصصة (Custom Payments)', 'olama-registration' ); ?></h1>
    </div>

    <div class="olama-reg-box" style="max-width: 860px; margin-top: 20px;">
        <form id="olama-reg-custom-payment-form">
            <?php wp_nonce_field( 'olama_reg_custom_payment', 'custom_payment_nonce' ); ?>
            
            <?php if ( $linked_agreement ) : ?>
                <div style="background:#f0fdf4; border:1px solid #86efac; border-radius:8px; padding:12px; margin-bottom:20px; font-weight:700; color:var(--reg-success);">
                    <span class="dashicons dashicons-paperclip"></span>
                    مرتبط بالعقد رقم: <?php echo esc_html( $linked_agreement['number'] ); ?>
                </div>
                <input type="hidden" name="linked_agreement_id" value="<?php echo esc_attr( $linked_agreement['id'] ); ?>">
                <input type="hidden" name="linked_fee_ids" value="<?php echo esc_attr( implode(',', $linked_agreement['fee_ids']) ); ?>">
            <?php endif; ?>

            <!-- ── Step 1 Header ────────────────────────────────────────── -->
            <div style="background:var(--reg-primary); color:#fff; padding:12px 20px; border-radius:8px 8px 0 0; font-weight:700; margin:-20px -20px 20px -20px;">
                1. اختيار العميل والمستفيدين
            </div>

            <!-- Customer Type Toggle -->
            <div style="margin-bottom:20px; padding:14px; background:#f1f5f9; border-radius:8px; border:1px solid #cbd5e1;">
                <label style="display:block; font-weight:700; margin-bottom:10px; color:var(--reg-primary);">نوع العميل:</label>
                <div style="display:flex; gap:24px;">
                    <label style="cursor:pointer; display:flex; align-items:center; gap:6px;">
                        <input type="radio" name="customer_type" value="internal" id="cp_type_internal" checked>
                        <span>عائلة مسجلة (Internal)</span>
                    </label>
                    <label style="cursor:pointer; display:flex; align-items:center; gap:6px;">
                        <input type="radio" name="customer_type" value="external" id="cp_type_external">
                        <span>عميل خارجي (Walk-in)</span>
                    </label>
                </div>
            </div>

            <!-- ── Internal Family Section ──────────────────────────────── -->
            <div id="cp_internal_customer_wrap" style="margin-bottom:20px;">
                <label style="display:block; font-weight:700; margin-bottom:8px; color:var(--reg-primary);">بحث عن العائلة:</label>
                <select id="cp_family_search" class="olama-reg-family-search" style="width:100%;">
                    <option value="">-- ابحث باسم الأب، الأم، أو رقم العائلة --</option>
                </select>
                <input type="hidden" id="cp_family_uid" name="family_uid">
            </div>

            <!-- ── External Customer Section ────────────────────────────── -->
            <div id="cp_external_customer_wrap" style="display:none; margin-bottom:20px;">
                <div style="display:flex; gap:12px; align-items:flex-end; margin-bottom:16px;">
                    <div style="flex:1;">
                        <label style="display:block; font-weight:700; margin-bottom:6px; color:var(--reg-primary);">
                            بحث عن عميل خارجي <span style="color:red">*</span>
                        </label>
                        <select id="cp_ext_customer_search" style="width:100%;">
                            <option value="">-- ابحث بالاسم أو الهاتف أو اسم الابن --</option>
                        </select>
                        <input type="hidden" id="cp_ext_customer_id" name="ext_customer_id">
                    </div>
                    <div>
                        <button type="button" id="cp_btn_add_ext_customer" class="button button-secondary"
                                style="height:38px; display:flex; align-items:center; gap:5px; white-space:nowrap;">
                            <span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;margin-top:2px;"></span>
                            عميل جديد
                        </button>
                    </div>
                </div>

                <!-- Children DB checkboxes (loaded after customer selection) -->
                <div id="cp_ext_children_section" style="display:none;">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                        <label style="font-weight:700; color:var(--reg-primary); font-size:14px;">
                            اختيار الأبناء المستفيدين:
                        </label>
                        <button type="button" id="cp_ext_quick_add_child_btn" class="button button-secondary"
                                style="font-size:12px; height:28px; display:flex; align-items:center; gap:4px;">
                            <span class="dashicons dashicons-plus" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-top:1px;"></span>
                            إضافة ابن جديد
                        </button>
                    </div>

                    <!-- DB-backed checkboxes — each has data-child-id -->
                    <div id="cp_ext_students_list" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px,1fr)); gap:10px; margin-bottom:12px;">
                        <!-- Injected by JS after customer selection -->
                    </div>

                    <!-- Quick-add new child during payment (saves to DB first) -->
                    <div id="cp_ext_new_child_row" style="display:none; background:#f0fdf4; border:1px dashed #86efac; border-radius:8px; padding:12px; margin-top:8px;">
                        <div style="display:flex; gap:8px; align-items:flex-end;">
                            <div style="flex:2;">
                                <label style="font-size:12px; font-weight:700; margin-bottom:4px; display:block;">اسم الابن <span style="color:red">*</span></label>
                                <input type="text" id="cp_new_child_name" class="regular-text" style="width:100%;" placeholder="الاسم الكامل">
                            </div>
                            <div style="flex:1;">
                                <label style="font-size:12px; font-weight:700; margin-bottom:4px; display:block;">الصف</label>
                                <input type="text" id="cp_new_child_grade" class="regular-text" style="width:100%;" placeholder="مثال: رابع">
                            </div>
                            <button type="button" id="cp_new_child_save_btn" class="button button-primary"
                                    style="background:var(--reg-success); border-color:var(--reg-success); height:34px; white-space:nowrap;">
                                حفظ وإضافة
                            </button>
                            <button type="button" id="cp_new_child_cancel_btn" class="button button-secondary" style="height:34px;">إلغاء</button>
                        </div>
                        <div id="cp_new_child_loading" style="display:none; margin-top:8px; font-size:12px; color:var(--reg-primary);">
                            <span class="spinner is-active" style="float:none; margin:0 4px 0 0;"></span> جاري الحفظ...
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div id="cp_ext_no_children_msg" style="display:none; text-align:center; color:#64748b; font-size:13px; padding:12px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0;">
                        لا يوجد أبناء مسجلون لهذا العميل بعد. يمكنك إضافة ابن جديد بالضغط على الزر أعلاه.
                    </div>

                    <!-- "No child selected" pay directly -->
                    <div style="margin-top:8px;">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px; color:#475569;">
                            <input type="checkbox" id="cp_ext_pay_customer_direct" style="width:16px; height:16px;">
                            <span>دفعة مباشرة للعميل (بدون تحديد ابن)</span>
                        </label>
                    </div>
                </div>

                <div id="cp_ext_no_customer_msg" style="text-align:center; color:#94a3b8; font-size:13px; padding:16px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0;">
                    ابحث عن عميل خارجي أعلاه لعرض الأبناء المسجلين
                </div>
            </div>

            <!-- Internal students container -->
            <div id="cp_students_container" style="display:none; margin-bottom:28px; background:#f8fafc; padding:15px; border-radius:8px; border:1px solid #e2e8f0;">
                <label style="display:block; font-weight:700; margin-bottom:12px; color:var(--reg-primary);">اختيار الطلاب المستفيدين:</label>
                <div id="cp_students_list" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px,1fr)); gap:10px;">
                    <!-- Injected via AJAX -->
                </div>
                <div id="cp_students_error" style="color:#dc2626; font-size:13px; margin-top:8px; display:none;">يجب اختيار طالب واحد على الأقل.</div>
            </div>

            <!-- ── Step 2 Header ────────────────────────────────────────── -->
            <div style="background:var(--reg-primary); color:#fff; padding:12px 20px; border-radius:4px 4px 0 0; font-weight:700; margin:0 -20px 20px -20px;">
                2. تفاصيل الخدمة والرسوم
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
                <div>
                    <label style="display:block; font-weight:700; margin-bottom:8px; color:var(--reg-primary);">نوع الخدمة:</label>
                    <select id="cp_service_type" name="service_type" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" required>
                        <option value="">-- اختر الخدمة --</option>
                        <?php foreach ( $services as $srv ) : ?>
                            <option value="<?php echo esc_attr($srv); ?>"><?php echo esc_html($srv); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-weight:700; margin-bottom:8px; color:var(--reg-primary);">نموذج الرسوم المرجعي:</label>
                    <select id="cp_fee_template" name="fee_template_id" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                        <option value="">-- اختر نموذج الرسوم --</option>
                        <?php foreach ( $fee_templates as $fee ) :
                            $total_val = array_sum( array_column( $fee->items, 'amount' ) );
                        ?>
                            <option value="<?php echo esc_attr($fee->id); ?>" data-amount="<?php echo esc_attr($total_val); ?>">
                                <?php echo esc_html($fee->template_name); ?> (<?php echo number_format($total_val, 2); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; margin-bottom:28px;">
                <div>
                    <label style="display:block; font-weight:700; margin-bottom:8px; color:var(--reg-primary);">قيمة الدفعة (للفرد):</label>
                    <div style="position:relative;">
                        <input type="number" id="cp_amount" name="amount" step="0.01" min="0.01"
                               style="width:100%; padding:8px 40px 8px 8px; border:1px solid #cbd5e1; border-radius:6px;" required>
                        <span style="position:absolute; left:10px; top:10px; color:#64748b; font-weight:700;">د.أ</span>
                    </div>
                </div>
                <div>
                    <label style="display:block; font-weight:700; margin-bottom:8px; color:var(--reg-primary);">الخصم الممنوح:</label>
                    <div style="position:relative;">
                        <input type="number" id="cp_discount" name="discount" step="0.01" min="0" value="0.00"
                               style="width:100%; padding:8px 40px 8px 8px; border:1px solid #cbd5e1; border-radius:6px;">
                        <span style="position:absolute; left:10px; top:10px; color:#64748b; font-weight:700;">د.أ</span>
                    </div>
                </div>
                <div>
                    <label style="display:block; font-weight:700; margin-bottom:8px; color:var(--reg-primary);">طريقة الدفع:</label>
                    <select name="payment_method" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" required>
                        <option value="cash">نقدي (كاش)</option>
                        <option value="bank_transfer">تحويل بنكي</option>
                        <option value="cheque">شيك بنكي</option>
                        <option value="online">دفع إلكتروني</option>
                    </select>
                </div>
            </div>

            <div style="text-align:left; border-top:1px solid #e2e8f0; padding-top:20px; display:flex; align-items:center; justify-content:space-between;">
                <div style="font-weight:700; color:var(--reg-primary);">
                    الإجمالي: <span id="cp_total_display" style="color:var(--reg-success); font-size:20px;">0.00 د.أ</span>
                </div>
                <button type="submit" id="cp_submit_btn" class="button button-primary"
                        style="background:var(--reg-success); border-color:var(--reg-success); font-size:16px; padding:6px 28px;">
                    حفظ وإصدار الفاتورة والسند
                </button>
            </div>

            <div id="cp_loading" style="display:none; text-align:center; margin-top:15px; color:var(--reg-primary); font-weight:700;">
                <span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> جاري المعالجة...
            </div>
            <div id="cp_response_msg" style="margin-top:15px; display:none;"></div>
        </form>
    </div>
</div>

<?php include OLAMA_REG_PATH . 'admin/views/partial-customer-modal.php'; ?>

<?php if ( $linked_agreement ) : ?>
<script>
    window.OS_LINKED_AGREEMENT = <?php echo wp_json_encode( $linked_agreement ); ?>;
</script>
<?php endif; ?>
