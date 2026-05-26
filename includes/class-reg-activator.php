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
        self::create_customers_table( $wpdb, $charset );
        self::create_customer_children_table( $wpdb, $charset );
        self::create_agreements_tables( $wpdb, $charset );
        self::upgrade_invoices_table( $wpdb );
    }




    // ── Create olama_customers ───────────────────────────────────────────────
    private static function create_customers_table( $wpdb, string $charset ): void {
        $table = $wpdb->prefix . 'olama_customers';

        $sql = "CREATE TABLE {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_uid varchar(20) NOT NULL DEFAULT '',
            customer_name varchar(150) NOT NULL,
            phone varchar(50) DEFAULT NULL,
            notes text DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY customer_uid (customer_uid),
            KEY idx_customer_name (customer_name),
            KEY idx_phone (phone)
        ) {$charset};";

        dbDelta( $sql );

        // Safely add columns on pre-existing tables
        $existing = $wpdb->get_col( "DESCRIBE {$table}", 0 );

        if ( ! in_array( 'customer_uid', (array) $existing, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN `customer_uid` varchar(20) NOT NULL DEFAULT '' AFTER `id`" );
        }
        if ( ! in_array( 'notes', (array) $existing, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN `notes` text DEFAULT NULL AFTER `phone`" );
        }
        if ( ! in_array( 'is_active', (array) $existing, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN `is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `notes`" );
        }
        // Remove the broken UNIQUE constraint on phone (allow NULL duplicates safely)
        $indexes = $wpdb->get_col( "SHOW INDEX FROM {$table} WHERE Key_name = 'phone'", 2 );
        if ( ! empty( $indexes ) ) {
            $wpdb->query( "ALTER TABLE {$table} DROP INDEX `phone`" );
        }

        // Backfill missing customer_uid values
        $rows = $wpdb->get_results( "SELECT id FROM {$table} WHERE customer_uid = '' OR customer_uid IS NULL" );
        foreach ( $rows as $row ) {
            $uid = 'CUST-' . str_pad( $row->id, 4, '0', STR_PAD_LEFT );
            $wpdb->update( $table, [ 'customer_uid' => $uid ], [ 'id' => $row->id ] );
        }

        // Drop children_names column (data migrated to olama_customer_children)
        // Only drop after children table is populated (done in create_customer_children_table)
    }

    // ── Create olama_customer_children ───────────────────────────────────────
    private static function create_customer_children_table( $wpdb, string $charset ): void {
        $table = $wpdb->prefix . 'olama_customer_children';

        $sql = "CREATE TABLE {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            child_uid varchar(30) NOT NULL DEFAULT '',
            customer_id bigint(20) UNSIGNED NOT NULL,
            child_name varchar(150) NOT NULL,
            grade varchar(100) DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY child_uid (child_uid),
            KEY idx_customer_id (customer_id)
        ) {$charset};";

        dbDelta( $sql );

        // Safely add columns on pre-existing tables
        $existing = $wpdb->get_col( "DESCRIBE {$table}", 0 );
        if ( ! in_array( 'is_active', (array) $existing, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN `is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `grade`" );
        }
        if ( ! in_array( 'notes', (array) $existing, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN `notes` text DEFAULT NULL AFTER `is_active`" );
        }

        // Backfill: migrate JSON children from olama_customers.children_names → this table
        $customers_table = $wpdb->prefix . 'olama_customers';
        $customers = $wpdb->get_results( "SELECT id, customer_uid, children_names FROM {$customers_table} WHERE children_names IS NOT NULL AND children_names != '' AND children_names != '[]'" );

        foreach ( $customers as $cust ) {
            $children = json_decode( $cust->children_names, true );
            if ( ! is_array( $children ) || empty( $children ) ) continue;

            $seq = 1;
            foreach ( $children as $child ) {
                $child_name = sanitize_text_field( $child['name'] ?? '' );
                if ( empty( $child_name ) ) { $seq++; continue; }

                $child_uid = $cust->customer_uid . '-C' . str_pad( $seq, 2, '0', STR_PAD_LEFT );

                // Skip if this child_uid already exists
                $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE child_uid = %s", $child_uid ) );
                if ( $exists ) { $seq++; continue; }

                $wpdb->insert( $table, [
                    'child_uid'   => $child_uid,
                    'customer_id' => $cust->id,
                    'child_name'  => $child_name,
                    'grade'       => sanitize_text_field( $child['grade'] ?? '' ) ?: null,
                    'is_active'   => 1,
                ], [ '%s', '%d', '%s', '%s', '%d' ] );

                $seq++;
            }
        }
    }

    // ── Add ext columns to olama_invoices ─────────────────────────────────────
    private static function upgrade_invoices_table( $wpdb ): void {
        $table = $wpdb->prefix . 'olama_invoices';
        $existing = $wpdb->get_col( "DESCRIBE {$table}", 0 );

        if ( ! in_array( 'ext_customer_id', (array) $existing, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN `ext_customer_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `student_uid`" );
        }
        if ( ! in_array( 'ext_child_id', (array) $existing, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN `ext_child_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `ext_customer_id`" );
        }
        if ( ! in_array( 'agreement_id', (array) $existing, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN `agreement_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `id`" );
            $wpdb->query( "ALTER TABLE {$table} ADD KEY `agreement_id` (`agreement_id`)" );
        }
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

    // ── Create Agreements Tables ──────────────────────────────────────────────
    public static function create_agreements_tables( $wpdb, string $charset ): void {
        // 1. Agreements
        $sql_agreements = "CREATE TABLE {$wpdb->prefix}olama_agreements (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            agreement_number VARCHAR(30) NOT NULL,
            payer_type VARCHAR(20) NOT NULL,
            payer_id VARCHAR(50) NOT NULL,
            participant_type VARCHAR(20) NOT NULL,
            participant_id BIGINT UNSIGNED NOT NULL,
            participant_ids TEXT DEFAULT NULL,
            activity_type VARCHAR(60) NOT NULL,
            template_id BIGINT UNSIGNED DEFAULT NULL,
            academic_year_id INT UNSIGNED DEFAULT NULL,
            start_date DATE NOT NULL,
            end_date DATE DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            total_amount DECIMAL(12,3) NOT NULL DEFAULT 0,
            notes TEXT DEFAULT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY agreement_number (agreement_number),
            KEY payer (payer_type, payer_id),
            KEY participant (participant_type, participant_id),
            KEY status (status)
        ) {$charset};";

        // 2. Agreement Fees
        $sql_fees = "CREATE TABLE {$wpdb->prefix}olama_agreement_fees (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            agreement_id BIGINT UNSIGNED NOT NULL,
            child_id VARCHAR(50) DEFAULT NULL,
            fee_category VARCHAR(60) NOT NULL,
            label VARCHAR(255) NOT NULL,
            amount DECIMAL(12,3) NOT NULL DEFAULT 0,
            discount DECIMAL(12,3) NOT NULL DEFAULT 0,
            net_amount DECIMAL(12,3) NOT NULL DEFAULT 0,
            due_date DATE DEFAULT NULL,
            invoice_id BIGINT UNSIGNED DEFAULT NULL,
            paid_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY agreement_id (agreement_id),
            KEY invoice_id (invoice_id)
        ) {$charset};";

        // 3. Agreement Clauses
        $sql_clauses = "CREATE TABLE {$wpdb->prefix}olama_agreement_clauses (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            agreement_id BIGINT UNSIGNED NOT NULL,
            clause_text TEXT NOT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY agreement_id (agreement_id)
        ) {$charset};";

        // 4. Agreement Templates
        $sql_templates = "CREATE TABLE {$wpdb->prefix}olama_agreement_templates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            activity_type VARCHAR(60) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY activity_type (activity_type)
        ) {$charset};";

        // 5. Template Fees
        $sql_tpl_fees = "CREATE TABLE {$wpdb->prefix}olama_agreement_template_fees (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            template_id BIGINT UNSIGNED NOT NULL,
            fee_category VARCHAR(60) NOT NULL,
            label VARCHAR(255) NOT NULL,
            amount DECIMAL(12,3) NOT NULL DEFAULT 0,
            discount DECIMAL(12,3) NOT NULL DEFAULT 0,
            net_amount DECIMAL(12,3) NOT NULL DEFAULT 0,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY template_id (template_id)
        ) {$charset};";

        // 6. Template Clauses
        $sql_tpl_clauses = "CREATE TABLE {$wpdb->prefix}olama_agreement_template_clauses (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            template_id BIGINT UNSIGNED NOT NULL,
            clause_text TEXT NOT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY template_id (template_id)
        ) {$charset};";

        // 7. Clause Bank
        $sql_clause_bank = "CREATE TABLE {$wpdb->prefix}olama_agreement_clause_bank (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            clause_text TEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id)
        ) {$charset};";

        dbDelta( $sql_agreements );
        dbDelta( $sql_fees );
        dbDelta( $sql_clauses );
        dbDelta( $sql_templates );
        dbDelta( $sql_tpl_fees );
        dbDelta( $sql_tpl_clauses );
        dbDelta( $sql_clause_bank );

        // Safely add child_id column to existing table if not present
        $existing_fees = $wpdb->get_col( "DESCRIBE {$wpdb->prefix}olama_agreement_fees", 0 );
        if ( ! empty( $existing_fees ) && ! in_array( 'child_id', (array) $existing_fees, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}olama_agreement_fees ADD COLUMN `child_id` VARCHAR(50) DEFAULT NULL AFTER `agreement_id`" );
        } else {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}olama_agreement_fees MODIFY COLUMN `child_id` VARCHAR(50) DEFAULT NULL" );
        }
    }
}
