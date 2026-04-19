<?php
/**
 * Print Card — A4-ready family registration card
 * Variables: $family_uid (string)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$family   = Olama_Reg_Family::get_family( $family_uid );
if ( ! $family ) { wp_die( __( 'Family not found.', 'olama-registration' ) ); }

$students = Olama_Reg_Student::get_family_students( $family_uid );
$school_name = get_option( 'olama_school_settings', [] )['school_name_ar'] ?? get_bloginfo( 'name' );
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<title><?php echo esc_html( $school_name ); ?> — بطاقة عائلة <?php echo esc_html( $family_uid ); ?></title>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap');
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Tajawal', Arial, sans-serif; font-size: 13px; color: #1a1a2e; background: #fff; direction: rtl; }
    .card { width: 190mm; min-height: 280mm; margin: 10mm auto; padding: 10mm; border: 2px solid #e8920a; border-radius: 6px; }
    .card-header { text-align: center; margin-bottom: 8mm; border-bottom: 2px solid #e8920a; padding-bottom: 4mm; }
    .school-name { font-size: 18pt; font-weight: 700; color: #c4780a; }
    .card-title  { font-size: 13pt; margin-top: 2mm; color: #555; }
    .uid-badge   { display: inline-block; background: #e8920a; color: #fff; font-size: 20pt; font-weight: 700; padding: 2mm 6mm; border-radius: 6px; margin-top: 3mm; letter-spacing: 2px; }
    .section     { margin-bottom: 6mm; }
    .section h3  { font-size: 11pt; font-weight: 700; background: #fff3e0; color: #c4780a; padding: 2mm 3mm; border-right: 4px solid #e8920a; margin-bottom: 3mm; }
    .grid        { display: grid; grid-template-columns: repeat(2, 1fr); gap: 2mm 4mm; }
    .field       { display: flex; flex-direction: column; gap: 1mm; }
    .field label { font-size: 9pt; color: #888; }
    .field span  { font-size: 10pt; font-weight: 500; background: #fafafa; padding: 1mm 2mm; border-radius: 3px; border: 1px solid #eee; min-height: 6mm; }
    table        { width: 100%; border-collapse: collapse; font-size: 10pt; }
    th, td       { border: 1px solid #ddd; padding: 1.5mm 2mm; text-align: right; }
    th           { background: #fff3e0; color: #c4780a; font-weight: 700; }
    tr:nth-child(even) td { background: #fafafa; }
    .footer      { text-align: center; margin-top: 8mm; font-size: 9pt; color: #aaa; border-top: 1px solid #eee; padding-top: 3mm; }
    @media print {
        body { margin: 0; }
        .card { margin: 0; border: none; width: 100%; }
        @page { size: A4; margin: 10mm; }
    }
</style>
</head>
<body onload="window.print()">

<div class="card">

    <!-- Header -->
    <div class="card-header">
        <div class="school-name"><?php echo esc_html( $school_name ); ?></div>
        <div class="card-title">بطاقة تسجيل العائلة</div>
        <div class="uid-badge"><?php echo esc_html( $family->family_uid ); ?></div>
    </div>

    <!-- Father Data -->
    <div class="section">
        <h3>بيانات الأب</h3>
        <div class="grid">
            <div class="field"><label>الاسم الكامل</label>
                <span><?php echo esc_html( trim( implode( ' ', [ $family->father_first_name, $family->father_second_name, $family->father_third_name, $family->father_family_name ] ) ) ); ?></span>
            </div>
            <div class="field"><label>الجنسية</label><span><?php echo esc_html( $family->father_nationality ?? '—' ); ?></span></div>
            <div class="field"><label>المهنة</label><span><?php echo esc_html( $family->father_job ?? '—' ); ?></span></div>
            <div class="field"><label>جهة العمل</label><span><?php echo esc_html( $family->father_workplace ?? '—' ); ?></span></div>
            <div class="field"><label>جوال</label><span dir="ltr"><?php echo esc_html( $family->father_mobile ?? '—' ); ?></span></div>
            <div class="field"><label>البريد الإلكتروني</label><span dir="ltr"><?php echo esc_html( $family->father_email ?? '—' ); ?></span></div>
        </div>
    </div>

    <!-- Mother Data -->
    <div class="section">
        <h3>بيانات الأم</h3>
        <div class="grid">
            <div class="field"><label>الاسم الكامل</label><span><?php echo esc_html( $family->mother_full_name ?? '—' ); ?></span></div>
            <div class="field"><label>الجنسية</label><span><?php echo esc_html( $family->mother_nationality ?? '—' ); ?></span></div>
            <div class="field"><label>جوال</label><span dir="ltr"><?php echo esc_html( $family->mother_mobile ?? '—' ); ?></span></div>
            <div class="field"><label>البريد الإلكتروني</label><span dir="ltr"><?php echo esc_html( $family->mother_email ?? '—' ); ?></span></div>
        </div>
    </div>

    <!-- Address -->
    <div class="section">
        <h3>بيانات السكن</h3>
        <div class="grid">
            <div class="field"><label>المنطقة</label><span><?php echo esc_html( $family->residential_area ?? '—' ); ?></span></div>
            <div class="field"><label>العنوان</label><span><?php echo esc_html( $family->home_address ?? '—' ); ?></span></div>
            <div class="field"><label>المبنى / الشقة</label><span><?php echo esc_html( ( $family->building_number ?? '—' ) . ' / ' . ( $family->apartment_number ?? '—' ) ); ?></span></div>
            <div class="field"><label>هاتف المنزل</label><span dir="ltr"><?php echo esc_html( $family->home_phone ?? '—' ); ?></span></div>
        </div>
    </div>

    <!-- Students Table -->
    <?php if ( ! empty( $students ) ): ?>
    <div class="section">
        <h3>الطلاب المسجلون</h3>
        <table>
            <thead>
                <tr>
                    <th>رقم الطالب</th>
                    <th>الاسم</th>
                    <th>الجنس</th>
                    <th>تاريخ الميلاد</th>
                    <th>الصف / الشعبة</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $students as $s ): ?>
                <tr>
                    <td><?php echo esc_html( $s->student_uid ); ?></td>
                    <td><?php echo esc_html( $s->student_name ); ?></td>
                    <td><?php echo $s->gender === 'male' ? 'ذكر' : ( $s->gender === 'female' ? 'أنثى' : '—' ); ?></td>
                    <td><?php echo esc_html( $s->dob ?? '—' ); ?></td>
                    <td><?php echo esc_html( ( $s->grade_name ?? '' ) . ( $s->section_name ? ' / ' . $s->section_name : '' ) ?: '—' ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Signatures -->
    <div class="section" style="margin-top:10mm;">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:4mm;text-align:center;">
            <div style="border-top:1px solid #ccc;padding-top:2mm;">توقيع ولي الأمر</div>
            <div style="border-top:1px solid #ccc;padding-top:2mm;">مدير المدرسة</div>
            <div style="border-top:1px solid #ccc;padding-top:2mm;">الختم</div>
        </div>
    </div>

    <div class="footer">
        <?php echo esc_html( $school_name ); ?> — تاريخ الطباعة: <?php echo date_i18n( 'd/m/Y' ); ?>
    </div>

</div>
</body>
</html>
