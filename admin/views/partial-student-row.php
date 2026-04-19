<?php
/**
 * Student accordion row partial
 * Variables: $s (student object), $photo_url, $s_history, $s_transport, $is_open, $nationalities, $active_year_id
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$sv = fn( string $k, string $def = '' ) => esc_attr( $s->$k ?? $def );
?>
<div class="olama-reg-student-row <?php echo $is_open ? 'open' : ''; ?>"
     data-student-uid="<?php echo esc_attr( $s->student_uid ); ?>"
     id="student-<?php echo esc_attr( $s->student_uid ); ?>">

    <!-- Row Header -->
    <div class="olama-reg-student-row-header">
        <div class="olama-reg-student-row-info">
            <img src="<?php echo esc_url( $photo_url ); ?>" class="olama-reg-student-thumb" alt="">
            <span class="olama-reg-uid-badge olama-reg-uid-badge--student"><?php echo esc_html( $s->student_uid ); ?></span>
            <strong class="olama-reg-student-name"><?php echo esc_html( $s->student_name ); ?></strong>
            <?php if ( ! empty( $s->grade_name ) ): ?>
                <span class="olama-reg-meta"><?php echo esc_html( $s->grade_name . ( $s->section_name ? ' / ' . $s->section_name : '' ) ); ?></span>
            <?php endif; ?>
            <?php if ( $s->blacklist ?? false ): ?>
                <span class="olama-reg-badge olama-reg-badge--blacklist">⛔ <?php esc_html_e( 'قائمة سوداء', 'olama-registration' ); ?></span>
            <?php endif; ?>
        </div>
        <button type="button" class="olama-reg-student-toggle" aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>">
            <span class="dashicons dashicons-arrow-down-alt2"></span>
        </button>
    </div>

    <!-- Accordion Body -->
    <div class="olama-reg-student-body" <?php echo $is_open ? '' : 'style="display:none"'; ?>>

        <!-- Sub-tabs -->
        <nav class="olama-reg-sub-tabs">
            <button class="olama-reg-sub-tab active" data-subtab="basic-<?php echo esc_attr($s->student_uid); ?>">
                <?php esc_html_e( 'البيانات الأساسية', 'olama-registration' ); ?>
            </button>
            <button class="olama-reg-sub-tab" data-subtab="school-<?php echo esc_attr($s->student_uid); ?>">
                <?php esc_html_e( 'البيانات المدرسية', 'olama-registration' ); ?>
            </button>
            <button class="olama-reg-sub-tab" data-subtab="transport-<?php echo esc_attr($s->student_uid); ?>">
                <?php esc_html_e( 'النقل', 'olama-registration' ); ?>
            </button>
        </nav>

        <!-- Basic Data -->
        <div class="olama-reg-sub-pane active" id="basic-<?php echo esc_attr($s->student_uid); ?>">
            <div class="olama-reg-grid">
                <div class="olama-reg-field olama-reg-field--required">
                    <label><?php esc_html_e( 'الاسم الأول', 'olama-registration' ); ?> <span class="required">*</span></label>
                    <input type="text" class="s-field" name="first_name" value="<?php echo $sv('first_name'); ?>" required>
                </div>
                <div class="olama-reg-field"><label><?php esc_html_e( 'الثاني', 'olama-registration' ); ?></label>
                    <input type="text" class="s-field" name="second_name" value="<?php echo $sv('second_name'); ?>">
                </div>
                <div class="olama-reg-field"><label><?php esc_html_e( 'الثالث', 'olama-registration' ); ?></label>
                    <input type="text" class="s-field" name="third_name" value="<?php echo $sv('third_name'); ?>">
                </div>
                <div class="olama-reg-field olama-reg-field--required">
                    <label><?php esc_html_e( 'اسم العائلة', 'olama-registration' ); ?> <span class="required">*</span></label>
                    <input type="text" class="s-field" name="student_family_name" value="<?php echo $sv('student_family_name'); ?>" required>
                </div>
                <div class="olama-reg-field"><label><?php esc_html_e( 'العائلة الثانوي', 'olama-registration' ); ?></label>
                    <input type="text" class="s-field" name="secondary_family_name" value="<?php echo $sv('secondary_family_name'); ?>">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'الجنس', 'olama-registration' ); ?></label>
                    <div class="olama-reg-radio-group">
                        <label><input type="radio" name="gender_<?php echo esc_attr($s->student_uid); ?>" class="s-field" data-field="gender" value="male" <?php checked( $s->gender ?? '', 'male' ); ?>> <?php esc_html_e('ذكر','olama-registration'); ?></label>
                        <label><input type="radio" name="gender_<?php echo esc_attr($s->student_uid); ?>" class="s-field" data-field="gender" value="female" <?php checked( $s->gender ?? '', 'female'); ?>> <?php esc_html_e('أنثى','olama-registration'); ?></label>
                    </div>
                </div>
                <div class="olama-reg-field"><label><?php esc_html_e( 'تاريخ الميلاد', 'olama-registration' ); ?></label>
                    <input type="text" class="s-field olama-reg-datepicker" name="dob" value="<?php echo $sv('dob'); ?>">
                </div>
                <div class="olama-reg-field"><label><?php esc_html_e( 'مكان الميلاد', 'olama-registration' ); ?></label>
                    <input type="text" class="s-field" name="birth_place" value="<?php echo $sv('birth_place'); ?>">
                </div>
                <div class="olama-reg-field"><label><?php esc_html_e( 'الرقم الوطني', 'olama-registration' ); ?></label>
                    <input type="text" class="s-field" name="national_id" value="<?php echo $sv('national_id'); ?>" dir="ltr">
                </div>
                <div class="olama-reg-field"><label><?php esc_html_e( 'الرقم غير الناقل', 'olama-registration' ); ?></label>
                    <input type="text" class="s-field" name="non_trans_id" value="<?php echo $sv('non_trans_id'); ?>" dir="ltr">
                </div>
                <div class="olama-reg-field"><label><?php esc_html_e( 'الجنسية', 'olama-registration' ); ?></label>
                    <input type="text" class="s-field" name="nationality" value="<?php echo $sv('nationality'); ?>">
                </div>
                <div class="olama-reg-field"><label><?php esc_html_e( 'اسم الأم', 'olama-registration' ); ?></label>
                    <input type="text" class="s-field" name="mother_name" value="<?php echo $sv('mother_name'); ?>">
                </div>
                <div class="olama-reg-field"><label><?php esc_html_e( 'الهاتف', 'olama-registration' ); ?></label>
                    <input type="tel" class="s-field" name="student_phone" value="<?php echo $sv('student_phone'); ?>" dir="ltr">
                </div>
                <div class="olama-reg-field"><label><?php esc_html_e( 'البريد الإلكتروني', 'olama-registration' ); ?></label>
                    <input type="email" class="s-field" name="student_email" value="<?php echo $sv('student_email'); ?>" dir="ltr">
                </div>
                <div class="olama-reg-field"><label><?php esc_html_e( 'الصورة الشخصية', 'olama-registration' ); ?></label>
                    <div class="olama-reg-photo-upload">
                        <img src="<?php echo esc_url( $photo_url ); ?>" class="olama-reg-photo-preview" alt="">
                        <input type="hidden" class="s-field" name="photo_attachment_id" value="<?php echo $sv('photo_attachment_id'); ?>">
                        <button type="button" class="button olama-reg-upload-photo"><?php esc_html_e( 'اختر صورة', 'olama-registration' ); ?></button>
                    </div>
                </div>
                <div class="olama-reg-field"><label><?php esc_html_e( 'المدرسة السابقة', 'olama-registration' ); ?></label>
                    <input type="text" class="s-field" name="from_school" value="<?php echo $sv('from_school'); ?>">
                </div>
                <div class="olama-reg-field"><label><?php esc_html_e( 'المعدل التراكمي', 'olama-registration' ); ?></label>
                    <input type="number" class="s-field" name="gpa" value="<?php echo $sv('gpa'); ?>" min="0" max="100" step="0.01">
                </div>
                <div class="olama-reg-field"><label><?php esc_html_e( 'تاريخ الالتحاق بالثانوية', 'olama-registration' ); ?></label>
                    <input type="text" class="s-field olama-reg-datepicker" name="high_school_join_date" value="<?php echo $sv('high_school_join_date'); ?>">
                </div>
                <div class="olama-reg-field olama-reg-field--checkbox olama-reg-field--wide">
                    <label>
                        <input type="checkbox" class="s-field" name="blacklist" value="1" <?php checked( $s->blacklist ?? 0, 1 ); ?>>
                        <?php esc_html_e( 'قائمة سوداء', 'olama-registration' ); ?>
                    </label>
                    <div class="olama-reg-blacklist-reason" <?php echo ( $s->blacklist ?? 0 ) ? '' : 'style="display:none"'; ?>>
                        <label><?php esc_html_e( 'سبب القائمة السوداء', 'olama-registration' ); ?></label>
                        <textarea class="s-field" name="blacklist_reason" rows="2"><?php echo esc_textarea( $s->blacklist_reason ?? '' ); ?></textarea>
                    </div>
                </div>
                <div class="olama-reg-field olama-reg-field--wide">
                    <label><?php esc_html_e( 'ملاحظات', 'olama-registration' ); ?></label>
                    <textarea class="s-field" name="student_notes" rows="2"><?php echo esc_textarea( $s->student_notes ?? '' ); ?></textarea>
                </div>
            </div>
        </div><!-- basic -->

        <!-- School History -->
        <div class="olama-reg-sub-pane" id="school-<?php echo esc_attr($s->student_uid); ?>" style="display:none">
            <div class="olama-reg-table-wrap">
                <table class="olama-reg-history-table">
                    <thead><tr>
                        <th><?php esc_html_e( 'العام', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'المدرسة', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'الصف', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'الفرع', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'الشعبة', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'تاريخ التسجيل', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'تاريخ الانسحاب', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'الحالة', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'الحالي', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'حفظ', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'حذف', 'olama-registration' ); ?></th>
                    </tr></thead>
                    <tbody class="olama-reg-history-body" data-student-uid="<?php echo esc_attr($s->student_uid); ?>">
                        <?php foreach ( $s_history as $hist ): ?>
                        <tr data-hist-id="<?php echo (int)$hist->id; ?>">
                            <td><input type="text" value="<?php echo esc_attr($hist->academic_year ?? ''); ?>" name="academic_year" class="olama-reg-inline-input"></td>
                            <td><input type="text" value="<?php echo esc_attr($hist->school_name ?? ''); ?>" name="school_name" class="olama-reg-inline-input"></td>
                            <td><input type="text" value="<?php echo esc_attr($hist->grade ?? ''); ?>" name="grade" class="olama-reg-inline-input"></td>
                            <td><input type="text" value="<?php echo esc_attr($hist->branch ?? ''); ?>" name="branch" class="olama-reg-inline-input"></td>
                            <td><input type="text" value="<?php echo esc_attr($hist->section ?? ''); ?>" name="section" class="olama-reg-inline-input"></td>
                            <td><input type="text" value="<?php echo esc_attr($hist->registration_date ?? ''); ?>" name="registration_date" class="olama-reg-inline-input olama-reg-datepicker"></td>
                            <td><input type="text" value="<?php echo esc_attr($hist->withdrawal_date ?? ''); ?>" name="withdrawal_date" class="olama-reg-inline-input olama-reg-datepicker"></td>
                            <td><input type="text" value="<?php echo esc_attr($hist->student_status ?? ''); ?>" name="student_status" class="olama-reg-inline-input"></td>
                            <td><input type="checkbox" name="is_current" value="1" <?php checked($hist->is_current,1); ?>></td>
                            <td><button type="button" class="button button-small olama-reg-save-hist-row"><?php esc_html_e('حفظ','olama-registration'); ?></button></td>
                            <td><button type="button" class="button button-small olama-reg-delete-hist-row" style="color:red"><?php esc_html_e('حذف','olama-registration'); ?></button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="button" class="button olama-reg-add-hist-row" data-student-uid="<?php echo esc_attr($s->student_uid); ?>">
                + <?php esc_html_e( 'إضافة سطر', 'olama-registration' ); ?>
            </button>
        </div><!-- school -->

        <!-- Transport -->
        <div class="olama-reg-sub-pane" id="transport-<?php echo esc_attr($s->student_uid); ?>" style="display:none">
            <div class="olama-reg-grid" data-student-uid="<?php echo esc_attr($s->student_uid); ?>">
                <?php $t = $s_transport; ?>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'الاتجاه', 'olama-registration' ); ?></label>
                    <select class="st-field" name="direction">
                        <?php foreach ( [ '' => '--اختر--', 'go' => 'ذهاب', 'return' => 'إياب', 'both' => 'ذهاب وإياب' ] as $k => $lbl ): ?>
                            <option value="<?php echo esc_attr($k); ?>" <?php selected( $t->direction ?? '', $k ); ?>><?php echo esc_html($lbl); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="olama-reg-field olama-reg-field--checkbox">
                    <label><input type="checkbox" class="st-field" name="has_bus_go" value="1" <?php checked($t->has_bus_go ?? 0,1); ?>><?php esc_html_e('حافلة ذهاب','olama-registration'); ?></label>
                </div>
                <div class="olama-reg-field olama-reg-field--checkbox">
                    <label><input type="checkbox" class="st-field" name="has_bus_return" value="1" <?php checked($t->has_bus_return ?? 0,1); ?>><?php esc_html_e('حافلة إياب','olama-registration'); ?></label>
                </div>
                <div class="olama-reg-field olama-reg-field--checkbox">
                    <label><input type="checkbox" class="st-field" name="has_attendance_go" value="1" <?php checked($t->has_attendance_go ?? 0,1); ?>><?php esc_html_e('حضور ذهاب','olama-registration'); ?></label>
                </div>
                <div class="olama-reg-field olama-reg-field--checkbox">
                    <label><input type="checkbox" class="st-field" name="has_attendance_return" value="1" <?php checked($t->has_attendance_return ?? 0,1); ?>><?php esc_html_e('حضور إياب','olama-registration'); ?></label>
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'رسوم النقل', 'olama-registration' ); ?></label>
                    <input type="number" class="st-field" name="transport_fees" value="<?php echo esc_attr($t->transport_fees ?? ''); ?>" step="0.01">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'من تاريخ', 'olama-registration' ); ?></label>
                    <input type="text" class="st-field olama-reg-datepicker" name="transport_date_from" value="<?php echo esc_attr($t->transport_date_from ?? ''); ?>">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'إلى تاريخ', 'olama-registration' ); ?></label>
                    <input type="text" class="st-field olama-reg-datepicker" name="transport_date_to" value="<?php echo esc_attr($t->transport_date_to ?? ''); ?>">
                </div>
                <div class="olama-reg-field olama-reg-field--checkbox">
                    <label><input type="checkbox" class="st-field" name="transport_is_active" value="1" <?php checked($t->transport_is_active ?? 1,1); ?>><?php esc_html_e('فعال','olama-registration'); ?></label>
                </div>
            </div>
            <div class="olama-reg-form-actions">
                <button type="button" class="button button-primary olama-reg-save-transport"
                        data-student-uid="<?php echo esc_attr($s->student_uid); ?>">
                    <?php esc_html_e( 'حفظ بيانات النقل', 'olama-registration' ); ?>
                </button>
            </div>
        </div><!-- transport -->

        <!-- Student Save Bar -->
        <div class="olama-reg-form-actions olama-reg-student-save-bar">
            <button type="button" class="button button-primary olama-reg-save-student"
                    data-student-uid="<?php echo esc_attr($s->student_uid); ?>"
                    data-family-uid="<?php echo esc_attr($family_uid); ?>">
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e( 'حفظ بيانات الطالب', 'olama-registration' ); ?>
            </button>
        </div>

    </div><!-- .olama-reg-student-body -->
</div><!-- .olama-reg-student-row -->
