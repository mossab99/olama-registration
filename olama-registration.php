<?php
/**
 * Plugin Name: Olama Registration
 * Plugin URI:  https://olama.online/olama-registration
 * Description: Family-centric student registration system for Olama School. Requires Olama School System plugin.
 * Version:     1.0.1
 * Author:      د. مصعب الحنيطي
 * Author URI:  https://olama.online
 * Text Domain: olama-registration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Constants ────────────────────────────────────────────────────────────────
define( 'OLAMA_REG_VERSION',             '1.0.1' );
define( 'OLAMA_REG_MIN_SCHOOL_VERSION',  '2.3.9' );
define( 'OLAMA_REG_PATH',               plugin_dir_path( __FILE__ ) );
define( 'OLAMA_REG_URL',                plugin_dir_url( __FILE__ ) );
define( 'OLAMA_REG_FILE',               __FILE__ );

// ── Dependency Guard ─────────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'olama_reg_init', 5 );

function olama_reg_init() {

    // 1. Check parent plugin class exists
    if ( ! class_exists( 'Olama_School_DB' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
                . '<strong>Olama Registration:</strong> '
                . esc_html__( 'Requires the Olama School System plugin to be installed and active.', 'olama-registration' )
                . '</p></div>';
        } );
        return;
    }

    // 2. Check minimum version
    if ( defined( 'OLAMA_SCHOOL_VERSION' ) && version_compare( OLAMA_SCHOOL_VERSION, OLAMA_REG_MIN_SCHOOL_VERSION, '<' ) ) {
        add_action( 'admin_notices', function () {
            /* translators: %s = minimum required version */
            echo '<div class="notice notice-error"><p>'
                . sprintf(
                    esc_html__( 'Olama Registration requires Olama School System version %s or higher.', 'olama-registration' ),
                    esc_html( OLAMA_REG_MIN_SCHOOL_VERSION )
                )
                . '</p></div>';
        } );
        return;
    }

    // ── Load includes ────────────────────────────────────────────────────────
    require_once OLAMA_REG_PATH . 'includes/class-reg-id-generator.php';
    require_once OLAMA_REG_PATH . 'includes/class-reg-activator.php';
    require_once OLAMA_REG_PATH . 'includes/class-reg-family.php';
    require_once OLAMA_REG_PATH . 'includes/class-reg-student.php';
    require_once OLAMA_REG_PATH . 'includes/class-reg-academic-history.php';
    require_once OLAMA_REG_PATH . 'includes/class-reg-transport.php';
    require_once OLAMA_REG_PATH . 'includes/class-reg-financial.php';

    // ── Load admin ───────────────────────────────────────────────────────────
    if ( is_admin() ) {
        require_once OLAMA_REG_PATH . 'admin/class-reg-ajax.php';
        require_once OLAMA_REG_PATH . 'admin/class-reg-family-table.php';
        require_once OLAMA_REG_PATH . 'admin/class-reg-student-table.php';
        require_once OLAMA_REG_PATH . 'admin/class-reg-admin.php';
        new Olama_Reg_Admin();
        new Olama_Reg_Ajax();
    }

    // Schema version check — run migrations if version changed
    $installed = get_option( 'olama_reg_version', '0' );
    if ( version_compare( $installed, OLAMA_REG_VERSION, '<' ) ) {
        Olama_Reg_Activator::run_migrations();
        update_option( 'olama_reg_version', OLAMA_REG_VERSION );
    }

    // ── Load translations ────────────────────────────────────────────────────
    load_plugin_textdomain(
        'olama-registration',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
}

// ── Activation hook ──────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'olama_reg_activate' );

function olama_reg_activate() {
    ob_start();
    try {
        // Require files manually — plugins_loaded hasn't fired yet during activation
        if ( ! class_exists( 'Olama_Reg_Activator' ) ) {
            require_once OLAMA_REG_PATH . 'includes/class-reg-activator.php';
        }
        Olama_Reg_Activator::activate();
        flush_rewrite_rules();
    } catch ( Exception $e ) {
        error_log( 'Olama Registration Activation Error: ' . $e->getMessage() );
    }
    if ( ob_get_length() > 0 ) {
        ob_end_clean();
    }
}

// ── Deactivation hook ────────────────────────────────────────────────────────
register_deactivation_hook( __FILE__, 'olama_reg_deactivate' );

function olama_reg_deactivate() {
    flush_rewrite_rules();
}
