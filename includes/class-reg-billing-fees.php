<?php
/**
 * CRUD for Fee Templates.
 *
 * Tables used:
 *   {prefix}olama_fee_templates
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Olama_Reg_Billing_Fees {

    private static function t( string $name ): string {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    /**
     * Get all fee templates.
     */
    public static function get_templates(): array {
        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT * FROM " . self::t( 'olama_fee_templates' ) . " ORDER BY template_name ASC"
        ) ?: [];

        foreach ( $results as $row ) {
            $row->items = ! empty( $row->items ) ? json_decode( $row->items, true ) : [];
        }

        return $results;
    }

    /**
     * Get a single fee template by ID.
     */
    public static function get_template( int $id ): ?object {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::t( 'olama_fee_templates' ) . " WHERE id = %d",
            $id
        ) );

        if ( ! $row ) {
            return null;
        }

        $row->items = ! empty( $row->items ) ? json_decode( $row->items, true ) : [];
        return $row;
    }

    /**
     * Save a fee template (create or update).
     *
     * @param array $data {
     *   id (optional), template_name, grade_id, installments,
     *   items: [ [ description, amount ], ... ]
     * }
     * @return int|\WP_Error
     */
    public static function save_template( array $data ): int|\WP_Error {
        global $wpdb;

        $id            = absint( $data['id'] ?? 0 );
        $template_name = sanitize_text_field( $data['template_name'] ?? '' );
        $grade_id      = sanitize_text_field( $data['grade_id'] ?? '' ) ?: null;
        $installments  = max( 1, absint( $data['installments'] ?? 1 ) );

        if ( ! $template_name ) {
            return new \WP_Error( 'missing_name', __( 'Template name is required.', 'olama-registration' ) );
        }

        // Sanitize items array
        $items = [];
        if ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
            foreach ( $data['items'] as $item ) {
                $desc = sanitize_text_field( $item['description'] ?? '' );
                $amt  = round( (float) ( $item['amount'] ?? 0 ), 2 );
                if ( $desc ) {
                    $items[] = [
                        'description' => $desc,
                        'amount'      => $amt,
                    ];
                }
            }
        }

        $payload = [
            'template_name' => $template_name,
            'grade_id'      => $grade_id,
            'installments'  => $installments,
            'items'         => wp_json_encode( $items ),
        ];

        if ( $id ) {
            $result = $wpdb->update(
                self::t( 'olama_fee_templates' ),
                $payload,
                [ 'id' => $id ]
            );
            if ( false === $result ) {
                return new \WP_Error( 'db_error', $wpdb->last_error );
            }
            return $id;
        } else {
            $result = $wpdb->insert(
                self::t( 'olama_fee_templates' ),
                $payload
            );
            if ( ! $result ) {
                return new \WP_Error( 'db_error', $wpdb->last_error );
            }
            return (int) $wpdb->insert_id;
        }
    }

    /**
     * Delete a fee template.
     */
    public static function delete_template( int $id ): bool {
        global $wpdb;
        $result = $wpdb->delete( self::t( 'olama_fee_templates' ), [ 'id' => $id ] );
        return false !== $result;
    }
}
