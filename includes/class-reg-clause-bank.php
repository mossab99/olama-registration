<?php
/**
 * Global Clause Bank CRUD
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Olama_Reg_Clause_Bank {

    public static function get_all(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreement_clause_bank';
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" );
    }

    public static function get_active(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreement_clause_bank';
        return $wpdb->get_results( "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY id DESC" );
    }

    public static function get( int $id ): object|null {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreement_clause_bank';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
    }

    public static function add( array $data ): int|false {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreement_clause_bank';

        $inserted = $wpdb->insert( $table, [
            'title'       => sanitize_text_field( $data['title'] ?? '' ),
            'clause_text' => wp_kses_post( $data['clause_text'] ?? '' ),
            'is_active'   => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
        ] );

        return $inserted ? (int) $wpdb->insert_id : false;
    }

    public static function update( int $id, array $data ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreement_clause_bank';

        $update_data = [
            'title'       => sanitize_text_field( $data['title'] ?? '' ),
            'clause_text' => wp_kses_post( $data['clause_text'] ?? '' ),
        ];

        if ( isset( $data['is_active'] ) ) {
            $update_data['is_active'] = (int) $data['is_active'];
        }

        $updated = $wpdb->update( $table, $update_data, [ 'id' => $id ] );
        return $updated !== false;
    }

    public static function delete( int $id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreement_clause_bank';
        $deleted = $wpdb->delete( $table, [ 'id' => $id ] );
        return $deleted !== false;
    }
}
