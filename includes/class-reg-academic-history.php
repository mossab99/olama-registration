<?php
/**
 * Academic History CRUD
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Olama_Reg_Academic_History {

    public static function get_history( string $student_uid ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_reg_academic_history
             WHERE student_uid = %s
             ORDER BY is_current DESC, registration_date DESC, id DESC",
            $student_uid
        ) ) ?: [];
    }

    public static function save_row( array $data ): int|\WP_Error {
        global $wpdb;

        $student_uid = sanitize_text_field( $data['student_uid'] ?? '' );
        if ( ! $student_uid ) {
            return new \WP_Error( 'missing_uid', __( 'Student UID is required.', 'olama-registration' ) );
        }

        $payload = [
            'student_uid'       => $student_uid,
            'academic_year'     => sanitize_text_field( $data['academic_year']     ?? '' ),
            'school_name'       => sanitize_text_field( $data['school_name']       ?? '' ),
            'grade'             => sanitize_text_field( $data['grade']             ?? '' ),
            'branch'            => sanitize_text_field( $data['branch']            ?? '' ),
            'section'           => sanitize_text_field( $data['section']           ?? '' ),
            'registration_date' => ! empty( $data['registration_date'] ) ? Olama_School_Helpers::sanitize_date( $data['registration_date'] ) : null,
            'withdrawal_date'   => ! empty( $data['withdrawal_date'] )   ? Olama_School_Helpers::sanitize_date( $data['withdrawal_date'] )   : null,
            'student_status'    => sanitize_text_field( $data['student_status']    ?? '' ),
            'hist_notes'        => sanitize_textarea_field( $data['hist_notes']    ?? '' ),
            'is_current'        => isset( $data['is_current'] ) ? (int) $data['is_current'] : 0,
        ];

        // If marking as current, demote all other rows for this student
        if ( $payload['is_current'] === 1 ) {
            $wpdb->update(
                $wpdb->prefix . 'olama_reg_academic_history',
                [ 'is_current' => 0 ],
                [ 'student_uid' => $student_uid ]
            );
        }

        $id = ! empty( $data['id'] ) ? (int) $data['id'] : 0;

        if ( $id ) {
            $result = $wpdb->update(
                $wpdb->prefix . 'olama_reg_academic_history',
                $payload,
                [ 'id' => $id ]
            );
            return $result !== false ? $id : new \WP_Error( 'db_error', $wpdb->last_error );
        }

        $result = $wpdb->insert( $wpdb->prefix . 'olama_reg_academic_history', $payload );
        return $result ? (int) $wpdb->insert_id : new \WP_Error( 'db_error', $wpdb->last_error );
    }

    public static function delete_row( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( $wpdb->prefix . 'olama_reg_academic_history', [ 'id' => $id ] );
    }
}
