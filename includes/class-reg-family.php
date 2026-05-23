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
            $where .= ' AND ( f.family_uid LIKE %s OR f.family_name LIKE %s )';
            $params[] = $like;
            $params[] = $like;
        }

        // is_active doesn't exist in core olama_families anymore, we might need to remove status checks if it doesn't exist, but we will leave it out for now.
        // If core table has no is_active, this would throw a SQL error. Let's check core table:
        // core olama_families has: id, family_uid, family_name, mother_mobile, father_mobile, address, created_at
        // It does NOT have is_active. So we must remove is_active filtering.


        $params[] = $per_page;
        $params[] = $offset;

        $sql = "SELECT f.*,
                    0 AS active_student_count, /* Removed as is_active not in core students table? Wait core students has is_active */
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
            $where .= ' AND ( family_uid LIKE %s OR family_name LIKE %s )';
            $params[] = $like;
            $params[] = $like;
        }

        // is_active removed as it doesn't exist in core family table

        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}olama_families WHERE {$where}";
        return (int) ( empty( $params ) ? $wpdb->get_var( $sql ) : $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) ) );
    }


}
