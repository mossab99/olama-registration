<?php
/**
 * WP_List_Table — Students
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Olama_Reg_Student_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'student',
            'plural'   => 'students',
            'ajax'     => false,
        ] );
    }

    public function get_columns(): array {
        return [
            'student_uid'  => __( 'رقم الطالب', 'olama-registration' ),
            'student_name' => __( 'الاسم الكامل', 'olama-registration' ),
            'family_uid'   => __( 'رقم العائلة', 'olama-registration' ),
            'grade'        => __( 'الصف / الشعبة', 'olama-registration' ),
            'status_col'   => __( 'الحالة', 'olama-registration' ),
        ];
    }

    public function prepare_items(): void {
        global $wpdb;

        $search  = sanitize_text_field( $_REQUEST['s'] ?? '' );
        $like    = '%' . $wpdb->esc_like( $search ) . '%';

        $where  = '1=1';
        $params = [];

        if ( $search ) {
            $where   .= ' AND ( s.student_uid LIKE %s OR s.student_name LIKE %s OR s.national_id LIKE %s )';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $per_page = 20;
        $current  = $this->get_pagenum();
        $offset   = ( $current - 1 ) * $per_page;

        $count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}olama_students s WHERE {$where}";
        $total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ) : $wpdb->get_var( $count_sql ) );

        $params[] = $per_page;
        $params[] = $offset;

        $sql = "SELECT s.*,
                    g.grade_name, sec.section_name
                FROM {$wpdb->prefix}olama_students s
                LEFT JOIN (
                    SELECT e1.* FROM {$wpdb->prefix}olama_student_enrollment e1
                    WHERE e1.id = (
                        SELECT MAX(e2.id) FROM {$wpdb->prefix}olama_student_enrollment e2
                        WHERE e2.student_uid = e1.student_uid
                    )
                ) e ON e.student_uid = s.student_uid
                LEFT JOIN {$wpdb->prefix}olama_sections sec ON sec.id = e.section_id
                LEFT JOIN {$wpdb->prefix}olama_grades g ON g.id = sec.grade_id
                WHERE {$where}
                ORDER BY CAST( s.student_uid AS UNSIGNED ) DESC
                LIMIT %d OFFSET %d";

        $this->items = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) ) ?: [];

        $this->set_pagination_args( [
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total / $per_page ),
        ] );

        $this->_column_headers = [ $this->get_columns(), [], [] ];
    }

    protected function column_student_uid( $item ): string {
        $url = add_query_arg( [
            'page'       => 'olama-registration',
            'action'     => 'edit',
            'family_uid' => $item->family_id,
            'tab'        => 'students',
            'student_uid'=> $item->student_uid,
        ], admin_url( 'admin.php' ) );

        return '<a href="' . esc_url( $url ) . '" class="olama-reg-uid-badge olama-reg-uid-badge--student">'
             . esc_html( $item->student_uid )
             . '</a>';
    }

    protected function column_student_name( $item ): string {
        return esc_html( $item->student_name ?? '—' );
    }

    protected function column_family_uid( $item ): string {
        $url = add_query_arg( [ 'page' => 'olama-registration', 'action' => 'edit', 'family_uid' => $item->family_id ], admin_url( 'admin.php' ) );
        return '<a href="' . esc_url( $url ) . '">'
             . '<span class="olama-reg-uid-badge olama-reg-uid-badge--family">' . esc_html( $item->family_id ?? '—' ) . '</span>'
             . '</a>';
    }

    protected function column_grade( $item ): string {
        $grade   = esc_html( $item->grade_name   ?? '' );
        $section = esc_html( $item->section_name ?? '' );
        if ( ! $grade && ! $section ) return '—';
        return $grade . ( $section ? " / {$section}" : '' );
    }

    protected function column_status_col( $item ): string {
        if ( $item->blacklist ?? false ) {
            return '<span class="olama-reg-badge olama-reg-badge--blacklist">' . __( 'قائمة سوداء', 'olama-registration' ) . '</span>';
        }
        if ( $item->is_active ) {
            return '<span class="olama-reg-badge olama-reg-badge--active">' . __( 'نشط', 'olama-registration' ) . '</span>';
        }
        return '<span class="olama-reg-badge olama-reg-badge--inactive">' . __( 'غير نشط', 'olama-registration' ) . '</span>';
    }

    protected function column_default( $item, $column_name ): string {
        return esc_html( $item->$column_name ?? '—' );
    }
}
