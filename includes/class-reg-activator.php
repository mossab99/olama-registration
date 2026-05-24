<?php
/**
 * Activator — schema migrations via dbDelta.
 * Extends existing tables; creates new tables.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Olama_Reg_Activator {

    /**
     * Called once on plugin activation hook.
     */
    public static function activate(): void {
        self::run_migrations();
        update_option( 'olama_reg_version', OLAMA_REG_VERSION );
    }

    public static function run_migrations(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        self::create_financial_table( $wpdb, $charset );
        self::create_billing_tables( $wpdb, $charset );
        self::create_settlement_receipts_table( $wpdb, $charset );
    }




    // ── Create olama_reg_financial ───────────────────────────────────────────
    private static function create_financial_table( $wpdb, string $charset ): void {

        $table = $wpdb->prefix . 'olama_reg_financial';

        $sql = "CREATE TABLE {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            family_uid varchar(20) NOT NULL,
            academic_year_id int(10) UNSIGNED NOT NULL DEFAULT 0,
            entitlement_date date DEFAULT NULL,
            calculation_method varchar(100) DEFAULT NULL,
            percentage decimal(5,2) DEFAULT NULL,
            amount_due decimal(10,2) NOT NULL DEFAULT 0.00,
            amount_paid decimal(10,2) NOT NULL DEFAULT 0.00,
            payments_revolving decimal(10,2) NOT NULL DEFAULT 0.00,
            payment_reference varchar(150) DEFAULT NULL,
            fin_notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_family_year (family_uid,academic_year_id)
        ) {$charset};";

        dbDelta( $sql );

        // Safely add updated_at on pre-existing tables where dbDelta may skip it.
        $existing = $wpdb->get_col( "DESCRIBE {$table}", 0 );
        if ( ! in_array( 'updated_at', (array) $existing, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP" );
        }
    }


    // ── Create billing-related tables ─────────────────────────────────────────
    private static function create_billing_tables( $wpdb, string $charset ): void {

        // 1. Fee templates
        $sql_fee_templates = "CREATE TABLE {$wpdb->prefix}olama_fee_templates (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            template_name varchar(255) NOT NULL,
            grade_id varchar(100) DEFAULT NULL,
            installments int(11) DEFAULT 1,
            items text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) {$charset};";

        // 2. Invoices
        $sql_invoices = "CREATE TABLE {$wpdb->prefix}olama_invoices (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_number varchar(50) NOT NULL,
            family_uid varchar(50) NOT NULL,
            student_uid varchar(50) DEFAULT NULL,
            academic_year_id int(10) UNSIGNED NOT NULL DEFAULT 0,
            fee_template_id mediumint(9) DEFAULT NULL,
            issue_date date DEFAULT NULL,
            due_date date DEFAULT NULL,
            status varchar(50) NOT NULL DEFAULT 'draft',
            subtotal decimal(10,2) NOT NULL DEFAULT 0.00,
            discount decimal(10,2) NOT NULL DEFAULT 0.00,
            total decimal(10,2) NOT NULL DEFAULT 0.00,
            amount_paid decimal(10,2) NOT NULL DEFAULT 0.00,
            balance decimal(10,2) NOT NULL DEFAULT 0.00,
            notes text DEFAULT NULL,
            created_by bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY invoice_number (invoice_number),
            KEY family_uid (family_uid),
            KEY academic_year_id (academic_year_id)
        ) {$charset};";

        // 3. Invoice items
        $sql_items = "CREATE TABLE {$wpdb->prefix}olama_invoice_items (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id bigint(20) UNSIGNED NOT NULL,
            description varchar(255) NOT NULL,
            quantity decimal(10,2) NOT NULL DEFAULT 1.00,
            unit_price decimal(10,2) NOT NULL DEFAULT 0.00,
            line_total decimal(10,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY  (id),
            KEY invoice_id (invoice_id)
        ) {$charset};";

        // 4. Invoice installments
        $sql_installments = "CREATE TABLE {$wpdb->prefix}olama_invoice_installments (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id bigint(20) UNSIGNED NOT NULL,
            installment_no int(11) NOT NULL,
            due_date date DEFAULT NULL,
            amount_due decimal(10,2) NOT NULL DEFAULT 0.00,
            amount_paid decimal(10,2) NOT NULL DEFAULT 0.00,
            status varchar(50) NOT NULL DEFAULT 'pending',
            PRIMARY KEY  (id),
            KEY invoice_id (invoice_id)
        ) {$charset};";

        // 5. Payments
        $sql_payments = "CREATE TABLE {$wpdb->prefix}olama_payments (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id bigint(20) UNSIGNED NOT NULL,
            installment_id bigint(20) UNSIGNED DEFAULT NULL,
            family_uid varchar(50) NOT NULL,
            payment_date date DEFAULT NULL,
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            method varchar(50) NOT NULL DEFAULT 'cash',
            reference varchar(150) DEFAULT NULL,
            received_by bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY invoice_id (invoice_id),
            KEY family_uid (family_uid)
        ) {$charset};";

        // 6. Billing Audit
        $sql_audit = "CREATE TABLE {$wpdb->prefix}olama_billing_audit (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) UNSIGNED NOT NULL,
            action varchar(100) NOT NULL,
            actor_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            before_state longtext DEFAULT NULL,
            after_state longtext DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY entity_type_id (entity_type,entity_id)
        ) {$charset};";

        dbDelta( $sql_fee_templates );
        dbDelta( $sql_invoices );
        dbDelta( $sql_items );
        dbDelta( $sql_installments );
        dbDelta( $sql_payments );
        dbDelta( $sql_audit );
    }

    // ── Create Settlement Receipts Table ───────────────────────────────────────
    private static function create_settlement_receipts_table( $wpdb, string $charset ): void {
        $table = $wpdb->prefix . 'olama_settlement_receipts';

        $sql = "CREATE TABLE {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            receipt_number varchar(50) NOT NULL,
            family_id varchar(50) NOT NULL,
            student_id varchar(50) DEFAULT NULL,
            payment_category varchar(100) NOT NULL,
            original_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            settled_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            remaining_balance decimal(10,2) NOT NULL DEFAULT 0.00,
            payment_method varchar(50) NOT NULL DEFAULT 'cash',
            oracle_receipt_number varchar(150) DEFAULT NULL,
            settlement_date date DEFAULT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending_settlement',
            notes text DEFAULT NULL,
            created_by bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            updated_by bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            settled_by bigint(20) UNSIGNED DEFAULT NULL,
            cancelled_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY receipt_number (receipt_number),
            KEY family_id (family_id),
            KEY status (status)
        ) {$charset};";

        dbDelta( $sql );
    }
}
