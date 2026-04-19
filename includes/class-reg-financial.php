<?php
/**
 * Financial Entitlements CRUD — balance is always computed, never stored
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Olama_Reg_Financial {

    // ── Read ──────────────────────────────────────────────────────────────────

    public static function get_entitlements( string $family_uid, int $academic_year_id ): array {
        global $wpdb;

        $params = [ $family_uid ];
        $year_clause = '';
        if ( $academic_year_id > 0 ) {
            $year_clause = ' AND academic_year_id = %d';
            $params[]    = $academic_year_id;
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT *,
                    ( amount_due - amount_paid + payments_revolving ) AS balance
             FROM {$wpdb->prefix}olama_reg_financial
             WHERE family_uid = %s {$year_clause}
             ORDER BY entitlement_date ASC, id ASC",
            ...$params
        ) ) ?: [];
    }

    public static function get_totals( string $family_uid, int $academic_year_id ): object {
        global $wpdb;

        $params = [ $family_uid ];
        $year_clause = '';
        if ( $academic_year_id > 0 ) {
            $year_clause = ' AND academic_year_id = %d';
            $params[]    = $academic_year_id;
        }

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT
                 SUM( amount_due )          AS total_due,
                 SUM( amount_paid )         AS total_paid,
                 SUM( payments_revolving )  AS total_revolving,
                 SUM( amount_due - amount_paid + payments_revolving ) AS total_balance
             FROM {$wpdb->prefix}olama_reg_financial
             WHERE family_uid = %s {$year_clause}",
            ...$params
        ) ) ?: (object) [
            'total_due'       => 0,
            'total_paid'      => 0,
            'total_revolving' => 0,
            'total_balance'   => 0,
        ];
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    public static function save_row( array $data ): int|\WP_Error {
        global $wpdb;

        $family_uid = sanitize_text_field( $data['family_uid'] ?? '' );
        if ( ! $family_uid ) {
            return new \WP_Error( 'missing_family', __( 'Family UID is required.', 'olama-registration' ) );
        }

        $payload = [
            'family_uid'          => $family_uid,
            'academic_year_id'    => (int) ( $data['academic_year_id'] ?? 0 ),
            'entitlement_date'    => ! empty( $data['entitlement_date'] ) ? Olama_School_Helpers::sanitize_date( $data['entitlement_date'] ) : null,
            'calculation_method'  => sanitize_text_field( $data['calculation_method'] ?? '' ),
            'percentage'          => is_numeric( $data['percentage'] ?? null ) ? round( (float) $data['percentage'], 2 ) : null,
            'amount_due'          => is_numeric( $data['amount_due'] ?? null ) ? round( (float) $data['amount_due'], 2 ) : 0.00,
            'amount_paid'         => is_numeric( $data['amount_paid'] ?? null ) ? round( (float) $data['amount_paid'], 2 ) : 0.00,
            'payments_revolving'  => is_numeric( $data['payments_revolving'] ?? null ) ? round( (float) $data['payments_revolving'], 2 ) : 0.00,
            'payment_reference'   => sanitize_text_field( $data['payment_reference'] ?? '' ),
            'fin_notes'           => sanitize_textarea_field( $data['fin_notes'] ?? '' ),
        ];

        $id = ! empty( $data['id'] ) ? (int) $data['id'] : 0;

        if ( $id ) {
            $result = $wpdb->update( $wpdb->prefix . 'olama_reg_financial', $payload, [ 'id' => $id ] );
            if ( $result === false ) return new \WP_Error( 'db_error', $wpdb->last_error );
            return $id;
        }

        $result = $wpdb->insert( $wpdb->prefix . 'olama_reg_financial', $payload );
        if ( ! $result ) return new \WP_Error( 'db_error', $wpdb->last_error );

        return (int) $wpdb->insert_id;
    }

    public static function delete_row( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( $wpdb->prefix . 'olama_reg_financial', [ 'id' => $id ] );
    }
}
