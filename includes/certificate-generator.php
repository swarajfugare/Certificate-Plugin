<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartCert_Generator {
    private $font_path;
    private $font_registry = array();
    private $fallback_fonts = array();

    public function __construct() {
        $this->font_registry = $this->get_plugin_fonts();
        $candidate = $this->font_registry['samarata'] ?? '';
        $this->font_path = file_exists( $candidate ) ? $candidate : '';
        $this->fallback_fonts = $this->get_system_fonts();
    }

    public function generate_certificate( $template_reference, $student_name, $serial = '', $generated_at = '', $context = array() ) {
        $uploads = wp_upload_dir();
        $tmp_dir = trailingslashit( $uploads['basedir'] ) . 'smartcertify';

        if ( ! file_exists( $tmp_dir ) ) {
            wp_mkdir_p( $tmp_dir );
        }

        $prepared = $this->prepare_template_background( $template_reference, $tmp_dir );
        if ( ! $prepared['path'] || ! file_exists( $prepared['path'] ) ) {
            SmartCert_Logger::debug( array( 'generate_certificate_error' => 'template_background_not_prepared', 'template' => $template_reference ) );
            return false;
        }

        $context = is_array( $context ) ? $context : array();
        $context['student_name'] = $student_name;
        $context['serial'] = $serial;
        $context['generated_at'] = $generated_at;
        if ( empty( $context['qr_payload'] ) ) {
            $context['qr_payload'] = SmartCert_Helpers::get_qr_payload( $serial, $context['verification_url'] ?? '' );
        }

        $annotated_image = $this->annotate_image( $prepared['path'], $context, $tmp_dir );
        if ( ! $annotated_image || ! file_exists( $annotated_image ) ) {
            if ( ! empty( $prepared['cleanup'] ) ) {
                @unlink( $prepared['path'] );
            }
            SmartCert_Logger::debug( array( 'generate_certificate_error' => 'annotation_failed' ) );
            return false;
        }

        $pdf_path = $this->create_pdf_from_image( $annotated_image, $tmp_dir );

        if ( ! empty( $prepared['cleanup'] ) ) {
            @unlink( $prepared['path'] );
        }
        @unlink( $annotated_image );

        if ( ! $pdf_path || ! file_exists( $pdf_path ) || ! $this->is_valid_pdf( $pdf_path ) ) {
            SmartCert_Logger::debug( array( 'generate_certificate_error' => 'pdf_build_failed', 'pdf_path' => $pdf_path ) );
            return false;
        }

        return $pdf_path;
    }

    private function prepare_template_background( $template_reference, $tmp_dir ) {
        $attachment_id = 0;
        $url = '';

        if ( is_array( $template_reference ) ) {
            $attachment_id = intval( $template_reference['attachment_id'] ?? 0 );
            $url = trim( (string) ( $template_reference['url'] ?? '' ) );
        } else {
            $url = trim( (string) $template_reference );
        }

        $source = SmartCert_Helpers::resolve_media_source( $attachment_id, $url );

        if ( ! empty( $source['path'] ) && file_exists( $source['path'] ) ) {
            return $this->prepare_local_template_background( $source['path'], $source['mime'], $tmp_dir );
        }

        if ( ! empty( $source['url'] ) ) {
            return $this->prepare_remote_template_background( $source['url'], $source['mime'], $tmp_dir );
        }

        return array( 'path' => '', 'cleanup' => false );
    }

    private function prepare_local_template_background( $path, $mime, $tmp_dir ) {
        $ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
        $is_pdf = 'pdf' === $ext || 'application/pdf' === $mime;

        if ( $is_pdf ) {
            $rendered = $this->render_pdf_to_png( $path, $tmp_dir );
            return array( 'path' => $rendered, 'cleanup' => false );
        }

        if ( file_exists( $path ) ) {
            return array( 'path' => $path, 'cleanup' => false );
        }

        return array( 'path' => '', 'cleanup' => false );
    }

    private function prepare_remote_template_background( $url, $mime, $tmp_dir ) {
        $cache_dir = $this->get_cache_dir( $tmp_dir );
        $ext = strtolower( pathinfo( strtok( $url, '?' ), PATHINFO_EXTENSION ) );
        $is_pdf = 'pdf' === $ext || 'application/pdf' === $mime;
        $cache_source = $cache_dir . '/remote_template_' . md5( $url ) . '.' . ( $is_pdf ? 'pdf' : ( $ext ? $ext : 'png' ) );

        if ( file_exists( $cache_source ) ) {
            if ( $is_pdf ) {
                $rendered = $this->render_pdf_to_png( $cache_source, $tmp_dir );
                return array( 'path' => $rendered, 'cleanup' => false );
            }

            return array( 'path' => $cache_source, 'cleanup' => false );
        }

        $response = wp_remote_get(
            $url,
            array(
                'timeout'   => 8,
                'sslverify' => false,
            )
        );

        if ( is_wp_error( $response ) ) {
            SmartCert_Logger::debug( array( 'template_fetch_error' => $response->get_error_message(), 'url' => $url ) );
            return array( 'path' => '', 'cleanup' => false );
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            SmartCert_Logger::debug( array( 'template_fetch_error' => 'empty_body', 'url' => $url ) );
            return array( 'path' => '', 'cleanup' => false );
        }

        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        $is_pdf = false !== strpos( (string) $content_type, 'pdf' ) || 'pdf' === $ext || 'application/pdf' === $mime;
        $cache_source = $cache_dir . '/remote_template_' . md5( $url ) . '.' . ( $is_pdf ? 'pdf' : ( $ext ? $ext : 'png' ) );
        file_put_contents( $cache_source, $body );

        if ( $is_pdf ) {
            $rendered = $this->render_pdf_to_png( $cache_source, $tmp_dir );
            return array( 'path' => $rendered, 'cleanup' => false );
        }

        return array( 'path' => $cache_source, 'cleanup' => false );
    }

    private function render_pdf_to_png( $pdf_path, $tmp_dir ) {
        if ( ! class_exists( 'Imagick' ) ) {
            SmartCert_Logger::debug( array( 'pdf_render_error' => 'imagick_required_for_pdf_templates', 'path' => $pdf_path ) );
            return false;
        }

        try {
            $cache_dir = $this->get_cache_dir( $tmp_dir );
            $version = file_exists( $pdf_path ) ? filemtime( $pdf_path ) : time();
            $target = $cache_dir . '/pdf_template_' . md5( $pdf_path . '|' . $version . '|170' ) . '.png';
            if ( file_exists( $target ) ) {
                return $target;
            }

            $imagick = new Imagick();
            $imagick->setResolution( 170, 170 );
            $imagick->readImage( $pdf_path . '[0]' );
            $imagick->setImageBackgroundColor( 'white' );
            $imagick = $imagick->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );
            $imagick->setImageFormat( 'png' );
            $imagick->writeImage( $target );
            $imagick->clear();

            return file_exists( $target ) ? $target : false;
        } catch ( Exception $e ) {
            SmartCert_Logger::debug( array( 'pdf_render_error' => $e->getMessage(), 'path' => $pdf_path ) );
            return false;
        }
    }

    private function annotate_image( $image_path, $context, $work_dir = '' ) {
        if ( class_exists( 'Imagick' ) ) {
            $result = $this->annotate_with_imagick( $image_path, $context, $work_dir );
            if ( $result ) {
                return $result;
            }
        }

        return $this->annotate_with_gd( $image_path, $context, $work_dir );
    }

    private function annotate_with_imagick( $image_path, $context, $work_dir = '' ) {
        try {
            $work_dir = $this->prepare_working_directory( $work_dir ?: dirname( $image_path ) );
            $im = new Imagick( $image_path );
            $width = $im->getImageWidth();
            $height = $im->getImageHeight();
            $layout = SmartCert_Helpers::get_layout_settings();
            $name_font = $this->get_font_by_choice( $layout['name_font_family'] ?? 'sans', 'sans' );
            $class_font = $this->get_font_by_choice( $layout['class_font_family'] ?? 'montserrat_bold', 'sans' );
            $batch_font = $this->get_font_by_choice( $layout['batch_font_family'] ?? 'montserrat_bold', 'sans' );
            $teacher_font = $this->get_font_by_choice( $layout['teacher_name_font_family'] ?? 'montserrat_bold', 'sans' );
            $meta_font = $this->get_font_by_choice( $layout['meta_font_family'] ?? 'sans', 'sans' );
            $name_x = SmartCert_Helpers::get_coordinate( $layout['name_x'], $width, $width / 2 );
            $name_y = SmartCert_Helpers::get_coordinate( $layout['name_y'], $height, $height * 0.55 );
            $class_x = SmartCert_Helpers::get_coordinate( $layout['class_x'], $width, $width / 2 );
            $class_y = SmartCert_Helpers::get_coordinate( $layout['class_y'], $height, $height * 0.25 );
            $batch_x = SmartCert_Helpers::get_coordinate( $layout['batch_x'], $width, $width / 2 );
            $batch_y = SmartCert_Helpers::get_coordinate( $layout['batch_y'], $height, $height * 0.305 );
            $teacher_signature_x = intval( SmartCert_Helpers::get_coordinate( $layout['teacher_signature_x'], $width, $width * 0.64 ) );
            $teacher_signature_y = intval( SmartCert_Helpers::get_coordinate( $layout['teacher_signature_y'], $height, $height * 0.75 ) );
            $teacher_name_x = SmartCert_Helpers::get_coordinate( $layout['teacher_name_x'], $width, $width * 0.70 );
            $teacher_name_y = SmartCert_Helpers::get_coordinate( $layout['teacher_name_y'], $height, $height * 0.925 );
            $qr_x = intval( SmartCert_Helpers::get_coordinate( $layout['qr_x'], $width, $width * 0.847 ) );
            $qr_y = intval( SmartCert_Helpers::get_coordinate( $layout['qr_y'], $height, $height * 0.808 ) );
            $meta_x = SmartCert_Helpers::get_coordinate( $layout['meta_x'], $width, $width * 0.835 );
            $meta_y = SmartCert_Helpers::get_coordinate( $layout['meta_y'], $height, $height * 0.78 );

            $name_font_size = $this->fit_text_size_imagick( $im, $context['student_name'], $width * 0.62, intval( $layout['name_font_size'] ), $name_font );
            $class_font_size = $this->fit_text_size_imagick( $im, $context['class_name'] ?? '', $width * 0.55, intval( $layout['class_font_size'] ), $class_font );
            $batch_label = ! empty( $context['batch_name'] ) ? 'Batch: ' . $context['batch_name'] : '';
            $batch_font_size = $this->fit_text_size_imagick( $im, $batch_label, $width * 0.45, intval( $layout['batch_font_size'] ), $batch_font );

            $this->draw_centered_text_imagick(
                $im,
                $context['student_name'],
                $name_x,
                $name_y,
                $name_font_size,
                $name_font,
                'black'
            );

            if ( ! empty( $context['class_name'] ) ) {
                $this->draw_centered_text_imagick(
                    $im,
                    $context['class_name'],
                    $class_x,
                    $class_y,
                    $class_font_size,
                    $class_font,
                    'black'
                );
            }

            if ( $batch_label ) {
                $this->draw_centered_text_imagick(
                    $im,
                    $batch_label,
                    $batch_x,
                    $batch_y,
                    $batch_font_size,
                    $batch_font,
                    '#202020'
                );
            }

            $signature_path = $this->prepare_overlay_asset(
                intval( $context['teacher_signature_id'] ?? 0 ),
                $context['teacher_signature_url'] ?? '',
                $work_dir
            );
            if ( $signature_path ) {
                $signature = new Imagick( $signature_path );
                $sig_width = max( 40, intval( SmartCert_Helpers::get_coordinate( $layout['teacher_signature_width'], $width, $width * 0.125 ) ) );
                $sig_height = max( 20, intval( SmartCert_Helpers::get_coordinate( $layout['teacher_signature_height'], $height, $height * 0.065 ) ) );
                $signature->resizeImage( $sig_width, $sig_height, Imagick::FILTER_LANCZOS, 1, true );
                $im->compositeImage( $signature, Imagick::COMPOSITE_DEFAULT, $teacher_signature_x, $teacher_signature_y );
                $signature->clear();
            }

            if ( ! empty( $context['teacher_name'] ) ) {
                $teacher_font_size = $this->fit_text_size_imagick( $im, $context['teacher_name'], $width * 0.22, intval( $layout['teacher_name_font_size'] ), $teacher_font );
                $this->draw_centered_text_imagick(
                    $im,
                    $context['teacher_name'],
                    $teacher_name_x,
                    $teacher_name_y,
                    $teacher_font_size,
                    $teacher_font,
                    'black'
                );
            }

            $qr_size = max( 80, intval( SmartCert_Helpers::get_coordinate( $layout['qr_size'], $width, $width * 0.085 ) ) );
            $qr_path = $this->create_qr_code_image( $context['qr_payload'] ?? ( $context['verification_url'] ?? '' ), $qr_size, $work_dir );
            if ( $qr_path ) {
                $qr = new Imagick( $qr_path );
                $qr->resizeImage( $qr_size, $qr_size, Imagick::FILTER_LANCZOS, 1 );
                $im->compositeImage( $qr, Imagick::COMPOSITE_DEFAULT, $qr_x, $qr_y );
                $qr->clear();
            }

            $meta = trim(
                'SN: ' . ( $context['serial'] ?? '' ) .
                ( ! empty( $context['generated_at'] ) ? "\n" . $context['generated_at'] : '' )
            );

            if ( trim( $meta ) !== 'SN:' ) {
                $this->draw_multiline_text_imagick(
                    $im,
                    $meta,
                    $meta_x,
                    $meta_y,
                    intval( $layout['meta_font_size'] ),
                    $meta_font,
                    '#222222',
                    'right'
                );
            }

            $output = $work_dir . '/annotated_' . uniqid( '', true ) . '.png';
            $im->setImageFormat( 'png' );
            $im->writeImage( $output );
            $im->clear();

            return file_exists( $output ) ? $output : false;
        } catch ( Exception $e ) {
            SmartCert_Logger::debug( array( 'annotate_imagick_error' => $e->getMessage() ) );
            return false;
        }
    }

    private function annotate_with_gd( $image_path, $context, $work_dir = '' ) {
        $work_dir = $this->prepare_working_directory( $work_dir ?: dirname( $image_path ) );
        $bytes = file_get_contents( $image_path );
        if ( ! $bytes ) {
            return false;
        }

        $img = imagecreatefromstring( $bytes );
        if ( ! $img ) {
            return false;
        }

        imagealphablending( $img, true );
        imagesavealpha( $img, true );

        $width = imagesx( $img );
        $height = imagesy( $img );
        $layout = SmartCert_Helpers::get_layout_settings();
        $name_font = $this->get_font_by_choice( $layout['name_font_family'] ?? 'sans', 'sans' );
        $class_font = $this->get_font_by_choice( $layout['class_font_family'] ?? 'montserrat_bold', 'sans' );
        $batch_font = $this->get_font_by_choice( $layout['batch_font_family'] ?? 'montserrat_bold', 'sans' );
        $teacher_font = $this->get_font_by_choice( $layout['teacher_name_font_family'] ?? 'montserrat_bold', 'sans' );
        $meta_font = $this->get_font_by_choice( $layout['meta_font_family'] ?? 'sans', 'sans' );
        $black = imagecolorallocate( $img, 0, 0, 0 );
        $dark = imagecolorallocate( $img, 34, 34, 34 );
        $name_x = SmartCert_Helpers::get_coordinate( $layout['name_x'], $width, $width / 2 );
        $name_y = SmartCert_Helpers::get_coordinate( $layout['name_y'], $height, $height * 0.55 );
        $class_x = SmartCert_Helpers::get_coordinate( $layout['class_x'], $width, $width / 2 );
        $class_y = SmartCert_Helpers::get_coordinate( $layout['class_y'], $height, $height * 0.25 );
        $batch_x = SmartCert_Helpers::get_coordinate( $layout['batch_x'], $width, $width / 2 );
        $batch_y = SmartCert_Helpers::get_coordinate( $layout['batch_y'], $height, $height * 0.305 );
        $teacher_signature_x = intval( SmartCert_Helpers::get_coordinate( $layout['teacher_signature_x'], $width, $width * 0.64 ) );
        $teacher_signature_y = intval( SmartCert_Helpers::get_coordinate( $layout['teacher_signature_y'], $height, $height * 0.75 ) );
        $teacher_name_x = SmartCert_Helpers::get_coordinate( $layout['teacher_name_x'], $width, $width * 0.70 );
        $teacher_name_y = SmartCert_Helpers::get_coordinate( $layout['teacher_name_y'], $height, $height * 0.925 );
        $meta_x = intval( SmartCert_Helpers::get_coordinate( $layout['meta_x'], $width, $width * 0.835 ) );
        $meta_y = intval( SmartCert_Helpers::get_coordinate( $layout['meta_y'], $height, $height * 0.78 ) );
        $batch_label = ! empty( $context['batch_name'] ) ? 'Batch: ' . $context['batch_name'] : '';

        $this->draw_centered_text_gd(
            $img,
            $context['student_name'],
            $name_x,
            $name_y,
            $name_font,
            intval( $layout['name_font_size'] ),
            $black,
            $width * 0.62
        );

        if ( ! empty( $context['class_name'] ) ) {
            $this->draw_centered_text_gd(
                $img,
                $context['class_name'],
                $class_x,
                $class_y,
                $class_font,
                intval( $layout['class_font_size'] ),
                $black,
                $width * 0.55
            );
        }

        if ( $batch_label ) {
            $this->draw_centered_text_gd(
                $img,
                $batch_label,
                $batch_x,
                $batch_y,
                $batch_font,
                intval( $layout['batch_font_size'] ),
                $dark,
                $width * 0.45
            );
        }

        $signature_path = $this->prepare_overlay_asset(
            intval( $context['teacher_signature_id'] ?? 0 ),
            $context['teacher_signature_url'] ?? '',
            $work_dir
        );
        if ( $signature_path ) {
            $this->overlay_image_with_gd(
                $img,
                $signature_path,
                $teacher_signature_x,
                $teacher_signature_y,
                max( 40, intval( SmartCert_Helpers::get_coordinate( $layout['teacher_signature_width'], $width, $width * 0.125 ) ) ),
                max( 20, intval( SmartCert_Helpers::get_coordinate( $layout['teacher_signature_height'], $height, $height * 0.065 ) ) )
            );
        }

        if ( ! empty( $context['teacher_name'] ) ) {
            $this->draw_centered_text_gd(
                $img,
                $context['teacher_name'],
                $teacher_name_x,
                $teacher_name_y,
                $teacher_font,
                intval( $layout['teacher_name_font_size'] ),
                $black,
                $width * 0.22
            );
        }

        $qr_size = max( 80, intval( SmartCert_Helpers::get_coordinate( $layout['qr_size'], $width, $width * 0.085 ) ) );
        $qr_path = $this->create_qr_code_image( $context['qr_payload'] ?? ( $context['verification_url'] ?? '' ), $qr_size, $work_dir );
        if ( $qr_path ) {
            $this->overlay_image_with_gd(
                $img,
                $qr_path,
                intval( SmartCert_Helpers::get_coordinate( $layout['qr_x'], $width, $width * 0.847 ) ),
                intval( SmartCert_Helpers::get_coordinate( $layout['qr_y'], $height, $height * 0.808 ) ),
                $qr_size,
                $qr_size
            );
        }

        $meta_size = max( 10, intval( $layout['meta_font_size'] ) );

        if ( ! empty( $context['serial'] ) ) {
            $this->draw_right_text_gd( $img, 'SN: ' . $context['serial'], $meta_x, $meta_y, $meta_font, $meta_size, $dark );
        }
        if ( ! empty( $context['generated_at'] ) ) {
            $this->draw_right_text_gd( $img, $context['generated_at'], $meta_x, $meta_y + ( $meta_size * 2 ), $meta_font, $meta_size, $dark );
        }

        $output = $work_dir . '/annotated_' . uniqid( '', true ) . '.png';
        imagepng( $img, $output );
        imagedestroy( $img );

        return file_exists( $output ) ? $output : false;
    }

    private function prepare_overlay_asset( $attachment_id, $url, $tmp_dir ) {
        $source = SmartCert_Helpers::resolve_media_source( $attachment_id, $url );

        if ( ! empty( $source['path'] ) && file_exists( $source['path'] ) ) {
            return $source['path'];
        }

        if ( empty( $source['url'] ) ) {
            return false;
        }

        $cache_dir = $this->get_cache_dir( $tmp_dir );
        $ext = strtolower( pathinfo( strtok( $source['url'], '?' ), PATHINFO_EXTENSION ) );
        $cached_overlay = $cache_dir . '/overlay_' . md5( $source['url'] ) . '.' . ( $ext ? $ext : 'png' );
        if ( file_exists( $cached_overlay ) ) {
            return $cached_overlay;
        }

        $response = wp_remote_get( $source['url'], array( 'timeout' => 8, 'sslverify' => false ) );
        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            return false;
        }

        $target = $cached_overlay;
        file_put_contents( $target, $body );

        return file_exists( $target ) ? $target : false;
    }

    private function create_qr_code_image( $payload, $size, $tmp_dir ) {
        $payload = trim( (string) $payload );
        if ( '' === $payload ) {
            return false;
        }

        $cache_dir = $this->get_cache_dir( $tmp_dir );
        $target = $cache_dir . '/qr_' . md5( $payload . '|' . intval( $size ) ) . '.png';
        if ( file_exists( $target ) ) {
            return $target;
        }

        if ( $this->create_local_qr_code_image( $payload, $size, $target ) ) {
            return $target;
        }
        SmartCert_Logger::debug( array( 'qr_error' => 'local_qr_generation_failed', 'payload' => $payload ) );
        return false;
    }

    private function create_local_qr_code_image( $payload, $size, $target ) {
        if ( class_exists( 'SmartCert_Local_QR' ) && SmartCert_Local_QR::render_png( $payload, $target, $size ) ) {
            return true;
        }

        if ( class_exists( 'QRcode' ) && is_callable( array( 'QRcode', 'png' ) ) ) {
            try {
                $scale = max( 3, intval( ceil( intval( $size ) / 30 ) ) );
                QRcode::png( $payload, $target, 'M', $scale, 0 );
                if ( file_exists( $target ) ) {
                    return true;
                }
            } catch ( Exception $e ) {
                SmartCert_Logger::debug( array( 'local_qr_error' => $e->getMessage(), 'engine' => 'phpqrcode' ) );
            }
        }

        if ( $this->create_qr_with_qrencode_binary( $payload, $size, $target ) ) {
            return true;
        }

        return false;
    }

    private function create_qr_with_qrencode_binary( $payload, $size, $target ) {
        if ( ! function_exists( 'shell_exec' ) || ! function_exists( 'escapeshellarg' ) ) {
            return false;
        }

        $binary = @shell_exec( 'command -v qrencode' );
        $binary = trim( (string) $binary );
        if ( '' === $binary ) {
            return false;
        }

        $scale = max( 3, intval( ceil( intval( $size ) / 30 ) ) );
        $command = escapeshellarg( $binary ) .
            ' -o ' . escapeshellarg( $target ) .
            ' -s ' . intval( $scale ) .
            ' -m 0 ' . escapeshellarg( $payload ) . ' 2>&1';

        @shell_exec( $command );

        return file_exists( $target );
    }

    private function prepare_working_directory( $work_dir ) {
        $work_dir = rtrim( (string) $work_dir, '/\\' );
        if ( '' === $work_dir ) {
            $uploads = wp_upload_dir();
            $work_dir = trailingslashit( $uploads['basedir'] ) . 'smartcertify';
        }

        if ( ! file_exists( $work_dir ) ) {
            wp_mkdir_p( $work_dir );
        }

        return $work_dir;
    }

    private function get_cache_dir( $tmp_dir ) {
        $tmp_dir = $this->prepare_working_directory( $tmp_dir );
        $cache_dir = trailingslashit( $tmp_dir ) . 'cache';

        if ( ! file_exists( $cache_dir ) ) {
            wp_mkdir_p( $cache_dir );
        }

        return $cache_dir;
    }

    private function draw_panel_imagick( $im, $center_x, $top_y, $panel_width, $panel_height ) {
        $left = max( 0, $center_x - ( $panel_width / 2 ) );
        $right = min( $im->getImageWidth(), $center_x + ( $panel_width / 2 ) );
        $top = max( 0, $top_y );
        $bottom = min( $im->getImageHeight(), $top + $panel_height );

        $draw = new ImagickDraw();
        $draw->setFillColor( new ImagickPixel( 'rgba(249,249,249,0.93)' ) );
        $draw->setStrokeColor( new ImagickPixel( 'transparent' ) );
        $draw->rectangle( $left, $top, $right, $bottom );
        $im->drawImage( $draw );
    }

    private function draw_centered_text_imagick( $im, $text, $center_x, $baseline_y, $font_size, $font, $color ) {
        $draw = new ImagickDraw();
        $draw->setFillColor( new ImagickPixel( $color ) );
        $draw->setFontSize( $font_size );
        if ( $font ) {
            $draw->setFont( $font );
        }

        $metrics = $im->queryFontMetrics( $draw, $text );
        $x = $center_x - ( $metrics['textWidth'] / 2 );
        $im->annotateImage( $draw, $x, $baseline_y, 0, $text );
    }

    private function draw_multiline_text_imagick( $im, $text, $anchor_x, $anchor_y, $font_size, $font, $color, $align = 'left' ) {
        $lines = preg_split( '/\r?\n/', trim( (string) $text ) );
        $line_height = $font_size + 6;
        $offset = 0;

        foreach ( $lines as $line ) {
            if ( '' === trim( $line ) ) {
                $offset += $line_height;
                continue;
            }

            $draw = new ImagickDraw();
            $draw->setFillColor( new ImagickPixel( $color ) );
            $draw->setFontSize( $font_size );
            if ( $font ) {
                $draw->setFont( $font );
            }

            $metrics = $im->queryFontMetrics( $draw, $line );
            $x = $anchor_x;
            if ( 'right' === $align ) {
                $x = $anchor_x - $metrics['textWidth'];
            } elseif ( 'center' === $align ) {
                $x = $anchor_x - ( $metrics['textWidth'] / 2 );
            }

            $im->annotateImage( $draw, $x, $anchor_y + $offset, 0, $line );
            $offset += $line_height;
        }
    }

    private function fit_text_size_imagick( $im, $text, $max_width, $preferred_size, $font ) {
        $size = max( 14, intval( $preferred_size ) );
        if ( '' === trim( (string) $text ) ) {
            return $size;
        }

        while ( $size > 14 ) {
            $draw = new ImagickDraw();
            $draw->setFontSize( $size );
            if ( $font ) {
                $draw->setFont( $font );
            }

            $metrics = $im->queryFontMetrics( $draw, $text );
            if ( ! empty( $metrics['textWidth'] ) && $metrics['textWidth'] <= $max_width ) {
                return $size;
            }

            $size -= 2;
        }

        return 14;
    }

    private function draw_panel_gd( $img, $center_x, $top_y, $panel_width, $panel_height ) {
        $left = max( 0, intval( $center_x - ( $panel_width / 2 ) ) );
        $right = min( imagesx( $img ), intval( $center_x + ( $panel_width / 2 ) ) );
        $top = max( 0, intval( $top_y ) );
        $bottom = min( imagesy( $img ), intval( $top_y + $panel_height ) );
        $panel_color = imagecolorallocatealpha( $img, 249, 249, 249, 16 );

        imagefilledrectangle( $img, $left, $top, $right, $bottom, $panel_color );
    }

    private function draw_centered_text_gd( $img, $text, $center_x, $baseline_y, $font, $preferred_size, $color, $max_width ) {
        $size = $this->fit_text_size_gd( $font, $text, $preferred_size, $max_width );

        if ( $font && file_exists( $font ) ) {
            $bbox = imagettfbbox( $size, 0, $font, $text );
            if ( $bbox ) {
                $text_width = abs( $bbox[2] - $bbox[0] );
                $x = intval( $center_x - ( $text_width / 2 ) );
                imagettftext( $img, $size, 0, $x, intval( $baseline_y ), $color, $font, $text );
                return;
            }
        }

        $font_width = imagefontwidth( 5 );
        $x = intval( $center_x - ( strlen( $text ) * $font_width / 2 ) );
        imagestring( $img, 5, $x, intval( $baseline_y ), $text, $color );
    }

    private function draw_right_text_gd( $img, $text, $anchor_x, $baseline_y, $font, $size, $color ) {
        if ( $font && file_exists( $font ) ) {
            $bbox = imagettfbbox( $size, 0, $font, $text );
            if ( $bbox ) {
                $text_width = abs( $bbox[2] - $bbox[0] );
                $x = intval( $anchor_x - $text_width );
                imagettftext( $img, $size, 0, $x, intval( $baseline_y ), $color, $font, $text );
                return;
            }
        }

        $font_width = imagefontwidth( 3 );
        $x = intval( $anchor_x - ( strlen( $text ) * $font_width ) );
        imagestring( $img, 3, $x, intval( $baseline_y ), $text, $color );
    }

    private function fit_text_size_gd( $font, $text, $preferred_size, $max_width ) {
        $size = max( 14, intval( $preferred_size ) );

        if ( ! $font || ! file_exists( $font ) || '' === trim( (string) $text ) ) {
            return $size;
        }

        while ( $size > 14 ) {
            $bbox = imagettfbbox( $size, 0, $font, $text );
            if ( $bbox && abs( $bbox[2] - $bbox[0] ) <= $max_width ) {
                return $size;
            }
            $size -= 2;
        }

        return 14;
    }

    private function overlay_image_with_gd( $base, $overlay_path, $x, $y, $target_width, $target_height ) {
        $bytes = @file_get_contents( $overlay_path );
        if ( ! $bytes ) {
            return;
        }

        $overlay = @imagecreatefromstring( $bytes );
        if ( ! $overlay ) {
            return;
        }

        imagealphablending( $overlay, true );
        imagesavealpha( $overlay, true );

        imagecopyresampled(
            $base,
            $overlay,
            intval( $x ),
            intval( $y ),
            0,
            0,
            intval( $target_width ),
            intval( $target_height ),
            imagesx( $overlay ),
            imagesy( $overlay )
        );

        imagedestroy( $overlay );
    }

    private function get_system_fonts() {
        $fonts = array();
        $candidates = array(
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Helvetica.ttc',
            '/Library/Fonts/Arial.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSerif-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf',
            '/System/Library/Fonts/Georgia.ttf',
            '/System/Library/Fonts/Courier New.ttf',
            'C:\\Windows\\Fonts\\arial.ttf',
            'C:\\Windows\\Fonts\\georgia.ttf',
        );

        foreach ( $candidates as $path ) {
            if ( file_exists( $path ) ) {
                $fonts[] = $path;
            }
        }

        return $fonts;
    }

    private function get_plugin_fonts() {
        $fonts = array(
            'samarata'        => SMARTCERTIFY_DIR . 'assets/font/Samarata.ttf',
            'montserrat_bold' => SMARTCERTIFY_DIR . 'assets/font/Montserrat-Bold.ttf',
        );

        foreach ( $fonts as $key => $path ) {
            if ( ! file_exists( $path ) ) {
                unset( $fonts[ $key ] );
            }
        }

        return $fonts;
    }

    private function get_system_font_by_choice( $choice ) {
        $choice = sanitize_key( (string) $choice );
        $candidates = array(
            'sans' => array(
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/System/Library/Fonts/Supplemental/Arial.ttf',
                '/System/Library/Fonts/Helvetica.ttc',
                '/Library/Fonts/Arial.ttf',
                'C:\\Windows\\Fonts\\arial.ttf',
            ),
            'serif' => array(
                '/usr/share/fonts/truetype/liberation/LiberationSerif-Regular.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf',
                '/System/Library/Fonts/Georgia.ttf',
                'C:\\Windows\\Fonts\\georgia.ttf',
            ),
        );

        foreach ( $candidates[ $choice ] ?? array() as $path ) {
            if ( file_exists( $path ) ) {
                return $path;
            }
        }

        return null;
    }

    private function get_font_by_choice( $choice, $fallback_choice = 'sans' ) {
        $choice = sanitize_key( (string) $choice );

        if ( isset( $this->font_registry[ $choice ] ) && file_exists( $this->font_registry[ $choice ] ) ) {
            return $this->font_registry[ $choice ];
        }

        $system_font = $this->get_system_font_by_choice( $choice );
        if ( $system_font ) {
            return $system_font;
        }

        if ( $choice !== $fallback_choice ) {
            return $this->get_font_by_choice( $fallback_choice, 'sans' );
        }

        return $this->get_safe_font();
    }

    private function get_normal_font( $primary_font = '' ) {
        foreach ( array_filter( array( $primary_font ) ) as $font ) {
            if ( file_exists( $font ) ) {
                return $font;
            }
        }

        foreach ( $this->fallback_fonts as $font ) {
            if ( file_exists( $font ) ) {
                return $font;
            }
        }

        if ( ! empty( $this->font_path ) && file_exists( $this->font_path ) ) {
            return $this->font_path;
        }

        return null;
    }

    private function get_safe_font( $primary_font = '' ) {
        if ( ! empty( $primary_font ) && file_exists( $primary_font ) ) {
            return $primary_font;
        }

        if ( ! empty( $this->font_path ) && file_exists( $this->font_path ) ) {
            return $this->font_path;
        }

        foreach ( $this->fallback_fonts as $font ) {
            if ( file_exists( $font ) ) {
                return $font;
            }
        }

        return null;
    }

    private function create_pdf_from_image( $image_path, $tmp_dir ) {
        if ( ! file_exists( $image_path ) ) {
            return false;
        }

        $image_info = getimagesize( $image_path );
        if ( ! $image_info ) {
            return false;
        }

        list( $img_width, $img_height ) = $image_info;

        if ( $this->has_tcpdf() ) {
            $pdf_path = $this->create_pdf_with_tcpdf( $image_path, $img_width, $img_height, $tmp_dir );
            if ( $pdf_path ) {
                return $pdf_path;
            }
        }

        return $this->build_simple_pdf( $image_path, $img_width, $img_height, $tmp_dir );
    }

    private function create_pdf_with_tcpdf( $image_path, $img_width, $img_height, $tmp_dir ) {
        $tcpdf_paths = array(
            WP_CONTENT_DIR . '/vendor/tecnickcom/tcpdf/tcpdf.php',
            SMARTCERTIFY_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php',
        );

        foreach ( $tcpdf_paths as $tcpdf_file ) {
            if ( file_exists( $tcpdf_file ) ) {
                try {
                    require_once $tcpdf_file;

                    $pdf = new TCPDF( 'L', 'mm', array( $img_width / 2.834645669, $img_height / 2.834645669 ) );
                    $pdf->SetAutoPageBreak( false );
                    $pdf->AddPage();
                    $pdf->Image( $image_path, 0, 0, $pdf->getPageWidth(), $pdf->getPageHeight(), 'PNG' );

                    $pdf_path = $tmp_dir . '/certificate_' . uniqid( '', true ) . '.pdf';
                    $pdf->Output( $pdf_path, 'F' );

                    return $pdf_path;
                } catch ( Exception $e ) {
                    SmartCert_Logger::debug( array( 'tcpdf_error' => $e->getMessage() ) );
                }
            }
        }

        return false;
    }

    private function has_tcpdf() {
        if ( class_exists( 'TCPDF' ) ) {
            return true;
        }

        $paths = array(
            WP_CONTENT_DIR . '/vendor/tecnickcom/tcpdf/tcpdf.php',
            SMARTCERTIFY_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php',
        );

        foreach ( $paths as $path ) {
            if ( file_exists( $path ) ) {
                return true;
            }
        }

        return false;
    }

    private function build_simple_pdf( $image_path, $img_width, $img_height, $tmp_dir ) {
        $image_bytes = file_get_contents( $image_path );
        if ( ! $image_bytes ) {
            return false;
        }

        $img = imagecreatefromstring( $image_bytes );
        if ( ! $img ) {
            return false;
        }

        $rgb_img = imagecreatetruecolor( $img_width, $img_height );
        if ( ! $rgb_img ) {
            imagedestroy( $img );
            return false;
        }

        imagecopy( $rgb_img, $img, 0, 0, 0, 0, $img_width, $img_height );
        imagedestroy( $img );

        ob_start();
        imagejpeg( $rgb_img, null, 90 );
        $jpeg_data = ob_get_clean();
        imagedestroy( $rgb_img );

        if ( ! $jpeg_data ) {
            return false;
        }

        $dpi = 96;
        $pdf_width = round( ( $img_width / $dpi ) * 72, 2 );
        $pdf_height = round( ( $img_height / $dpi ) * 72, 2 );
        $pdf_content = $this->construct_pdf_structure( $jpeg_data, $pdf_width, $pdf_height, $img_width, $img_height );

        if ( substr( $pdf_content, 0, 4 ) !== '%PDF' ) {
            return false;
        }

        $pdf_path = $tmp_dir . '/certificate_' . uniqid( '', true ) . '.pdf';
        file_put_contents( $pdf_path, $pdf_content );

        return file_exists( $pdf_path ) ? $pdf_path : false;
    }

    private function construct_pdf_structure( $image_data, $pdf_width, $pdf_height, $img_width_px, $img_height_px ) {
        $image_size = strlen( $image_data );

        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /XObject << /Image 4 0 R >> >> /MediaBox [0 0 {$pdf_width} {$pdf_height}] /Contents 5 0 R >>\nendobj\n";
        $obj4 = "4 0 obj\n<< /Type /XObject /Subtype /Image /Width {$img_width_px} /Height {$img_height_px} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$image_size} >>\nstream\n" . $image_data . "\nendstream\nendobj\n";
        $content_stream = "q\n{$pdf_width} 0 0 {$pdf_height} 0 0 cm\n/Image Do\nQ";
        $obj5 = "5 0 obj\n<< /Length " . strlen( $content_stream ) . " >>\nstream\n{$content_stream}\nendstream\nendobj\n";

        $header = "%PDF-1.4\n";
        $xref_data = array();
        $current_pos = strlen( $header );

        $xref_data[1] = $current_pos;
        $current_pos += strlen( $obj1 );
        $xref_data[2] = $current_pos;
        $current_pos += strlen( $obj2 );
        $xref_data[3] = $current_pos;
        $current_pos += strlen( $obj3 );
        $xref_data[4] = $current_pos;
        $current_pos += strlen( $obj4 );
        $xref_data[5] = $current_pos;
        $current_pos += strlen( $obj5 );

        $xref_pos = $current_pos;
        $xref = "xref\n0 6\n0000000000 65535 f \n";
        foreach ( $xref_data as $pos ) {
            $xref .= sprintf( "%010d 00000 n \n", $pos );
        }

        $trailer = "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xref_pos}\n%%EOF";

        return $header . $obj1 . $obj2 . $obj3 . $obj4 . $obj5 . $xref . $trailer;
    }

    private function is_valid_pdf( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return false;
        }

        $handle = fopen( $file_path, 'rb' );
        if ( ! $handle ) {
            return false;
        }

        $header = fread( $handle, 4 );
        fclose( $handle );

        return '%PDF' === $header;
    }
}
