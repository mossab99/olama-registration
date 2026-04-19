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
            return OLAMA_REG_URL . 'assets/images/no-photo.png';
        }
        return wp_get_attachment_url( $attachment_id ) ?: OLAMA_REG_URL . 'assets/images/no-photo.png';
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    /**
     * Create a new student, auto-assigning their UID from the family sequence.
     * Returns the new student_uid, or WP_Error.
     */
    public static function create( string $family_uid, array $data ): string|\WP_Error {
        global $wpdb;

        // 1. Load family to get its row ID (needed for atomic sequence update)
        $family = Olama_Reg_Family::get_family( $family_uid );
        if ( ! $family ) {
            return new \WP_Error( 'no_family', __( 'Family not found.', 'olama-registration' ) );
        }

        // 2. Reserve sequence atomically
        try {
            $sequence   = Olama_Reg_ID_Generator::reserve_next_sequence( (int) $family->id );
            $student_uid = Olama_Reg_ID_Generator::generate_student_uid( $family_uid, $sequence );
        } catch ( \Exception $e ) {
            return new \WP_Error( 'sequence_error', $e->getMessage() );
        }

        // 3. Build full name for the legacy student_name column
        $full_name = trim(
            implode( ' ', array_filter( [
                sanitize_text_field( $data['first_name']           ?? '' ),
                sanitize_text_field( $data['second_name']          ?? '' ),
                sanitize_text_field( $data['third_name']           ?? '' ),
                sanitize_text_field( $data['student_family_name']  ?? '' ),
            ] ) )
        );

        // 4. Assemble insert payload
        $payload = self::build_payload( $data );
        $payload['student_uid']         = $student_uid;
        $payload['family_id']           = $family_uid;
        $payload['student_name']        = $full_name ?: $student_uid;
        $payload['sequence_in_family']  = $sequence;
        $payload['created_at']          = current_time( 'mysql', 1 );

        $result = $wpdb->insert( $wpdb->prefix . 'olama_students', $payload );
        if ( $result === false ) {
            // Attempt to roll back the sequence counter
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->prefix}olama_families
                 SET next_student_seq = next_student_seq - 1
                 WHERE id = %d AND next_student_seq > 1",
                $family->id
            ) );
            return new \WP_Error( 'db_error', $wpdb->last_error );
        }

        // 5. Create an initial enrollment record (no section yet)
        if ( class_exists( 'Olama_School_Academic' ) ) {
            $active_year = Olama_School_Academic::get_active_year();
            if ( $active_year ) {
                $student_id = (int) $wpdb->insert_id;
                $wpdb->insert( $wpdb->prefix . 'olama_student_enrollment', [
                    'student_id'       => $student_id,
                    'student_uid'      => $student_uid,
                    'academic_year_id' => $active_year->id,
                    'section_id'       => 0,
                    'enrollment_date'  => current_time( 'mysql', 1 ),
                    'status'           => 'active',
                ] );
            }
        }

        return $student_uid;
    }

    /**
     * Update a student. UID is immutable.
     */
    public static function update( string $student_uid, array $data ): bool|\WP_Error {
        global $wpdb;

        $payload = self::build_payload( $data );

        // Sync legacy student_name
        $parts = array_filter( [
            sanitize_text_field( $data['first_name']          ?? '' ),
            sanitize_text_field( $data['second_name']         ?? '' ),
            sanitize_text_field( $data['third_name']          ?? '' ),
            sanitize_text_field( $data['student_family_name'] ?? '' ),
        ] );
        if ( ! empty( $parts ) ) {
            $payload['student_name'] = implode( ' ', $parts );
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'olama_students',
            $payload,
            [ 'student_uid' => $student_uid ]
        );

        if ( $result === false ) {
            return new \WP_Error( 'db_error', $wpdb->last_error );
        }

        // Clear existing cache so other modules see updated data
        delete_transient( 'olama_students_list_0_0' );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_olama_students_list%'" );

        return true;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function build_payload( array $data ): array {
        $text = fn( string $k ) => sanitize_text_field( $data[ $k ] ?? '' );
        $date = fn( string $k ) => ! empty( $data[ $k ] ) ? Olama_School_Helpers::sanitize_date( $data[ $k ] ) : null;

        return [
            'first_name'           => $text( 'first_name' ),
            'second_name'          => $text( 'second_name' ),
            'third_name'           => $text( 'third_name' ),
            'student_family_name'  => $text( 'student_family_name' ),
            'secondary_family_name'=> $text( 'secondary_family_name' ),
            'national_id'          => $text( 'national_id' ),
            'non_trans_id'         => $text( 'non_trans_id' ),
            'gender'               => in_array( $data['gender'] ?? '', [ 'male', 'female' ], true ) ? $data['gender'] : null,
            'dob'                  => $date( 'dob' ),
            'birth_place'          => $text( 'birth_place' ),
            'nationality'          => $text( 'nationality' ),
            'mother_name'          => $text( 'mother_name' ),
            'student_email'        => sanitize_email( $data['student_email'] ?? '' ),
            'student_phone'        => $text( 'student_phone' ),
            'photo_attachment_id'  => ! empty( $data['photo_attachment_id'] ) ? (int) $data['photo_attachment_id'] : null,
            'from_school'          => $text( 'from_school' ),
            'gpa'                  => is_numeric( $data['gpa'] ?? null ) ? round( (float) $data['gpa'], 2 ) : null,
            'high_school_join_date'=> $date( 'high_school_join_date' ),
            'blacklist'            => isset( $data['blacklist'] ) ? 1 : 0,
            'blacklist_reason'     => sanitize_textarea_field( $data['blacklist_reason'] ?? '' ),
            'student_notes'        => sanitize_textarea_field( $data['student_notes'] ?? '' ),
            'is_active'            => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
        ];
    }
}
