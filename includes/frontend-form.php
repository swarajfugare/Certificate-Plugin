<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartCert_Frontend {
    public function __construct() {
        add_shortcode( 'smartcertify_form', array( $this, 'render_form' ) );
        add_shortcode( 'smartcertify_verify', array( $this, 'render_verify_shortcode' ) );
        add_action( 'wp_ajax_smartcertify_get_certificate_ajax', array( $this, 'ajax_get_certificate' ) );
        add_action( 'wp_ajax_nopriv_smartcertify_get_certificate_ajax', array( $this, 'ajax_get_certificate' ) );
        add_action( 'wp_ajax_smartcertify_get_batches', array( $this, 'ajax_get_batches' ) );
        add_action( 'wp_ajax_nopriv_smartcertify_get_batches', array( $this, 'ajax_get_batches' ) );
        add_action( 'wp_ajax_smartcertify_refresh_nonce', array( $this, 'ajax_refresh_nonce' ) );
        add_action( 'wp_ajax_nopriv_smartcertify_refresh_nonce', array( $this, 'ajax_refresh_nonce' ) );
        add_action( 'init', array( $this, 'handle_pdf_request' ) );
        add_action( 'template_redirect', array( $this, 'maybe_render_public_verify_page' ) );
        add_filter( 'document_title_parts', array( $this, 'filter_verify_document_title' ) );
    }

    public function render_form() {
        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }
        if ( ! headers_sent() ) {
            nocache_headers();
        }

        $classes = apply_filters( 'smartcertify_allowed_classes', SmartCert_Helpers::get_classes() );
        $verify_page_url = esc_url( trim( (string) get_option( 'smartcertify_verify_button_url', '' ) ) );
        $require_login = SmartCert_Helpers::is_login_required_for_download();
        $current_user = wp_get_current_user();
        $is_logged_in = $current_user && $current_user->exists();
        $default_name = $is_logged_in ? $current_user->display_name : '';
        $login_form = '';

        if ( $require_login && ! $is_logged_in ) {
            $login_form = wp_login_form(
                array(
                    'echo'           => false,
                    'remember'       => true,
                    'redirect'       => SmartCert_Helpers::get_current_request_url(),
                    'label_username' => 'Email / Username',
                    'label_password' => 'Password',
                    'label_remember' => 'Remember me',
                    'label_log_in'   => 'Login To Continue',
                )
            );
        }

        ob_start();
        do_action( 'smartcertify_before_form_render' );
        ?>
        <?php if ( $require_login && ! $is_logged_in ) : ?>
            <div class="sc-login-overlay" role="dialog" aria-modal="true" aria-labelledby="sc-login-title">
                <div class="sc-login-card">
                    <div class="sc-login-badge">Account Required</div>
                    <h2 id="sc-login-title">Login Before Downloading Your Certificate</h2>
                    <p>For security, certificate generation and download are now linked to the student account. After login, SmartCertify will use your account email for certificate delivery.</p>
                    <div class="sc-login-form">
                        <?php echo $login_form; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="sc-form-wrapper<?php echo ( $require_login && ! $is_logged_in ) ? ' sc-form-wrapper--locked' : ''; ?>">
            <div class="sc-instruction-section">
                <h2 class="sc-instruction-title">Certificate Download</h2>
                <p class="sc-instruction-intro">Select your class and batch, enter your name and code, then generate your certificate instantly.</p>
                <ol class="sc-instruction-list">
                    <li>Select your class.</li>
                    <li>Select your batch.</li>
                    <li>Enter your full name exactly as provided.</li>
                    <li>Enter your code and generate the certificate.</li>
                </ol>
                <p class="sc-instruction-footer">Every certificate now includes QR-based verification and can be sent to your account email automatically.</p>
                <?php if ( $verify_page_url ) : ?>
                    <p class="sc-instruction-action">
                        <a class="sc-btn sc-btn-secondary" href="<?php echo esc_url( $verify_page_url ); ?>">Verify Certificate</a>
                    </p>
                <?php endif; ?>
            </div>

            <div class="sc-form-section">
                <div class="sc-form-header">
                    <div class="sc-form-header-icon">🎓</div>
                    <h2 class="sc-form-title">Get Your Certificate</h2>
                </div>

                <form id="smartcertify_form" class="sc-elegant-form" method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
                    <?php wp_nonce_field( 'smartcertify_frontend' ); ?>
                    <input type="hidden" name="action" value="smartcertify_get_certificate_ajax" />

                    <div class="sc-form-group">
                        <label class="sc-form-label">Class</label>
                        <select name="class_id" id="sc_class_id" class="sc-form-input" required>
                            <option value="">Select your class</option>
                            <?php foreach ( $classes as $class ) : ?>
                                <option value="<?php echo esc_attr( $class->id ); ?>">
                                    <?php echo esc_html( $class->class_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sc-form-group sc-hidden" id="sc_batch_group">
                        <label class="sc-form-label">Batch</label>
                        <select name="batch_id" id="sc_batch_id" class="sc-form-input" required disabled>
                            <option value="">Select batch</option>
                        </select>
                    </div>

                    <div id="sc_form_details" class="sc-hidden">
                        <div class="sc-form-group">
                            <label class="sc-form-label">Your Name</label>
                            <input type="text" name="student_name" class="sc-form-input" id="sc_student_name" value="<?php echo esc_attr( $default_name ); ?>" placeholder="Enter your full name" required />
                        </div>

                        <div class="sc-form-group">
                            <label class="sc-form-label">Certificate Code</label>
                            <input type="text" name="code" class="sc-form-input" maxlength="20" placeholder="Enter your code" required />
                        </div>

                        <button class="sc-btn sc-btn-primary sc-btn-full" type="submit">Get Certificate</button>
                    </div>
                </form>

                <div id="sc_alert" class="sc-form-message"></div>

                <div id="sc_success_container" class="sc-success-message" style="display:none;">
                    <div class="sc-success-icon">
                        <svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </div>
                    <h3>Certificate Ready!</h3>
                    <p>Your certificate has been generated successfully.</p>
                    <p id="sc_serial_note" class="sc-meta-note"></p>

                    <div class="sc-action-buttons">
                        <a id="sc_download_button" href="#" download="certificate.pdf" class="sc-btn sc-btn-primary">Download PDF</a>
                        <a id="sc_view_button" href="#" target="_blank" class="sc-btn sc-btn-secondary">View Online</a>
                    </div>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    public function render_verify_shortcode() {
        $serial = sanitize_text_field( $_REQUEST['smartcertify_serial'] ?? '' );
        $name = sanitize_text_field( $_REQUEST['smartcertify_student_name'] ?? '' );

        ob_start();
        ?>
        <div class="sc-verify-wrapper">
            <div class="sc-verify-card">
                <h2 class="sc-verify-title">Verify Certificate</h2>
                <p class="sc-verify-copy">Enter the certificate serial number to check whether the certificate is valid.</p>

                <?php
                echo SmartCert_Helpers::get_qr_scanner_markup(
                    array(
                        'target_input'    => 'input[name="smartcertify_serial"]',
                        'submit_on_scan'  => true,
                        'redirect_on_url' => true,
                        'title'           => 'Scan Certificate QR',
                        'copy'            => 'Use your phone or laptop camera, or upload a QR image to verify the certificate instantly.',
                    )
                );
                ?>

                <form method="get" class="sc-verify-form">
                    <div class="sc-form-group">
                        <label class="sc-form-label">Serial Number</label>
                        <input type="text" class="sc-form-input" name="smartcertify_serial" value="<?php echo esc_attr( $serial ); ?>" placeholder="Enter serial number" required />
                    </div>
                    <div class="sc-form-group">
                        <label class="sc-form-label">Student Name (optional)</label>
                        <input type="text" class="sc-form-input" name="smartcertify_student_name" value="<?php echo esc_attr( $name ); ?>" placeholder="Enter student name" />
                    </div>
                    <button class="sc-btn sc-btn-primary" type="submit">Verify</button>
                </form>

                <?php
                if ( $serial ) {
                    echo $this->build_verification_markup( $serial, $name );
                }
                ?>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    public function ajax_refresh_nonce() {
        wp_send_json_success(
            array(
                'nonce' => wp_create_nonce( 'smartcertify_frontend' ),
            )
        );
    }

    public function ajax_get_batches() {
        $class_id = intval( $_REQUEST['class_id'] ?? 0 );
        $class = SmartCert_Helpers::get_class( $class_id );

        if ( ! $class ) {
            wp_send_json_error( array( 'message' => 'Invalid class selected.' ) );
        }

        $batches = SmartCert_Helpers::get_batches( $class_id );
        $payload = array();

        foreach ( $batches as $batch ) {
            $payload[] = array(
                'id'   => intval( $batch->id ),
                'name' => $batch->batch_name,
            );
        }

        wp_send_json_success(
            array(
                'class'   => $class->class_name,
                'batches' => $payload,
            )
        );
    }

    public function ajax_get_certificate() {
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'smartcertify_frontend' ) ) {
            wp_send_json_error( 'Session expired. Please try again.' );
        }

        global $wpdb;
        $current_user = wp_get_current_user();
        $require_login = SmartCert_Helpers::is_login_required_for_download();
        $user_id = ( $current_user && $current_user->exists() ) ? intval( $current_user->ID ) : 0;
        $account_email = ( $current_user && $current_user->exists() ) ? sanitize_email( $current_user->user_email ) : '';

        if ( $require_login && ! $user_id ) {
            wp_send_json_error( 'Please log in to download your certificate.' );
        }

        $class_id = intval( $_POST['class_id'] ?? 0 );
        $batch_id = intval( $_POST['batch_id'] ?? 0 );
        $student = sanitize_text_field( $_POST['student_name'] ?? '' );
        $code = SmartCert_Helpers::sanitize_code( $_POST['code'] ?? '' );

        $class = SmartCert_Helpers::get_class( $class_id );
        if ( ! $class ) {
            wp_send_json_error( 'Selected class not found.' );
        }

        $batch = SmartCert_Helpers::get_batch( $batch_id );
        if ( ! $batch || intval( $batch->class_id ) !== intval( $class->id ) ) {
            wp_send_json_error( 'Please select a valid batch.' );
        }

        $code_row = $this->find_code_row( $class, $batch_id, $code );
        if ( ! $code_row ) {
            wp_send_json_error( 'Invalid code or class.' );
        }

        if ( 'active' !== strtolower( (string) ( $code_row['status'] ?? 'active' ) ) ) {
            wp_send_json_error( 'This code is not active right now.' );
        }

        if ( ! empty( $code_row['student_name'] ) && ! SmartCert_Helpers::names_match( $code_row['student_name'], $student ) ) {
            wp_send_json_error( 'This code is assigned to another student name.' );
        }

        if ( $require_login && ! empty( $code_row['student_email'] ) && ! SmartCert_Helpers::compare_value( strtolower( $code_row['student_email'] ), strtolower( $account_email ) ) ) {
            wp_send_json_error( 'This code is assigned to another account email.' );
        }

        $download_count = intval( $code_row['download_count'] ?? 0 );
        $download_limit = intval( $code_row['download_limit'] ?? 3 );
        $download_limit = apply_filters( 'smartcertify_download_limit', $download_limit, $class->class_name, $code );

        if ( $download_limit > 0 && $download_count >= $download_limit ) {
            do_action( 'smartcertify_download_limit_exceeded', $class->class_name, $code );
            wp_send_json_error( 'Download limit exceeded for this code.' );
        }

        $issued = SmartCert_Service::issue_certificate(
            array(
                'class'                    => $class,
                'batch'                    => $batch,
                'student_name'             => $student,
                'student_email'            => $account_email ?: ( $code_row['student_email'] ?? '' ),
                'student_phone'            => $code_row['student_phone'] ?? '',
                'user_id'                  => $user_id,
                'code'                     => $code,
                'teacher_name'             => $batch->teacher_name,
                'teacher_signature_id'     => intval( $batch->teacher_signature_id ),
                'teacher_signature_url'    => $batch->teacher_signature_url,
                'status'                   => 'valid',
                'increment_download_count' => true,
                'code_row_id'              => intval( $code_row['id'] ),
                'sync_code_contact_fields' => true,
                'trigger_auto_delivery'    => true,
            )
        );

        if ( is_wp_error( $issued ) ) {
            wp_send_json_error( $issued->get_error_message() );
        }

        wp_send_json_success(
            array(
                'url'             => esc_url_raw( $issued['url'] ),
                'serial'          => $issued['serial'],
                'generated_at'    => $issued['generated_at'],
                'verification_url'=> esc_url_raw( $issued['verification_url'] ),
                'download_count'  => $download_count + 1,
                'download_limit'  => $download_limit,
                'delivery'        => $issued['delivery'],
                'reused'          => ! empty( $issued['reused'] ),
            )
        );
    }

    private function find_code_row( $class, $batch_id, $code ) {
        global $wpdb;

        $table = $wpdb->prefix . 'smartcertify_codes';
        $sql = $wpdb->prepare(
            "SELECT * FROM $table
             WHERE code = %s
             AND (class_id = %d OR (class_id = 0 AND class_name = %s))
             AND batch_id = %d
             ORDER BY id DESC
             LIMIT 1",
            $code,
            intval( $class->id ),
            $class->class_name,
            intval( $batch_id )
        );

        return $wpdb->get_row( $sql, ARRAY_A );
    }

    public function handle_pdf_request() {
        if ( ! isset( $_GET['smartcertify_pdf'] ) ) {
            return;
        }

        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }
        ini_set( 'output_buffering', 'off' );

        $pdf_file = sanitize_file_name( $_GET['file'] ?? '' );
        if ( ! $pdf_file ) {
            wp_die( 'PDF file not specified', '', array( 'response' => 400 ) );
        }

        $expires = intval( $_GET['expires'] ?? 0 );
        $audience = sanitize_text_field( wp_unslash( $_GET['aud'] ?? '' ) );
        $token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );

        if ( ! SmartCert_Helpers::validate_download_token( $pdf_file, $expires, $audience, $token ) ) {
            wp_die( 'This download link is invalid or has expired.', '', array( 'response' => 403 ) );
        }

        if ( SmartCert_Helpers::is_login_required_for_download() ) {
            if ( ! is_user_logged_in() ) {
                auth_redirect();
                exit;
            }

            if ( ! SmartCert_Helpers::current_user_matches_download_audience( $audience ) ) {
                wp_die( 'You do not have permission to download this certificate.', '', array( 'response' => 403 ) );
            }
        }

        $cert = SmartCert_Cleanup::get_valid_certificate( $pdf_file );
        if ( ! $cert ) {
            global $wpdb;
            $stored = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}smartcertify_certificates WHERE file_name = %s ORDER BY id DESC LIMIT 1",
                    $pdf_file
                ),
                ARRAY_A
            );

            if ( $stored ) {
                $stored = SmartCert_Cleanup::sync_certificate_status( $stored );
                $status = $stored['status'] ?? 'expired';
                $message = 'Certificate has expired. Please generate a new certificate.';
                $title = 'Certificate Expired';

                if ( 'revoked' === $status ) {
                    $message = 'This certificate has been revoked and is no longer available.';
                    $title = 'Certificate Revoked';
                } elseif ( 'renewed' === $status ) {
                    $message = 'This certificate has been replaced by a newer certificate record.';
                    $title = 'Certificate Reissued';
                } elseif ( 'deleted' === $status ) {
                    $message = 'This certificate file is no longer available.';
                    $title = 'Certificate Deleted';
                }

                wp_die( $message, $title, array( 'response' => 410 ) );
            }

            wp_die( 'Certificate has expired. Please generate a new certificate.', 'Certificate Expired', array( 'response' => 410 ) );
        }

        $uploads = wp_upload_dir();
        $pdf_path = trailingslashit( $uploads['basedir'] ) . 'smartcertify_public/' . $pdf_file;

        $real_path = realpath( $pdf_path );
        $real_base = realpath( trailingslashit( $uploads['basedir'] ) . 'smartcertify_public' );

        if ( ! $real_path || ! $real_base || strpos( $real_path, $real_base ) !== 0 ) {
            wp_die( 'Invalid PDF file path', '', array( 'response' => 403 ) );
        }

        if ( ! file_exists( $pdf_path ) || ! is_file( $pdf_path ) ) {
            wp_die( 'PDF file not found', '', array( 'response' => 404 ) );
        }

        $file_size = filesize( $pdf_path );
        if ( ! $file_size ) {
            wp_die( 'Cannot determine file size', '', array( 'response' => 500 ) );
        }

        $start = 0;
        $end = $file_size - 1;
        $content_length = $file_size;

        if ( isset( $_SERVER['HTTP_RANGE'] ) && preg_match( '/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches ) ) {
            $start = intval( $matches[1] );
            $end = '' === $matches[2] ? $file_size - 1 : intval( $matches[2] );

            if ( $start > $end || $start >= $file_size || $end >= $file_size ) {
                header( 'HTTP/1.1 416 Requested Range Not Satisfiable' );
                header( 'Content-Range: bytes */' . $file_size );
                exit;
            }

            $content_length = $end - $start + 1;
            header( 'HTTP/1.1 206 Partial Content' );
            header( 'Content-Range: bytes ' . $start . '-' . $end . '/' . $file_size );
        } else {
            header( 'HTTP/1.1 200 OK' );
        }

        error_reporting( E_ALL );
        ini_set( 'display_errors', 'off' );
        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: inline; filename="certificate.pdf"' );
        header( 'Content-Length: ' . $content_length );
        header( 'Accept-Ranges: bytes' );
        header( 'X-Frame-Options: SAMEORIGIN' );
        header( 'Access-Control-Allow-Origin: *' );
        header( 'Access-Control-Allow-Methods: GET, HEAD, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Range' );
        header( 'X-Litespeed-Cache-Control: no-cache' );
        header( 'Cache-Key: off' );

        if ( isset( $_SERVER['HTTP_RANGE'] ) ) {
            $handle = fopen( $pdf_path, 'rb' );
            if ( $handle ) {
                fseek( $handle, $start );
                $bytes_sent = 0;

                while ( ! feof( $handle ) && $bytes_sent < $content_length ) {
                    $chunk = fread( $handle, min( 1024 * 1024, $content_length - $bytes_sent ) );
                    if ( false === $chunk ) {
                        break;
                    }
                    echo $chunk;
                    $bytes_sent += strlen( $chunk );
                    flush();
                }

                fclose( $handle );
            }
        } else {
            readfile( $pdf_path );
        }

        exit;
    }

    public function maybe_render_public_verify_page() {
        if ( is_admin() || empty( $_GET['smartcertify_verify'] ) ) {
            return;
        }

        $serial = sanitize_text_field( wp_unslash( $_GET['smartcertify_verify'] ) );
        $markup = $this->build_verification_markup( $serial );

        status_header( 200 );
        nocache_headers();

        get_header();
        ?>
        <main id="primary" class="site-main smartcertify-verify-page">
            <div class="sc-verify-wrapper">
                <div class="sc-verify-card">
                    <h1 class="sc-verify-title">Certificate Verification</h1>
                    <p class="sc-verify-copy">Scan result from the certificate QR code. You can share this page directly as proof of verification.</p>
                    <?php echo $markup; ?>
                </div>
            </div>
        </main>
        <?php
        get_footer();
        exit;
    }

    public function filter_verify_document_title( $parts ) {
        if ( is_admin() || empty( $_GET['smartcertify_verify'] ) ) {
            return $parts;
        }

        $parts['title'] = 'Certificate Verification';
        return $parts;
    }

    private function build_verification_markup( $serial, $student_name = '' ) {
        $result = SmartCert_Service::verify_certificate( $serial, $student_name );
        $certificate = $result['certificate'] ?? array();
        $status = $result['status'] ?? 'not_found';
        $class = in_array( $status, array( 'valid' ), true ) ? 'sc-verify-success' : ( in_array( $status, array( 'expired', 'renewed' ), true ) ? 'sc-verify-warning' : 'sc-verify-error' );

        $html = '<div class="sc-verify-result ' . esc_attr( $class ) . '"><h3>' . esc_html( $result['title'] ?? 'Certificate Verification' ) . '</h3><p>' . esc_html( $result['message'] ?? '' ) . '</p>';

        if ( ! empty( $certificate ) ) {
            $details = array(
                'Student'       => $certificate['student_name'] ?? '',
                'Class'         => $certificate['class_name'] ?? '',
                'Batch'         => ! empty( $certificate['batch_name'] ) ? $certificate['batch_name'] : 'N/A',
                'Teacher'       => ! empty( $certificate['teacher_name'] ) ? $certificate['teacher_name'] : 'N/A',
                'Serial'        => $certificate['serial'] ?? '',
                'Issued'        => $certificate['generated_at'] ?? '',
                'Valid Until'   => ! empty( $certificate['certificate_expires_at'] ) ? $certificate['certificate_expires_at'] : 'N/A',
                'Status'        => ucfirst( $status ),
            );

            if ( ! empty( $certificate['revoke_reason'] ) && 'revoked' === $status ) {
                $details['Reason'] = $certificate['revoke_reason'];
            }

            if ( ! empty( $result['replacement'] ) ) {
                $details['Latest Serial'] = $result['replacement']['serial'] ?? '';
            }

            $html .= '<ul class="sc-verify-list">';
            foreach ( $details as $label => $value ) {
                $html .= '<li><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</div>';

        return $html;
    }
}

new SmartCert_Frontend();
