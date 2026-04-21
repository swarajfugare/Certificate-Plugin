<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Lightweight bundled QR generator.
 *
 * This implementation is intentionally focused on SmartCertify serial payloads.
 * It renders QR Version 1-L in alphanumeric mode, which comfortably fits values
 * such as `SC-XXXXXXXXXX` without depending on remote APIs.
 */
class SmartCert_Local_QR {
    const VERSION = 1;
    const SIZE = 21;
    const DATA_CODEWORDS = 19;
    const EC_CODEWORDS = 7;
    const FORMAT_L_MASK_0 = 0b111011111000100;

    private static $alphanumeric = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

    public static function render_png( $payload, $target_path, $size = 240 ) {
        if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagepng' ) ) {
            return false;
        }

        $payload = strtoupper( trim( (string) $payload ) );
        if ( '' === $payload || ! self::is_supported_payload( $payload ) ) {
            return false;
        }

        $matrix = self::build_matrix( $payload );
        if ( empty( $matrix ) ) {
            return false;
        }

        return self::render_matrix_to_png( $matrix, $target_path, max( 64, intval( $size ) ) );
    }

    private static function is_supported_payload( $payload ) {
        $length = strlen( $payload );
        if ( $length > 25 ) {
            return false;
        }

        for ( $i = 0; $i < $length; $i++ ) {
            if ( false === strpos( self::$alphanumeric, $payload[ $i ] ) ) {
                return false;
            }
        }

        return true;
    }

    private static function build_matrix( $payload ) {
        $data_codewords = self::build_data_codewords( $payload );
        if ( empty( $data_codewords ) ) {
            return array();
        }

        $ec_codewords = self::build_error_correction( $data_codewords );
        $bits = self::bytes_to_bits( array_merge( $data_codewords, $ec_codewords ) );

        $matrix = array();
        $reserved = array();

        for ( $y = 0; $y < self::SIZE; $y++ ) {
            $matrix[ $y ] = array_fill( 0, self::SIZE, null );
            $reserved[ $y ] = array_fill( 0, self::SIZE, false );
        }

        self::place_finder( $matrix, $reserved, 0, 0 );
        self::place_finder( $matrix, $reserved, self::SIZE - 7, 0 );
        self::place_finder( $matrix, $reserved, 0, self::SIZE - 7 );
        self::place_timing_patterns( $matrix, $reserved );
        self::reserve_format_areas( $reserved );
        self::set_module( $matrix, $reserved, 8, ( 4 * self::VERSION ) + 9, 1 );

        self::place_data_bits( $matrix, $reserved, $bits );
        self::apply_mask_zero( $matrix, $reserved );
        self::place_format_information( $matrix, $reserved );

        return $matrix;
    }

    private static function build_data_codewords( $payload ) {
        $bits = '0010'; // alphanumeric mode
        $bits .= str_pad( decbin( strlen( $payload ) ), 9, '0', STR_PAD_LEFT );

        $length = strlen( $payload );
        for ( $i = 0; $i < $length; $i += 2 ) {
            $first = strpos( self::$alphanumeric, $payload[ $i ] );
            if ( false === $first ) {
                return array();
            }

            if ( isset( $payload[ $i + 1 ] ) ) {
                $second = strpos( self::$alphanumeric, $payload[ $i + 1 ] );
                if ( false === $second ) {
                    return array();
                }
                $bits .= str_pad( decbin( ( $first * 45 ) + $second ), 11, '0', STR_PAD_LEFT );
            } else {
                $bits .= str_pad( decbin( $first ), 6, '0', STR_PAD_LEFT );
            }
        }

        $capacity = self::DATA_CODEWORDS * 8;
        $remaining = $capacity - strlen( $bits );
        if ( $remaining < 0 ) {
            return array();
        }

        $bits .= str_repeat( '0', min( 4, $remaining ) );
        while ( strlen( $bits ) % 8 !== 0 ) {
            $bits .= '0';
        }

        $codewords = array();
        for ( $i = 0; $i < strlen( $bits ); $i += 8 ) {
            $codewords[] = bindec( substr( $bits, $i, 8 ) );
        }

        $pads = array( 0xEC, 0x11 );
        $pad_index = 0;
        while ( count( $codewords ) < self::DATA_CODEWORDS ) {
            $codewords[] = $pads[ $pad_index % 2 ];
            $pad_index++;
        }

        return $codewords;
    }

    private static function build_error_correction( $data_codewords ) {
        $generator = array( 87, 229, 146, 149, 238, 102, 21 );
        $message = array_merge( $data_codewords, array_fill( 0, self::EC_CODEWORDS, 0 ) );

        for ( $i = 0; $i < count( $data_codewords ); $i++ ) {
            $factor = $message[ $i ];
            if ( 0 === $factor ) {
                continue;
            }

            foreach ( $generator as $index => $coefficient ) {
                $message[ $i + $index + 1 ] ^= self::gf_multiply( $coefficient, $factor );
            }
        }

        return array_slice( $message, -self::EC_CODEWORDS );
    }

    private static function gf_multiply( $a, $b ) {
        $result = 0;

        while ( $b > 0 ) {
            if ( $b & 1 ) {
                $result ^= $a;
            }

            $a <<= 1;
            if ( $a & 0x100 ) {
                $a ^= 0x11D;
            }

            $b >>= 1;
        }

        return $result;
    }

    private static function bytes_to_bits( $bytes ) {
        $bits = '';
        foreach ( $bytes as $byte ) {
            $bits .= str_pad( decbin( intval( $byte ) ), 8, '0', STR_PAD_LEFT );
        }
        return $bits;
    }

    private static function place_finder( &$matrix, &$reserved, $start_x, $start_y ) {
        for ( $y = -1; $y <= 7; $y++ ) {
            for ( $x = -1; $x <= 7; $x++ ) {
                $target_x = $start_x + $x;
                $target_y = $start_y + $y;

                if ( $target_x < 0 || $target_y < 0 || $target_x >= self::SIZE || $target_y >= self::SIZE ) {
                    continue;
                }

                $is_separator = ( $x === -1 || $x === 7 || $y === -1 || $y === 7 );
                $is_border = ( 0 === $x || 6 === $x || 0 === $y || 6 === $y );
                $is_center = ( $x >= 2 && $x <= 4 && $y >= 2 && $y <= 4 );
                $value = ( $is_separator ? 0 : ( $is_border || $is_center ? 1 : 0 ) );

                self::set_module( $matrix, $reserved, $target_x, $target_y, $value );
            }
        }
    }

    private static function place_timing_patterns( &$matrix, &$reserved ) {
        for ( $i = 8; $i < self::SIZE - 8; $i++ ) {
            $value = ( $i % 2 === 0 ) ? 1 : 0;
            self::set_module( $matrix, $reserved, $i, 6, $value );
            self::set_module( $matrix, $reserved, 6, $i, $value );
        }
    }

    private static function reserve_format_areas( &$reserved ) {
        for ( $i = 0; $i < 9; $i++ ) {
            if ( 6 !== $i ) {
                $reserved[8][ $i ] = true;
                $reserved[ $i ][8] = true;
            }
        }

        for ( $i = 0; $i < 8; $i++ ) {
            $reserved[8][ self::SIZE - 1 - $i ] = true;
            if ( $i < 7 ) {
                $reserved[ self::SIZE - 1 - $i ][8] = true;
            }
        }
    }

    private static function place_data_bits( &$matrix, &$reserved, $bits ) {
        $bit_index = 0;
        $direction = -1;

        for ( $x = self::SIZE - 1; $x > 0; $x -= 2 ) {
            if ( 6 === $x ) {
                $x--;
            }

            for ( $offset = 0; $offset < self::SIZE; $offset++ ) {
                $y = ( -1 === $direction ) ? ( self::SIZE - 1 - $offset ) : $offset;

                for ( $col_offset = 0; $col_offset < 2; $col_offset++ ) {
                    $current_x = $x - $col_offset;
                    if ( $reserved[ $y ][ $current_x ] ) {
                        continue;
                    }

                    $bit = ( $bit_index < strlen( $bits ) ) ? intval( $bits[ $bit_index ] ) : 0;
                    $matrix[ $y ][ $current_x ] = $bit;
                    $bit_index++;
                }
            }

            $direction *= -1;
        }
    }

    private static function apply_mask_zero( &$matrix, $reserved ) {
        for ( $y = 0; $y < self::SIZE; $y++ ) {
            for ( $x = 0; $x < self::SIZE; $x++ ) {
                if ( $reserved[ $y ][ $x ] ) {
                    continue;
                }

                if ( ( ( $x + $y ) % 2 ) === 0 ) {
                    $matrix[ $y ][ $x ] = $matrix[ $y ][ $x ] ? 0 : 1;
                }
            }
        }
    }

    private static function place_format_information( &$matrix, &$reserved ) {
        $format = self::FORMAT_L_MASK_0;

        for ( $i = 0; $i <= 5; $i++ ) {
            self::set_module( $matrix, $reserved, 8, $i, self::format_bit( $format, $i ) );
        }
        self::set_module( $matrix, $reserved, 8, 7, self::format_bit( $format, 6 ) );
        self::set_module( $matrix, $reserved, 8, 8, self::format_bit( $format, 7 ) );
        self::set_module( $matrix, $reserved, 7, 8, self::format_bit( $format, 8 ) );
        for ( $i = 9; $i <= 14; $i++ ) {
            self::set_module( $matrix, $reserved, 14 - $i, 8, self::format_bit( $format, $i ) );
        }

        for ( $i = 0; $i <= 7; $i++ ) {
            self::set_module( $matrix, $reserved, self::SIZE - 1 - $i, 8, self::format_bit( $format, $i ) );
        }
        for ( $i = 8; $i <= 14; $i++ ) {
            self::set_module( $matrix, $reserved, 8, self::SIZE - 15 + $i, self::format_bit( $format, $i ) );
        }
    }

    private static function format_bit( $format, $index ) {
        return ( $format >> $index ) & 1;
    }

    private static function set_module( &$matrix, &$reserved, $x, $y, $value ) {
        if ( $x < 0 || $y < 0 || $x >= self::SIZE || $y >= self::SIZE ) {
            return;
        }

        $matrix[ $y ][ $x ] = intval( $value ) ? 1 : 0;
        $reserved[ $y ][ $x ] = true;
    }

    private static function render_matrix_to_png( $matrix, $target_path, $size ) {
        $quiet_zone = 1;
        $total = self::SIZE + ( $quiet_zone * 2 );
        $image = imagecreatetruecolor( $size, $size );
        if ( ! $image ) {
            return false;
        }

        $white = imagecolorallocate( $image, 255, 255, 255 );
        $black = imagecolorallocate( $image, 0, 0, 0 );
        imagefilledrectangle( $image, 0, 0, $size, $size, $white );

        $scale = $size / $total;

        for ( $y = 0; $y < self::SIZE; $y++ ) {
            for ( $x = 0; $x < self::SIZE; $x++ ) {
                if ( empty( $matrix[ $y ][ $x ] ) ) {
                    continue;
                }

                $left = (int) floor( ( $x + $quiet_zone ) * $scale );
                $top = (int) floor( ( $y + $quiet_zone ) * $scale );
                $right = (int) ceil( ( $x + $quiet_zone + 1 ) * $scale ) - 1;
                $bottom = (int) ceil( ( $y + $quiet_zone + 1 ) * $scale ) - 1;
                imagefilledrectangle( $image, $left, $top, $right, $bottom, $black );
            }
        }

        $saved = imagepng( $image, $target_path );
        imagedestroy( $image );

        return $saved && file_exists( $target_path );
    }
}
