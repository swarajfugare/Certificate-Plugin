<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SmartCert_Cleanup - Handles temporary certificate storage and automatic cleanup
 * 
 * Responsibilities:
 * 1. Register WordPress Cron event for cleanup
 * 2. Check certificate expiry on each access (lazy deletion)
 * 3. Delete expired certificate files and update database
 * 4. Log all cleanup operations
 */
class SmartCert_Cleanup {
    const CRON_HOOK = 'smartcertify_cleanup_expired_certificates';
    const OPTION_KEY = 'smartcertify_cert_expiry_hours';
    const VALIDITY_OPTION_KEY = 'smartcertify_certificate_validity_days';
    const DEFAULT_EXPIRY_HOURS = 24;
    const DEFAULT_VALIDITY_DAYS = 365;

    public static function init() {
        // Schedule WordPress Cron event on activation
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
        }
        
        // Hook the cleanup action
        add_action( self::CRON_HOOK, array( __CLASS__, 'cleanup_expired_certificates' ) );
    }

    public static function deactivate() {
        // Remove scheduled event
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * Get configured expiry time in hours (default: 24 hours)
     * 
     * @return int Expiry duration in hours
     */
    public static function get_expiry_hours() {
        $hours = get_option( self::OPTION_KEY, self::DEFAULT_EXPIRY_HOURS );
        return max( 1, intval( $hours ) ); // Minimum 1 hour
    }

    /**
     * Set certificate expiry duration (admin setting)
     * 
     * @param int $hours Expiry duration in hours
     */
    public static function set_expiry_hours( $hours ) {
        $hours = max( 1, intval( $hours ) );
        update_option( self::OPTION_KEY, $hours );
    }

    public static function get_validity_days() {
        $days = get_option( self::VALIDITY_OPTION_KEY, self::DEFAULT_VALIDITY_DAYS );
        return max( 1, intval( $days ) );
    }

    public static function set_validity_days( $days ) {
        $days = max( 1, intval( $days ) );
        update_option( self::VALIDITY_OPTION_KEY, $days );
    }

    /**
     * Calculate expiry time for a new certificate
     * 
     * @return string DateTime string in format 'Y-m-d H:i:s'
     */
    public static function get_expiry_time() {
        $expiry_hours = self::get_expiry_hours();
        $expiry_timestamp = time() + ( $expiry_hours * 3600 );
        return date( 'Y-m-d H:i:s', $expiry_timestamp );
    }

    public static function get_certificate_expiry_time() {
        $validity_days = self::get_validity_days();
        $expiry_timestamp = time() + ( $validity_days * DAY_IN_SECONDS );
        return date( 'Y-m-d H:i:s', $expiry_timestamp );
    }

    /**
     * Check if a certificate has expired
     * 
     * @param string $expires_at DateTime string in format 'Y-m-d H:i:s'
     * @return bool True if expired, false if still valid
     */
    public static function is_expired( $expires_at ) {
        $expiry_timestamp = strtotime( $expires_at );
        return time() > $expiry_timestamp;
    }

    /**
     * Get time remaining until certificate expires (in minutes)
     * 
     * @param string $expires_at DateTime string in format 'Y-m-d H:i:s'
     * @return int Minutes remaining, negative if expired
     */
    public static function get_minutes_remaining( $expires_at ) {
        $expiry_timestamp = strtotime( $expires_at );
        return intval( ( $expiry_timestamp - time() ) / 60 );
    }

    /**
     * Register a newly generated certificate in the database
     * 
     * @param string $file_name The PDF filename
     * @param string $file_path The absolute file path
     * @param string $student_name Student name
     * @param string $class_name Class name
     * @param string $code Enrollment code
     * @return int|bool Certificate ID on success, false on failure
     */
    public static function register_certificate( $file_name, $file_path, $student_name, $class_name, $code, $context = array() ) {
        global $wpdb;

        $certs_table = $wpdb->prefix . 'smartcertify_certificates';
        $temp_expires_at = ! empty( $context['expires_at'] ) ? sanitize_text_field( $context['expires_at'] ) : self::get_expiry_time();
        $certificate_expires_at = ! empty( $context['certificate_expires_at'] ) ? sanitize_text_field( $context['certificate_expires_at'] ) : self::get_certificate_expiry_time();
        $generated_at = ! empty( $context['generated_at'] ) ? sanitize_text_field( $context['generated_at'] ) : current_time( 'mysql' );
        
        $result = $wpdb->insert(
            $certs_table,
            array(
                'file_name'        => $file_name,
                'file_path'        => $file_path,
                'user_id'          => intval( $context['user_id'] ?? 0 ),
                'student_name'     => $student_name,
                'student_email'    => sanitize_email( $context['student_email'] ?? '' ),
                'student_phone'    => sanitize_text_field( $context['student_phone'] ?? '' ),
                'class_id'         => intval( $context['class_id'] ?? 0 ),
                'batch_id'         => intval( $context['batch_id'] ?? 0 ),
                'class_name'       => $class_name,
                'batch_name'       => sanitize_text_field( $context['batch_name'] ?? '' ),
                'teacher_name'     => sanitize_text_field( $context['teacher_name'] ?? '' ),
                'code'             => $code,
                'serial'           => sanitize_text_field( $context['serial'] ?? '' ),
                'template_version' => sanitize_text_field( $context['template_version'] ?? '' ),
                'verification_url' => esc_url_raw( $context['verification_url'] ?? '' ),
                'status'           => sanitize_text_field( $context['status'] ?? 'valid' ),
                'certificate_expires_at' => $certificate_expires_at,
                'generated_at'     => $generated_at,
                'expires_at'       => $temp_expires_at,
                'renewed_from_id'  => intval( $context['renewed_from_id'] ?? 0 ),
                'delivery_email_status' => sanitize_text_field( $context['delivery_email_status'] ?? '' ),
                'delivery_whatsapp_status' => sanitize_text_field( $context['delivery_whatsapp_status'] ?? '' ),
                'delivery_notes'   => sanitize_textarea_field( $context['delivery_notes'] ?? '' ),
            ),
            array( '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
        );

        if ( $result ) {
            SmartCert_Logger::debug( array(
                'action' => 'register_certificate',
                'file_name' => $file_name,
                'student' => $student_name,
                'class' => $class_name,
                'batch' => $context['batch_name'] ?? '',
                'serial' => $context['serial'] ?? '',
                'expires_at' => $temp_expires_at,
                'certificate_expires_at' => $certificate_expires_at,
                'success' => true
            ) );
            return $wpdb->insert_id;
        } else {
            SmartCert_Logger::debug( array(
                'action' => 'register_certificate',
                'file_name' => $file_name,
                'error' => $wpdb->last_error,
                'success' => false
            ) );
            return false;
        }
    }

    /**
     * Check if a certificate exists and is not expired
     * 
     * @param string $file_name The PDF filename
     * @return array|null Certificate record if valid, null if not found or expired
     */
    public static function get_valid_certificate( $file_name ) {
        global $wpdb;

        $certs_table = $wpdb->prefix . 'smartcertify_certificates';
        
        $cert = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $certs_table WHERE file_name = %s AND deleted_at IS NULL LIMIT 1",
            $file_name
        ), ARRAY_A );

        if ( ! $cert ) {
            return null;
        }

        $cert = self::sync_certificate_status( $cert );

        // Check if expired
        if ( self::is_expired( $cert['expires_at'] ) ) {
            SmartCert_Logger::debug( array(
                'action' => 'get_valid_certificate',
                'file_name' => $file_name,
                'status' => 'expired',
                'expires_at' => $cert['expires_at'],
                'current_time' => current_time( 'mysql' )
            ) );
            return null;
        }

        if ( ! in_array( $cert['status'] ?? 'valid', array( 'valid', '' ), true ) ) {
            return null;
        }

        return $cert;
    }

    public static function get_certificate_by_id( $certificate_id ) {
        global $wpdb;

        $certs_table = $wpdb->prefix . 'smartcertify_certificates';
        $cert = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $certs_table WHERE id = %d LIMIT 1",
                intval( $certificate_id )
            ),
            ARRAY_A
        );

        return $cert ? self::sync_certificate_status( $cert ) : null;
    }

    public static function get_certificate_by_serial( $serial ) {
        global $wpdb;

        $certs_table = $wpdb->prefix . 'smartcertify_certificates';
        $cert = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $certs_table WHERE serial = %s ORDER BY id DESC LIMIT 1",
                sanitize_text_field( $serial )
            ),
            ARRAY_A
        );

        return $cert ? self::sync_certificate_status( $cert ) : null;
    }

    public static function sync_certificate_status( $cert ) {
        if ( empty( $cert['id'] ) ) {
            return $cert;
        }

        global $wpdb;
        $certs_table = $wpdb->prefix . 'smartcertify_certificates';
        $current_status = (string) ( $cert['status'] ?? 'valid' );
        $new_status = $current_status;

        if ( ! empty( $cert['revoked_at'] ) ) {
            $new_status = 'revoked';
        } elseif ( ! empty( $cert['renewed_to_id'] ) && intval( $cert['renewed_to_id'] ) > 0 ) {
            $new_status = 'renewed';
        } elseif ( ! empty( $cert['deleted_at'] ) ) {
            $new_status = 'deleted';
        } elseif ( ! empty( $cert['certificate_expires_at'] ) && strtotime( $cert['certificate_expires_at'] ) && time() > strtotime( $cert['certificate_expires_at'] ) ) {
            $new_status = 'expired';
        } elseif ( '' === $new_status ) {
            $new_status = 'valid';
        }

        if ( $new_status !== $current_status ) {
            $wpdb->update(
                $certs_table,
                array( 'status' => $new_status ),
                array( 'id' => intval( $cert['id'] ) ),
                array( '%s' ),
                array( '%d' )
            );
            $cert['status'] = $new_status;
        }

        return $cert;
    }

    public static function refresh_certificate_statuses() {
        global $wpdb;

        $certs_table = $wpdb->prefix . 'smartcertify_certificates';
        $wpdb->query(
            "UPDATE $certs_table
             SET status = 'expired'
             WHERE deleted_at IS NULL
             AND revoked_at IS NULL
             AND renewed_to_id = 0
             AND certificate_expires_at IS NOT NULL
             AND certificate_expires_at < NOW()
             AND status <> 'expired'"
        );
    }

    public static function update_delivery_status( $certificate_id, $channel, $status, $note = '' ) {
        global $wpdb;

        $channel = sanitize_key( $channel );
        if ( ! in_array( $channel, array( 'email', 'whatsapp' ), true ) ) {
            return false;
        }

        $columns = array(
            'status' => 'delivery_' . $channel . '_status',
        );

        $update = array(
            $columns['status'] => sanitize_text_field( $status ),
        );
        $formats = array( '%s' );

        if ( '' !== trim( (string) $note ) ) {
            $update['delivery_notes'] = sanitize_textarea_field( $note );
            $formats[] = '%s';
        }

        return false !== $wpdb->update(
            $wpdb->prefix . 'smartcertify_certificates',
            $update,
            array( 'id' => intval( $certificate_id ) ),
            $formats,
            array( '%d' )
        );
    }

    public static function revoke_certificate( $certificate_id, $reason = '' ) {
        global $wpdb;

        $cert = self::get_certificate_by_id( $certificate_id );
        if ( ! $cert ) {
            return false;
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'smartcertify_certificates',
            array(
                'status'        => 'revoked',
                'revoked_at'    => current_time( 'mysql' ),
                'revoke_reason' => sanitize_textarea_field( $reason ),
            ),
            array( 'id' => intval( $certificate_id ) ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        return false !== $result;
    }

    public static function mark_certificate_renewed( $old_certificate_id, $new_certificate_id ) {
        global $wpdb;

        $old_certificate_id = intval( $old_certificate_id );
        $new_certificate_id = intval( $new_certificate_id );

        if ( ! $old_certificate_id || ! $new_certificate_id ) {
            return false;
        }

        $updated_old = $wpdb->update(
            $wpdb->prefix . 'smartcertify_certificates',
            array(
                'status'        => 'renewed',
                'renewed_to_id' => $new_certificate_id,
            ),
            array( 'id' => $old_certificate_id ),
            array( '%s', '%d' ),
            array( '%d' )
        );

        $updated_new = $wpdb->update(
            $wpdb->prefix . 'smartcertify_certificates',
            array(
                'renewed_from_id' => $old_certificate_id,
                'status'          => 'valid',
            ),
            array( 'id' => $new_certificate_id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        return false !== $updated_old && false !== $updated_new;
    }

    public static function renew_certificate_expiry( $certificate_id, $days = 0 ) {
        global $wpdb;

        $cert = self::get_certificate_by_id( $certificate_id );
        if ( ! $cert ) {
            return false;
        }

        if ( ! empty( $cert['deleted_at'] ) || ! empty( $cert['renewed_to_id'] ) || ! empty( $cert['revoked_at'] ) ) {
            return false;
        }

        $days = intval( $days );
        if ( $days <= 0 ) {
            $days = self::get_validity_days();
        }
        $days = max( 1, $days );

        $base_timestamp = time();
        if ( ! empty( $cert['certificate_expires_at'] ) ) {
            $existing_expiry = strtotime( $cert['certificate_expires_at'] );
            if ( $existing_expiry && $existing_expiry > $base_timestamp ) {
                $base_timestamp = $existing_expiry;
            }
        }

        $new_expiry = date( 'Y-m-d H:i:s', $base_timestamp + ( $days * DAY_IN_SECONDS ) );
        $result = $wpdb->update(
            $wpdb->prefix . 'smartcertify_certificates',
            array(
                'certificate_expires_at' => $new_expiry,
                'status'                 => 'valid',
            ),
            array( 'id' => intval( $certificate_id ) ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        return false !== $result ? $new_expiry : false;
    }

    /**
     * Delete a certificate file and mark as deleted in database
     * 
     * @param int|string $cert_id Certificate ID or file_name
     * @param bool $is_file_name True if $cert_id is file_name, false if ID
     * @return bool True on success, false on failure
     */
    public static function delete_certificate( $cert_id, $is_file_name = false ) {
        global $wpdb;

        $certs_table = $wpdb->prefix . 'smartcertify_certificates';
        
        // Get certificate record
        if ( $is_file_name ) {
            $cert = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM $certs_table WHERE file_name = %s AND deleted_at IS NULL LIMIT 1",
                $cert_id
            ), ARRAY_A );
        } else {
            $cert = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM $certs_table WHERE id = %d AND deleted_at IS NULL LIMIT 1",
                $cert_id
            ), ARRAY_A );
        }

        if ( ! $cert ) {
            return false;
        }

        // Delete physical file
        $deleted_file = false;
        if ( ! empty( $cert['file_path'] ) && file_exists( $cert['file_path'] ) ) {
            $deleted_file = @unlink( $cert['file_path'] );
        } else {
            // File doesn't exist, but we'll mark it as deleted anyway
            $deleted_file = true;
        }

        // Mark as deleted in database
        $updated = $wpdb->update(
            $certs_table,
            array( 'deleted_at' => current_time( 'mysql' ) ),
            array( 'id' => intval( $cert['id'] ) ),
            array( '%s' ),
            array( '%d' )
        );

        if ( $deleted_file && $updated !== false ) {
            SmartCert_Logger::debug( array(
                'action' => 'delete_certificate',
                'cert_id' => $cert['id'],
                'file_name' => $cert['file_name'],
                'student' => $cert['student_name'],
                'class' => $cert['class_name'],
                'file_deleted' => $deleted_file,
                'db_updated' => $updated,
                'reason' => 'Expired',
                'success' => true
            ) );
            return true;
        } else {
            SmartCert_Logger::debug( array(
                'action' => 'delete_certificate',
                'cert_id' => $cert['id'],
                'file_name' => $cert['file_name'],
                'file_deleted' => $deleted_file,
                'db_updated' => $updated,
                'error' => $wpdb->last_error,
                'success' => false
            ) );
            return false;
        }
    }

    /**
     * WordPress Cron: Cleanup all expired certificates
     * Called hourly by WordPress Cron
     * 
     * @return int Number of certificates deleted
     */
    public static function cleanup_expired_certificates() {
        global $wpdb;

        $certs_table = $wpdb->prefix . 'smartcertify_certificates';
        self::refresh_certificate_statuses();
        
        // Find all expired certificates that haven't been deleted yet
        $expired = $wpdb->get_results( "
            SELECT id, file_name, file_path, student_name, class_name 
            FROM $certs_table 
            WHERE deleted_at IS NULL 
            AND expires_at < NOW()
            LIMIT 100
        ", ARRAY_A );

        if ( empty( $expired ) ) {
            SmartCert_Logger::debug( array(
                'action' => 'cleanup_expired_certificates',
                'status' => 'no_expired_certificates',
                'found' => 0
            ) );
            return 0;
        }

        $deleted_count = 0;
        foreach ( $expired as $cert ) {
            if ( self::delete_certificate( $cert['id'] ) ) {
                $deleted_count++;
            }
        }

        SmartCert_Logger::debug( array(
            'action' => 'cleanup_expired_certificates',
            'status' => 'completed',
            'found' => count( $expired ),
            'deleted' => $deleted_count,
            'timestamp' => current_time( 'mysql' )
        ) );

        return $deleted_count;
    }

    /**
     * Manual cleanup trigger for testing/admin purposes
     * 
     * @return int Number of certificates deleted
     */
    public static function manual_cleanup() {
        return self::cleanup_expired_certificates();
    }

    /**
     * Get cleanup statistics
     * 
     * @return array Statistics including total, active, expired, deleted counts
     */
    public static function get_statistics() {
        global $wpdb;

        $certs_table = $wpdb->prefix . 'smartcertify_certificates';
        self::refresh_certificate_statuses();
        
        $total = intval( $wpdb->get_var( "SELECT COUNT(*) FROM $certs_table" ) );
        $active = intval( $wpdb->get_var( "SELECT COUNT(*) FROM $certs_table WHERE deleted_at IS NULL AND status = 'valid'" ) );
        $expired = intval( $wpdb->get_var( "SELECT COUNT(*) FROM $certs_table WHERE deleted_at IS NULL AND status = 'expired'" ) );
        $revoked = intval( $wpdb->get_var( "SELECT COUNT(*) FROM $certs_table WHERE deleted_at IS NULL AND status = 'revoked'" ) );
        $renewed = intval( $wpdb->get_var( "SELECT COUNT(*) FROM $certs_table WHERE deleted_at IS NULL AND status = 'renewed'" ) );
        $deleted = intval( $wpdb->get_var( "SELECT COUNT(*) FROM $certs_table WHERE deleted_at IS NOT NULL" ) );

        return array(
            'total' => $total,
            'active' => $active,
            'expired' => $expired,
            'revoked' => $revoked,
            'renewed' => $renewed,
            'deleted' => $deleted,
            'expiry_hours' => self::get_expiry_hours(),
            'validity_days' => self::get_validity_days(),
            'next_cleanup' => wp_next_scheduled( self::CRON_HOOK )
        );
    }
}
