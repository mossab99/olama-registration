<?php
/**
 * Agreements Core Header class
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Olama_Reg_Agreement {

    /**
     * Create a new agreement
     */
    public static function create( array $data ): int|false {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreements';

        $number = Olama_Reg_ID_Generator::generate_id( 'AGR' );

        $defaults = [
            'agreement_number' => $number,
            'payer_type'       => 'customer',
            'payer_id'         => 0,
            'participant_type' => 'child',
            'participant_id'   => 0, // kept for backward compatibility
            'participant_ids'  => null,
            'activity_type'    => 'kindergarten',
            'template_id'      => null,
            'academic_year_id' => null,
            'start_date'       => current_time( 'Y-m-d' ),
            'end_date'         => null,
            'status'           => 'draft',
            'total_amount'     => 0,
            'notes'            => '',
            'created_by'       => get_current_user_id(),
            'created_at'       => current_time( 'mysql' ),
            'updated_at'       => current_time( 'mysql' ),
        ];

        $insert_data = wp_parse_args( $data, $defaults );

        // Ensure we don't pass null to not-null string fields if they are missing
        if ( empty( $insert_data['payer_type'] ) ) return false;

        if ( isset( $insert_data['participant_ids'] ) && is_array( $insert_data['participant_ids'] ) ) {
            $insert_data['participant_ids'] = wp_json_encode( array_map( 'intval', $insert_data['participant_ids'] ) );
        } elseif ( empty( $insert_data['participant_ids'] ) && ! empty( $insert_data['participant_id'] ) ) {
            $insert_data['participant_ids'] = wp_json_encode( [ (int) $insert_data['participant_id'] ] );
        }

        $inserted = $wpdb->insert( $table, $insert_data );

        if ( $inserted ) {
            return (int) $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Update header fields
     */
    public static function update( int $id, array $data ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreements';

        if ( empty( $data ) ) return true;

        // Remove fields that shouldn't be updated here
        unset( $data['id'], $data['agreement_number'], $data['created_at'], $data['created_by'] );
        $data['updated_at'] = current_time( 'mysql' );

        if ( isset( $data['participant_ids'] ) && is_array( $data['participant_ids'] ) ) {
            $data['participant_ids'] = wp_json_encode( array_map( 'intval', $data['participant_ids'] ) );
        }

        $updated = $wpdb->update( $table, $data, [ 'id' => $id ] );
        
        return $updated !== false;
    }

    /**
     * Get single agreement row
     */
    public static function get( int $id ): object|null {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreements';

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
        
        if ( $row ) {
            if ( ! empty( $row->participant_ids ) ) {
                $row->participant_ids_array = json_decode( $row->participant_ids, true ) ?: [];
            } else {
                $row->participant_ids_array = [ (int) $row->participant_id ];
            }
        }
        
        if ( ! $row ) return null;

        if ( empty( $row->participant_type ) ) {
            $row->participant_type = ( $row->payer_type === 'family' ) ? 'student' : 'child';
        }

        // Resolve display names
        $row->payer_name = self::resolve_payer_name( $row->payer_type, $row->payer_id );
        
        $row->participant_names = [];
        foreach ( $row->participant_ids_array as $pid ) {
            $row->participant_names[ $pid ] = self::resolve_participant_name( $row->participant_type, (string)$pid );
        }
        $row->participant_name = implode( ' ، ', $row->participant_names );

        return $row;
    }

    /**
     * List agreements with filters
     */
    public static function get_list( array $args = [] ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreements';

        $where = ["1=1"];
        $values = [];

        if ( ! empty( $args['status'] ) ) {
            $where[] = "status = %s";
            $values[] = $args['status'];
        }

        if ( ! empty( $args['payer_type'] ) ) {
            $where[] = "payer_type = %s";
            $values[] = $args['payer_type'];
        }

        if ( ! empty( $args['activity_type'] ) ) {
            $where[] = "activity_type = %s";
            $values[] = $args['activity_type'];
        }

        $where_sql = implode( ' AND ', $where );
        
        $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC";
        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, ...$values );
        }

        $results = $wpdb->get_results( $sql );
        
        // Add names
        foreach ( $results as $row ) {
            $row->payer_name = self::resolve_payer_name( $row->payer_type, $row->payer_id );
            $row->participant_name = self::resolve_participant_name( $row->participant_type, $row->participant_id );
        }

        return $results;
    }

    /**
     * Change status with validation
     */
    public static function change_status( int $id, string $new_status ): bool|WP_Error {
        $allowed_statuses = [ 'draft', 'active', 'completed', 'cancelled' ];
        if ( ! in_array( $new_status, $allowed_statuses, true ) ) {
            return new WP_Error( 'invalid_status', 'Invalid status provided.' );
        }

        $agreement = self::get( $id );
        if ( ! $agreement ) {
            return new WP_Error( 'not_found', 'Agreement not found.' );
        }

        // Add any business logic rules here (e.g., can't cancel a completed agreement)
        if ( $agreement->status === 'completed' && $new_status === 'cancelled' ) {
            return new WP_Error( 'invalid_transition', 'Cannot cancel a completed agreement.' );
        }

        return self::update( $id, [ 'status' => $new_status ] );
    }

    /**
     * Recalculate total_amount from sum of all net_amount in agreement_fees
     */
    public static function recalculate_total( int $id ): void {
        global $wpdb;
        $fees_table = $wpdb->prefix . 'olama_agreement_fees';
        $agr_table  = $wpdb->prefix . 'olama_agreements';

        $total = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(net_amount) FROM {$fees_table} WHERE agreement_id = %d",
            $id
        ) );

        $wpdb->update(
            $agr_table,
            [ 'total_amount' => $total, 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $id ],
            [ '%f', '%s' ],
            [ '%d' ]
        );
    }

    // ── Helper Resolvers ───────────────────────────────────────────────────

    private static function resolve_payer_name( string $type, string $id ): string {
        global $wpdb;
        if ( $type === 'customer' ) {
            $table = $wpdb->prefix . 'olama_customers';
            $name = $wpdb->get_var( $wpdb->prepare( "SELECT customer_name FROM {$table} WHERE customer_uid = %s OR id = %d", $id, (int)$id ) );
            return $name ? $name : 'Unknown Customer';
        } elseif ( $type === 'family' ) {
            if ( class_exists( 'Olama_School_DB' ) ) {
                $family = Olama_School_DB::get_family( $id );
                if ( $family ) return $family->guardian_name ?? 'Unknown Family';
            }
            return $id;
        }
        return '';
    }

    private static function resolve_participant_name( string $type, string $id ): string {
        global $wpdb;
        if ( $type === 'child' ) {
            $table = $wpdb->prefix . 'olama_customer_children';
            $name = $wpdb->get_var( $wpdb->prepare( "SELECT child_name FROM {$table} WHERE child_uid = %s OR id = %d", $id, (int)$id ) );
            return $name ? $name : 'Unknown Child';
        } elseif ( $type === 'student' ) {
            if ( class_exists( 'Olama_School_DB' ) ) {
                $student = Olama_School_DB::get_student( $id );
                if ( $student ) return ( $student->first_name ?? '' ) . ' ' . ( $student->last_name ?? '' );
            }
            return $id;
        }
        return '';
    }
}
