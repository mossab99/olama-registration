<?php
/**
 * Extended Transport CRUD — reads/writes olama_student_bus_assignments
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Olama_Reg_Transport {

    public static function get_transport( string $student_uid, int $academic_year_id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT a.*, b.bus_number, b.plate_number
             FROM {$wpdb->prefix}olama_student_bus_assignments a
             LEFT JOIN {$wpdb->prefix}olama_transport_buses b ON b.id = a.bus_id
             WHERE a.student_uid = %s AND a.academic_year_id = %d",
            $student_uid,
            $academic_year_id
        ) ) ?: null;
    }

    public static function save_transport( string $student_uid, int $academic_year_id, array $data ): bool|\WP_Error {
        global $wpdb;

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}olama_student_bus_assignments
             WHERE student_uid = %s AND academic_year_id = %d",
            $student_uid,
            $academic_year_id
        ) );

        // Get the student's auto-increment id for the required column
        $student_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}olama_students WHERE student_uid = %s",
            $student_uid
        ) );

        $payload = [
            'student_uid'            => $student_uid,
            'academic_year_id'       => $academic_year_id,
            'bus_id'                 => (int) ( $data['bus_id'] ?? 0 ),
            'pickup_location'        => sanitize_text_field( $data['pickup_location'] ?? '' ),
            'dropoff_location'       => sanitize_text_field( $data['dropoff_location'] ?? '' ),
            'notes'                  => sanitize_textarea_field( $data['notes'] ?? '' ),
            'direction'              => sanitize_text_field( $data['direction'] ?? '' ),
            'has_bus_go'             => isset( $data['has_bus_go'] ) ? 1 : 0,
            'has_bus_return'         => isset( $data['has_bus_return'] ) ? 1 : 0,
            'has_attendance_go'      => isset( $data['has_attendance_go'] ) ? 1 : 0,
            'has_attendance_return'  => isset( $data['has_attendance_return'] ) ? 1 : 0,
            'transport_fees'         => is_numeric( $data['transport_fees'] ?? null ) ? (float) $data['transport_fees'] : null,
            'transport_date_from'    => ! empty( $data['transport_date_from'] ) ? Olama_School_Helpers::sanitize_date( $data['transport_date_from'] ) : null,
            'transport_date_to'      => ! empty( $data['transport_date_to'] )   ? Olama_School_Helpers::sanitize_date( $data['transport_date_to'] )   : null,
            'transport_is_active'    => isset( $data['transport_is_active'] ) ? 1 : 0,
        ];

        if ( $existing ) {
            $result = $wpdb->update(
                $wpdb->prefix . 'olama_student_bus_assignments',
                $payload,
                [ 'student_uid' => $student_uid, 'academic_year_id' => $academic_year_id ]
            );
        } else {
            $payload['student_id']  = $student_id;
            $payload['assigned_by'] = get_current_user_id();
            $result = $wpdb->insert( $wpdb->prefix . 'olama_student_bus_assignments', $payload );
        }

        if ( $result === false ) {
            return new \WP_Error( 'db_error', $wpdb->last_error );
        }

        return true;
    }
}
