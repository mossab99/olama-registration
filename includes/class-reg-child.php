<?php
/**
 * Customer Child CRUD Model
 * Mirrors Olama_Reg_Student for external customer children.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Olama_Reg_Child {

    private static function t(): string {
        global $wpdb;
        return $wpdb->prefix . 'olama_customer_children';
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    public static function get( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT ch.*, c.customer_uid, c.customer_name
             FROM " . self::t() . " ch
             LEFT JOIN {$wpdb->prefix}olama_customers c ON c.id = ch.customer_id
             WHERE ch.id = %d",
            $id
        ) ) ?: null;
    }

    public static function get_by_uid( string $uid ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::t() . " WHERE child_uid = %s",
            $uid
        ) ) ?: null;
    }

    /**
     * Get all active children for a customer.
     */
    public static function get_by_customer( int $customer_id, bool $active_only = true ): array {
        global $wpdb;
        $active_clause = $active_only ? ' AND is_active = 1' : '';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . self::t() . " WHERE customer_id = %d {$active_clause} ORDER BY id ASC",
            $customer_id
        ) ) ?: [];
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public static function add( int $customer_id, array $data ): int|\WP_Error {
        global $wpdb;

        $child_name = sanitize_text_field( $data['child_name'] ?? $data['name'] ?? '' );
        if ( empty( $child_name ) ) {
            return new \WP_Error( 'missing_name', 'اسم الابن مطلوب.' );
        }

        // Generate next sequence for this customer
        $max_seq = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE( MAX( CAST( SUBSTRING( child_uid, LOCATE('-C', child_uid) + 2 ) AS UNSIGNED ) ), 0 )
             FROM " . self::t() . " WHERE customer_id = %d",
            $customer_id
        ) );
        $next_seq = $max_seq + 1;

        // Get customer_uid for the child_uid prefix
        $customer_uid = $wpdb->get_var( $wpdb->prepare(
            "SELECT customer_uid FROM {$wpdb->prefix}olama_customers WHERE id = %d",
            $customer_id
        ) );

        if ( ! $customer_uid ) {
            return new \WP_Error( 'not_found', 'العميل غير موجود.' );
        }

        $child_uid = $customer_uid . '-C' . str_pad( $next_seq, 2, '0', STR_PAD_LEFT );

        $inserted = $wpdb->insert( self::t(), [
            'child_uid'   => $child_uid,
            'customer_id' => $customer_id,
            'child_name'  => $child_name,
            'grade'       => sanitize_text_field( $data['grade'] ?? '' ) ?: null,
            'is_active'   => 1,
            'notes'       => sanitize_textarea_field( $data['notes'] ?? '' ) ?: null,
        ], [ '%s', '%d', '%s', '%s', '%d', '%s' ] );

        if ( ! $inserted ) {
            return new \WP_Error( 'db_error', 'خطأ في قاعدة البيانات: ' . $wpdb->last_error );
        }

        return $wpdb->insert_id;
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public static function update( int $id, array $data ): bool|\WP_Error {
        global $wpdb;

        if ( ! self::get( $id ) ) {
            return new \WP_Error( 'not_found', 'الابن غير موجود.' );
        }

        $payload = [];

        if ( isset( $data['child_name'] ) || isset( $data['name'] ) ) {
            $name = sanitize_text_field( $data['child_name'] ?? $data['name'] );
            if ( ! $name ) return new \WP_Error( 'missing_name', 'اسم الابن مطلوب.' );
            $payload['child_name'] = $name;
        }
        if ( array_key_exists( 'grade', $data ) ) {
            $payload['grade'] = sanitize_text_field( $data['grade'] ) ?: null;
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

    public static function delete( int $id ): bool|\WP_Error {
        global $wpdb;

        if ( ! self::get( $id ) ) {
            return new \WP_Error( 'not_found', 'الابن غير موجود.' );
        }

        // Soft delete
        $result = $wpdb->update( self::t(), [ 'is_active' => 0 ], [ 'id' => $id ] );
        return false !== $result ? true : new \WP_Error( 'db_error', $wpdb->last_error );
    }
}
