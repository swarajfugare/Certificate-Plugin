<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartCert_Service {
    public static function issue_certificate( $args = array() ) {
        $args = wp_parse_args(
            $args,
            array(
                'class'                    => null,
                'class_id'                 => 0,
                'batch'                    => null,
                'batch_id'                 => 0,
                'student_name'             => '',
                'student_email'            => '',
                'student_phone'            => '',
                'user_id'                  => 0,
                'code'                     => '',
                'teacher_name'             => '',
                'teacher_signature_id'     => 0,
                'teacher_signature_url'    => '',
                'status'                   => 'valid',
                'serial'                   => '',
                'verification_url'         => '',
                'generated_label'          => '',
                'generated_at'             => '',
                'expires_at'               => '',
                'certificate_expires_at'   => '',
                'renewed_from_id'          => 0,
                'increment_download_count' => false,
                'code_row_id'              => 0,
                'sync_code_contact_fields' => false,
                'trigger_auto_delivery'    => false,
                'send_email'               => false,
                'send_whatsapp'            => false,
                'duplicate_rule'           => '',
                'ignore_duplicate_rule'    => false,
                'template_version'         => '',
            )
        );

        $class = is_object( $args['class'] ) ? $args['class'] : SmartCert_Helpers::get_class( $args['class_id'] );
        if ( ! $class ) {
            return new WP_Error( 'invalid_class', 'Selected class not found.' );
        }

        $batch = is_object( $args['batch'] ) ? $args['batch'] : SmartCert_Helpers::get_batch( $args['batch_id'] );
        if ( ! $batch || intval( $batch->class_id ) !== intval( $class->id ) ) {
            return new WP_Error( 'invalid_batch', 'Please select a valid batch.' );
        }

        $student_name = sanitize_text_field( $args['student_name'] );
        if ( '' === $student_name ) {
            return new WP_Error( 'missing_student', 'Student name is required.' );
        }

        $code = SmartCert_Helpers::sanitize_code( $args['code'] );
        if ( '' === $code ) {
            return new WP_Error( 'missing_code', 'Certificate code is required.' );
        }

        $student_email = sanitize_email( $args['student_email'] );
        $student_phone = sanitize_text_field( $args['student_phone'] );
        $user_id = intval( $args['user_id'] );

        $template_reference = SmartCert_Helpers::resolve_template_reference( $class );
        if ( empty( $template_reference['attachment_id'] ) && empty( $template_reference['url'] ) ) {
            return new WP_Error( 'missing_template', 'Template not found. Please contact the administrator.' );
        }

        $template_version = sanitize_text_field( $args['template_version'] );
        if ( '' === $template_version ) {
            $template_version = sanitize_text_field( $template_reference['version_id'] ?? '' );
        }

        if ( empty( $args['ignore_duplicate_rule'] ) ) {
            $duplicate = self::find_existing_duplicate_certificate(
                array(
                    'class_id'      => intval( $class->id ),
                    'batch_id'      => intval( $batch->id ),
                    'code'          => $code,
                    'student_name'  => $student_name,
                    'student_email' => $student_email,
                    'user_id'       => $user_id,
                )
            );

            if ( ! empty( $duplicate ) ) {
                $duplicate_rule = sanitize_key( $args['duplicate_rule'] ?: SmartCert_Helpers::get_duplicate_rule() );
                if ( 'block' === $duplicate_rule ) {
                    return new WP_Error( 'duplicate_certificate', 'A valid certificate already exists for this student, class, batch, and code.' );
                }

                if ( 'reuse_active' === $duplicate_rule ) {
                    $needs_email_update = $student_email && empty( $duplicate['student_email'] );
                    $needs_user_update = $user_id > 0 && empty( $duplicate['user_id'] );

                    if ( ! empty( $duplicate['id'] ) && ( $student_email || $user_id > 0 ) ) {
                        global $wpdb;
                        $update = array();
                        $formats = array();

                        if ( $needs_email_update ) {
                            $update['student_email'] = $student_email;
                            $formats[] = '%s';
                        }

                        if ( $needs_user_update ) {
                            $update['user_id'] = $user_id;
                            $formats[] = '%d';
                        }

                        if ( $update ) {
                            $wpdb->update(
                                $wpdb->prefix . 'smartcertify_certificates',
                                $update,
                                array( 'id' => intval( $duplicate['id'] ) ),
                                $formats,
                                array( '%d' )
                            );
                            $duplicate = SmartCert_Cleanup::get_certificate_by_id( intval( $duplicate['id'] ) );
                        }
                    }

                    if ( $needs_email_update ) {
                        $duplicate['student_email'] = $student_email;
                    }
                    if ( $needs_user_update ) {
                        $duplicate['user_id'] = $user_id;
                    }

                    if ( ! empty( $args['increment_download_count'] ) ) {
                        self::increment_code_download_count( $args, $class, $batch, $code );
                    }

                    if ( ! empty( $args['sync_code_contact_fields'] ) ) {
                        self::sync_code_contact_fields(
                            array_merge(
                                $args,
                                array(
                                    'student_email' => $student_email,
                                    'student_phone' => $student_phone,
                                )
                            ),
                            $code
                        );
                    }

                    $duplicate_urls = self::build_certificate_urls( $duplicate, $user_id, $student_email );
                    $delivery = self::maybe_process_delivery(
                        $duplicate,
                        $duplicate_urls,
                        array(
                            'trigger_auto_delivery' => ! empty( $args['trigger_auto_delivery'] ),
                            'send_email'            => ! empty( $args['send_email'] ),
                            'send_whatsapp'         => ! empty( $args['send_whatsapp'] ),
                        )
                    );

                    SmartCert_Logger::log(
                        $student_name,
                        $class->class_name,
                        $code,
                        'reused',
                        $duplicate['serial'],
                        array(
                            'certificate_id'   => intval( $duplicate['id'] ),
                            'user_id'          => $user_id ?: intval( $duplicate['user_id'] ?? 0 ),
                            'class_id'         => intval( $class->id ),
                            'batch_id'         => intval( $batch->id ),
                            'batch_name'       => $batch->batch_name,
                            'teacher_name'     => $duplicate['teacher_name'] ?? '',
                            'template_version' => $duplicate['template_version'] ?? $template_version,
                            'verification_url' => $duplicate_urls['verification_url'],
                            'status'           => $duplicate['status'] ?? 'valid',
                        )
                    );

                    if ( class_exists( 'SmartCert_Integrations' ) ) {
                        SmartCert_Integrations::dispatch_webhook( 'certificate.reused', $duplicate, $duplicate_urls );
                    }

                    return array(
                        'certificate_id'   => intval( $duplicate['id'] ),
                        'certificate'      => $duplicate,
                        'file_name'        => $duplicate['file_name'],
                        'file_path'        => $duplicate['file_path'],
                        'url'              => $duplicate_urls['certificate_url'],
                        'serial'           => $duplicate['serial'],
                        'generated_at'     => $duplicate['generated_at'],
                        'verification_url' => $duplicate_urls['verification_url'],
                        'delivery'         => $delivery,
                        'class'            => $class,
                        'batch'            => $batch,
                        'reused'           => true,
                    );
                }
            }
        }

        $serial = sanitize_text_field( $args['serial'] );
        if ( '' === $serial ) {
            $serial = self::generate_serial( $class->class_name, $batch->batch_name, $student_name );
        }

        $generated_label = sanitize_text_field( $args['generated_label'] );
        if ( '' === $generated_label ) {
            $generated_label = self::get_formatted_timestamp();
        }

        $verification_url = esc_url_raw( $args['verification_url'] );
        if ( '' === $verification_url ) {
            $verification_url = SmartCert_Helpers::get_verify_url( $serial );
        }

        $qr_payload = SmartCert_Helpers::get_qr_payload( $serial, $verification_url );

        $teacher_name = sanitize_text_field( $args['teacher_name'] );
        if ( '' === $teacher_name ) {
            $teacher_name = sanitize_text_field( $batch->teacher_name ?? '' );
        }

        $generator = new SmartCert_Generator();
        $pdf_path = $generator->generate_certificate(
            $template_reference,
            $student_name,
            $serial,
            $generated_label,
            array(
                'class_name'            => $class->class_name,
                'class_id'              => intval( $class->id ),
                'batch_name'            => $batch->batch_name,
                'batch_id'              => intval( $batch->id ),
                'teacher_name'          => $teacher_name,
                'teacher_signature_id'  => intval( $args['teacher_signature_id'] ?: ( $batch->teacher_signature_id ?? 0 ) ),
                'teacher_signature_url' => $args['teacher_signature_url'] ?: ( $batch->teacher_signature_url ?? '' ),
                'qr_payload'            => $qr_payload,
                'verification_url'      => $verification_url,
            )
        );

        if ( ! $pdf_path || ! file_exists( $pdf_path ) ) {
            return new WP_Error( 'generation_failed', 'Failed to generate certificate. Please try again.' );
        }

        $public_target = self::copy_generated_pdf_to_public_dir( $pdf_path, $student_name, $class->class_name, $serial );
        @unlink( $pdf_path );

        if ( is_wp_error( $public_target ) ) {
            return $public_target;
        }

        $certificate_id = SmartCert_Cleanup::register_certificate(
            $public_target['file_name'],
            $public_target['file_path'],
            $student_name,
            $class->class_name,
            $code,
            array(
                'class_id'               => intval( $class->id ),
                'batch_id'               => intval( $batch->id ),
                'batch_name'             => $batch->batch_name,
                'user_id'                => $user_id,
                'teacher_name'           => $teacher_name,
                'serial'                 => $serial,
                'template_version'       => $template_version,
                'verification_url'       => $verification_url,
                'status'                 => sanitize_text_field( $args['status'] ?: 'valid' ),
                'student_email'          => $student_email,
                'student_phone'          => $student_phone,
                'renewed_from_id'        => intval( $args['renewed_from_id'] ),
                'certificate_expires_at' => sanitize_text_field( $args['certificate_expires_at'] ),
                'generated_at'           => sanitize_text_field( $args['generated_at'] ),
                'expires_at'             => sanitize_text_field( $args['expires_at'] ),
            )
        );

        if ( ! $certificate_id ) {
            @unlink( $public_target['file_path'] );
            return new WP_Error( 'register_failed', 'Certificate was created, but saving it failed.' );
        }

        if ( ! empty( $args['renewed_from_id'] ) ) {
            SmartCert_Cleanup::mark_certificate_renewed( intval( $args['renewed_from_id'] ), intval( $certificate_id ) );
        }

        $certificate = SmartCert_Cleanup::get_certificate_by_id( $certificate_id );
        $urls = self::build_certificate_urls( $certificate, $user_id, $student_email );
        $certificate_url = $urls['certificate_url'];

        SmartCert_Logger::log(
            $student_name,
            $class->class_name,
            $code,
            'generated',
            $serial,
            array(
                'certificate_id' => intval( $certificate_id ),
                'user_id'        => $user_id,
                'class_id'       => intval( $class->id ),
                'batch_id'       => intval( $batch->id ),
                'batch_name'     => $batch->batch_name,
                'teacher_name'   => $teacher_name,
                'template_version' => $template_version,
                'verification_url' => $verification_url,
                'status'         => sanitize_text_field( $args['status'] ?: 'valid' ),
            )
        );

        if ( ! empty( $args['increment_download_count'] ) ) {
            self::increment_code_download_count( $args, $class, $batch, $code );
        }

        if ( ! empty( $args['sync_code_contact_fields'] ) ) {
            self::sync_code_contact_fields( $args, $code );
        }

        $delivery = self::maybe_process_delivery(
            $certificate,
            $urls,
            array(
                'trigger_auto_delivery' => ! empty( $args['trigger_auto_delivery'] ),
                'send_email'            => ! empty( $args['send_email'] ),
                'send_whatsapp'         => ! empty( $args['send_whatsapp'] ),
            )
        );

        do_action(
            'smartcertify_after_certificate_generated',
            $student_name,
            $class->class_name,
            $public_target['file_path'],
            array(
                'certificate_id'   => intval( $certificate_id ),
                'batch_name'       => $batch->batch_name,
                'serial'           => $serial,
                'verification_url' => $verification_url,
            )
        );

        if ( class_exists( 'SmartCert_Integrations' ) ) {
            SmartCert_Integrations::dispatch_webhook(
                'certificate.issued',
                $certificate,
                array(
                    'certificate_url'  => $certificate_url,
                    'verification_url' => $verification_url,
                    'delivery'         => $delivery,
                )
            );
        }

        return array(
            'certificate_id'   => intval( $certificate_id ),
            'certificate'      => $certificate,
            'file_name'        => $public_target['file_name'],
            'file_path'        => $public_target['file_path'],
            'url'              => $certificate_url,
            'serial'           => $serial,
            'generated_at'     => $generated_label,
            'verification_url' => $verification_url,
            'delivery'         => $delivery,
            'class'            => $class,
            'batch'            => $batch,
            'reused'           => false,
        );
    }

    public static function reissue_certificate( $certificate_id, $args = array() ) {
        $cert = SmartCert_Cleanup::get_certificate_by_id( $certificate_id );
        if ( ! $cert ) {
            return new WP_Error( 'missing_certificate', 'Certificate not found.' );
        }

        return self::issue_certificate(
            array_merge(
                $args,
                array(
                    'class_id'                 => intval( $cert['class_id'] ),
                    'batch_id'                 => intval( $cert['batch_id'] ),
                    'student_name'             => $cert['student_name'],
                    'student_email'            => $cert['student_email'],
                    'student_phone'            => $cert['student_phone'],
                    'user_id'                  => intval( $cert['user_id'] ?? 0 ),
                    'code'                     => $cert['code'],
                    'teacher_name'             => $cert['teacher_name'],
                    'renewed_from_id'          => intval( $cert['id'] ),
                    'certificate_expires_at'   => $cert['certificate_expires_at'],
                    'increment_download_count' => false,
                    'ignore_duplicate_rule'    => true,
                )
            )
        );
    }

    public static function send_certificate_email( $certificate_id ) {
        $certificate = SmartCert_Cleanup::get_certificate_by_id( $certificate_id );
        if ( ! $certificate ) {
            return new WP_Error( 'missing_certificate', 'Certificate not found.' );
        }

        $result = self::maybe_process_delivery(
            $certificate,
            self::build_certificate_urls( $certificate ),
            array(
                'trigger_auto_delivery' => false,
                'send_email'            => true,
                'send_whatsapp'         => false,
            )
        );

        if ( 'sent' !== ( $result['email_status'] ?? '' ) ) {
            return new WP_Error( 'email_failed', 'Email delivery could not be completed.' );
        }

        return $result;
    }

    public static function get_whatsapp_link( $certificate_id ) {
        $certificate = SmartCert_Cleanup::get_certificate_by_id( $certificate_id );
        if ( ! $certificate ) {
            return '';
        }

        if ( empty( $certificate['student_phone'] ) ) {
            return '';
        }

        $settings = SmartCert_Helpers::get_delivery_settings();
        $urls = self::build_certificate_urls( $certificate );
        $message = SmartCert_Helpers::replace_delivery_tokens(
            $settings['whatsapp_message'],
            array(
                'student_name'    => $certificate['student_name'] ?? '',
                'class_name'      => $certificate['class_name'] ?? '',
                'batch_name'      => $certificate['batch_name'] ?? '',
                'certificate_url' => $urls['certificate_url'],
                'verification_url'=> $urls['verification_url'],
                'serial'          => $certificate['serial'] ?? '',
            )
        );

        return SmartCert_Helpers::build_whatsapp_url( $certificate['student_phone'], $message );
    }

    public static function verify_certificate( $serial, $student_name = '' ) {
        $serial = sanitize_text_field( $serial );
        $student_name = sanitize_text_field( $student_name );

        if ( '' === $serial ) {
            return array(
                'found'   => false,
                'status'  => 'missing',
                'title'   => 'Enter A Serial Number',
                'message' => 'Please enter a certificate serial number to verify the record.',
            );
        }

        $certificate = SmartCert_Cleanup::get_certificate_by_serial( $serial );
        if ( ! $certificate ) {
            return array(
                'found'   => false,
                'status'  => 'not_found',
                'title'   => 'Certificate Not Found',
                'message' => 'No certificate was found for this serial number.',
            );
        }

        if ( $student_name && ! SmartCert_Helpers::names_match( $certificate['student_name'], $student_name ) ) {
            return array(
                'found'       => true,
                'status'      => 'name_mismatch',
                'title'       => 'Name Mismatch',
                'message'     => 'The serial exists, but the provided student name does not match our records.',
                'certificate' => $certificate,
            );
        }

        $status = sanitize_key( $certificate['status'] ?: 'valid' );
        $result = array(
            'found'       => true,
            'status'      => $status,
            'title'       => 'Certificate Verified',
            'message'     => 'This certificate is active and recorded in SmartCertify.',
            'certificate' => $certificate,
            'replacement' => null,
        );

        if ( 'expired' === $status ) {
            $result['title'] = 'Certificate Expired';
            $result['message'] = 'This certificate was issued correctly, but its validity period has ended.';
        } elseif ( 'revoked' === $status ) {
            $result['title'] = 'Certificate Revoked';
            $result['message'] = 'This certificate has been revoked and should no longer be treated as valid.';
        } elseif ( 'renewed' === $status ) {
            $result['title'] = 'Certificate Reissued';
            $result['message'] = 'This certificate has been replaced by a newer certificate record.';
            if ( ! empty( $certificate['renewed_to_id'] ) ) {
                $result['replacement'] = SmartCert_Cleanup::get_certificate_by_id( intval( $certificate['renewed_to_id'] ) );
            }
        } elseif ( 'deleted' === $status ) {
            $result['title'] = 'Certificate Deleted';
            $result['message'] = 'This certificate file is no longer available.';
        }

        return $result;
    }

    public static function generate_serial( $class_name, $batch_name, $student_name ) {
        return 'SC-' . strtoupper(
            substr(
                md5(
                    sanitize_text_field( $class_name ) . '|' .
                    sanitize_text_field( $batch_name ) . '|' .
                    sanitize_text_field( $student_name ) . '|' .
                    microtime( true ) . '|' . wp_rand( 1000, 9999 )
                ),
                0,
                10
            )
        );
    }

    public static function get_formatted_timestamp() {
        try {
            if ( function_exists( 'wp_timezone' ) ) {
                $dt = new DateTime( 'now', wp_timezone() );
            } else {
                $dt = new DateTime( 'now' );
            }
            return $dt->format( 'd M Y, g:i A' );
        } catch ( Exception $e ) {
            return date( 'd M Y, g:i A' );
        }
    }

    private static function copy_generated_pdf_to_public_dir( $pdf_path, $student_name, $class_name, $serial ) {
        $uploads = wp_upload_dir();
        $target_dir = trailingslashit( $uploads['basedir'] ) . 'smartcertify_public';

        if ( ! file_exists( $target_dir ) ) {
            wp_mkdir_p( $target_dir );
        }

        if ( ! file_exists( $target_dir ) ) {
            return new WP_Error( 'public_dir_missing', 'Failed to prepare certificate storage.' );
        }

        $file_name = apply_filters(
            'smartcertify_certificate_filename',
            sanitize_file_name( $student_name . '-' . $class_name . '-' . $serial . '.pdf' ),
            $student_name,
            $class_name
        );
        $basename = uniqid( 'certificate_', true ) . '-' . $file_name;
        $target = trailingslashit( $target_dir ) . $basename;

        if ( ! @copy( $pdf_path, $target ) ) {
            return new WP_Error( 'copy_failed', 'Failed to prepare certificate download.' );
        }

        return array(
            'file_name' => $basename,
            'file_path' => $target,
        );
    }

    private static function increment_code_download_count( $args, $class, $batch, $code ) {
        global $wpdb;

        $table = $wpdb->prefix . 'smartcertify_codes';
        $code_row_id = intval( $args['code_row_id'] ?? 0 );

        if ( $code_row_id > 0 ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE $table
                     SET download_count = download_count + 1, last_generated_at = %s
                     WHERE id = %d",
                    current_time( 'mysql' ),
                    $code_row_id
                )
            );
            return;
        }

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table
                 SET download_count = download_count + 1, last_generated_at = %s
                 WHERE class_id = %d AND batch_id = %d AND code = %s
                 ORDER BY id DESC
                 LIMIT 1",
                current_time( 'mysql' ),
                intval( $class->id ),
                intval( $batch->id ),
                $code
            )
        );
    }

    private static function sync_code_contact_fields( $args, $code ) {
        global $wpdb;

        $code_row_id = intval( $args['code_row_id'] ?? 0 );
        if ( $code_row_id <= 0 ) {
            return;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT student_name, student_email, student_phone FROM {$wpdb->prefix}smartcertify_codes WHERE id = %d LIMIT 1",
                $code_row_id
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return;
        }

        $updates = array();
        $formats = array();

        if ( empty( $row['student_name'] ) && ! empty( $args['student_name'] ) ) {
            $updates['student_name'] = sanitize_text_field( $args['student_name'] );
            $formats[] = '%s';
        }

        if ( empty( $row['student_email'] ) && ! empty( $args['student_email'] ) ) {
            $updates['student_email'] = sanitize_email( $args['student_email'] );
            $formats[] = '%s';
        }

        if ( empty( $row['student_phone'] ) && ! empty( $args['student_phone'] ) ) {
            $updates['student_phone'] = sanitize_text_field( $args['student_phone'] );
            $formats[] = '%s';
        }

        if ( $updates ) {
            $wpdb->update(
                $wpdb->prefix . 'smartcertify_codes',
                $updates,
                array( 'id' => $code_row_id ),
                $formats,
                array( '%d' )
            );
        }
    }

    private static function maybe_process_delivery( $certificate, $urls, $options ) {
        $result = array(
            'email_status'    => '',
            'whatsapp_status' => '',
            'whatsapp_url'    => '',
        );

        if ( empty( $certificate ) || empty( $certificate['id'] ) ) {
            return $result;
        }

        $settings = SmartCert_Helpers::get_delivery_settings();
        if ( empty( $certificate['student_email'] ) && ! empty( $certificate['user_id'] ) ) {
            $user = get_userdata( intval( $certificate['user_id'] ) );
            if ( $user && ! empty( $user->user_email ) ) {
                $certificate['student_email'] = sanitize_email( $user->user_email );
            }
        }
        $token_data = array(
            'student_name'    => $certificate['student_name'] ?? '',
            'class_name'      => $certificate['class_name'] ?? '',
            'batch_name'      => $certificate['batch_name'] ?? '',
            'certificate_url' => $urls['certificate_url'] ?? '',
            'verification_url'=> $urls['verification_url'] ?? '',
            'serial'          => $certificate['serial'] ?? '',
        );

        $send_email = ! empty( $options['send_email'] ) || ( ! empty( $options['trigger_auto_delivery'] ) && ! empty( $settings['auto_email'] ) );
        if ( $send_email ) {
            if ( ! empty( $certificate['student_email'] ) ) {
                $subject = SmartCert_Helpers::replace_delivery_tokens( $settings['email_subject'], $token_data );
                $plain_message = SmartCert_Helpers::replace_delivery_tokens( $settings['email_message'], $token_data );
                $message = SmartCert_Helpers::build_email_template_html( $subject, $plain_message, $token_data );
                $sent = wp_mail(
                    sanitize_email( $certificate['student_email'] ),
                    $subject,
                    $message,
                    array(
                        'Content-Type: text/html; charset=UTF-8',
                        'From: ' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . ' <' . sanitize_email( get_option( 'admin_email' ) ) . '>',
                    )
                );
                $result['email_status'] = $sent ? 'sent' : 'failed';
                SmartCert_Cleanup::update_delivery_status( intval( $certificate['id'] ), 'email', $result['email_status'] );
                if ( $sent && class_exists( 'SmartCert_Integrations' ) ) {
                    SmartCert_Integrations::dispatch_webhook( 'certificate.email_sent', $certificate, $urls );
                }
            } else {
                $result['email_status'] = 'missing_email';
                SmartCert_Cleanup::update_delivery_status( intval( $certificate['id'] ), 'email', 'missing_email' );
            }
        }

        $send_whatsapp = ! empty( $options['send_whatsapp'] ) || ( ! empty( $options['trigger_auto_delivery'] ) && ! empty( $settings['auto_whatsapp'] ) );
        if ( $send_whatsapp ) {
            if ( ! empty( $certificate['student_phone'] ) ) {
                $message = SmartCert_Helpers::replace_delivery_tokens( $settings['whatsapp_message'], $token_data );
                $result['whatsapp_url'] = SmartCert_Helpers::build_whatsapp_url( $certificate['student_phone'], $message );
                $result['whatsapp_status'] = $result['whatsapp_url'] ? 'ready' : 'failed';
                SmartCert_Cleanup::update_delivery_status( intval( $certificate['id'] ), 'whatsapp', $result['whatsapp_status'] );
            } else {
                $result['whatsapp_status'] = 'missing_phone';
                SmartCert_Cleanup::update_delivery_status( intval( $certificate['id'] ), 'whatsapp', 'missing_phone' );
            }
        }

        return $result;
    }

    private static function find_existing_duplicate_certificate( $args ) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM {$wpdb->prefix}smartcertify_certificates
                 WHERE class_id = %d
                 AND batch_id = %d
                 AND code = %s
                 AND deleted_at IS NULL
                 ORDER BY id DESC
                 LIMIT 20",
                intval( $args['class_id'] ?? 0 ),
                intval( $args['batch_id'] ?? 0 ),
                sanitize_text_field( $args['code'] ?? '' )
            ),
            ARRAY_A
        );

        if ( ! $rows ) {
            return null;
        }

        $requested_name = sanitize_text_field( $args['student_name'] ?? '' );
        $requested_email = sanitize_email( $args['student_email'] ?? '' );
        $requested_user_id = intval( $args['user_id'] ?? 0 );

        foreach ( $rows as $row ) {
            $row = SmartCert_Cleanup::sync_certificate_status( $row );
            if ( 'valid' !== ( $row['status'] ?? 'valid' ) ) {
                continue;
            }

            if ( ! empty( $row['expires_at'] ) && strtotime( $row['expires_at'] ) && time() > strtotime( $row['expires_at'] ) ) {
                continue;
            }

            if ( ! empty( $row['certificate_expires_at'] ) && strtotime( $row['certificate_expires_at'] ) && time() > strtotime( $row['certificate_expires_at'] ) ) {
                continue;
            }

            if ( ! empty( $row['file_path'] ) && ! file_exists( $row['file_path'] ) ) {
                continue;
            }

            if ( $requested_user_id > 0 && intval( $row['user_id'] ?? 0 ) > 0 && intval( $row['user_id'] ) !== $requested_user_id ) {
                continue;
            }

            if ( $requested_email && ! empty( $row['student_email'] ) && ! SmartCert_Helpers::compare_value( strtolower( $row['student_email'] ), strtolower( $requested_email ) ) ) {
                continue;
            }

            if ( ! empty( $row['student_name'] ) && $requested_name && ! SmartCert_Helpers::names_match( $row['student_name'], $requested_name ) ) {
                continue;
            }

            return $row;
        }

        return null;
    }

    private static function build_certificate_urls( $certificate, $preferred_user_id = 0, $preferred_email = '' ) {
        $certificate = is_array( $certificate ) ? $certificate : array();
        $user_id = intval( $preferred_user_id ?: intval( $certificate['user_id'] ?? 0 ) );
        $email = sanitize_email( $preferred_email ?: ( $certificate['student_email'] ?? '' ) );
        if ( '' === $email && $user_id > 0 ) {
            $user = get_userdata( $user_id );
            if ( $user && ! empty( $user->user_email ) ) {
                $email = sanitize_email( $user->user_email );
            }
        }
        $expires = ! empty( $certificate['expires_at'] ) ? strtotime( $certificate['expires_at'] ) : 0;

        return array(
            'certificate_url'  => SmartCert_Helpers::get_public_certificate_url(
                $certificate['file_name'] ?? '',
                array(
                    'user_id' => $user_id,
                    'email'   => $email,
                    'expires' => $expires,
                )
            ),
            'verification_url' => ! empty( $certificate['verification_url'] ) ? $certificate['verification_url'] : SmartCert_Helpers::get_verify_url( $certificate['serial'] ?? '' ),
        );
    }
}
