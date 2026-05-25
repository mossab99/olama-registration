<?php
/**
 * External Customer Modal — Create / Edit with inline children CRUD
 * Shared across pages. On customers page, $cust_modal_rendered = true is set before include.
 * On custom-payments page: renders a minimal quick-add modal instead.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( isset( $cust_modal_rendered ) && $cust_modal_rendered ) :
// ── Full Create/Edit Modal (customers page) ────────────────────────────────
?>
<div id="cust-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.55); z-index:99999; justify-content:center; align-items:flex-start; overflow-y:auto; padding:30px 0;" dir="rtl">
    <div id="cust-modal-box" style="background:#fff; width:600px; max-width:96%; border-radius:14px; box-shadow:0 24px 64px rgba(0,0,0,0.25); margin:auto;">

        <!-- Modal Header -->
        <div style="background:var(--reg-primary); color:#fff; padding:16px 24px; border-radius:14px 14px 0 0; display:flex; align-items:center; justify-content:space-between;">
            <h3 id="cust-modal-title" style="margin:0; font-size:16px; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-businessman" style="font-size:20px;width:20px;height:20px;"></span>
                إضافة عميل جديد
            </h3>
            <button type="button" id="cust-modal-close" style="background:none; border:none; color:rgba(255,255,255,0.85); cursor:pointer; font-size:22px; line-height:1; padding:0; margin:0;">&times;</button>
        </div>

        <!-- Customer Info Fields -->
        <div style="padding:24px; border-bottom:1px solid #e2e8f0;">
            <input type="hidden" id="cust-modal-customer-id" value="">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <label style="display:block; font-weight:700; margin-bottom:6px; font-size:13px; color:var(--reg-primary);">
                        اسم العميل <span style="color:red">*</span>
                    </label>
                    <input type="text" id="cust-modal-name" class="regular-text" style="width:100%;" placeholder="الاسم الكامل">
                </div>
                <div>
                    <label style="display:block; font-weight:700; margin-bottom:6px; font-size:13px; color:var(--reg-primary);">رقم الهاتف</label>
                    <input type="text" id="cust-modal-phone" class="regular-text" style="width:100%;" placeholder="07xxxxxxxx" dir="ltr">
                </div>
            </div>
            <div>
                <label style="display:block; font-weight:700; margin-bottom:6px; font-size:13px; color:var(--reg-primary);">ملاحظات</label>
                <textarea id="cust-modal-notes" style="width:100%; height:52px; resize:vertical; border:1px solid #cbd5e1; border-radius:6px; padding:6px 10px;" placeholder="أي ملاحظات إضافية..."></textarea>
            </div>
        </div>

        <!-- Children Section -->
        <div style="padding:20px 24px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                <label style="font-weight:700; color:var(--reg-primary); font-size:14px;">
                    <span class="dashicons dashicons-groups" style="vertical-align:middle; font-size:16px;width:16px;height:16px;"></span>
                    الأبناء
                </label>
                <button type="button" id="cust-modal-add-child-btn" class="button button-secondary" style="font-size:12px; height:30px; display:flex; align-items:center; gap:4px;">
                    <span class="dashicons dashicons-plus" style="font-size:14px;width:14px;height:14px;margin-top:2px;"></span>
                    إضافة ابن
                </button>
            </div>

            <!-- Saved children list (populated in edit mode via AJAX) -->
            <div id="cust-modal-saved-children" style="display:flex; flex-direction:column; gap:8px; margin-bottom:8px;">
                <!-- Rows injected by JS for edit mode -->
            </div>

            <!-- Inline add-new-child form (always visible when clicked) -->
            <div id="cust-modal-new-child-form" style="display:none; background:#f0fdf4; border:1px dashed #86efac; border-radius:8px; padding:12px; margin-bottom:8px;">
                <div style="display:grid; grid-template-columns:1fr 1fr auto; gap:8px; align-items:flex-end;">
                    <div>
                        <label style="font-size:12px; font-weight:700; display:block; margin-bottom:4px;">اسم الابن <span style="color:red">*</span></label>
                        <input type="text" id="cust-modal-new-child-name" class="regular-text" style="width:100%;" placeholder="الاسم الكامل">
                    </div>
                    <div>
                        <label style="font-size:12px; font-weight:700; display:block; margin-bottom:4px;">الصف</label>
                        <input type="text" id="cust-modal-new-child-grade" class="regular-text" style="width:100%;" placeholder="مثال: رابع أ">
                    </div>
                    <div style="display:flex; gap:6px;">
                        <button type="button" id="cust-modal-save-new-child" class="button button-primary"
                                style="background:var(--reg-success); border-color:var(--reg-success); height:34px; white-space:nowrap;">
                            حفظ
                        </button>
                        <button type="button" id="cust-modal-cancel-new-child" class="button button-secondary" style="height:34px;">إلغاء</button>
                    </div>
                </div>
                <div id="cust-modal-new-child-loading" style="display:none; margin-top:8px; color:var(--reg-primary); font-size:12px;">
                    <span class="spinner is-active" style="float:none; margin:0 4px 0 0;"></span> جاري الحفظ...
                </div>
            </div>

            <!-- Pending new children (for create mode before saving customer) -->
            <div id="cust-modal-pending-children" style="display:flex; flex-direction:column; gap:8px;">
                <!-- Pending child rows added by JS in create mode -->
            </div>

            <div id="cust-modal-no-children" style="display:none; text-align:center; padding:12px; color:#94a3b8; font-size:13px; background:#f8fafc; border-radius:6px; border:1px solid #e2e8f0;">
                لا يوجد أبناء مسجلون بعد.
            </div>
        </div>

        <!-- Footer -->
        <div style="padding:14px 24px; background:#f8fafc; border-top:1px solid #e2e8f0; border-radius:0 0 14px 14px; display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" id="cust-modal-cancel" class="button button-secondary">إلغاء</button>
            <button type="button" id="cust-modal-save" class="button button-primary"
                    style="background:var(--reg-success); border-color:var(--reg-success);">
                <span id="cust-modal-save-label">حفظ</span>
            </button>
        </div>
    </div>
</div>

<?php else :
// ── Quick-Add Modal (custom-payments page) — lightweight ──────────────────
?>
<div id="ext-quick-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.55); z-index:99999; justify-content:center; align-items:center;">
    <div style="background:#fff; width:520px; max-width:95%; border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,0.25); overflow:hidden;" dir="rtl">

        <div style="background:var(--reg-primary); color:#fff; padding:16px 24px; display:flex; align-items:center; justify-content:space-between;">
            <h3 style="margin:0; font-size:16px;">
                <span class="dashicons dashicons-businessman" style="vertical-align:middle; margin-left:6px;"></span>
                إضافة عميل خارجي جديد
            </h3>
            <button type="button" id="modal_ext_btn_cancel" style="background:none; border:none; color:rgba(255,255,255,0.8); cursor:pointer; font-size:22px; line-height:1; padding:0;">&times;</button>
        </div>

        <div style="padding:24px;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <label style="display:block; font-weight:700; margin-bottom:6px; font-size:13px; color:var(--reg-primary);">
                        الاسم <span style="color:red">*</span>
                    </label>
                    <input type="text" id="modal_ext_name" class="regular-text" style="width:100%;" placeholder="اسم العميل">
                </div>
                <div>
                    <label style="display:block; font-weight:700; margin-bottom:6px; font-size:13px; color:var(--reg-primary);">الهاتف</label>
                    <input type="text" id="modal_ext_phone" class="regular-text" style="width:100%;" placeholder="07xxxxxxxx" dir="ltr">
                </div>
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block; font-weight:700; margin-bottom:6px; font-size:13px; color:var(--reg-primary);">ملاحظات</label>
                <textarea id="modal_ext_notes" style="width:100%; height:50px; resize:vertical; border:1px solid #cbd5e1; border-radius:6px; padding:6px 10px;"></textarea>
            </div>

            <div style="border-top:1px solid #e2e8f0; padding-top:16px;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                    <label style="font-weight:700; color:var(--reg-primary); font-size:13px;">أبناء العميل (اختياري)</label>
                    <button type="button" id="modal_ext_add_child_btn" class="button button-secondary" style="font-size:12px;">
                        <span class="dashicons dashicons-plus" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-top:2px;"></span>
                        إضافة ابن
                    </button>
                </div>
                <div id="modal_ext_children_list" style="display:flex; flex-direction:column; gap:8px;"></div>
            </div>
        </div>

        <div style="padding:16px 24px; background:#f8fafc; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" id="modal_ext_btn_cancel2" class="button button-secondary">إلغاء</button>
            <button type="button" id="modal_ext_btn_save" class="button button-primary"
                    style="background:var(--reg-success); border-color:var(--reg-success);">
                حفظ وتحديد العميل
            </button>
        </div>
    </div>
</div>
<?php endif; ?>
