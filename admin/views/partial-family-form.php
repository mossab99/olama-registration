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
    </nav>

    <!-- ════════════════════════════════════════════════════════════════
         TAB 1 — FAMILY DATA
    ════════════════════════════════════════════════════════════════ -->
    <div class="olama-reg-tab-pane <?php echo $active_tab === 'family' ? 'active' : ''; ?>" id="tab-family">

        <!-- Father Section -->
        <div class="olama-reg-section">
            <h3 class="olama-reg-section-title">
                <span class="dashicons dashicons-businessman"></span>
                <?php esc_html_e( 'بيانات الأب', 'olama-registration' ); ?>
            </h3>
            <div class="olama-reg-grid">

                <div class="olama-reg-field olama-reg-field--required">
                    <label><?php esc_html_e( 'الاسم الأول', 'olama-registration' ); ?></label>
                    <input type="text" name="father_first_name"
                           value="<?php echo $val('father_first_name'); ?>"
                           placeholder="<?php esc_attr_e( 'الاسم الأول', 'olama-registration' ); ?>"
                           required>
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'الثاني', 'olama-registration' ); ?></label>
                    <input type="text" name="father_second_name"
                           value="<?php echo $val('father_second_name'); ?>"
                           placeholder="<?php esc_attr_e( 'اسم الأب', 'olama-registration' ); ?>">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'الثالث', 'olama-registration' ); ?></label>
                    <input type="text" name="father_third_name"
                           value="<?php echo $val('father_third_name'); ?>"
                           placeholder="<?php esc_attr_e( 'اسم الجد', 'olama-registration' ); ?>">
                </div>
                <div class="olama-reg-field olama-reg-field--required">
                    <label><?php esc_html_e( 'اسم العائلة', 'olama-registration' ); ?></label>
                    <input type="text" name="father_family_name"
                           value="<?php echo $val('father_family_name'); ?>"
                           placeholder="<?php esc_attr_e( 'اللقب / الكنية', 'olama-registration' ); ?>"
                           required>
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'العائلة الثانوي', 'olama-registration' ); ?></label>
                    <input type="text" name="father_secondary_family"
                           value="<?php echo $val('father_secondary_family'); ?>">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'الاسم (لاتيني)', 'olama-registration' ); ?></label>
                    <input type="text" name="father_name_t"
                           value="<?php echo $val('father_name_t'); ?>"
                           dir="ltr"
                           placeholder="Full Name in English">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'الجنسية', 'olama-registration' ); ?></label>
                    <select name="father_nationality" class="olama-reg-select2">
                        <option value=""><?php esc_html_e( '-- اختر الجنسية --', 'olama-registration' ); ?></option>
                        <?php foreach ( $nationalities as $nat ): ?>
                            <option value="<?php echo esc_attr($nat); ?>" <?php selected( $f->father_nationality ?? '', $nat ); ?>>
                                <?php echo esc_html($nat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'المهنة', 'olama-registration' ); ?></label>
                    <input type="text" name="father_job"
                           value="<?php echo $val('father_job'); ?>"
                           placeholder="<?php esc_attr_e( 'مثال: مهندس، طبيب...', 'olama-registration' ); ?>">
                </div>
                <div class="olama-reg-field olama-reg-field--wide">
                    <label><?php esc_html_e( 'جهة العمل', 'olama-registration' ); ?></label>
                    <input type="text" name="father_workplace"
                           value="<?php echo $val('father_workplace'); ?>"
                           placeholder="<?php esc_attr_e( 'اسم الشركة أو المؤسسة', 'olama-registration' ); ?>">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'هاتف', 'olama-registration' ); ?></label>
                    <input type="tel" name="father_phone"
                           value="<?php echo $val('father_phone'); ?>"
                           dir="ltr"
                           placeholder="+966 5x xxx xxxx">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'جوال', 'olama-registration' ); ?></label>
                    <input type="tel" name="father_mobile"
                           value="<?php echo $val('father_mobile'); ?>"
                           dir="ltr"
                           placeholder="+966 5x xxx xxxx">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'البريد الإلكتروني', 'olama-registration' ); ?></label>
                    <input type="email" name="father_email"
                           value="<?php echo $val('father_email'); ?>"
                           dir="ltr"
                           placeholder="example@mail.com">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'نوع الوثيقة', 'olama-registration' ); ?></label>
                    <select name="father_doc_type">
                        <?php foreach ( [ '' => '-- اختر --', 'national_id' => 'هوية وطنية', 'passport' => 'جواز سفر', 'iqama' => 'إقامة', 'other' => 'أخرى' ] as $k => $lbl ): ?>
                            <option value="<?php echo esc_attr($k); ?>" <?php selected( $f->father_doc_type ?? '', $k ); ?>><?php echo esc_html($lbl); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'رقم الوثيقة', 'olama-registration' ); ?></label>
                    <input type="text" name="father_doc_number"
                           value="<?php echo $val('father_doc_number'); ?>"
                           dir="ltr">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'مكان الإصدار', 'olama-registration' ); ?></label>
                    <input type="text" name="father_doc_issue_place"
                           value="<?php echo $val('father_doc_issue_place'); ?>">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'تاريخ الإصدار', 'olama-registration' ); ?></label>
                    <input type="text" name="father_doc_issue_date"
                           value="<?php echo $val('father_doc_issue_date'); ?>"
                           class="olama-reg-datepicker"
                           placeholder="YYYY-MM-DD">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'تاريخ الانتهاء', 'olama-registration' ); ?></label>
                    <input type="text" name="father_doc_expiry_date"
                           value="<?php echo $val('father_doc_expiry_date'); ?>"
                           class="olama-reg-datepicker"
                           placeholder="YYYY-MM-DD">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'شؤون الموظفين', 'olama-registration' ); ?></label>
                    <input type="text" name="father_employee_affairs"
                           value="<?php echo $val('father_employee_affairs'); ?>">
                </div>
                <div class="olama-reg-field olama-reg-field--checkbox">
                    <input type="checkbox" name="father_is_employee" value="1"
                           id="father_is_employee"
                           <?php checked( $f->father_is_employee ?? 0, 1 ); ?>>
                    <label for="father_is_employee" style="cursor:pointer; font-weight:600;">
                        <?php esc_html_e( 'موظف في المدرسة', 'olama-registration' ); ?>
                    </label>
                </div>

            </div><!-- .olama-reg-grid -->
        </div><!-- Father Section -->

        <!-- Mother Section -->
        <div class="olama-reg-section">
            <h3 class="olama-reg-section-title">
                <span class="dashicons dashicons-businesswoman"></span>
                <?php esc_html_e( 'بيانات الأم', 'olama-registration' ); ?>
            </h3>
            <div class="olama-reg-grid">

                <div class="olama-reg-field olama-reg-field--wide">
                    <label><?php esc_html_e( 'الاسم الكامل', 'olama-registration' ); ?></label>
                    <input type="text" name="mother_full_name"
                           value="<?php echo $val('mother_full_name'); ?>"
                           placeholder="<?php esc_attr_e( 'اسم الأم الكامل', 'olama-registration' ); ?>">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'الجنسية', 'olama-registration' ); ?></label>
                    <select name="mother_nationality" class="olama-reg-select2">
                        <option value=""><?php esc_html_e( '-- اختر --', 'olama-registration' ); ?></option>
                        <?php foreach ( $nationalities as $nat ): ?>
                            <option value="<?php echo esc_attr($nat); ?>" <?php selected( $f->mother_nationality ?? '', $nat ); ?>><?php echo esc_html($nat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'المهنة', 'olama-registration' ); ?></label>
                    <input type="text" name="mother_job"
                           value="<?php echo $val('mother_job'); ?>">
                </div>
                <div class="olama-reg-field olama-reg-field--wide">
                    <label><?php esc_html_e( 'جهة العمل', 'olama-registration' ); ?></label>
                    <input type="text" name="mother_workplace"
                           value="<?php echo $val('mother_workplace'); ?>">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'جوال', 'olama-registration' ); ?></label>
                    <input type="tel" name="mother_mobile"
                           value="<?php echo $val('mother_mobile'); ?>"
                           dir="ltr"
                           placeholder="+966 5x xxx xxxx">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'البريد الإلكتروني', 'olama-registration' ); ?></label>
                    <input type="email" name="mother_email"
                           value="<?php echo $val('mother_email'); ?>"
                           dir="ltr"
                           placeholder="example@mail.com">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'شؤون الموظفين', 'olama-registration' ); ?></label>
                    <input type="text" name="mother_employee_affairs"
                           value="<?php echo $val('mother_employee_affairs'); ?>">
                </div>
                <div class="olama-reg-field olama-reg-field--checkbox">
                    <input type="checkbox" name="mother_is_employee" value="1"
                           id="mother_is_employee"
                           <?php checked( $f->mother_is_employee ?? 0, 1 ); ?>>
                    <label for="mother_is_employee" style="cursor:pointer; font-weight:600;">
                        <?php esc_html_e( 'موظفة في المدرسة', 'olama-registration' ); ?>
                    </label>
                </div>

            </div>
        </div><!-- Mother Section -->

        <!-- Other Data -->
        <div class="olama-reg-section">
            <h3 class="olama-reg-section-title">
                <span class="dashicons dashicons-location"></span>
                <?php esc_html_e( 'البيانات التكميلية والعنوان', 'olama-registration' ); ?>
            </h3>
            <div class="olama-reg-grid">

                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'المنطقة السكنية', 'olama-registration' ); ?></label>
                    <input type="text" name="residential_area"
                           value="<?php echo $val('residential_area'); ?>">
                </div>
                <div class="olama-reg-field olama-reg-field--wide">
                    <label><?php esc_html_e( 'العنوان التفصيلي', 'olama-registration' ); ?></label>
                    <input type="text" name="home_address"
                           value="<?php echo $val('home_address'); ?>"
                           placeholder="<?php esc_attr_e( 'الشارع، الحي، المدينة', 'olama-registration' ); ?>">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'رقم المبنى', 'olama-registration' ); ?></label>
                    <input type="text" name="building_number"
                           value="<?php echo $val('building_number'); ?>">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'رقم الشقة', 'olama-registration' ); ?></label>
                    <input type="text" name="apartment_number"
                           value="<?php echo $val('apartment_number'); ?>">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'هاتف المنزل', 'olama-registration' ); ?></label>
                    <input type="tel" name="home_phone"
                           value="<?php echo $val('home_phone'); ?>"
                           dir="ltr">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'التصنيف', 'olama-registration' ); ?></label>
                    <select name="classification">
                        <?php foreach ( [ '' => '-- اختر --', 'vip' => 'VIP', 'regular' => 'عادي', 'staff' => 'موظف', 'other' => 'أخرى' ] as $k => $lbl ): ?>
                            <option value="<?php echo esc_attr($k); ?>" <?php selected( $f->classification ?? '', $k ); ?>><?php echo esc_html($lbl); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="olama-reg-field olama-reg-field--wide">
                    <label><?php esc_html_e( 'ملاحظات', 'olama-registration' ); ?></label>
                    <textarea name="reg_notes" rows="3"
                              placeholder="<?php esc_attr_e( 'أي ملاحظات أو تفاصيل إضافية...', 'olama-registration' ); ?>"><?php echo esc_textarea( $f->reg_notes ?? '' ); ?></textarea>
                </div>
                <div class="olama-reg-field olama-reg-field--checkbox">
                    <input type="checkbox" name="is_active" value="1"
                           id="is_active"
                           <?php checked( $f->is_active ?? 1, 1 ); ?>>
                    <label for="is_active" style="cursor:pointer; font-weight:600; color:var(--reg-success);">
                        <?php esc_html_e( 'حساب فعال', 'olama-registration' ); ?>
                    </label>
                </div>

            </div>
        </div><!-- Other Data -->

        <!-- Save Button -->
        <div class="olama-reg-form-actions">
            <input type="hidden" name="family_uid" id="olama-reg-family-uid" value="<?php echo esc_attr( $family_uid ); ?>">
            <button type="button" id="olama-reg-save-family" class="olama-reg-btn olama-reg-btn--primary">
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e( 'حفظ بيانات العائلة', 'olama-registration' ); ?>
            </button>
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
            <button type="button" id="olama-reg-add-student"
                    class="olama-reg-btn olama-reg-btn--primary"
                    data-family-uid="<?php echo esc_attr( $family_uid ); ?>">
                <span class="dashicons dashicons-plus"></span>
                <?php esc_html_e( 'إضافة طالب جديد', 'olama-registration' ); ?>
            </button>
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
                    $s_history  = Olama_Reg_Academic_History::get_history( $s->student_uid );
                    $s_transport= Olama_Reg_Transport::get_transport( $s->student_uid, $active_year_id );
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
        <?php else: ?>

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
            <button type="button" id="olama-reg-add-fin-row" class="olama-reg-btn olama-reg-btn--secondary">
                <span class="dashicons dashicons-plus"></span>
                <?php esc_html_e( 'إضافة سطر', 'olama-registration' ); ?>
            </button>
        </div>

        <div class="olama-reg-table-wrap">
            <table class="olama-reg-fin-table" id="olama-reg-fin-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'تاريخ الاستحقاق', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'طريقة الحساب', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'النسبة %', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'المبلغ المستحق', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'المبلغ المدفوع', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'المدفوعات والدائرة', 'olama-registration' ); ?></th>
                        <th class="olama-reg-fin-balance"><?php esc_html_e( 'الرصيد', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'المرجع', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'ملاحظات', 'olama-registration' ); ?></th>
                        <th style="width:50px; text-align:center;"><?php esc_html_e( 'حفظ', 'olama-registration' ); ?></th>
                        <th style="width:50px; text-align:center;"><?php esc_html_e( 'حذف', 'olama-registration' ); ?></th>
                    </tr>
                </thead>
                <tbody id="olama-reg-fin-body">
                    <?php
                        $fin_rows = Olama_Reg_Financial::get_entitlements( $family_uid, $active_year_id );
                        foreach ( $fin_rows as $row ) {
                            include OLAMA_REG_PATH . 'admin/views/partial-fin-row.php';
                        }
                    ?>
                </tbody>
                <tfoot>
                    <tr class="olama-reg-fin-totals" id="olama-reg-fin-totals">
                        <?php
                            $totals = Olama_Reg_Financial::get_totals( $family_uid, $active_year_id );
                        ?>
                        <td colspan="3"><strong><?php esc_html_e( 'المجموع الإجمالي', 'olama-registration' ); ?></strong></td>
                        <td class="olama-reg-total" id="total-due"><?php echo number_format( (float)($totals->total_due ?? 0), 2 ); ?></td>
                        <td class="olama-reg-total" id="total-paid"><?php echo number_format( (float)($totals->total_paid ?? 0), 2 ); ?></td>
                        <td class="olama-reg-total" id="total-revolving"><?php echo number_format( (float)($totals->total_revolving ?? 0), 2 ); ?></td>
                        <td class="olama-reg-total olama-reg-fin-balance" id="total-balance"><?php echo number_format( (float)($totals->total_balance ?? 0), 2 ); ?></td>
                        <td colspan="4"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php endif; // $f check ?>
    </div><!-- #tab-financial -->

</div><!-- .olama-reg-form-wrapper -->
