<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartCert_Helpers {
    public static function get_classes() {
        global $wpdb;
        $table = $wpdb->prefix . 'smartcertify_classes';
        return $wpdb->get_results( "SELECT * FROM $table ORDER BY class_name ASC" );
    }

    public static function get_class( $class_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'smartcertify_classes';
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table WHERE id = %d LIMIT 1", intval( $class_id ) )
        );
    }

    public static function get_class_by_name( $class_name ) {
        global $wpdb;
        $table = $wpdb->prefix . 'smartcertify_classes';
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE LOWER(TRIM(class_name)) = LOWER(TRIM(%s)) LIMIT 1",
                sanitize_text_field( $class_name )
            )
        );
    }

    public static function get_batches( $class_id = 0 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'smartcertify_batches';

        if ( intval( $class_id ) > 0 ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $table WHERE class_id = %d AND is_active = 1 ORDER BY batch_name ASC",
                    intval( $class_id )
                )
            );
        }

        return $wpdb->get_results( "SELECT * FROM $table WHERE is_active = 1 ORDER BY batch_name ASC" );
    }

    public static function get_batches_grouped_by_class() {
        $grouped = array();
        foreach ( self::get_batches() as $batch ) {
            $class_id = intval( $batch->class_id );
            if ( ! isset( $grouped[ $class_id ] ) ) {
                $grouped[ $class_id ] = array();
            }
            $grouped[ $class_id ][] = $batch;
        }
        return $grouped;
    }

    public static function get_batch( $batch_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'smartcertify_batches';
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table WHERE id = %d LIMIT 1", intval( $batch_id ) )
        );
    }

    public static function get_batch_by_name( $class_id, $batch_name ) {
        global $wpdb;
        $table = $wpdb->prefix . 'smartcertify_batches';
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE class_id = %d AND LOWER(TRIM(batch_name)) = LOWER(TRIM(%s)) LIMIT 1",
                intval( $class_id ),
                sanitize_text_field( $batch_name )
            )
        );
    }

    public static function sanitize_code( $code ) {
        return preg_replace( '/[^0-9A-Za-z]/', '', (string) $code );
    }

    public static function normalize_name( $name ) {
        $name = wp_strip_all_tags( (string) $name );
        $name = preg_replace( '/\s+/', ' ', trim( $name ) );
        return function_exists( 'mb_strtolower' ) ? mb_strtolower( $name ) : strtolower( $name );
    }

    public static function names_match( $a, $b ) {
        return self::normalize_name( $a ) === self::normalize_name( $b );
    }

    public static function is_valid_template_value( $value ) {
        if ( empty( $value ) ) {
            return false;
        }

        $ext = strtolower( pathinfo( strtok( (string) $value, '?' ), PATHINFO_EXTENSION ) );
        return in_array( $ext, array( 'png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf' ), true );
    }

    public static function get_master_template() {
        $active_version = self::get_active_template_version();
        $attachment_id = intval( $active_version['attachment_id'] ?? get_option( 'smartcertify_master_template_id', 0 ) );
        $url = trim( (string) ( $active_version['url'] ?? get_option( 'smartcertify_master_template_url', '' ) ) );

        if ( $attachment_id ) {
            $attachment_url = wp_get_attachment_url( $attachment_id );
            if ( $attachment_url ) {
                $url = $attachment_url;
            }
        }

        return array(
            'attachment_id' => $attachment_id,
            'url'           => $url,
            'version_id'    => sanitize_text_field( $active_version['id'] ?? '' ),
            'label'         => sanitize_text_field( $active_version['label'] ?? '' ),
        );
    }

    public static function resolve_template_reference( $class = null ) {
        $template = self::get_master_template();

        if ( ! empty( $template['attachment_id'] ) || ! empty( $template['url'] ) ) {
            return $template;
        }

        if ( $class && ! empty( $class->certificate_template ) ) {
            return array(
                'attachment_id' => 0,
                'url'           => $class->certificate_template,
            );
        }

        return array(
            'attachment_id' => 0,
            'url'           => '',
        );
    }

    public static function resolve_media_source( $attachment_id = 0, $url = '' ) {
        $attachment_id = intval( $attachment_id );
        $url = trim( (string) $url );
        $mime = '';
        $path = '';

        if ( $attachment_id > 0 ) {
            $path = get_attached_file( $attachment_id );
            $mime = get_post_mime_type( $attachment_id );
            if ( empty( $url ) ) {
                $attachment_url = wp_get_attachment_url( $attachment_id );
                if ( $attachment_url ) {
                    $url = $attachment_url;
                }
            }
        }

        if ( ! $path && $url ) {
            $uploads = wp_upload_dir();
            if ( strpos( $url, $uploads['baseurl'] ) === 0 ) {
                $path = trailingslashit( $uploads['basedir'] ) . ltrim( substr( $url, strlen( $uploads['baseurl'] ) ), '/' );
            }
        }

        if ( ! $mime ) {
            $probe = $path ? $path : $url;
            $ext = strtolower( pathinfo( strtok( (string) $probe, '?' ), PATHINFO_EXTENSION ) );
            $mime_map = array(
                'png'  => 'image/png',
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif'  => 'image/gif',
                'webp' => 'image/webp',
                'pdf'  => 'application/pdf',
            );
            $mime = $mime_map[ $ext ] ?? '';
        }

        return array(
            'attachment_id' => $attachment_id,
            'url'           => $url,
            'path'          => $path,
            'mime'          => $mime,
        );
    }

    public static function get_coordinate( $raw, $dimension, $fallback = 0 ) {
        $raw = trim( (string) $raw );
        if ( '' === $raw ) {
            return floatval( $fallback );
        }

        if ( false !== strpos( $raw, '%' ) ) {
            return round( ( floatval( $raw ) / 100 ) * floatval( $dimension ), 2 );
        }

        return floatval( $raw );
    }

    public static function get_font_choices() {
        return array(
            'sans'             => 'System Sans',
            'montserrat_bold'  => 'Montserrat Bold',
            'serif'            => 'Serif',
            'samarata'         => 'Samarata',
        );
    }

    public static function get_layout_defaults() {
        return array(
            'name_x'                  => '50%',
            'name_y'                  => '55%',
            'name_font_size'          => '58',
            'name_font_family'        => 'sans',
            'class_x'                 => '50%',
            'class_y'                 => '25%',
            'class_font_size'         => '32',
            'class_font_family'       => 'montserrat_bold',
            'batch_x'                 => '50%',
            'batch_y'                 => '30.5%',
            'batch_font_size'         => '19',
            'batch_font_family'       => 'montserrat_bold',
            'teacher_signature_x'     => '64%',
            'teacher_signature_y'     => '75%',
            'teacher_signature_width' => '12.5%',
            'teacher_signature_height'=> '6.5%',
            'teacher_name_x'          => '70%',
            'teacher_name_y'          => '92.5%',
            'teacher_name_font_size'  => '24',
            'teacher_name_font_family'=> 'montserrat_bold',
            'qr_x'                    => '84.7%',
            'qr_y'                    => '80.8%',
            'qr_size'                 => '8.5%',
            'meta_x'                  => '83.5%',
            'meta_y'                  => '78%',
            'meta_font_size'          => '11',
            'meta_font_family'        => 'sans',
        );
    }

    public static function get_legacy_layout_defaults() {
        return array(
            'name_x'                  => '50%',
            'name_y'                  => '53%',
            'name_font_size'          => '66',
            'name_font_family'        => 'sans',
            'class_x'                 => '50%',
            'class_y'                 => '28%',
            'class_font_size'         => '34',
            'class_font_family'       => 'montserrat_bold',
            'batch_x'                 => '50%',
            'batch_y'                 => '34%',
            'batch_font_size'         => '18',
            'batch_font_family'       => 'montserrat_bold',
            'teacher_signature_x'     => '63%',
            'teacher_signature_y'     => '73%',
            'teacher_signature_width' => '14%',
            'teacher_signature_height'=> '8%',
            'teacher_name_x'          => '70%',
            'teacher_name_y'          => '88%',
            'teacher_name_font_size'  => '18',
            'teacher_name_font_family'=> 'montserrat_bold',
            'qr_x'                    => '84%',
            'qr_y'                    => '80%',
            'qr_size'                 => '10%',
            'meta_x'                  => '83%',
            'meta_y'                  => '75%',
            'meta_font_size'          => '12',
            'meta_font_family'        => 'sans',
        );
    }

    public static function get_layout_settings() {
        $defaults = self::get_layout_defaults();
        $legacy_defaults = self::get_legacy_layout_defaults();
        $settings = array();

        foreach ( $defaults as $key => $default ) {
            $stored = get_option( 'smartcertify_' . $key, null );

            if ( null === $stored ) {
                $settings[ $key ] = (string) $default;
                continue;
            }

            if (
                isset( $legacy_defaults[ $key ] ) &&
                (string) $stored === (string) $legacy_defaults[ $key ]
            ) {
                $settings[ $key ] = (string) $default;
                continue;
            }

            $settings[ $key ] = (string) $stored;
        }

        return $settings;
    }

    public static function sanitize_layout_settings( $layout ) {
        $sanitized = array();

        if ( ! is_array( $layout ) ) {
            return $sanitized;
        }

        foreach ( array_keys( self::get_layout_defaults() ) as $key ) {
            if ( ! array_key_exists( $key, $layout ) ) {
                continue;
            }

            $value = $layout[ $key ];
            if ( is_array( $value ) || is_object( $value ) ) {
                continue;
            }

            $sanitized[ $key ] = sanitize_text_field( (string) $value );
        }

        return $sanitized;
    }

    public static function get_qr_scanner_markup( $args = array() ) {
        $args = wp_parse_args(
            $args,
            array(
                'target_input'    => '',
                'submit_on_scan'  => false,
                'redirect_on_url' => true,
                'title'           => 'Scan QR Code',
                'copy'            => 'Use your camera or upload a QR code image to read the serial automatically.',
                'start_label'     => 'Start Camera',
                'stop_label'      => 'Stop Camera',
                'upload_label'    => 'Upload QR Image',
                'status'          => 'Camera is off. You can start scanning or upload a QR image.',
            )
        );

        ob_start();
        ?>
        <div
            class="sc-qr-scanner"
            data-target-input="<?php echo esc_attr( $args['target_input'] ); ?>"
            data-submit-on-scan="<?php echo $args['submit_on_scan'] ? '1' : '0'; ?>"
            data-redirect-on-url="<?php echo $args['redirect_on_url'] ? '1' : '0'; ?>"
        >
            <div class="sc-qr-scanner-head">
                <h3 class="sc-qr-scanner-title"><?php echo esc_html( $args['title'] ); ?></h3>
                <p class="sc-qr-scanner-copy"><?php echo esc_html( $args['copy'] ); ?></p>
            </div>

            <div class="sc-qr-scanner-actions">
                <button type="button" class="sc-btn sc-btn-secondary sc-qr-start"><?php echo esc_html( $args['start_label'] ); ?></button>
                <button type="button" class="sc-btn sc-btn-secondary sc-qr-stop" disabled><?php echo esc_html( $args['stop_label'] ); ?></button>
                <button type="button" class="sc-btn sc-btn-secondary sc-qr-upload-trigger"><?php echo esc_html( $args['upload_label'] ); ?></button>
                <input type="file" class="sc-qr-upload" accept="image/*" hidden />
            </div>

            <div class="sc-qr-scanner-stage">
                <video class="sc-qr-video" playsinline muted hidden></video>
                <div class="sc-qr-frame" aria-hidden="true"></div>
                <div class="sc-qr-placeholder">
                    <strong>QR Preview Area</strong>
                    <span>Point the camera at the certificate QR code or upload a saved QR image.</span>
                </div>
            </div>

            <p class="sc-qr-status" role="status"><?php echo esc_html( $args['status'] ); ?></p>
        </div>
        <?php

        return ob_get_clean();
    }

    public static function get_default_batch_name() {
        return 'Batch 1';
    }

    public static function get_default_batch_settings() {
        $signature_id = intval( get_option( 'smartcertify_default_batch_signature_id', 0 ) );
        $signature_url = trim( (string) get_option( 'smartcertify_default_batch_signature_url', '' ) );

        if ( $signature_id > 0 ) {
            $attachment_url = wp_get_attachment_url( $signature_id );
            if ( $attachment_url ) {
                $signature_url = $attachment_url;
            }
        }

        return array(
            'batch_name'           => self::get_default_batch_name(),
            'teacher_name'         => sanitize_text_field( get_option( 'smartcertify_default_batch_teacher_name', '' ) ),
            'teacher_signature_id' => $signature_id,
            'teacher_signature_url'=> $signature_url,
        );
    }

    public static function get_current_request_url() {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
        return home_url( $request_uri );
    }

    public static function is_login_required_for_download() {
        return 1 === intval( get_option( 'smartcertify_require_login', 1 ) );
    }

    public static function get_duplicate_rule() {
        $rule = sanitize_key( get_option( 'smartcertify_duplicate_rule', 'reuse_active' ) );
        if ( ! in_array( $rule, array( 'reuse_active', 'block', 'always_new' ), true ) ) {
            $rule = 'reuse_active';
        }
        return $rule;
    }

    public static function get_download_token_lifetime_minutes() {
        $default_minutes = max( 15, intval( SmartCert_Cleanup::get_expiry_hours() ) * 60 );
        return max( 5, intval( get_option( 'smartcertify_download_token_minutes', $default_minutes ) ) );
    }

    public static function get_webhook_settings() {
        return array(
            'url'    => esc_url_raw( trim( (string) get_option( 'smartcertify_webhook_url', '' ) ) ),
            'secret' => sanitize_text_field( get_option( 'smartcertify_webhook_secret', '' ) ),
        );
    }

    public static function get_api_settings() {
        $key = sanitize_text_field( get_option( 'smartcertify_api_key', '' ) );
        if ( '' === $key ) {
            $key = wp_generate_password( 32, false, false );
            update_option( 'smartcertify_api_key', $key );
        }

        return array(
            'key'        => $key,
            'verify_url' => rest_url( 'smartcertify/v1/verify/SC-DEMO1234' ),
            'issue_url'  => rest_url( 'smartcertify/v1/issue' ),
            'health_url' => rest_url( 'smartcertify/v1/health' ),
        );
    }

    public static function get_site_logo_url() {
        $custom_logo_id = intval( get_theme_mod( 'custom_logo' ) );
        if ( $custom_logo_id > 0 ) {
            $logo = wp_get_attachment_image_url( $custom_logo_id, 'full' );
            if ( $logo ) {
                return $logo;
            }
        }

        $site_icon_id = intval( get_option( 'site_icon' ) );
        if ( $site_icon_id > 0 ) {
            $icon = wp_get_attachment_image_url( $site_icon_id, 'full' );
            if ( $icon ) {
                return $icon;
            }
        }

        return '';
    }

    public static function get_template_versions() {
        $versions = get_option( 'smartcertify_template_versions', array() );
        if ( ! is_array( $versions ) ) {
            $versions = array();
        }

        if ( empty( $versions ) ) {
            $legacy = array(
                'attachment_id' => intval( get_option( 'smartcertify_master_template_id', 0 ) ),
                'url'           => trim( (string) get_option( 'smartcertify_master_template_url', '' ) ),
            );

            if ( ! empty( $legacy['attachment_id'] ) || ! empty( $legacy['url'] ) ) {
                $version_id = 'tpl_' . substr( md5( wp_json_encode( $legacy ) ), 0, 12 );
                $versions[ $version_id ] = self::normalize_template_version(
                    array(
                        'id'            => $version_id,
                        'label'         => self::get_template_label_from_url( $legacy['url'] ),
                        'attachment_id' => $legacy['attachment_id'],
                        'url'           => $legacy['url'],
                        'created_at'    => current_time( 'mysql' ),
                        'is_active'     => 1,
                    ),
                    $version_id
                );
                update_option( 'smartcertify_template_versions', $versions );
                update_option( 'smartcertify_active_template_version', $version_id );
            }
        }

        foreach ( $versions as $version_id => $version ) {
            $versions[ $version_id ] = self::normalize_template_version( $version, $version_id );
        }

        uasort(
            $versions,
            function( $left, $right ) {
                return strcmp( (string) ( $right['created_at'] ?? '' ), (string) ( $left['created_at'] ?? '' ) );
            }
        );

        return $versions;
    }

    public static function get_active_template_version() {
        $versions = self::get_template_versions();
        $active_id = sanitize_text_field( get_option( 'smartcertify_active_template_version', '' ) );

        if ( $active_id && isset( $versions[ $active_id ] ) ) {
            return $versions[ $active_id ];
        }

        foreach ( $versions as $version ) {
            if ( ! empty( $version['is_active'] ) ) {
                return $version;
            }
        }

        return array();
    }

    public static function store_template_version( $attachment_id, $url, $label = '' ) {
        $attachment_id = intval( $attachment_id );
        $url = esc_url_raw( trim( (string) $url ) );

        if ( ! $attachment_id && '' === $url ) {
            return false;
        }

        $versions = self::get_template_versions();
        $version_id = 'tpl_' . substr( md5( $attachment_id . '|' . $url . '|' . microtime( true ) . '|' . wp_rand( 100, 999 ) ), 0, 12 );
        $versions[ $version_id ] = self::normalize_template_version(
            array(
                'id'            => $version_id,
                'label'         => sanitize_text_field( $label ) ?: self::get_template_label_from_url( $url ),
                'attachment_id' => $attachment_id,
                'url'           => $url,
                'created_at'    => current_time( 'mysql' ),
                'is_active'     => 1,
            ),
            $version_id
        );

        foreach ( $versions as $candidate_id => $version ) {
            $versions[ $candidate_id ]['is_active'] = ( $candidate_id === $version_id ) ? 1 : 0;
        }

        update_option( 'smartcertify_template_versions', $versions );
        update_option( 'smartcertify_active_template_version', $version_id );
        update_option( 'smartcertify_master_template_id', $attachment_id );
        update_option( 'smartcertify_master_template_url', $url );

        return $versions[ $version_id ];
    }

    public static function activate_template_version( $version_id ) {
        $version_id = sanitize_text_field( $version_id );
        $versions = self::get_template_versions();
        if ( empty( $version_id ) || ! isset( $versions[ $version_id ] ) ) {
            return false;
        }

        foreach ( $versions as $candidate_id => $version ) {
            $versions[ $candidate_id ]['is_active'] = ( $candidate_id === $version_id ) ? 1 : 0;
        }

        update_option( 'smartcertify_template_versions', $versions );
        update_option( 'smartcertify_active_template_version', $version_id );
        update_option( 'smartcertify_master_template_id', intval( $versions[ $version_id ]['attachment_id'] ) );
        update_option( 'smartcertify_master_template_url', esc_url_raw( $versions[ $version_id ]['url'] ) );

        return true;
    }

    private static function normalize_template_version( $version, $fallback_id = '' ) {
        $id = sanitize_text_field( $version['id'] ?? $fallback_id );
        $url = esc_url_raw( $version['url'] ?? '' );

        return array(
            'id'            => $id,
            'label'         => sanitize_text_field( $version['label'] ?? self::get_template_label_from_url( $url ) ),
            'attachment_id' => intval( $version['attachment_id'] ?? 0 ),
            'url'           => $url,
            'created_at'    => sanitize_text_field( $version['created_at'] ?? current_time( 'mysql' ) ),
            'is_active'     => ! empty( $version['is_active'] ) ? 1 : 0,
        );
    }

    private static function get_template_label_from_url( $url ) {
        $path = wp_parse_url( (string) $url, PHP_URL_PATH );
        $file = $path ? basename( $path ) : 'Master Template';
        return sanitize_text_field( $file ?: 'Master Template' );
    }

    public static function get_delivery_settings() {
        $email_subject = trim( (string) get_option( 'smartcertify_email_subject', 'Your certificate is ready' ) );
        $email_message = trim( (string) get_option( 'smartcertify_email_message', "Hello {student_name},\n\nYour certificate for {class_name} is ready.\nDownload: {certificate_url}\nVerify: {verification_url}\n\nRegards,\n{site_name}" ) );
        $whatsapp_message = trim( (string) get_option( 'smartcertify_whatsapp_message', 'Hello {student_name}, your certificate for {class_name} is ready. Download: {certificate_url} Verify: {verification_url}' ) );

        return array(
            'auto_email'        => intval( get_option( 'smartcertify_auto_email_delivery', 0 ) ),
            'auto_whatsapp'     => intval( get_option( 'smartcertify_auto_whatsapp_delivery', 0 ) ),
            'email_subject'     => '' !== $email_subject ? $email_subject : 'Your certificate is ready',
            'email_message'     => '' !== $email_message ? $email_message : "Hello {student_name},\n\nYour certificate for {class_name} is ready.\nDownload: {certificate_url}\nVerify: {verification_url}\n\nRegards,\n{site_name}",
            'whatsapp_message'  => '' !== $whatsapp_message ? $whatsapp_message : 'Hello {student_name}, your certificate for {class_name} is ready. Download: {certificate_url} Verify: {verification_url}',
        );
    }

    public static function build_email_template_html( $subject, $message, $data = array() ) {
        $subject = esc_html( (string) $subject );
        $message = nl2br( esc_html( (string) $message ) );
        $certificate_url = esc_url( $data['certificate_url'] ?? '' );
        $verification_url = esc_url( $data['verification_url'] ?? '' );
        $logo = esc_url( self::get_site_logo_url() );
        $site_name = esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
        $logo_markup = '';

        if ( $logo ) {
            $logo_markup = '<div style="margin-bottom:20px;"><img src="' . $logo . '" alt="' . $site_name . '" style="max-height:64px;width:auto;display:block;margin:0 auto;" /></div>';
        }

        $buttons = '';
        if ( $certificate_url ) {
            $buttons .= '<a href="' . $certificate_url . '" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:999px;font-weight:600;margin:8px 6px;">Download Certificate</a>';
        }
        if ( $verification_url ) {
            $buttons .= '<a href="' . $verification_url . '" style="display:inline-block;background:#f1f5f9;color:#0f172a;text-decoration:none;padding:12px 22px;border-radius:999px;font-weight:600;margin:8px 6px;">Verify Certificate</a>';
        }

        return '<!doctype html><html><body style="margin:0;background:#f8fafc;padding:32px 16px;font-family:Arial,sans-serif;color:#0f172a;">'
            . '<div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;border:1px solid #e2e8f0;">'
            . '<div style="background:linear-gradient(135deg,#dbeafe 0%,#eff6ff 100%);padding:28px 32px;text-align:center;">'
            . $logo_markup
            . '<div style="font-size:13px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#1d4ed8;">Certificate Delivery</div>'
            . '<h1 style="margin:10px 0 0;font-size:28px;line-height:1.2;color:#0f172a;">' . $subject . '</h1>'
            . '</div>'
            . '<div style="padding:32px;">'
            . '<div style="font-size:15px;line-height:1.8;color:#334155;">' . $message . '</div>'
            . ( $buttons ? '<div style="margin-top:28px;text-align:center;">' . $buttons . '</div>' : '' )
            . '<div style="margin-top:28px;padding:18px 20px;background:#f8fafc;border-radius:18px;font-size:13px;line-height:1.7;color:#475569;">'
            . 'This email was sent by ' . $site_name . ' using your WordPress mail setup. If your site uses an SMTP plugin, SmartCertify automatically sends through that same mailer.'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</body></html>';
    }

    public static function replace_delivery_tokens( $template, $data = array() ) {
        $replacements = array(
            '{student_name}'    => sanitize_text_field( $data['student_name'] ?? '' ),
            '{class_name}'      => sanitize_text_field( $data['class_name'] ?? '' ),
            '{batch_name}'      => sanitize_text_field( $data['batch_name'] ?? '' ),
            '{certificate_url}' => esc_url_raw( $data['certificate_url'] ?? '' ),
            '{verification_url}'=> esc_url_raw( $data['verification_url'] ?? '' ),
            '{serial}'          => sanitize_text_field( $data['serial'] ?? '' ),
            '{site_name}'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
        );

        return strtr( (string) $template, $replacements );
    }

    public static function build_whatsapp_url( $phone, $message ) {
        $digits = preg_replace( '/\D+/', '', (string) $phone );
        if ( '' === $digits ) {
            return '';
        }

        return 'https://wa.me/' . rawurlencode( $digits ) . '?text=' . rawurlencode( (string) $message );
    }

    public static function get_verify_url( $serial ) {
        return add_query_arg(
            array( 'smartcertify_verify' => rawurlencode( sanitize_text_field( $serial ) ) ),
            home_url( '/' )
        );
    }

    public static function get_qr_payload( $serial, $verification_url = '' ) {
        $serial = strtoupper( sanitize_text_field( $serial ) );
        if ( preg_match( '/^[0-9A-Z $%*+\-.\/:]{1,25}$/', $serial ) ) {
            return $serial;
        }

        return sanitize_text_field( $verification_url );
    }

    public static function get_download_audience_marker( $args = array() ) {
        $args = wp_parse_args(
            $args,
            array(
                'user_id' => 0,
                'email'   => '',
            )
        );

        $user_id = intval( $args['user_id'] );
        if ( $user_id > 0 ) {
            return 'u:' . $user_id;
        }

        $email = sanitize_email( $args['email'] );
        if ( '' !== $email ) {
            return 'e:' . md5( strtolower( $email ) );
        }

        return 'login';
    }

    public static function create_download_token( $file_name, $expires, $audience ) {
        $payload = sanitize_file_name( $file_name ) . '|' . intval( $expires ) . '|' . sanitize_text_field( $audience );
        return hash_hmac( 'sha256', $payload, wp_salt( 'smartcertify_download' ) );
    }

    public static function validate_download_token( $file_name, $expires, $audience, $token ) {
        $expires = intval( $expires );
        if ( $expires < time() ) {
            return false;
        }

        $expected = self::create_download_token( $file_name, $expires, $audience );
        return hash_equals( $expected, (string) $token );
    }

    public static function current_user_matches_download_audience( $audience ) {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        if ( ! is_user_logged_in() ) {
            return false;
        }

        $audience = (string) $audience;
        if ( 'login' === $audience || '' === $audience ) {
            return true;
        }

        if ( 0 === strpos( $audience, 'u:' ) ) {
            return intval( substr( $audience, 2 ) ) === get_current_user_id();
        }

        if ( 0 === strpos( $audience, 'e:' ) ) {
            $user = wp_get_current_user();
            $hash = md5( strtolower( sanitize_email( $user->user_email ) ) );
            return hash_equals( substr( $audience, 2 ), $hash );
        }

        return false;
    }

    public static function get_public_certificate_url( $file_name, $args = array() ) {
        $args = wp_parse_args(
            $args,
            array(
                'user_id'  => 0,
                'email'    => '',
                'expires'  => 0,
                'audience' => '',
            )
        );

        $expires = intval( $args['expires'] );
        if ( $expires <= time() ) {
            $expires = time() + ( self::get_download_token_lifetime_minutes() * MINUTE_IN_SECONDS );
        }

        $audience = sanitize_text_field( $args['audience'] );
        if ( '' === $audience ) {
            $audience = self::get_download_audience_marker(
                array(
                    'user_id' => intval( $args['user_id'] ),
                    'email'   => sanitize_email( $args['email'] ),
                )
            );
        }

        return add_query_arg(
            array(
                'smartcertify_pdf' => 1,
                'file'             => sanitize_file_name( $file_name ),
                'expires'          => $expires,
                'aud'              => $audience,
                'token'            => self::create_download_token( $file_name, $expires, $audience ),
            ),
            home_url( '/' )
        );
    }

    public static function get_export_option_names() {
        global $wpdb;

        $rows = $wpdb->get_col(
            "SELECT option_name
             FROM {$wpdb->options}
             WHERE option_name LIKE 'smartcertify_%'
             ORDER BY option_name ASC"
        );

        return array_map( 'sanitize_text_field', $rows ?: array() );
    }

    public static function compare_value( $a, $b ) {
        return trim( (string) $a ) === trim( (string) $b );
    }
}
