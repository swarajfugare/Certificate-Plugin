<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartCert_Logger {
    public static function log( $student_name, $class_name, $code, $action = 'generated', $serial = '', $context = array() ) {
        global $wpdb;

        $table = $wpdb->prefix . 'smartcertify_logs';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $time = current_time( 'mysql' );

        $payload = array(
            'certificate_id'    => intval( $context['certificate_id'] ?? 0 ),
            'user_id'          => intval( $context['user_id'] ?? 0 ),
            'class_id'         => intval( $context['class_id'] ?? 0 ),
            'batch_id'         => intval( $context['batch_id'] ?? 0 ),
            'student_name'     => sanitize_text_field( $student_name ),
            'class_name'       => sanitize_text_field( $class_name ),
            'batch_name'       => sanitize_text_field( $context['batch_name'] ?? '' ),
            'teacher_name'     => sanitize_text_field( $context['teacher_name'] ?? '' ),
            'code'             => sanitize_text_field( $code ),
            'ip_address'       => sanitize_text_field( $ip ),
            'timestamp'        => $time,
            'action'           => sanitize_text_field( $action ),
            'serial'           => sanitize_text_field( $serial ),
            'template_version' => sanitize_text_field( $context['template_version'] ?? '' ),
            'verification_url' => esc_url_raw( $context['verification_url'] ?? '' ),
            'status'           => sanitize_text_field( $context['status'] ?? 'valid' ),
        );

        $wpdb->insert(
            $table,
            $payload,
            array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    public static function debug( $message ) {
        if ( isset( $_GET['smartcertify_pdf'] ) ) {
            return;
        }

        $uploads = wp_upload_dir();
        $dir = trailingslashit( $uploads['basedir'] ) . 'smartcertify';
        if ( ! file_exists( $dir ) ) {
            @mkdir( $dir, 0755, true );
        }

        $file = $dir . '/debug.log';
        $time = gmdate( 'Y-m-d H:i:s' );
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $line = "[{$time}] {$ip} - " . print_r( $message, true ) . "\n";

        @file_put_contents( $file, $line, FILE_APPEND | LOCK_EX );
    }
}
