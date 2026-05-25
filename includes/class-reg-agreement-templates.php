<?php
/**
 * Agreement Templates CRUD
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Olama_Reg_Agreement_Templates {

    public static function get( int $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}olama_agreement_templates WHERE id = %d", $id ) );
    }

    public static function get_list( array $args = [] ) {
        global $wpdb;
        $where = "WHERE 1=1";
        
        if ( isset( $args['activity_type'] ) && $args['activity_type'] !== '' ) {
            $where .= $wpdb->prepare( " AND activity_type = %s", $args['activity_type'] );
        }
        
        if ( isset( $args['is_active'] ) ) {
            $where .= $wpdb->prepare( " AND is_active = %d", $args['is_active'] );
        }

        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}olama_agreement_templates {$where} ORDER BY name ASC" );
    }

    public static function create( array $data ): int|WP_Error {
        global $wpdb;

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'olama_agreement_templates',
            [
                'activity_type' => sanitize_text_field( $data['activity_type'] ?? '' ),
                'name'          => sanitize_text_field( $data['name'] ?? '' ),
                'description'   => sanitize_textarea_field( $data['description'] ?? '' ),
                'is_active'     => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
                'created_at'    => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%d', '%s' ]
        );

        if ( ! $inserted ) {
            return new WP_Error( 'db_error', 'Could not insert template.' );
        }

        return (int) $wpdb->insert_id;
    }

    public static function get_fees( int $template_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}olama_agreement_template_fees WHERE template_id = %d ORDER BY sort_order ASC", $template_id ) );
    }

    public static function get_clauses( int $template_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}olama_agreement_template_clauses WHERE template_id = %d ORDER BY sort_order ASC", $template_id ) );
    }

    public static function save_template_relations( int $template_id, array $fees, array $clauses ) {
        global $wpdb;

        // Clear existing
        $wpdb->delete( $wpdb->prefix . 'olama_agreement_template_fees', [ 'template_id' => $template_id ] );
        $wpdb->delete( $wpdb->prefix . 'olama_agreement_template_clauses', [ 'template_id' => $template_id ] );

        // Insert fees
        if ( ! empty( $fees['category'] ) && is_array( $fees['category'] ) ) {
            $sort = 1;
            foreach ( $fees['category'] as $i => $cat ) {
                $category = sanitize_text_field( $cat );
                $label    = sanitize_text_field( $fees['label'][$i] ?? '' );
                $amount   = floatval( $fees['amount'][$i] ?? 0 );
                $discount = floatval( $fees['discount'][$i] ?? 0 );
                $net      = $amount - $discount;

                if ( ! empty( $label ) ) {
                    $wpdb->insert(
                        $wpdb->prefix . 'olama_agreement_template_fees',
                        [
                            'template_id'  => $template_id,
                            'fee_category' => $category,
                            'label'        => $label,
                            'amount'       => $amount,
                            'discount'     => $discount,
                            'net_amount'   => $net,
                            'sort_order'   => $sort++
                        ],
                        [ '%d', '%s', '%s', '%f', '%f', '%f', '%d' ]
                    );
                }
            }
        }

        // Insert clauses
        if ( ! empty( $clauses ) && is_array( $clauses ) ) {
            $sort = 1;
            foreach ( $clauses as $clause_text ) {
                $text = wp_kses_post( $clause_text );
                if ( ! empty( trim( $text ) ) ) {
                    $wpdb->insert(
                        $wpdb->prefix . 'olama_agreement_template_clauses',
                        [
                            'template_id' => $template_id,
                            'clause_text' => $text,
                            'sort_order'  => $sort++
                        ],
                        [ '%d', '%s', '%d' ]
                    );
                }
            }
        }
    }

    /**
     * Apply template to an agreement
     */
    public static function apply_to_agreement( int $template_id, int $agreement_id ): bool {
        $fees    = self::get_fees( $template_id );
        $clauses = self::get_clauses( $template_id );

        // Add fees
        foreach ( $fees as $fee ) {
            Olama_Reg_Agreement_Fees::add( $agreement_id, [
                'fee_category' => $fee->fee_category,
                'label'        => $fee->label,
                'amount'       => $fee->amount,
                'discount'     => $fee->discount,
            ] );
        }

        // Add clauses
        foreach ( $clauses as $clause ) {
            Olama_Reg_Agreement_Clauses::add( $agreement_id, $clause->clause_text );
        }

        return true;
    }
}
