<?php
/**
 * Agreement Clauses CRUD
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Olama_Reg_Agreement_Clauses {

    /**
     * Add a clause
     */
    public static function add( int $agreement_id, string $text, int $sort_order = 0 ): int|false {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreement_clauses';

        $inserted = $wpdb->insert( $table, [
            'agreement_id' => $agreement_id,
            'clause_text'  => $text,
            'sort_order'   => $sort_order,
        ] );

        if ( $inserted ) {
            return (int) $wpdb->insert_id;
        }
        return false;
    }

    /**
     * Update a clause
     */
    public static function update( int $id, string $text ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreement_clauses';

        $updated = $wpdb->update( $table, [ 'clause_text' => $text ], [ 'id' => $id ] );
        return $updated !== false;
    }

    /**
     * Delete a clause
     */
    public static function delete( int $id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreement_clauses';

        $deleted = $wpdb->delete( $table, [ 'id' => $id ] );
        return $deleted !== false;
    }

    /**
     * Update sort order for multiple clauses
     */
    public static function reorder( array $ordered_ids ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreement_clauses';

        $order = 1;
        foreach ( $ordered_ids as $id ) {
            $wpdb->update( $table, [ 'sort_order' => $order ], [ 'id' => (int) $id ] );
            $order++;
        }
    }

    /**
     * Get all clauses for an agreement
     */
    public static function get_by_agreement( int $agreement_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreement_clauses';
        
        return $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM {$table} WHERE agreement_id = %d ORDER BY sort_order ASC, id ASC", 
            $agreement_id 
        ) );
    }
}
