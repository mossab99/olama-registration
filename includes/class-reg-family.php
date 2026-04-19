<?php
/**
 * Extended Family CRUD
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Olama_Reg_Family {

    // ── Read ──────────────────────────────────────────────────────────────────

    public static function get_family( string $family_uid ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_families WHERE family_uid = %s",
            $family_uid
        ) ) ?: null;
    }

    public static function get_family_by_id( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_families WHERE id = %d",
            $id
        ) ) ?: null;
    }

    /**
     * Get family list for WP_List_Table.
     */
    public static function get_families_list( array $args = [] ): array {
        global $wpdb;

        $search    = sanitize_text_field( $args['search'] ?? '' );
        $status    = $args['status'] ?? 'all'; // 'all','active','inactive'
        $per_page  = max( 1, (int) ( $args['per_page'] ?? 20 ) );
        $offset    = max( 0, (int) ( $args['offset'] ?? 0 ) );

        $where = '1=1';
        $params = [];

        if ( $search !== '' ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= ' AND ( f.family_uid LIKE %s OR f.father_first_name LIKE %s OR f.father_family_name LIKE %s OR f.family_name LIKE %s )';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ( $status === 'active' ) {
            $where .= ' AND f.is_active = 1';
        } elseif ( $status === 'inactive' ) {
            $where .= ' AND f.is_active = 0';
        }

        $params[] = $per_page;
        $params[] = $offset;

        $sql = "SELECT f.*,
                    COUNT( CASE WHEN s.is_active = 1 THEN 1 END ) AS active_student_count,
                    COUNT( s.id ) AS total_student_count
                FROM {$wpdb->prefix}olama_families f
                LEFT JOIN {$wpdb->prefix}olama_students s ON s.family_id = f.family_uid
                WHERE {$where}
                GROUP BY f.id
                ORDER BY CAST( f.family_uid AS UNSIGNED ) DESC
                LIMIT %d OFFSET %d";

        return $wpdb->get_results( empty( $params ) ? $sql : $wpdb->prepare( $sql, ...$params ) ) ?: [];
    }

    public static function count_families( array $args = [] ): int {
        global $wpdb;

        $search = sanitize_text_field( $args['search'] ?? '' );
        $status = $args['status'] ?? 'all';

        $where  = '1=1';
        $params = [];

        if ( $search !== '' ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= ' AND ( family_uid LIKE %s OR father_first_name LIKE %s OR father_family_name LIKE %s OR family_name LIKE %s )';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ( $status === 'active' )   $where .= ' AND is_active = 1';
        if ( $status === 'inactive' ) $where .= ' AND is_active = 0';

        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}olama_families WHERE {$where}";
        return (int) ( empty( $params ) ? $wpdb->get_var( $sql ) : $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) ) );
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    /**
     * Create a new family. Auto-generates family_uid.
     * Returns family_uid on success, WP_Error on failure.
     */
    public static function create( array $data ): string|\WP_Error {
        global $wpdb;

        $uid = Olama_Reg_ID_Generator::next_family_uid();

        $payload = self::build_payload( $data );
        $payload['family_uid']  = $uid;
        $payload['family_name'] = sanitize_text_field( $data['father_family_name'] ?? $uid );
        $payload['created_at']  = current_time( 'mysql', 1 );

        $result = $wpdb->insert( $wpdb->prefix . 'olama_families', $payload );
        if ( $result === false ) {
            return new \WP_Error( 'db_error', $wpdb->last_error );
        }

        return $uid;
    }

    /**
     * Update an existing family record.
     */
    public static function update( string $family_uid, array $data ): bool|\WP_Error {
        global $wpdb;

        $payload = self::build_payload( $data );

        // Sync legacy family_name field
        if ( ! empty( $data['father_family_name'] ) ) {
            $payload['family_name'] = sanitize_text_field( $data['father_family_name'] );
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'olama_families',
            $payload,
            [ 'family_uid' => $family_uid ]
        );

        if ( $result === false ) {
            return new \WP_Error( 'db_error', $wpdb->last_error );
        }

        return true;
    }

    /**
     * Soft-delete: set is_active = 0.
     */
    public static function soft_delete( string $family_uid ): bool {
        global $wpdb;
        return (bool) $wpdb->update(
            $wpdb->prefix . 'olama_families',
            [ 'is_active' => 0 ],
            [ 'family_uid' => $family_uid ]
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function build_payload( array $data ): array {
        $text = fn( string $k ) => sanitize_text_field( $data[ $k ] ?? '' );
        $date = fn( string $k ) => ! empty( $data[ $k ] ) ? Olama_School_Helpers::sanitize_date( $data[ $k ] ) : null;

        return [
            // Father
            'father_first_name'       => $text( 'father_first_name' ),
            'father_second_name'      => $text( 'father_second_name' ),
            'father_third_name'       => $text( 'father_third_name' ),
            'father_family_name'      => $text( 'father_family_name' ),
            'father_secondary_family' => $text( 'father_secondary_family' ),
            'father_name_t'           => $text( 'father_name_t' ),
            'father_second_t'         => $text( 'father_second_t' ),
            'father_third_t'          => $text( 'father_third_t' ),
            'father_nationality'      => $text( 'father_nationality' ),
            'father_job'              => $text( 'father_job' ),
            'father_workplace'        => $text( 'father_workplace' ),
            'father_phone'            => $text( 'father_phone' ),
            'father_mobile'           => $text( 'father_mobile' ),
            'father_email'            => sanitize_email( $data['father_email'] ?? '' ),
            'father_doc_type'         => $text( 'father_doc_type' ),
            'father_doc_number'       => $text( 'father_doc_number' ),
            'father_doc_issue_place'  => $text( 'father_doc_issue_place' ),
            'father_doc_issue_date'   => $date( 'father_doc_issue_date' ),
            'father_doc_expiry_date'  => $date( 'father_doc_expiry_date' ),
            'father_employee_affairs' => $text( 'father_employee_affairs' ),
            'father_is_employee'      => isset( $data['father_is_employee'] ) ? 1 : 0,
            // Mother
            'mother_full_name'        => $text( 'mother_full_name' ),
            'mother_nationality'      => $text( 'mother_nationality' ),
            'mother_job'              => $text( 'mother_job' ),
            'mother_workplace'        => $text( 'mother_workplace' ),
            'mother_mobile'           => $text( 'mother_mobile' ),
            'mother_email'            => sanitize_email( $data['mother_email'] ?? '' ),
            'mother_employee_affairs' => $text( 'mother_employee_affairs' ),
            'mother_is_employee'      => isset( $data['mother_is_employee'] ) ? 1 : 0,
            // Other
            'residential_area'        => $text( 'residential_area' ),
            'home_address'            => $text( 'home_address' ),
            'address'                 => $text( 'address' ),
            'building_number'         => $text( 'building_number' ),
            'apartment_number'        => $text( 'apartment_number' ),
            'home_phone'              => $text( 'home_phone' ),
            'classification'          => $text( 'classification' ),
            'reg_notes'               => sanitize_textarea_field( $data['reg_notes'] ?? '' ),
            'is_active'               => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
        ];
    }
}
