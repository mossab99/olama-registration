<?php
/**
 * ID Generation — atomic sequence logic
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Olama_Reg_ID_Generator {

    /**
     * Generate the next available family UID (pure numeric string).
     * Looks at MAX of existing family_uid values cast to integer.
     */
    public static function next_family_uid(): string {
        global $wpdb;

        $max = $wpdb->get_var(
            "SELECT MAX( CAST( family_uid AS UNSIGNED ) )
             FROM {$wpdb->prefix}olama_families"
        );

        return (string) ( ( (int) $max ) + 1 );
    }

    /**
     * Reserve the next sequence for a family using MySQL's LAST_INSERT_ID()
     * atomic pattern — safe under concurrent requests.
     *
     * @param  int $family_row_id  The auto-increment PK of the family row.
     * @return int                 The reserved sequence (1–99).
     * @throws Exception           When family has reached 99-student limit.
     */
    public static function reserve_next_sequence( int $family_row_id ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'olama_families';

        // Atomically increment and capture — LAST_INSERT_ID() is connection-scoped
        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$table}
             SET next_student_seq = LAST_INSERT_ID( next_student_seq + 1 )
             WHERE id = %d
               AND next_student_seq <= 99",
            $family_row_id
        ) );

        if ( ! $updated ) {
            throw new Exception(
                __( 'Maximum students per family (99) has been reached.', 'olama-registration' )
            );
        }

        $seq = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );

        if ( $seq < 1 || $seq > 99 ) {
            throw new Exception(
                __( 'Could not reserve a valid student sequence number.', 'olama-registration' )
            );
        }

        return $seq;
    }

    /**
     * Build a student UID from a family UID and a sequence number.
     * e.g. family "876", sequence 2 → "87602"
     */
    public static function generate_student_uid( string $family_uid, int $sequence ): string {
        return $family_uid . str_pad( (string) $sequence, 2, '0', STR_PAD_LEFT );
    }
}
