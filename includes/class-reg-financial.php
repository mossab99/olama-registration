<?php
/**
 * Financial Entitlements CRUD — balance is always computed, never stored.
 *
 * Table: {$wpdb->prefix}olama_reg_financial
 *
 * Static methods consumed by Olama_Reg_Ajax:
 *   ::save_row( array $data )                                    → int|WP_Error
 *   ::delete_row( int $id )                                      → bool
 *   ::get_entitlements( string $family_uid, int $year_id )       → array
 *   ::get_totals( string $family_uid, int $year_id )             → object
 *   ::get_family_balance( string $family_uid, int $year_id )     → float
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Olama_Reg_Financial {

    /** @var string */
    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'olama_reg_financial';
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    /**
     * Return all entitlement rows for a family / year, ordered by date then id.
     * Each row object includes a computed `balance` column.
     *
     * @param string $family_uid
     * @param int    $academic_year_id  Pass 0 to retrieve all years.
     * @return object[]
     */
    public static function get_entitlements( string $family_uid, int $academic_year_id ): array {
        global $wpdb;

        $params      = [ $family_uid ];
        $year_clause = '';

        if ( $academic_year_id > 0 ) {
            $year_clause = ' AND academic_year_id = %d';
            $params[]    = $academic_year_id;
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT *,
                    ( amount_due - amount_paid + payments_revolving ) AS balance
             FROM " . self::table() . "
             WHERE family_uid = %s {$year_clause}
             ORDER BY entitlement_date ASC, id ASC",
            ...$params
        ) ) ?: [];
    }

    /**
     * Return aggregate totals for a family / year using SQL SUM().
     * Falls back to zero-filled object when no rows exist.
     *
     * Properties: total_due, total_paid, total_revolving, total_balance
     *
     * @param string $family_uid
     * @param int    $academic_year_id  Pass 0 for all years.
     * @return object
     */
    public static function get_totals( string $family_uid, int $academic_year_id ): object {
        global $wpdb;

        $params      = [ $family_uid ];
        $year_clause = '';

        if ( $academic_year_id > 0 ) {
            $year_clause = ' AND academic_year_id = %d';
            $params[]    = $academic_year_id;
        }

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                 COALESCE( SUM( amount_due ),           0 ) AS total_due,
                 COALESCE( SUM( amount_paid ),          0 ) AS total_paid,
                 COALESCE( SUM( payments_revolving ),   0 ) AS total_revolving,
                 COALESCE( SUM( amount_due - amount_paid + payments_revolving ), 0 ) AS total_balance
             FROM " . self::table() . "
             WHERE family_uid = %s {$year_clause}",
            ...$params
        ) );

        return $row ?: (object) [
            'total_due'       => 0,
            'total_paid'      => 0,
            'total_revolving' => 0,
            'total_balance'   => 0,
        ];
    }

    /**
     * Convenience accessor: net balance as a single float.
     * Intended for use by other modules (e.g. reports, billing integrations).
     *
     * balance = total_due - total_paid + total_revolving
     *
     * @param string $family_uid
     * @param int    $academic_year_id  Pass 0 for all years.
     * @return float
     */
    public static function get_family_balance( string $family_uid, int $academic_year_id ): float {
        $totals = self::get_totals( $family_uid, $academic_year_id );
        return (float) ( $totals->total_balance ?? 0 );
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    /**
     * Insert or update a financial entitlement row.
     *
     * Rules:
     *  - $data['id'] > 0  → UPDATE the existing row.
     *  - $data['id'] == 0 → INSERT a new row.
     *
     * @param array $data  Raw POST-style array; all fields are sanitised internally.
     * @return int|WP_Error  Row ID on success, WP_Error on failure.
     */
    public static function save_row( array $data ): int|\WP_Error {
        global $wpdb;

        // ── Required field ────────────────────────────────────────────────────
        $family_uid = sanitize_text_field( $data['family_uid'] ?? '' );
        if ( ! $family_uid ) {
            return new \WP_Error( 'missing_family', __( 'Family UID is required.', 'olama-registration' ) );
        }

        // ── Build sanitised payload ───────────────────────────────────────────
        $entitlement_date = null;
        if ( ! empty( $data['entitlement_date'] ) ) {
            // Use the shared date sanitizer when available, otherwise plain sanitize.
            if ( class_exists( 'Olama_School_Helpers' ) && method_exists( 'Olama_School_Helpers', 'sanitize_date' ) ) {
                $entitlement_date = Olama_School_Helpers::sanitize_date( $data['entitlement_date'] );
            } else {
                $raw = sanitize_text_field( $data['entitlement_date'] );
                // Accept YYYY-MM-DD only; discard anything else.
                $entitlement_date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ? $raw : null;
            }
        }

        $payload = [
            'family_uid'         => $family_uid,
            'academic_year_id'   => absint( $data['academic_year_id'] ?? 0 ),
            'entitlement_date'   => $entitlement_date,
            'calculation_method' => sanitize_text_field( $data['calculation_method'] ?? '' ),
            'percentage'         => is_numeric( $data['percentage'] ?? null )
                                        ? round( (float) $data['percentage'], 2 )
                                        : null,
            'amount_due'         => is_numeric( $data['amount_due'] ?? null )
                                        ? round( (float) $data['amount_due'], 2 )
                                        : 0.00,
            'amount_paid'        => is_numeric( $data['amount_paid'] ?? null )
                                        ? round( (float) $data['amount_paid'], 2 )
                                        : 0.00,
            'payments_revolving' => is_numeric( $data['payments_revolving'] ?? null )
                                        ? round( (float) $data['payments_revolving'], 2 )
                                        : 0.00,
            'payment_reference'  => sanitize_text_field( $data['payment_reference'] ?? '' ),
            'fin_notes'          => sanitize_textarea_field( $data['fin_notes'] ?? '' ),
        ];

        $id = absint( $data['id'] ?? 0 );

        // ── UPDATE ────────────────────────────────────────────────────────────
        if ( $id > 0 ) {
            $result = $wpdb->update(
                self::table(),
                $payload,
                [ 'id' => $id ],
                null,       // format auto-detected by wpdb
                [ '%d' ]    // where format
            );

            if ( false === $result ) {
                return new \WP_Error( 'db_update_error', $wpdb->last_error );
            }

            return $id;
        }

        // ── INSERT ────────────────────────────────────────────────────────────
        $result = $wpdb->insert( self::table(), $payload );

        if ( ! $result ) {
            return new \WP_Error( 'db_insert_error', $wpdb->last_error );
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Delete a financial row by ID.
     *
     * Verifies the row exists before attempting the delete.
     *
     * @param int $id
     * @return bool  true on success, false if row not found or delete failed.
     */
    public static function delete_row( int $id ): bool {
        global $wpdb;

        if ( $id <= 0 ) {
            return false;
        }

        // Verify the row exists.
        $exists = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE id = %d LIMIT 1",
            $id
        ) );

        if ( ! $exists ) {
            return false;
        }

        $deleted = $wpdb->delete(
            self::table(),
            [ 'id' => $id ],
            [ '%d' ]
        );

        return ( false !== $deleted && $deleted > 0 );
    }
}
