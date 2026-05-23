<?php
/**
 * Extended Student CRUD with auto-UID assignment
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Olama_Reg_Student {

    // ── Read ──────────────────────────────────────────────────────────────────

    public static function get_student( string $student_uid ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT s.*,
                    f.family_uid, f.father_first_name, f.father_family_name
             FROM {$wpdb->prefix}olama_students s
             LEFT JOIN {$wpdb->prefix}olama_families f ON f.family_uid = s.family_id
             WHERE s.student_uid = %s",
            $student_uid
        ) ) ?: null;
    }

    public static function get_family_students( string $family_uid ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT s.*,
                    e.section_id, e.academic_year_id, e.status AS enrollment_status,
                    g.grade_name, sec.section_name
             FROM {$wpdb->prefix}olama_students s
             LEFT JOIN (
                 SELECT e1.* FROM {$wpdb->prefix}olama_student_enrollment e1
                 WHERE e1.id = (
                     SELECT MAX(e2.id)
                     FROM {$wpdb->prefix}olama_student_enrollment e2
                     WHERE e2.student_uid = e1.student_uid
                 )
             ) e ON e.student_uid = s.student_uid
             LEFT JOIN {$wpdb->prefix}olama_sections sec ON sec.id = e.section_id
             LEFT JOIN {$wpdb->prefix}olama_grades g   ON g.id = sec.grade_id
             WHERE s.family_id = %s
             ORDER BY s.sequence_in_family ASC, s.id ASC",
            $family_uid
        ) ) ?: [];
    }

    public static function get_student_photo_url( ?int $attachment_id ): string {
        if ( ! $attachment_id ) {
            return get_avatar_url( 0, [ 'size' => 150 ] );
        }
        return wp_get_attachment_url( $attachment_id ) ?: get_avatar_url( 0, [ 'size' => 150 ] );
    }


}
