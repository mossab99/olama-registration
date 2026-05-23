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



        <!-- Basic Data -->
        <div class="olama-reg-sub-pane active" id="basic-<?php echo esc_attr($s->student_uid); ?>">

            <div class="olama-reg-grid">
                <div class="olama-reg-field olama-reg-field--wide">
                    <label><?php esc_html_e( 'الاسم الكامل', 'olama-registration' ); ?></label>
                    <input type="text" class="s-field" name="student_name" value="<?php echo esc_attr( $s->student_name ?? '' ); ?>" readonly style="background-color: #f0f0f1; cursor: not-allowed;">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'الرقم الوطني', 'olama-registration' ); ?></label>
                    <input type="text" class="s-field" name="national_id" value="<?php echo esc_attr( $s->national_id ?? '' ); ?>" dir="ltr" readonly style="background-color: #f0f0f1; cursor: not-allowed;">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'الجنس', 'olama-registration' ); ?></label>
                    <input type="text" class="s-field" name="gender" value="<?php echo esc_attr( ($s->gender ?? '') === 'male' ? 'ذكر' : (($s->gender ?? '') === 'female' ? 'أنثى' : '') ); ?>" readonly style="background-color: #f0f0f1; cursor: not-allowed;">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'تاريخ الميلاد', 'olama-registration' ); ?></label>
                    <input type="text" class="s-field" name="dob" value="<?php echo esc_attr( $s->dob ?? '' ); ?>" readonly style="background-color: #f0f0f1; cursor: not-allowed;">
                </div>
                <div class="olama-reg-field olama-reg-field--checkbox olama-reg-field--wide">
                    <label>
                        <input type="checkbox" class="s-field" name="is_active" value="1" <?php checked( $s->is_active ?? 1, 1 ); ?> disabled>
                        <?php esc_html_e( 'طالب فعال', 'olama-registration' ); ?>
                    </label>
                </div>
            </div>
        </div><!-- basic -->





    </div><!-- .olama-reg-student-body -->
</div><!-- .olama-reg-student-row -->
