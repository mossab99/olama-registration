<?php
/**
 * 3-Tab Family Form — Premium Redesign
 * Variables available: $family (object|null), $family_uid (string), $active_tab (string)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$f = $family; // shorthand

// Resolve active tab from URL
$active_tab      = sanitize_text_field( $_GET['tab'] ?? 'family' );
$open_student_uid = sanitize_text_field( $_GET['student_uid'] ?? '' );

// Get students for tab 2
$students = $f ? Olama_Reg_Student::get_family_students( $family_uid ) : [];

// Academic years for financial tab
$academic_years = [];
if ( class_exists( 'Olama_School_Academic' ) ) {
    $academic_years = (array) ( Olama_School_Academic::get_years() ?: [] );
}
$active_year_id = 0;
if ( class_exists( 'Olama_School_Academic' ) ) {
    $ay = Olama_School_Academic::get_active_year();
    if ( $ay ) $active_year_id = (int) $ay->id;
}

// Nationalities list
$nationalities = [ 'سعودي', 'أردني', 'مصري', 'سوري', 'لبناني', 'فلسطيني', 'يمني', 'عراقي', 'إماراتي', 'كويتي', 'بحريني', 'قطري', 'عُماني', 'مغربي', 'تونسي', 'ليبي', 'جزائري', 'سوداني', 'أخرى' ];

$val = fn( string $k, string $default = '' ) => esc_attr( $f->$k ?? $default );

// Read family info from Users & Permissions if available
$school_family = null;
if ( class_exists( 'Olama_School_Family' ) && ! empty( $family_uid ) ) {
    $school_family = Olama_School_Family::get_family( $family_uid );
}

$father_mobile_val = $school_family ? $school_family->father_mobile : ($f->father_mobile ?? '');
$mother_mobile_val = $school_family ? $school_family->mother_mobile : ($f->mother_mobile ?? '');
?>

<!-- UID Hero Card (only when editing) -->
<?php if ( $f && $family_uid ): ?>
<div class="olama-reg-uid-hero">
    <span class="dashicons dashicons-id-alt" style="font-size:36px;width:36px;height:36px;color:rgba(255,255,255,0.9);flex-shrink:0;"></span>
    <div>
        <span class="olama-reg-uid-hero-label"><?php esc_html_e( 'رقم ملف العائلة', 'olama-registration' ); ?></span>
        <div class="olama-reg-uid-hero-value" onclick="navigator.clipboard?.writeText('<?php echo esc_js( $family_uid ); ?>')" title="<?php esc_attr_e( 'انقر للنسخ', 'olama-registration' ); ?>">
            <?php echo esc_html( $family_uid ); ?>
        </div>
        <div class="olama-reg-uid-hero-meta">
            <?php
            $status_label = $f->is_active ? __( 'حساب فعال', 'olama-registration' ) : __( 'حساب غير فعال', 'olama-registration' );
            $student_count = count( $students );
            printf( esc_html__( '%s · %d طالب/طالبة', 'olama-registration' ), $status_label, $student_count );
            ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="olama-reg-form-wrapper" id="olama-reg-form-wrapper"
     data-family-uid="<?php echo esc_attr( $family_uid ); ?>">

    <!-- ── TAB NAV ──────────────────────────────────────────────────────── -->
    <nav class="olama-reg-tabs" role="tablist">
        <button class="olama-reg-tab <?php echo $active_tab === 'family' ? 'active' : ''; ?>"
                data-tab="family" role="tab">
            <span class="dashicons dashicons-groups"></span>
            <?php esc_html_e( 'بيانات العائلة', 'olama-registration' ); ?>
        </button>
        <button class="olama-reg-tab <?php echo $active_tab === 'students' ? 'active' : ''; ?>"
                data-tab="students" role="tab">
            <span class="dashicons dashicons-admin-users"></span>
            <?php esc_html_e( 'بيانات الطلبة', 'olama-registration' ); ?>
            <?php if ( count( $students ) ): ?>
                <span class="olama-reg-count-badge"><?php echo count( $students ); ?></span>
            <?php endif; ?>
        </button>
        <button class="olama-reg-tab <?php echo $active_tab === 'financial' ? 'active' : ''; ?>"
                data-tab="financial" role="tab"
                <?php echo ! $f ? 'disabled title="' . esc_attr__( 'احفظ بيانات العائلة أولاً', 'olama-registration' ) . '"' : ''; ?>>
            <span class="dashicons dashicons-money-alt"></span>
            <?php esc_html_e( 'الاستحقاق المالي', 'olama-registration' ); ?>
        </button>
        <button class="olama-reg-tab <?php echo $active_tab === 'agreements' ? 'active' : ''; ?>"
                data-tab="agreements" role="tab"
                <?php echo ! $f ? 'disabled title="' . esc_attr__( 'احفظ بيانات العائلة أولاً', 'olama-registration' ) . '"' : ''; ?>>
            <span class="dashicons dashicons-media-document"></span>
            <?php esc_html_e( 'العقود', 'olama-registration' ); ?>
        </button>
    </nav>

    <!-- ════════════════════════════════════════════════════════════════
         TAB 1 — FAMILY DATA
    ════════════════════════════════════════════════════════════════ -->
    <div class="olama-reg-tab-pane <?php echo $active_tab === 'family' ? 'active' : ''; ?>" id="tab-family">



        <!-- Core Family Data -->
        <div class="olama-reg-section">
            <h3 class="olama-reg-section-title">
                <span class="dashicons dashicons-groups"></span>
                <?php esc_html_e( 'البيانات الأساسية', 'olama-registration' ); ?>
            </h3>
            <div class="olama-reg-grid">

                <div class="olama-reg-field olama-reg-field--wide">
                    <label><?php esc_html_e( 'اسم العائلة', 'olama-registration' ); ?></label>
                    <input type="text" name="family_name"
                           value="<?php echo esc_attr( $school_family ? $school_family->family_name : ($f->family_name ?? '') ); ?>"
                           readonly style="background-color: #f0f0f1; cursor: not-allowed;">
                </div>

                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'جوال الأب', 'olama-registration' ); ?></label>
                    <input type="tel" name="father_mobile"
                           value="<?php echo esc_attr( $father_mobile_val ); ?>"
                           dir="ltr" readonly
                           style="background-color: #f0f0f1; cursor: not-allowed;">
                </div>
                
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'جوال الأم', 'olama-registration' ); ?></label>
                    <input type="tel" name="mother_mobile"
                           value="<?php echo esc_attr( $mother_mobile_val ); ?>"
                           dir="ltr" readonly
                           style="background-color: #f0f0f1; cursor: not-allowed;">
                </div>

                <div class="olama-reg-field olama-reg-field--wide">
                    <label><?php esc_html_e( 'العنوان', 'olama-registration' ); ?></label>
                    <textarea name="address" rows="2" readonly style="background-color: #f0f0f1; cursor: not-allowed;"><?php echo esc_textarea( $school_family ? $school_family->address : ($f->address ?? '') ); ?></textarea>
                </div>

            </div>
        </div>

        <!-- Form Actions (No Save Button) -->
        <div class="olama-reg-form-actions">
            <?php if ( $f ): ?>
            <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'olama-registration', 'action' => 'print', 'family_uid' => $family_uid ], admin_url( 'admin.php' ) ) ); ?>"
               class="olama-reg-btn olama-reg-btn--secondary" target="_blank">
                <span class="dashicons dashicons-printer"></span>
                <?php esc_html_e( 'طباعة بطاقة العائلة', 'olama-registration' ); ?>
            </a>
            <?php endif; ?>
        </div>

    </div><!-- #tab-family -->

    <!-- ════════════════════════════════════════════════════════════════
         TAB 2 — STUDENTS
    ════════════════════════════════════════════════════════════════ -->
    <div class="olama-reg-tab-pane <?php echo $active_tab === 'students' ? 'active' : ''; ?>" id="tab-students">

        <?php if ( ! $f ): ?>
            <div class="olama-reg-empty-state">
                <span class="dashicons dashicons-info-outline"></span>
                <p><?php esc_html_e( 'يرجى حفظ بيانات العائلة أولاً قبل إضافة الطلاب.', 'olama-registration' ); ?></p>
            </div>
        <?php else: ?>

        <div class="olama-reg-students-header">
            <span class="olama-reg-students-count">
                <span class="dashicons dashicons-admin-users" style="color:var(--reg-primary);"></span>
                <?php printf( esc_html__( 'عدد الطلاب: %d', 'olama-registration' ), count( $students ) ); ?>
            </span>
        </div>

        <div id="olama-reg-students-list">
            <?php if ( empty( $students ) ): ?>
                <div class="olama-reg-empty-state" id="olama-reg-no-students">
                    <span class="dashicons dashicons-admin-users"></span>
                    <p><?php esc_html_e( 'لا يوجد طلاب مضافون لهذه العائلة بعد.', 'olama-registration' ); ?></p>
                </div>
            <?php else: ?>
                <?php foreach ( $students as $s ):
                    $photo_url  = Olama_Reg_Student::get_student_photo_url( (int) ( $s->photo_attachment_id ?? 0 ) );

                    $is_open    = ( $open_student_uid === $s->student_uid );
                ?>
                <?php include OLAMA_REG_PATH . 'admin/views/partial-student-row.php'; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php endif; // $f check ?>
    </div><!-- #tab-students -->

    <!-- ════════════════════════════════════════════════════════════════
         TAB 3 — FINANCIAL
    ════════════════════════════════════════════════════════════════ -->
    <div class="olama-reg-tab-pane <?php echo $active_tab === 'financial' ? 'active' : ''; ?>" id="tab-financial">

        <?php if ( ! $f ): ?>
            <div class="olama-reg-empty-state">
                <span class="dashicons dashicons-money-alt"></span>
                <p><?php esc_html_e( 'يرجى حفظ بيانات العائلة أولاً.', 'olama-registration' ); ?></p>
            </div>
        <?php else: 
            $summary  = Olama_Reg_Billing_Invoice::get_invoice_summary( $family_uid, $active_year_id );
            $invoices = Olama_Reg_Billing_Invoice::get_family_invoices( $family_uid, $active_year_id );
            $payments = Olama_Reg_Billing_Payment::get_family_payments( $family_uid, $active_year_id );
        ?>

        <div class="olama-reg-fin-toolbar">
            <div class="olama-reg-field olama-reg-field--inline" style="gap:12px; align-items:center;">
                <label style="font-weight:700; white-space:nowrap; color:var(--reg-text-muted); font-size:13px;">
                    <?php esc_html_e( 'العام الدراسي:', 'olama-registration' ); ?>
                </label>
                <select id="olama-reg-fin-year" data-family-uid="<?php echo esc_attr( $family_uid ); ?>"
                        style="min-width:160px;">
                    <option value="0"><?php esc_html_e( 'جميع السنوات', 'olama-registration' ); ?></option>
                    <?php foreach ( $academic_years as $ay ): ?>
                        <option value="<?php echo esc_attr( $ay->id ); ?>"
                                <?php selected( $ay->id, $active_year_id ); ?>>
                            <?php echo esc_html( $ay->year_name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration-invoices&action=new&family_uid=' . $family_uid ) ); ?>" class="olama-reg-btn olama-reg-btn--primary">
                <span class="dashicons dashicons-plus"></span>
                <?php esc_html_e( 'إصدار فاتورة جديدة', 'olama-registration' ); ?>
            </a>
        </div>

        <!-- Summary Dashboard -->
        <div class="olama-reg-dashboard-cards" style="display: flex; gap: 20px; margin-bottom: 30px;">
            <div class="olama-reg-stat-card" style="flex:1; background:#fff; padding:20px; border-radius:12px; border:1px solid #e2e8f0; text-align:center;">
                <h4 style="margin:0; color:#64748b; font-size:14px;"><?php esc_html_e('إجمالي الفواتير', 'olama-registration'); ?></h4>
                <div style="font-size:28px; font-weight:800; color:#0f172a; margin-top:10px;">
                    <?php echo number_format( (float)($summary->total_invoiced ?? 0), 2 ); ?>
                </div>
            </div>
            <div class="olama-reg-stat-card" style="flex:1; background:#fff; padding:20px; border-radius:12px; border:1px solid #e2e8f0; text-align:center;">
                <h4 style="margin:0; color:#64748b; font-size:14px;"><?php esc_html_e('إجمالي المحصل', 'olama-registration'); ?></h4>
                <div style="font-size:28px; font-weight:800; color:#10b981; margin-top:10px;">
                    <?php echo number_format( (float)($summary->total_paid ?? 0), 2 ); ?>
                </div>
            </div>
            <div class="olama-reg-stat-card" style="flex:1; background:#fff; padding:20px; border-radius:12px; border:1px solid #e2e8f0; text-align:center;">
                <h4 style="margin:0; color:#64748b; font-size:14px;"><?php esc_html_e('الذمم المستحقة', 'olama-registration'); ?></h4>
                <div style="font-size:28px; font-weight:800; color:#ef4444; margin-top:10px;">
                    <?php echo number_format( (float)($summary->balance ?? 0), 2 ); ?>
                </div>
            </div>
        </div>

        <!-- Invoices List -->
        <div class="olama-reg-invoices-list">
            <?php if ( empty( $invoices ) ): ?>
                <div class="olama-reg-empty-state">
                    <span class="dashicons dashicons-media-document"></span>
                    <p><?php esc_html_e( 'لا توجد فواتير مسجلة لهذه العائلة.', 'olama-registration' ); ?></p>
                </div>
            <?php else: ?>
                <?php foreach ( $invoices as $inv ): 
                    // Filter payments for this invoice
                    $inv_payments = array_filter( $payments, fn($p) => (int)$p->invoice_id === (int)$inv->id );
                    
                    // Status Badge Logic
                    $status_colors = [
                        'draft'     => ['bg' => '#f1f5f9', 'text' => '#475569', 'label' => 'مسودة'],
                        'issued'    => ['bg' => '#e0f2fe', 'text' => '#0369a1', 'label' => 'صادرة'],
                        'partial'   => ['bg' => '#fef3c7', 'text' => '#b45309', 'label' => 'جزئية'],
                        'paid'      => ['bg' => '#dcfce7', 'text' => '#15803d', 'label' => 'مدفوعة'],
                        'overdue'   => ['bg' => '#fee2e2', 'text' => '#b91c1c', 'label' => 'متأخرة'],
                        'cancelled' => ['bg' => '#f3f4f6', 'text' => '#374151', 'label' => 'ملغاة'],
                    ];
                    $st = $status_colors[ $inv->status ] ?? $status_colors['draft'];
                ?>
                <div class="olama-reg-invoice-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; margin-bottom:20px; overflow:hidden;">
                    
                    <!-- Invoice Header -->
                    <div style="padding:20px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; background:#fafaf9;">
                        <div>
                            <div style="font-weight:700; color:#0f172a; font-size:16px;">
                                <?php echo esc_html( $inv->invoice_number ); ?>
                            </div>
                            <div style="color:#64748b; font-size:13px; margin-top:5px;">
                                <?php echo esc_html( $inv->issue_date ); ?> 
                                <?php if($inv->due_date): ?> &middot; <?php esc_html_e('تاريخ الاستحقاق:', 'olama-registration'); ?> <?php echo esc_html($inv->due_date); ?><?php endif; ?>
                            </div>
                        </div>
                        <div style="text-align:left;">
                            <span style="display:inline-block; padding:4px 12px; border-radius:999px; font-size:12px; font-weight:600; background:<?php echo $st['bg']; ?>; color:<?php echo $st['text']; ?>;">
                                <?php echo esc_html( $st['label'] ); ?>
                            </span>
                            <div style="font-size:20px; font-weight:800; color:#0f172a; margin-top:5px;">
                                <?php echo number_format( (float)$inv->total, 2 ); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Payments Nested List -->
                    <div style="padding:20px;">
                        <?php if ( empty( $inv_payments ) ): ?>
                            <p style="color:#94a3b8; font-size:13px; margin:0; text-align:center;">
                                <?php esc_html_e( 'لم يتم تسجيل أي دفعات لهذه الفاتورة بعد.', 'olama-registration' ); ?>
                            </p>
                        <?php else: ?>
                            <h5 style="margin:0 0 15px 0; color:#475569; font-size:14px; font-weight:700;">
                                <span class="dashicons dashicons-money-alt" style="font-size:16px; margin-top:0;"></span>
                                <?php esc_html_e( 'سجل الدفعات', 'olama-registration' ); ?>
                            </h5>
                            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                                <thead>
                                    <tr style="border-bottom:1px solid #e2e8f0;">
                                        <th style="padding:8px; text-align:right; color:#64748b; font-weight:600;"><?php esc_html_e('رقم السند', 'olama-registration'); ?></th>
                                        <th style="padding:8px; text-align:right; color:#64748b; font-weight:600;"><?php esc_html_e('تاريخ القبض', 'olama-registration'); ?></th>
                                        <th style="padding:8px; text-align:right; color:#64748b; font-weight:600;"><?php esc_html_e('طريقة الدفع', 'olama-registration'); ?></th>
                                        <th style="padding:8px; text-align:left; color:#64748b; font-weight:600;"><?php esc_html_e('المبلغ', 'olama-registration'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $inv_payments as $pay ): 
                                        $method_label = match($pay->method) {
                                            'cash' => 'نقدي', 'bank_transfer' => 'حوالة', 'cheque' => 'شيك', 'online' => 'إلكتروني', default => 'أخرى'
                                        };
                                    ?>
                                    <tr style="border-bottom:1px solid #f1f5f9;">
                                        <td style="padding:8px;">#<?php echo esc_html($pay->id); ?></td>
                                        <td style="padding:8px;"><?php echo esc_html($pay->payment_date); ?></td>
                                        <td style="padding:8px;">
                                            <span style="display:inline-block; background:#f0fdf4; color:#166534; padding:2px 8px; border-radius:4px; font-size:11px;">
                                                <?php echo esc_html($method_label); ?>
                                            </span>
                                            <?php if($pay->reference): ?>
                                                <div style="font-size:11px; color:#94a3b8; margin-top:2px;"><?php echo esc_html($pay->reference); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:8px; text-align:left; font-weight:700; color:#10b981;">
                                            <?php echo number_format((float)$pay->amount, 2); ?>
                                            <?php if ( (float)$pay->amount > 0 && $pay->method !== 'reversal' ): ?>
                                                <button class="button button-small olama-reg-reverse-payment-btn"
                                                        data-id="<?php echo esc_attr( $pay->id ); ?>"
                                                        title="<?php esc_attr_e( 'عكس السند', 'olama-registration' ); ?>"
                                                        style="color:#c62828; border:none; background:none; padding:0; vertical-align:middle; margin-right:5px;">
                                                    <span class="dashicons dashicons-undo" style="font-size:16px; width:16px; height:16px;"></span>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Invoice Footer Actions -->
                    <div style="padding:12px 20px; background:#f8fafc; border-top:1px solid #f1f5f9; display:flex; justify-content:flex-end; gap:10px;">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration-invoices&action=view&id=' . $inv->id ) ); ?>" class="olama-reg-btn" style="background:#fff; border:1px solid #cbd5e1; color:#475569;">
                            <span class="dashicons dashicons-visibility"></span> <?php esc_html_e('عرض الفاتورة', 'olama-registration'); ?>
                        </a>
                        <button type="button" class="olama-reg-btn olama-reg-btn--primary olama-reg-pay-invoice-trigger"
                                data-id="<?php echo esc_attr( $inv->id ); ?>"
                                data-no="<?php echo esc_attr( $inv->invoice_number ); ?>"
                                data-bal="<?php echo esc_attr( $inv->balance ); ?>"
                                data-family="<?php echo esc_attr( $family_uid ); ?>">
                            <span class="dashicons dashicons-plus"></span> <?php esc_html_e('تسجيل دفعة', 'olama-registration'); ?>
                        </button>
                        <?php if ( (float)$inv->amount_paid == 0 && $inv->status !== 'cancelled' ): ?>
                            <button class="olama-reg-btn olama-reg-cancel-invoice-btn" data-id="<?php echo esc_attr( $inv->id ); ?>" style="background:#fff; border:1px solid #fca5a5; color:#dc2626;">
                                <span class="dashicons dashicons-dismiss"></span> <?php esc_html_e('إلغاء الفاتورة', 'olama-registration'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php endif; // $f check ?>
    </div><!-- #tab-financial -->

    <!-- ════════════════════════════════════════════════════════════════
         TAB 4 — AGREEMENTS
    ════════════════════════════════════════════════════════════════ -->
    <div class="olama-reg-tab-pane <?php echo $active_tab === 'agreements' ? 'active' : ''; ?>" id="tab-agreements">
        <?php if ( ! $f ): ?>
            <div class="olama-reg-empty-state">
                <span class="dashicons dashicons-media-document"></span>
                <p><?php esc_html_e( 'يرجى حفظ بيانات العائلة أولاً.', 'olama-registration' ); ?></p>
            </div>
        <?php else: 
            $family_agreements = Olama_Reg_Agreement::get_list(['payer_id' => $family_uid, 'payer_type' => 'family']);
            // Also fetch by integer ID if needed
            if (empty($family_agreements) && isset($school_family->id)) {
                $family_agreements = Olama_Reg_Agreement::get_list(['payer_id' => $school_family->id, 'payer_type' => 'family']);
            }
            if (empty($family_agreements) && isset($f->id)) {
                $family_agreements = Olama_Reg_Agreement::get_list(['payer_id' => $f->id, 'payer_type' => 'family']);
            }

            // We need invoices to map them to agreements
            $all_family_invoices = Olama_Reg_Billing_Invoice::get_family_invoices( $family_uid, 0 );
        ?>

        <div class="olama-reg-agreements-list">
            <?php if ( empty( $family_agreements ) ): ?>
                <div class="olama-reg-empty-state">
                    <span class="dashicons dashicons-media-document"></span>
                    <p><?php esc_html_e( 'لا توجد عقود مسجلة لهذه العائلة.', 'olama-registration' ); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('رقم العقد', 'olama-registration'); ?></th>
                            <th><?php esc_html_e('الفواتير المرتبطة', 'olama-registration'); ?></th>
                            <th><?php esc_html_e('قيمة الفواتير', 'olama-registration'); ?></th>
                            <th><?php esc_html_e('المتبقي من الفواتير', 'olama-registration'); ?></th>
                            <th><?php esc_html_e('الإجراءات', 'olama-registration'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $family_agreements as $agr ): 
                            // Find related invoices for this agreement
                            $related_invoices = array_filter($all_family_invoices, fn($i) => (int)$i->agreement_id === (int)$agr->id);
                            
                            $invoice_numbers = [];
                            $total_invoice_value = 0;
                            $total_invoice_balance = 0;
                            
                            foreach ($related_invoices as $ri) {
                                $invoice_numbers[] = $ri->invoice_number;
                                $total_invoice_value += (float)$ri->total;
                                $total_invoice_balance += (float)$ri->balance;
                            }
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($agr->agreement_number); ?></strong></td>
                            <td>
                                <?php if (!empty($invoice_numbers)): ?>
                                    <?php echo esc_html(implode('، ', $invoice_numbers)); ?>
                                <?php else: ?>
                                    <span style="color:#94a3b8;"><?php esc_html_e('لا يوجد', 'olama-registration'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($total_invoice_value, 2); ?></td>
                            <td>
                                <span style="color: <?php echo $total_invoice_balance > 0 ? '#ef4444' : '#10b981'; ?>">
                                    <?php echo number_format($total_invoice_balance, 2); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration-agreements&action=print&id=' . $agr->id ) ); ?>" target="_blank" class="button button-small">
                                    <span class="dashicons dashicons-printer" style="margin-top:2px;"></span> <?php esc_html_e('طباعة العقد', 'olama-registration'); ?>
                                </a>
                                <?php if (!empty($related_invoices)): 
                                    // Take the first related invoice id to print
                                    $first_invoice_id = reset($related_invoices)->id;
                                ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration-invoices&action=print&id=' . $first_invoice_id ) ); ?>" target="_blank" class="button button-small">
                                    <span class="dashicons dashicons-media-text" style="margin-top:2px;"></span> <?php esc_html_e('طباعة تفاصيل الفاتورة', 'olama-registration'); ?>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div><!-- #tab-agreements -->

</div><!-- .olama-reg-form-wrapper -->
