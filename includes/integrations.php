<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartCert_Integrations {
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public function register_rest_routes() {
        register_rest_route(
            'smartcertify/v1',
            '/verify/(?P<serial>[A-Za-z0-9\-]+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_verify_certificate' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'smartcertify/v1',
            '/issue',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_issue_certificate' ),
                'permission_callback' => array( $this, 'rest_require_api_key' ),
            )
        );

        register_rest_route(
            'smartcertify/v1',
            '/health',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_health_snapshot' ),
                'permission_callback' => array( $this, 'rest_require_api_key' ),
            )
        );
    }

    public static function dispatch_webhook( $event, $certificate = array(), $context = array() ) {
        $settings = SmartCert_Helpers::get_webhook_settings();
        if ( empty( $settings['url'] ) ) {
            return false;
        }

        $payload = array(
            'event'       => sanitize_text_field( $event ),
            'timestamp'   => current_time( 'mysql' ),
            'site'        => array(
                'name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
                'url'  => home_url( '/' ),
            ),
            'certificate' => is_array( $certificate ) ? $certificate : array(),
            'context'     => is_array( $context ) ? $context : array(),
        );

        $body = wp_json_encode( $payload );
        $headers = array(
            'Content-Type'         => 'application/json; charset=utf-8',
            'X-SmartCertify-Event' => sanitize_text_field( $event ),
        );

        if ( ! empty( $settings['secret'] ) ) {
            $headers['X-SmartCertify-Signature'] = 'sha256=' . hash_hmac( 'sha256', $body, $settings['secret'] );
        }

        $response = wp_remote_post(
            $settings['url'],
            array(
                'timeout' => 15,
                'headers' => $headers,
                'body'    => $body,
            )
        );

        if ( is_wp_error( $response ) ) {
            SmartCert_Logger::debug(
                array(
                    'action'  => 'webhook_failed',
                    'event'   => $event,
                    'message' => $response->get_error_message(),
                )
            );
            return false;
        }

        return wp_remote_retrieve_response_code( $response );
    }

    public function rest_require_api_key( WP_REST_Request $request ) {
        $settings = SmartCert_Helpers::get_api_settings();
        if ( empty( $settings['key'] ) ) {
            return new WP_Error( 'smartcertify_api_disabled', 'SmartCertify API key is not configured.', array( 'status' => 403 ) );
        }

        $provided = trim( (string) $request->get_header( 'x-smartcertify-key' ) );
        if ( '' === $provided ) {
            $provided = sanitize_text_field( $request->get_param( 'smartcertify_key' ) );
        }

        if ( ! hash_equals( $settings['key'], $provided ) ) {
            return new WP_Error( 'smartcertify_api_forbidden', 'Invalid SmartCertify API key.', array( 'status' => 403 ) );
        }

        return true;
    }

    public function rest_verify_certificate( WP_REST_Request $request ) {
        $serial = sanitize_text_field( $request['serial'] ?? '' );
        $student_name = sanitize_text_field( $request->get_param( 'student_name' ) ?? '' );
        return rest_ensure_response( SmartCert_Service::verify_certificate( $serial, $student_name ) );
    }

    public function rest_issue_certificate( WP_REST_Request $request ) {
        global $wpdb;

        $class_id = intval( $request->get_param( 'class_id' ) );
        $batch_id = intval( $request->get_param( 'batch_id' ) );
        $student_name = sanitize_text_field( $request->get_param( 'student_name' ) );
        $student_email = sanitize_email( $request->get_param( 'student_email' ) );
        $student_phone = sanitize_text_field( $request->get_param( 'student_phone' ) );
        $code = SmartCert_Helpers::sanitize_code( $request->get_param( 'code' ) );

        $class = SmartCert_Helpers::get_class( $class_id );
        if ( ! $class ) {
            return new WP_Error( 'invalid_class', 'Selected class not found.', array( 'status' => 400 ) );
        }

        $batch = SmartCert_Helpers::get_batch( $batch_id );
        if ( ! $batch || intval( $batch->class_id ) !== intval( $class->id ) ) {
            return new WP_Error( 'invalid_batch', 'Selected batch not found.', array( 'status' => 400 ) );
        }

        $code_table = $wpdb->prefix . 'smartcertify_codes';
        $code_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $code_table
                 WHERE code = %s
                 AND batch_id = %d
                 AND (class_id = %d OR (class_id = 0 AND class_name = %s))
                 ORDER BY id DESC
                 LIMIT 1",
                $code,
                intval( $batch_id ),
                intval( $class->id ),
                $class->class_name
            ),
            ARRAY_A
        );

        if ( ! $code_row ) {
            return new WP_Error( 'invalid_code', 'The provided code does not match the selected class and batch.', array( 'status' => 400 ) );
        }

        if ( 'active' !== strtolower( (string) ( $code_row['status'] ?? 'active' ) ) ) {
            return new WP_Error( 'inactive_code', 'This code is not active right now.', array( 'status' => 400 ) );
        }

        if ( ! empty( $code_row['student_name'] ) && ! SmartCert_Helpers::names_match( $code_row['student_name'], $student_name ) ) {
            return new WP_Error( 'name_mismatch', 'This code is assigned to another student name.', array( 'status' => 400 ) );
        }

        $download_limit = intval( $code_row['download_limit'] ?? 0 );
        if ( $download_limit > 0 && intval( $code_row['download_count'] ?? 0 ) >= $download_limit ) {
            return new WP_Error( 'download_limit', 'Download limit exceeded for this code.', array( 'status' => 400 ) );
        }

        $result = SmartCert_Service::issue_certificate(
            array(
                'class'                    => $class,
                'batch'                    => $batch,
                'student_name'             => $student_name,
                'student_email'            => $student_email ?: ( $code_row['student_email'] ?? '' ),
                'student_phone'            => $student_phone ?: ( $code_row['student_phone'] ?? '' ),
                'user_id'                  => intval( $request->get_param( 'user_id' ) ),
                'code'                     => $code,
                'teacher_name'             => $batch->teacher_name,
                'teacher_signature_id'     => intval( $batch->teacher_signature_id ),
                'teacher_signature_url'    => $batch->teacher_signature_url,
                'status'                   => 'valid',
                'increment_download_count' => ! empty( $request->get_param( 'increment_download_count' ) ),
                'code_row_id'              => intval( $code_row['id'] ),
                'sync_code_contact_fields' => true,
                'trigger_auto_delivery'    => ! empty( $request->get_param( 'trigger_auto_delivery' ) ),
                'send_email'               => ! empty( $request->get_param( 'send_email' ) ),
                'send_whatsapp'            => ! empty( $request->get_param( 'send_whatsapp' ) ),
            )
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response(
            array(
                'success'          => true,
                'certificate_id'   => intval( $result['certificate_id'] ),
                'serial'           => $result['serial'],
                'certificate_url'  => $result['url'],
                'verification_url' => $result['verification_url'],
                'delivery'         => $result['delivery'],
                'reused'           => ! empty( $result['reused'] ),
            )
        );
    }

    public function rest_health_snapshot() {
        return rest_ensure_response(
            array(
                'plugin_version'   => defined( 'SMARTCERTIFY_VERSION' ) ? SMARTCERTIFY_VERSION : '',
                'local_qr_ready'   => class_exists( 'SmartCert_Local_QR' ),
                'gd_available'     => function_exists( 'imagecreatetruecolor' ),
                'imagick_available'=> class_exists( 'Imagick' ),
                'master_template'  => SmartCert_Helpers::get_master_template(),
                'login_required'   => SmartCert_Helpers::is_login_required_for_download(),
                'verify_url'       => SmartCert_Helpers::get_verify_url( 'SC-DEMO1234' ),
            )
        );
    }
}

new SmartCert_Integrations();
