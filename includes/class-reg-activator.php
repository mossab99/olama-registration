<?php
/**
 * Activator — schema migrations via dbDelta.
 * Extends existing tables; creates two new tables.
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

    /**
     * Run all schema migrations. Safe to call repeatedly (idempotent).
     */
    public static function run_migrations(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        self::extend_families_table( $wpdb, $charset );
        self::extend_students_table( $wpdb, $charset );
        self::extend_bus_assignments_table( $wpdb, $charset );
        self::create_academic_history_table( $wpdb, $charset );
        self::create_financial_table( $wpdb, $charset );
        self::backfill_sequence_counters( $wpdb );
    }

    // ── Extend olama_families ─────────────────────────────────────────────────
    private static function extend_families_table( $wpdb, string $charset ): void {

        $table = $wpdb->prefix . 'olama_families';

        // Use dbDelta for the base table (it exists in the parent plugin)
        $sql = "CREATE TABLE {$table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            family_uid varchar(50) NOT NULL,
            family_name varchar(255) NOT NULL,
            mother_mobile varchar(20) DEFAULT NULL,
            father_mobile varchar(20) DEFAULT NULL,
            address text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,

            father_first_name varchar(100) DEFAULT NULL,
            father_second_name varchar(100) DEFAULT NULL,
            father_third_name varchar(100) DEFAULT NULL,
            father_family_name varchar(100) DEFAULT NULL,
            father_secondary_family varchar(100) DEFAULT NULL,
            father_name_t varchar(100) DEFAULT NULL,
            father_second_t varchar(100) DEFAULT NULL,
            father_third_t varchar(100) DEFAULT NULL,
            father_nationality varchar(100) DEFAULT NULL,
            father_job varchar(100) DEFAULT NULL,
            father_workplace varchar(200) DEFAULT NULL,
            father_phone varchar(30) DEFAULT NULL,
            father_email varchar(150) DEFAULT NULL,
            father_doc_type varchar(50) DEFAULT NULL,
            father_doc_number varchar(100) DEFAULT NULL,
            father_doc_issue_place varchar(100) DEFAULT NULL,
            father_doc_issue_date date DEFAULT NULL,
            father_doc_expiry_date date DEFAULT NULL,
            father_employee_affairs varchar(100) DEFAULT NULL,
            father_is_employee tinyint(1) DEFAULT 0,

            mother_full_name varchar(200) DEFAULT NULL,
            mother_nationality varchar(100) DEFAULT NULL,
            mother_job varchar(100) DEFAULT NULL,
            mother_workplace varchar(200) DEFAULT NULL,
            mother_email varchar(150) DEFAULT NULL,
            mother_employee_affairs varchar(100) DEFAULT NULL,
            mother_is_employee tinyint(1) DEFAULT 0,

            residential_area varchar(100) DEFAULT NULL,
            home_address varchar(255) DEFAULT NULL,
            building_number varchar(20) DEFAULT NULL,
            apartment_number varchar(20) DEFAULT NULL,
            home_phone varchar(30) DEFAULT NULL,
            classification varchar(100) DEFAULT NULL,
            reg_notes text DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            next_student_seq tinyint(3) UNSIGNED DEFAULT 1,
            updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY  (id),
            UNIQUE KEY family_uid (family_uid)
        ) {$charset};";

        dbDelta( $sql );

        // Extra columns that dbDelta may miss on older MySQL — add-if-missing
        $existing = $wpdb->get_col( "DESCRIBE {$table}", 0 );
        $extras = [
            'next_student_seq' => "tinyint(3) UNSIGNED DEFAULT 1",
            'updated_at'       => "datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP",
        ];
        foreach ( $extras as $col => $def ) {
            if ( ! in_array( $col, (array) $existing, true ) ) {
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN `{$col}` {$def}" );
            }
        }
    }

    // ── Extend olama_students ─────────────────────────────────────────────────
    private static function extend_students_table( $wpdb, string $charset ): void {

        $sql = "CREATE TABLE {$wpdb->prefix}olama_students (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            student_name varchar(100) NOT NULL,
            student_uid varchar(50) NOT NULL,
            family_id varchar(50) DEFAULT NULL,
            dob date DEFAULT NULL,
            national_id varchar(50) DEFAULT NULL,
            gender varchar(20) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1 NOT NULL,

            sequence_in_family tinyint(3) UNSIGNED DEFAULT NULL,
            first_name varchar(100) DEFAULT NULL,
            second_name varchar(100) DEFAULT NULL,
            third_name varchar(100) DEFAULT NULL,
            student_family_name varchar(100) DEFAULT NULL,
            secondary_family_name varchar(100) DEFAULT NULL,
            non_trans_id varchar(50) DEFAULT NULL,
            birth_place varchar(100) DEFAULT NULL,
            nationality varchar(100) DEFAULT NULL,
            mother_name varchar(200) DEFAULT NULL,
            student_email varchar(150) DEFAULT NULL,
            student_phone varchar(30) DEFAULT NULL,
            photo_attachment_id int(11) DEFAULT NULL,
            from_school varchar(200) DEFAULT NULL,
            gpa decimal(4,2) DEFAULT NULL,
            high_school_join_date date DEFAULT NULL,
            blacklist tinyint(1) DEFAULT 0,
            blacklist_reason text DEFAULT NULL,
            student_notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY  (id),
            KEY student_uid (student_uid),
            KEY family_id (family_id)
        ) {$charset};";

        dbDelta( $sql );
    }

    // ── Extend olama_student_bus_assignments ─────────────────────────────────
    private static function extend_bus_assignments_table( $wpdb, string $charset ): void {

        $table = $wpdb->prefix . 'olama_student_bus_assignments';

        // Ensure the table exists (CREATE IF NOT EXISTS with base columns)
        $wpdb->query( "CREATE TABLE IF NOT EXISTS {$table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            student_id mediumint(9) NOT NULL DEFAULT 0,
            student_uid varchar(50) DEFAULT NULL,
            bus_id mediumint(9) NOT NULL DEFAULT 0,
            academic_year_id mediumint(9) NOT NULL DEFAULT 0,
            pickup_location varchar(255) DEFAULT NULL,
            dropoff_location varchar(255) DEFAULT NULL,
            notes text DEFAULT NULL,
            assigned_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            assigned_by bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (id)
        ) {$charset};" );

        // Safely add new ERP columns only if they don't already exist
        $new_columns = [
            'direction'            => "varchar(50) DEFAULT NULL",
            'has_bus_go'           => "tinyint(1) DEFAULT 0",
            'has_bus_return'       => "tinyint(1) DEFAULT 0",
            'has_attendance_go'    => "tinyint(1) DEFAULT 0",
            'has_attendance_return'=> "tinyint(1) DEFAULT 0",
            'transport_fees'       => "decimal(10,2) DEFAULT NULL",
            'transport_date_from'  => "date DEFAULT NULL",
            'transport_date_to'    => "date DEFAULT NULL",
            'transport_is_active'  => "tinyint(1) DEFAULT 1",
        ];

        $existing = $wpdb->get_col( "DESCRIBE {$table}", 0 );

        foreach ( $new_columns as $col => $definition ) {
            if ( ! in_array( $col, (array) $existing, true ) ) {
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN `{$col}` {$definition}" );
            }
        }

        // Add unique index if missing
        $indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'student_uid_year'" );
        if ( empty( $indexes ) && in_array( 'student_uid', (array) $existing, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD UNIQUE KEY student_uid_year (student_uid, academic_year_id)" );
        }
    }

    // ── Create olama_reg_academic_history ────────────────────────────────────
    private static function create_academic_history_table( $wpdb, string $charset ): void {

        $sql = "CREATE TABLE {$wpdb->prefix}olama_reg_academic_history (
            id int(11) NOT NULL AUTO_INCREMENT,
            student_uid varchar(50) NOT NULL,
            academic_year varchar(20) DEFAULT NULL,
            school_name varchar(200) DEFAULT NULL,
            grade varchar(100) DEFAULT NULL,
            branch varchar(100) DEFAULT NULL,
            section varchar(100) DEFAULT NULL,
            registration_date date DEFAULT NULL,
            withdrawal_date date DEFAULT NULL,
            student_status varchar(50) DEFAULT NULL,
            hist_notes text DEFAULT NULL,
            is_current tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY student_uid (student_uid),
            KEY is_current (is_current)
        ) {$charset};";

        dbDelta( $sql );
    }

    // ── Create olama_reg_financial ───────────────────────────────────────────
    private static function create_financial_table( $wpdb, string $charset ): void {

        $sql = "CREATE TABLE {$wpdb->prefix}olama_reg_financial (
            id int(11) NOT NULL AUTO_INCREMENT,
            family_uid varchar(50) NOT NULL,
            academic_year_id int(11) NOT NULL,
            entitlement_date date DEFAULT NULL,
            calculation_method varchar(100) DEFAULT NULL,
            percentage decimal(5,2) DEFAULT NULL,
            amount_due decimal(12,2) DEFAULT 0.00,
            amount_paid decimal(12,2) DEFAULT 0.00,
            payments_revolving decimal(12,2) DEFAULT 0.00,
            payment_reference varchar(100) DEFAULT NULL,
            fin_notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY family_uid (family_uid),
            KEY academic_year_id (academic_year_id)
        ) {$charset};";

        dbDelta( $sql );
    }

    // ── Back-fill next_student_seq for existing families ─────────────────────
    private static function backfill_sequence_counters( $wpdb ): void {

        // For each family that still has next_student_seq = 1 (or 0),
        // set it to (max existing sequence_in_family among its students) + 1.
        $wpdb->query( "
            UPDATE {$wpdb->prefix}olama_families f
            SET f.next_student_seq = GREATEST(
                    COALESCE(
                        ( SELECT MAX( CAST( SUBSTRING( s.student_uid, LENGTH( f.family_uid ) + 1 ) AS UNSIGNED ) )
                          FROM {$wpdb->prefix}olama_students s
                          WHERE s.family_id = f.family_uid
                        ),
                        0
                    ) + 1,
                    1
                )
            WHERE f.next_student_seq IS NULL OR f.next_student_seq = 0 OR f.next_student_seq = 1
        " );
    }
}
