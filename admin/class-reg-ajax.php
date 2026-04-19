<?php
/**
 * AJAX Endpoints — all 13 actions in one class
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Olama_Reg_Ajax {

    public function __construct() {
        $actions = [
            'olama_reg_save_family',
            'olama_reg_get_family',
            'olama_reg_soft_delete_family',
            'olama_reg_save_student',
            'olama_reg_get_student',
            'olama_reg_save_academic_history',
            'olama_reg_delete_history_row',
            'olama_reg_save_transport',
            'olama_reg_save_financial_row',
            'olama_reg_delete_financial_row',
            'olama_reg_get_financial',
            'olama_reg_search',
            'olama_reg_upload_photo',
        ];

        foreach ( $actions as $action ) {
            $method = 'ajax_' . str_replace( 'olama_reg_', '', $action );
            add_action( 'wp_ajax_' . $action, [ $this, $method ] );
        }
    }

    // ── Guard ─────────────────────────────────────────────────────────────────

    private function guard(): void {
        check_ajax_referer( 'olama_reg_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'olama-registration' ) ], 403 );
        }
    }

    // ── Family ────────────────────────────────────────────────────────────────

    public function ajax_save_family(): void {
        $this->guard();

        $family_uid = sanitize_text_field( $_POST['family_uid'] ?? '' );
        $data       = $_POST;

        if ( $family_uid ) {
            $result = Olama_Reg_Family::update( $family_uid, $data );
        } else {
            $result = Olama_Reg_Family::create( $data );
            if ( ! is_wp_error( $result ) ) {
                $family_uid = $result;
            }
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        $family = Olama_Reg_Family::get_family( $family_uid );
        wp_send_json_success( [
            'message'    => __( 'Family saved successfully.', 'olama-registration' ),
            'family_uid' => $family_uid,
            'family'     => $family,
        ] );
    }

    public function ajax_get_family(): void {
        $this->guard();

        $family_uid = sanitize_text_field( $_POST['family_uid'] ?? '' );
        $family     = Olama_Reg_Family::get_family( $family_uid );

        if ( ! $family ) {
            wp_send_json_error( [ 'message' => __( 'Family not found.', 'olama-registration' ) ] );
        }

        $students = Olama_Reg_Student::get_family_students( $family_uid );

        wp_send_json_success( [
            'family'   => $family,
            'students' => $students,
        ] );
    }

    public function ajax_soft_delete_family(): void {
        $this->guard();

        $family_uid = sanitize_text_field( $_POST['family_uid'] ?? '' );
        $result     = Olama_Reg_Family::soft_delete( $family_uid );

        if ( ! $result ) {
            wp_send_json_error( [ 'message' => __( 'Could not deactivate family.', 'olama-registration' ) ] );
        }

        wp_send_json_success( [ 'message' => __( 'Family deactivated.', 'olama-registration' ) ] );
    }

    // ── Student ───────────────────────────────────────────────────────────────

    public function ajax_save_student(): void {
        $this->guard();

        $student_uid = sanitize_text_field( $_POST['student_uid'] ?? '' );
        $family_uid  = sanitize_text_field( $_POST['family_uid']  ?? '' );
        $data        = $_POST;

        if ( $student_uid ) {
            $result = Olama_Reg_Student::update( $student_uid, $data );
        } else {
            if ( ! $family_uid ) {
                wp_send_json_error( [ 'message' => __( 'Family UID is required to create a student.', 'olama-registration' ) ] );
            }
            $result = Olama_Reg_Student::create( $family_uid, $data );
            if ( ! is_wp_error( $result ) ) {
                $student_uid = $result;
            }
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        $student = Olama_Reg_Student::get_student( $student_uid );
        wp_send_json_success( [
            'message'     => __( 'Student saved successfully.', 'olama-registration' ),
            'student_uid' => $student_uid,
            'student'     => $student,
        ] );
    }

    public function ajax_get_student(): void {
        $this->guard();

        $student_uid = sanitize_text_field( $_POST['student_uid'] ?? '' );
        $student     = Olama_Reg_Student::get_student( $student_uid );

        if ( ! $student ) {
            wp_send_json_error( [ 'message' => __( 'Student not found.', 'olama-registration' ) ] );
        }

        global $wpdb;
        $active_year_id = 0;
        if ( class_exists( 'Olama_School_Academic' ) ) {
            $ay = Olama_School_Academic::get_active_year();
            if ( $ay ) $active_year_id = (int) $ay->id;
        }

        $history   = Olama_Reg_Academic_History::get_history( $student_uid );
        $transport = Olama_Reg_Transport::get_transport( $student_uid, $active_year_id );

        wp_send_json_success( [
            'student'   => $student,
            'history'   => $history,
            'transport' => $transport,
            'photo_url' => Olama_Reg_Student::get_student_photo_url( (int) ( $student->photo_attachment_id ?? 0 ) ),
        ] );
    }

    // ── Academic History ──────────────────────────────────────────────────────

    public function ajax_save_academic_history(): void {
        $this->guard();

        $result = Olama_Reg_Academic_History::save_row( $_POST );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        $history = Olama_Reg_Academic_History::get_history( sanitize_text_field( $_POST['student_uid'] ) );
        wp_send_json_success( [
            'message' => __( 'History saved.', 'olama-registration' ),
            'id'      => $result,
            'history' => $history,
        ] );
    }

    public function ajax_delete_history_row(): void {
        $this->guard();

        $id     = (int) ( $_POST['id'] ?? 0 );
        $result = Olama_Reg_Academic_History::delete_row( $id );

        if ( ! $result ) {
            wp_send_json_error( [ 'message' => __( 'Could not delete row.', 'olama-registration' ) ] );
        }

        wp_send_json_success( [ 'message' => __( 'Row deleted.', 'olama-registration' ) ] );
    }

    // ── Transport ─────────────────────────────────────────────────────────────

    public function ajax_save_transport(): void {
        $this->guard();

        $student_uid      = sanitize_text_field( $_POST['student_uid']      ?? '' );
        $academic_year_id = (int) ( $_POST['academic_year_id'] ?? 0 );

        if ( ! $academic_year_id && class_exists( 'Olama_School_Academic' ) ) {
            $ay = Olama_School_Academic::get_active_year();
            if ( $ay ) $academic_year_id = (int) $ay->id;
        }

        $result = Olama_Reg_Transport::save_transport( $student_uid, $academic_year_id, $_POST );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [
            'message'   => __( 'Transport data saved.', 'olama-registration' ),
            'transport' => Olama_Reg_Transport::get_transport( $student_uid, $academic_year_id ),
        ] );
    }

    // ── Financial ─────────────────────────────────────────────────────────────

    public function ajax_save_financial_row(): void {
        $this->guard();

        $result = Olama_Reg_Financial::save_row( $_POST );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        $family_uid       = sanitize_text_field( $_POST['family_uid'] ?? '' );
        $academic_year_id = (int) ( $_POST['academic_year_id'] ?? 0 );

        wp_send_json_success( [
            'message' => __( 'Row saved.', 'olama-registration' ),
            'id'      => $result,
            'totals'  => Olama_Reg_Financial::get_totals( $family_uid, $academic_year_id ),
        ] );
    }

    public function ajax_delete_financial_row(): void {
        $this->guard();

        $id         = (int) ( $_POST['id'] ?? 0 );
        $family_uid = sanitize_text_field( $_POST['family_uid'] ?? '' );
        $year_id    = (int) ( $_POST['academic_year_id'] ?? 0 );

        Olama_Reg_Financial::delete_row( $id );

        wp_send_json_success( [
            'message' => __( 'Row deleted.', 'olama-registration' ),
            'totals'  => Olama_Reg_Financial::get_totals( $family_uid, $year_id ),
        ] );
    }

    public function ajax_get_financial(): void {
        $this->guard();

        $family_uid       = sanitize_text_field( $_POST['family_uid'] ?? '' );
        $academic_year_id = (int) ( $_POST['academic_year_id'] ?? 0 );

        wp_send_json_success( [
            'rows'   => Olama_Reg_Financial::get_entitlements( $family_uid, $academic_year_id ),
            'totals' => Olama_Reg_Financial::get_totals( $family_uid, $academic_year_id ),
        ] );
    }

    // ── Search ────────────────────────────────────────────────────────────────

    public function ajax_search(): void {
        $this->guard();

        global $wpdb;
        $q      = sanitize_text_field( $_POST['q'] ?? '' );
        $like   = '%' . $wpdb->esc_like( $q ) . '%';

        $families = $wpdb->get_results( $wpdb->prepare(
            "SELECT family_uid, father_first_name, father_family_name, is_active
             FROM {$wpdb->prefix}olama_families
             WHERE family_uid LIKE %s OR father_first_name LIKE %s OR father_family_name LIKE %s
             LIMIT 10",
            $like, $like, $like
        ) );

        $students = $wpdb->get_results( $wpdb->prepare(
            "SELECT student_uid, student_name, national_id, family_id
             FROM {$wpdb->prefix}olama_students
             WHERE student_uid LIKE %s OR student_name LIKE %s OR national_id LIKE %s
             LIMIT 10",
            $like, $like, $like
        ) );

        wp_send_json_success( [ 'families' => $families, 'students' => $students ] );
    }

    // ── Photo Upload ──────────────────────────────────────────────────────────

    public function ajax_upload_photo(): void {
        $this->guard();

        if ( empty( $_FILES['photo'] ) ) {
            wp_send_json_error( [ 'message' => __( 'No file received.', 'olama-registration' ) ] );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload( 'photo', 0 );

        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( [ 'message' => $attachment_id->get_error_message() ] );
        }

        wp_send_json_success( [
            'attachment_id' => $attachment_id,
            'url'           => wp_get_attachment_url( $attachment_id ),
            'thumb'         => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
        ] );
    }
}
