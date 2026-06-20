<?php
/**
 * External Customer CRUD Model
 * Mirrors Olama_Reg_Family for external walk-in customers.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Olama_Reg_Customer {

    private static function t(): string {
        global $wpdb;
        return $wpdb->prefix . 'olama_customers';
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    public static function get( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::t() . " WHERE id = %d",
            $id
        ) ) ?: null;
    }

    public static function get_by_uid( string $uid ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::t() . " WHERE customer_uid = %s",
            $uid
        ) ) ?: null;
    }

    public static function get_by_phone( string $phone ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::t() . " WHERE phone = %s LIMIT 1",
            $phone
        ) ) ?: null;
    }

    /**
     * Get list with optional search, pagination, active filter.
     * Defaults to active-only (is_active = 1). Pass active => null for all.
     */
    public static function get_list( array $args = [] ): array {
        global $wpdb;

        $search   = sanitize_text_field( $args['search'] ?? '' );
        $per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
        $offset   = max( 0, (int) ( $args['offset'] ?? 0 ) );
        // Default to active-only
        $active   = array_key_exists( 'active', $args ) ? $args['active'] : true;

        $where  = '1=1';
        $params = [];

        if ( $search !== '' ) {
            $like    = '%' . $wpdb->esc_like( $search ) . '%';
            // Also search child names
            $where  .= " AND ( c.customer_name LIKE %s OR c.phone LIKE %s OR c.customer_uid LIKE %s
                              OR EXISTS (
                                  SELECT 1 FROM {$wpdb->prefix}olama_customer_children ch2
                                  WHERE ch2.customer_id = c.id AND ch2.child_name LIKE %s
                              ) )";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ( $active !== null ) {
            $where   .= ' AND c.is_active = %d';
            $params[] = $active ? 1 : 0;
        }

        $params[] = $per_page;
        $params[] = $offset;

        $sql = "SELECT c.*,
                    COUNT( ch.id ) AS children_count
                FROM " . self::t() . " c
                LEFT JOIN {$wpdb->prefix}olama_customer_children ch
                    ON ch.customer_id = c.id AND ch.is_active = 1
                WHERE {$where}
                GROUP BY c.id
                ORDER BY c.id DESC
                LIMIT %d OFFSET %d";

        return $wpdb->get_results(
            empty( $params ) ? $sql : $wpdb->prepare( $sql, ...$params )
        ) ?: [];
    }

    public static function count( array $args = [] ): int {
        global $wpdb;

        $search = sanitize_text_field( $args['search'] ?? '' );
        // Default to active-only
        $active = array_key_exists( 'active', $args ) ? $args['active'] : true;

        $where  = '1=1';
        $params = [];

        if ( $search !== '' ) {
            $like    = '%' . $wpdb->esc_like( $search ) . '%';
            $where  .= " AND ( c.customer_name LIKE %s OR c.phone LIKE %s OR c.customer_uid LIKE %s
                              OR EXISTS (
                                  SELECT 1 FROM {$wpdb->prefix}olama_customer_children ch2
                                  WHERE ch2.customer_id = c.id AND ch2.child_name LIKE %s
                              ) )";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ( $active !== null ) {
            $where   .= ' AND c.is_active = %d';
            $params[] = $active ? 1 : 0;
        }

        $sql = "SELECT COUNT(*) FROM " . self::t() . " c WHERE {$where}";
        return (int) ( empty( $params )
            ? $wpdb->get_var( $sql )
            : $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) )
        );
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public static function create( array $data ): int|\WP_Error {
        global $wpdb;

        $name = sanitize_text_field( $data['customer_name'] ?? '' );
        if ( empty( $name ) ) {
            return new \WP_Error( 'missing_name', 'اسم العميل مطلوب.' );
        }

        $phone = sanitize_text_field( $data['phone'] ?? '' ) ?: null;

        // Phone duplicate check
        if ( $phone ) {
            $existing = self::get_by_phone( $phone );
            if ( $existing ) {
                return new \WP_Error( 'duplicate_phone', sprintf(
                    'رقم الهاتف %s مسجل مسبقاً للعميل: %s (%s)',
                    $phone, $existing->customer_name, $existing->customer_uid
                ) );
            }
        }

        $inserted = $wpdb->insert( self::t(), [
            'customer_name' => $name,
            'phone'         => $phone,
            'notes'         => sanitize_textarea_field( $data['notes'] ?? '' ) ?: null,
            'is_active'     => 1,
        ], [ '%s', '%s', '%s', '%d' ] );

        if ( ! $inserted ) {
            return new \WP_Error( 'db_error', 'خطأ في قاعدة البيانات: ' . $wpdb->last_error );
        }

        $new_id       = $wpdb->insert_id;
        $customer_uid = 'CUST-' . str_pad( $new_id, 4, '0', STR_PAD_LEFT );
        $wpdb->update( self::t(), [ 'customer_uid' => $customer_uid ], [ 'id' => $new_id ] );

        return $new_id;
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public static function update( int $id, array $data ): bool|\WP_Error {
        global $wpdb;

        $customer = self::get( $id );
        if ( ! $customer ) {
            return new \WP_Error( 'not_found', 'العميل غير موجود.' );
        }

        $payload = [];

        if ( isset( $data['customer_name'] ) ) {
            $name = sanitize_text_field( $data['customer_name'] );
            if ( ! $name ) return new \WP_Error( 'missing_name', 'اسم العميل مطلوب.' );
            $payload['customer_name'] = $name;
        }

        if ( array_key_exists( 'phone', $data ) ) {
            $phone = sanitize_text_field( $data['phone'] ) ?: null;
            // Check duplicate only if phone actually changed
            if ( $phone && $phone !== $customer->phone ) {
                $existing = self::get_by_phone( $phone );
                if ( $existing && $existing->id !== $id ) {
                    return new \WP_Error( 'duplicate_phone', sprintf(
                        'رقم الهاتف %s مسجل مسبقاً للعميل: %s (%s)',
                        $phone, $existing->customer_name, $existing->customer_uid
                    ) );
                }
            }
            $payload['phone'] = $phone;
        }

        if ( array_key_exists( 'notes', $data ) ) {
            $payload['notes'] = sanitize_textarea_field( $data['notes'] ) ?: null;
        }
        if ( array_key_exists( 'is_active', $data ) ) {
            $payload['is_active'] = (int) $data['is_active'];
        }

        if ( empty( $payload ) ) return true;

        $result = $wpdb->update( self::t(), $payload, [ 'id' => $id ] );
        return false !== $result ? true : new \WP_Error( 'db_error', $wpdb->last_error );
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    /**
     * Delete a customer.
     * - If no invoices are linked  → hard DELETE (full removal from DB).
     * - If invoices exist          → soft-delete (is_active = 0) to preserve financial history.
     */
    public static function delete( int $id ): bool|\WP_Error {
        global $wpdb;

        $customer = self::get( $id );
        if ( ! $customer ) {
            return new \WP_Error( 'not_found', 'العميل غير موجود.' );
        }

        // Check whether any invoices are linked to this customer
        $customer_uid = $customer->customer_uid ?: 'CUST-' . str_pad( $id, 4, '0', STR_PAD_LEFT );
        $has_invoices = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}olama_invoices WHERE ext_customer_id = %d OR family_uid = %s",
            $id,
            $customer_uid
        ) );

        if ( $has_invoices === 0 ) {
            // ── Hard delete: no financial history, remove completely ──
            // Delete children
            $wpdb->delete(
                $wpdb->prefix . 'olama_customer_children',
                [ 'customer_id' => $id ],
                [ '%d' ]
            );
            // Delete customer
            $result = $wpdb->delete( self::t(), [ 'id' => $id ], [ '%d' ] );
            return false !== $result
                ? true
                : new \WP_Error( 'db_error', 'خطأ في قاعدة البيانات: ' . $wpdb->last_error );
        }

        // ── Soft-delete: has financial history, just hide ──
        $wpdb->update(
            $wpdb->prefix . 'olama_customer_children',
            [ 'is_active' => 0 ],
            [ 'customer_id' => $id ]
        );

        $result = $wpdb->update( self::t(), [ 'is_active' => 0 ], [ 'id' => $id ] );
        return false !== $result
            ? true
            : new \WP_Error( 'db_error', 'خطأ في قاعدة البيانات: ' . $wpdb->last_error );
    }
}
