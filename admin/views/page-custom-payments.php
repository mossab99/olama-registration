<?php
/**
 * Custom Payments View
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$services = get_option( 'olama_reg_custom_services', ['دوسية', 'نشاط', 'مواصلات', 'امتحان إضافي'] );
$fee_templates = Olama_Reg_Billing_Fees::get_templates();
?>

<div class="wrap olama-reg-wrap">
    <div class="olama-reg-header">
        <h1><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'دفعات مخصصة (Custom Payments)', 'olama-registration' ); ?></h1>
    </div>

    <div class="olama-reg-box" style="max-width: 800px; margin-top: 20px;">
        <form id="olama-reg-custom-payment-form">
            <?php wp_nonce_field( 'olama_reg_custom_payment', 'custom_payment_nonce' ); ?>
            
            <div style="background: var(--reg-primary); color: #fff; padding: 12px 20px; border-radius: 8px 8px 0 0; font-weight: 700; margin: -20px -20px 20px -20px;">
                1. اختيار العائلة والطلاب
            </div>

            <!-- Customer Type Toggle -->
            <div style="margin-bottom: 20px; padding: 15px; background: #f1f5f9; border-radius: 8px; border: 1px solid #cbd5e1;">
                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: var(--reg-primary);">نوع العميل:</label>
                <div style="display: flex; gap: 20px;">
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 5px;">
                        <input type="radio" name="customer_type" value="internal" checked> 
                        عائلة مسجلة (Internal)
                    </label>
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 5px;">
                        <input type="radio" name="customer_type" value="external"> 
                        عميل خارجي (Walk-in/External)
                    </label>
                </div>
            </div>

            <!-- Family Search (Internal) -->
            <div id="cp_internal_customer_wrap" style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 700; margin-bottom: 8px; color: var(--reg-primary);">بحث عن العائلة:</label>
                <select id="cp_family_search" class="olama-reg-family-search" style="width:100%;">
                    <option value="">-- ابحث باسم الأب، الأم، أو رقم العائلة --</option>
                </select>
                <input type="hidden" id="cp_family_uid" name="family_uid" />
            </div>

            <!-- External Customer Fields -->
            <div id="cp_external_customer_wrap" style="display: none; margin-bottom: 20px; padding: 15px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; font-weight: 700; margin-bottom: 5px; color: var(--reg-primary);">اسم العميل الخارجي (الأب/الأم) <span style="color:red">*</span></label>
                        <input type="text" id="cp_ext_name" class="regular-text" style="width:100%;" placeholder="مثال: سارة محمد">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 700; margin-bottom: 5px; color: var(--reg-primary);">رقم الهاتف <span style="color:red">*</span></label>
                        <input type="text" id="cp_ext_phone" class="regular-text" style="width:100%;" placeholder="مثال: 0791234567">
                    </div>
                </div>

                <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 15px 0;">
                
                <label style="display: block; font-weight: 700; margin-bottom: 10px; color: var(--reg-primary);">إضافة أبناء للعميل الخارجي <span style="color:red">*</span></label>
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <input type="text" id="cp_ext_child_name" placeholder="اسم الابن/الابنة" class="regular-text" style="flex: 2;">
                    <input type="text" id="cp_ext_child_grade" placeholder="الصف / المرحلة (اختياري)" class="regular-text" style="flex: 1;">
                    <button type="button" id="cp_ext_add_child_btn" class="button button-secondary">إضافة ابن</button>
                </div>

                <div id="cp_ext_students_list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">
                    <!-- External children appended here via JS -->
                </div>
            </div>

            <!-- Students Container -->
            <div id="cp_students_container" style="display: none; margin-bottom: 30px; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <label style="display: block; font-weight: 700; margin-bottom: 12px; color: var(--reg-primary);">اختيار الطلاب المستفيدين من الخدمة:</label>
                <div id="cp_students_list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">
                    <!-- Checkboxes injected via AJAX -->
                </div>
                <div id="cp_students_error" style="color: #dc2626; font-size: 13px; margin-top: 8px; display: none;">يجب اختيار طالب واحد على الأقل.</div>
            </div>

            <div style="background: var(--reg-primary); color: #fff; padding: 12px 20px; border-radius: 4px 4px 0 0; font-weight: 700; margin: 0 -20px 20px -20px;">
                2. تفاصيل الخدمة والرسوم
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <!-- Service Type -->
                <div>
                    <label style="display: block; font-weight: 700; margin-bottom: 8px; color: var(--reg-primary);">نوع الخدمة:</label>
                    <select id="cp_service_type" name="service_type" style="width:100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                        <option value="">-- اختر الخدمة --</option>
                        <?php foreach ( $services as $srv ): ?>
                            <option value="<?php echo esc_attr( $srv ); ?>"><?php echo esc_html( $srv ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Fee Template -->
                <div>
                    <label style="display: block; font-weight: 700; margin-bottom: 8px; color: var(--reg-primary);">نموذج الرسوم المرجعي:</label>
                    <select id="cp_fee_template" name="fee_template_id" style="width:100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                        <option value="">-- اختر نموذج الرسوم --</option>
                        <?php foreach ( $fee_templates as $fee ): 
                            $total_val = 0;
                            foreach ( $fee->items as $item ) {
                                $total_val += (float) ( $item['amount'] ?? 0 );
                            }
                        ?>
                            <option value="<?php echo esc_attr( $fee->id ); ?>" data-amount="<?php echo esc_attr( $total_val ); ?>">
                                <?php echo esc_html( $fee->template_name ); ?> (<?php echo number_format( $total_val, 2 ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                <!-- Amount -->
                <div>
                    <label style="display: block; font-weight: 700; margin-bottom: 8px; color: var(--reg-primary);">قيمة الدفعة (للطالب الواحد):</label>
                    <div style="position: relative;">
                        <input type="number" id="cp_amount" name="amount" step="0.01" min="0.01" style="width:100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;" required />
                        <span style="position: absolute; left: 10px; top: 10px; color: #64748b; font-weight: 700;">د.أ</span>
                    </div>
                    <p style="font-size: 12px; color: #64748b; margin-top: 4px;">إجمالي الفاتورة سيكون (القيمة × عدد الطلاب)</p>
                </div>
                
                <!-- Discount -->
                <div>
                    <label style="display: block; font-weight: 700; margin-bottom: 8px; color: var(--reg-primary);">الخصم الممنوح:</label>
                    <div style="position: relative;">
                        <input type="number" id="cp_discount" name="discount" step="0.01" min="0" value="0.00" style="width:100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;" />
                        <span style="position: absolute; left: 10px; top: 10px; color: #64748b; font-weight: 700;">د.أ</span>
                    </div>
                </div>
                
                <!-- Payment Method -->
                <div>
                    <label style="display: block; font-weight: 700; margin-bottom: 8px; color: var(--reg-primary);">طريقة الدفع:</label>
                    <select name="payment_method" style="width:100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                        <option value="cash">نقدي (كاش)</option>
                        <option value="bank_transfer">تحويل بنكي</option>
                        <option value="cheque">شيك بنكي</option>
                        <option value="online">دفع إلكتروني</option>
                    </select>
                </div>
            </div>

            <div style="text-align: left; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                <div id="cp_summary" style="display: inline-block; margin-right: 20px; font-weight: 700; color: var(--reg-primary); vertical-align: middle;">
                    الإجمالي: <span id="cp_total_display" style="color: var(--reg-success); font-size: 18px;">0.00 د.أ</span>
                </div>
                <button type="submit" id="cp_submit_btn" class="button button-primary" style="background: var(--reg-success); border-color: var(--reg-success); font-size: 16px; padding: 6px 24px;">
                    حفظ وإصدار الفاتورة والسند
                </button>
            </div>
            
            <div id="cp_loading" style="display:none; text-align:center; margin-top:15px; color:var(--reg-primary); font-weight:700;">
                <span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> جاري المعالجة... يرجى الانتظار
            </div>
            <div id="cp_response_msg" style="margin-top: 15px; display: none;"></div>
        </form>
    </div>
</div>
