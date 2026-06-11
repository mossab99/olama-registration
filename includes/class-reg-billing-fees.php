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

    private static function ensure_schema(): void {
        global $wpdb;
        static $checked = false;

        if ( $checked ) {
            return;
        }

        $table = self::t( 'olama_fee_templates' );
        $columns = $wpdb->get_col( "DESCRIBE {$table}", 0 );

        if ( ! in_array( 'subject_type', (array) $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN `subject_type` varchar(20) NOT NULL DEFAULT 'general' AFTER `template_name`" );
        }

        if ( ! in_array( 'subject_value', (array) $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN `subject_value` varchar(255) DEFAULT NULL AFTER `subject_type`" );
        }

        $checked = true;
    }

    /**
     * Get all fee templates.
     */
    public static function get_templates(): array {
        global $wpdb;
        self::ensure_schema();

        $results = $wpdb->get_results(
            "SELECT * FROM " . self::t( 'olama_fee_templates' ) . " ORDER BY template_name ASC"
        ) ?: [];

        foreach ( $results as $row ) {
            $row->items = ! empty( $row->items ) ? json_decode( $row->items, true ) : [];
        }

        return $results;
    }

    public static function get_agreement_templates( string $agreement_nature = '' ): array {
        $templates = self::get_templates();

        return array_values( array_filter( $templates, static function ( $template ) use ( $agreement_nature ) {
            $subject_type = $template->subject_type ?? 'general';
            $subject_value = (string) ( $template->subject_value ?? '' );

            if ( $subject_type !== 'agreement' ) {
                return false;
            }

            return $agreement_nature === '' || $subject_value === $agreement_nature;
        } ) );
    }

    /**
     * Get a single fee template by ID.
     */
    public static function get_template( int $id ): ?object {
        global $wpdb;
        self::ensure_schema();

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
     *   id (optional), template_name, grade_id,
     *   items: [ [ description, amount ], ... ]
     * }
     * @return int|\WP_Error
     */
    public static function save_template( array $data ): int|\WP_Error {
        global $wpdb;
        self::ensure_schema();

        $id            = absint( $data['id'] ?? 0 );
        $template_name = sanitize_text_field( $data['template_name'] ?? '' );
        $subject_type  = sanitize_key( $data['subject_type'] ?? 'general' );
        $subject_value = sanitize_text_field( $data['subject_value'] ?? '' );
        $grade_id      = sanitize_text_field( $data['grade_id'] ?? '' ) ?: null;

        if ( ! $template_name ) {
            return new \WP_Error( 'missing_name', __( 'Template name is required.', 'olama-registration' ) );
        }

        if ( ! in_array( $subject_type, [ 'service', 'agreement', 'general' ], true ) ) {
            $subject_type = 'general';
        }

        if ( $subject_type === 'general' ) {
            $subject_value = '';
        } elseif ( ! $subject_value ) {
            return new \WP_Error( 'missing_subject', __( 'Template service or agreement type is required.', 'olama-registration' ) );
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
            'subject_type'  => $subject_type,
            'subject_value' => $subject_value ?: null,
            'grade_id'      => $grade_id,
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
        self::ensure_schema();

        $result = $wpdb->delete( self::t( 'olama_fee_templates' ), [ 'id' => $id ] );
        return false !== $result;
    }
}
