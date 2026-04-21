<?php
/**
 * Plugin Name: SmartCertify
 * Description: Upload certificate templates and let students generate personalized certificates via code verification.
 * Version: 4.0.0
 * Author: Swaraj Fugare
 * Text Domain: smartcertify
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SMARTCERTIFY_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMARTCERTIFY_URL', plugin_dir_url( __FILE__ ) );
define( 'SMARTCERTIFY_VERSION', '4.0.0' );

// Includes
require_once SMARTCERTIFY_DIR . 'includes/database-handler.php';
require_once SMARTCERTIFY_DIR . 'includes/helpers.php';
require_once SMARTCERTIFY_DIR . 'includes/logger.php';
require_once SMARTCERTIFY_DIR . 'includes/certificate-cleanup.php';
require_once SMARTCERTIFY_DIR . 'includes/local-qr.php';
require_once SMARTCERTIFY_DIR . 'includes/certificate-generator.php';
require_once SMARTCERTIFY_DIR . 'includes/certificate-service.php';
require_once SMARTCERTIFY_DIR . 'includes/integrations.php';
require_once SMARTCERTIFY_DIR . 'includes/admin-page.php';
require_once SMARTCERTIFY_DIR . 'includes/frontend-form.php';

// Activation / Deactivation hooks
register_activation_hook( __FILE__, 'smartcertify_activate' );
register_deactivation_hook( __FILE__, 'smartcertify_deactivate' );

function smartcertify_activate() {
    SmartCert_DB::create_tables();
    SmartCert_Cleanup::init();
}

function smartcertify_deactivate() {
    SmartCert_Cleanup::deactivate();
}

// Run a lightweight migration on admin init to ensure new columns exist for live sites
add_action( 'init', function(){
    if ( class_exists('SmartCert_DB') && method_exists('SmartCert_DB','update_schema') ) {
        SmartCert_DB::update_schema();
    }
    if ( class_exists('SmartCert_Cleanup') ) {
        SmartCert_Cleanup::init();
    }
} );

// Enqueue frontend assets (use versioning and plugins_url to avoid path encoding issues)
function smartcertify_enqueue_assets() {
    $css_file = SMARTCERTIFY_DIR . 'assets/css/style.css';
    $css_url  = plugins_url( 'assets/css/style.css', __FILE__ );
    $css_ver  = file_exists( $css_file ) ? filemtime( $css_file ) : false;

    // Elegant theme stylesheet
    $elegant_css_file = SMARTCERTIFY_DIR . 'assets/css/elegant-theme.css';
    $elegant_css_url  = plugins_url( 'assets/css/elegant-theme.css', __FILE__ );
    $elegant_css_ver  = file_exists( $elegant_css_file ) ? filemtime( $elegant_css_file ) : false;

    // Bootstrap 5 CDN
    wp_enqueue_style( 'bootstrap-5', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' );
    wp_enqueue_style( 'smartcertify-style', $css_url, array( 'bootstrap-5' ), $css_ver );
    wp_enqueue_style( 'smartcertify-elegant-theme', $elegant_css_url, array(), $elegant_css_ver );

    wp_enqueue_script( 'bootstrap-5-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', array('jquery'), null, true );

    $js_file = SMARTCERTIFY_DIR . 'assets/js/script.js';
    $js_url  = plugins_url( 'assets/js/script.js', __FILE__ );
    $js_ver  = file_exists( $js_file ) ? filemtime( $js_file ) : false;
    wp_enqueue_script( 'smartcertify-js', $js_url, array('jquery'), $js_ver, true );

    // Localize AJAX url and nonce for front-end script
    wp_localize_script( 'smartcertify-js', 'sc_ajax', array(
        'url'          => admin_url( 'admin-ajax.php' ),
        'nonce'        => wp_create_nonce( 'smartcertify_frontend' ),
        'logged_in'    => is_user_logged_in() ? 1 : 0,
        'login_url'    => wp_login_url( home_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' ) ),
        'require_login'=> class_exists( 'SmartCert_Helpers' ) && SmartCert_Helpers::is_login_required_for_download() ? 1 : 0,
    ) );

    // Inline fallback so sc_ajax is available even if localization is removed by caching
    $sc_fallback = sprintf(
        "window.sc_ajax = window.sc_ajax || %s;",
        wp_json_encode(
            array(
                'url'           => admin_url( 'admin-ajax.php' ),
                'nonce'         => wp_create_nonce( 'smartcertify_frontend' ),
                'logged_in'     => is_user_logged_in() ? 1 : 0,
                'login_url'     => wp_login_url( home_url( '/' ) ),
                'require_login' => class_exists( 'SmartCert_Helpers' ) && SmartCert_Helpers::is_login_required_for_download() ? 1 : 0,
            )
        )
    );
    wp_add_inline_script( 'smartcertify-js', $sc_fallback, 'before' );
}
add_action( 'wp_enqueue_scripts', 'smartcertify_enqueue_assets' );

// Admin assets: only load on plugin admin pages (hook contains 'smartcertify')
function smartcertify_admin_enqueue( $hook ) {
    if ( strpos( $hook, 'smartcertify' ) === false ) return;
    $css_file = SMARTCERTIFY_DIR . 'assets/css/style.css';
    $css_url  = plugins_url( 'assets/css/style.css', __FILE__ );
    $css_ver  = file_exists( $css_file ) ? filemtime( $css_file ) : false;
    wp_enqueue_style( 'smartcertify-admin-style', $css_url, array(), $css_ver );

    wp_enqueue_media();

    $js_file = SMARTCERTIFY_DIR . 'assets/js/script.js';
    $js_url  = plugins_url( 'assets/js/script.js', __FILE__ );
    $js_ver  = file_exists( $js_file ) ? filemtime( $js_file ) : false;
    wp_enqueue_script( 'smartcertify-admin-js', $js_url, array('jquery'), $js_ver, true );
}
add_action( 'admin_enqueue_scripts', 'smartcertify_admin_enqueue' );
