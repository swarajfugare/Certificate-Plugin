<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartCert_DB {
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $classes_table = $wpdb->prefix . 'smartcertify_classes';
        $batches_table = $wpdb->prefix . 'smartcertify_batches';
        $codes_table   = $wpdb->prefix . 'smartcertify_codes';
        $logs_table    = $wpdb->prefix . 'smartcertify_logs';
        $certs_table   = $wpdb->prefix . 'smartcertify_certificates';

        dbDelta(
            "CREATE TABLE $classes_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                class_name varchar(191) NOT NULL,
                certificate_template text DEFAULT '',
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY class_name (class_name)
            ) $charset_collate;"
        );

        dbDelta(
            "CREATE TABLE $batches_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                class_id mediumint(9) NOT NULL DEFAULT 0,
                batch_name varchar(191) NOT NULL,
                teacher_name varchar(191) DEFAULT '',
                teacher_signature_url text,
                teacher_signature_id bigint(20) NOT NULL DEFAULT 0,
                is_active tinyint(1) NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY class_id (class_id),
                KEY batch_name (batch_name)
            ) $charset_collate;"
        );

        dbDelta(
            "CREATE TABLE $codes_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                class_id mediumint(9) NOT NULL DEFAULT 0,
                batch_id mediumint(9) NOT NULL DEFAULT 0,
                class_name varchar(191) NOT NULL,
                code varchar(50) NOT NULL,
                student_name varchar(191) DEFAULT '',
                student_email varchar(191) DEFAULT '',
                student_phone varchar(50) DEFAULT '',
                status varchar(30) NOT NULL DEFAULT 'active',
                download_count int NOT NULL DEFAULT 0,
                download_limit int NOT NULL DEFAULT 3,
                last_generated_at datetime DEFAULT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY class_id (class_id),
                KEY batch_id (batch_id),
                KEY class_name (class_name),
                KEY code (code),
                UNIQUE KEY class_batch_code_unique (class_name(120),batch_id,code(50))
            ) $charset_collate;"
        );

        dbDelta(
            "CREATE TABLE $logs_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                certificate_id mediumint(9) NOT NULL DEFAULT 0,
                user_id bigint(20) NOT NULL DEFAULT 0,
                class_id mediumint(9) NOT NULL DEFAULT 0,
                batch_id mediumint(9) NOT NULL DEFAULT 0,
                student_name varchar(191) NOT NULL,
                class_name varchar(191) NOT NULL,
                batch_name varchar(191) DEFAULT '',
                teacher_name varchar(191) DEFAULT '',
                code varchar(50) NOT NULL,
                ip_address varchar(100) NOT NULL,
                timestamp datetime NOT NULL,
                action varchar(50) NOT NULL DEFAULT 'generated',
                serial varchar(100) DEFAULT '',
                template_version varchar(100) DEFAULT '',
                verification_url text,
                status varchar(30) NOT NULL DEFAULT 'valid',
                PRIMARY KEY  (id),
                KEY certificate_id (certificate_id),
                KEY user_id (user_id),
                KEY class_id (class_id),
                KEY batch_id (batch_id),
                KEY serial (serial)
            ) $charset_collate;"
        );

        dbDelta(
            "CREATE TABLE $certs_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                file_name varchar(191) NOT NULL,
                user_id bigint(20) NOT NULL DEFAULT 0,
                student_name varchar(191) NOT NULL,
                student_email varchar(191) DEFAULT '',
                student_phone varchar(50) DEFAULT '',
                class_id mediumint(9) NOT NULL DEFAULT 0,
                batch_id mediumint(9) NOT NULL DEFAULT 0,
                class_name varchar(191) NOT NULL,
                batch_name varchar(191) DEFAULT '',
                teacher_name varchar(191) DEFAULT '',
                code varchar(50) NOT NULL,
                serial varchar(100) DEFAULT '',
                template_version varchar(100) DEFAULT '',
                verification_url text,
                status varchar(30) NOT NULL DEFAULT 'valid',
                certificate_expires_at datetime DEFAULT NULL,
                generated_at datetime NOT NULL,
                expires_at datetime NOT NULL,
                revoked_at datetime DEFAULT NULL,
                revoke_reason text,
                renewed_from_id mediumint(9) NOT NULL DEFAULT 0,
                renewed_to_id mediumint(9) NOT NULL DEFAULT 0,
                delivery_email_status varchar(30) NOT NULL DEFAULT '',
                delivery_whatsapp_status varchar(30) NOT NULL DEFAULT '',
                delivery_notes text,
                file_path text NOT NULL,
                deleted_at datetime DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY class_batch_code (class_id,batch_id,code(50)),
                KEY expires_at (expires_at),
                KEY certificate_expires_at (certificate_expires_at),
                KEY deleted_at (deleted_at),
                KEY status (status),
                KEY serial (serial)
            ) $charset_collate;"
        );

        self::update_schema();
    }

    public static function update_schema() {
        global $wpdb;

        $classes_table = $wpdb->prefix . 'smartcertify_classes';
        $batches_table = $wpdb->prefix . 'smartcertify_batches';
        $codes_table   = $wpdb->prefix . 'smartcertify_codes';
        $logs_table    = $wpdb->prefix . 'smartcertify_logs';
        $certs_table   = $wpdb->prefix . 'smartcertify_certificates';

        self::maybe_create_missing_tables();

        self::maybe_add_column( $classes_table, 'created_at', "ALTER TABLE $classes_table ADD COLUMN created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00'" );

        self::maybe_add_column( $codes_table, 'class_id', "ALTER TABLE $codes_table ADD COLUMN class_id mediumint(9) NOT NULL DEFAULT 0" );
        self::maybe_add_column( $codes_table, 'batch_id', "ALTER TABLE $codes_table ADD COLUMN batch_id mediumint(9) NOT NULL DEFAULT 0" );
        self::maybe_add_column( $codes_table, 'student_name', "ALTER TABLE $codes_table ADD COLUMN student_name varchar(191) DEFAULT ''" );
        self::maybe_add_column( $codes_table, 'student_email', "ALTER TABLE $codes_table ADD COLUMN student_email varchar(191) DEFAULT ''" );
        self::maybe_add_column( $codes_table, 'student_phone', "ALTER TABLE $codes_table ADD COLUMN student_phone varchar(50) DEFAULT ''" );
        self::maybe_add_column( $codes_table, 'status', "ALTER TABLE $codes_table ADD COLUMN status varchar(30) NOT NULL DEFAULT 'active'" );
        self::maybe_add_column( $codes_table, 'download_count', "ALTER TABLE $codes_table ADD COLUMN download_count int NOT NULL DEFAULT 0" );
        self::maybe_add_column( $codes_table, 'download_limit', "ALTER TABLE $codes_table ADD COLUMN download_limit int NOT NULL DEFAULT 3" );
        self::maybe_add_column( $codes_table, 'last_generated_at', "ALTER TABLE $codes_table ADD COLUMN last_generated_at datetime DEFAULT NULL" );
        self::maybe_add_column( $codes_table, 'created_at', "ALTER TABLE $codes_table ADD COLUMN created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00'" );

        self::maybe_add_column( $logs_table, 'certificate_id', "ALTER TABLE $logs_table ADD COLUMN certificate_id mediumint(9) NOT NULL DEFAULT 0" );
        self::maybe_add_column( $logs_table, 'user_id', "ALTER TABLE $logs_table ADD COLUMN user_id bigint(20) NOT NULL DEFAULT 0" );
        self::maybe_add_column( $logs_table, 'class_id', "ALTER TABLE $logs_table ADD COLUMN class_id mediumint(9) NOT NULL DEFAULT 0" );
        self::maybe_add_column( $logs_table, 'batch_id', "ALTER TABLE $logs_table ADD COLUMN batch_id mediumint(9) NOT NULL DEFAULT 0" );
        self::maybe_add_column( $logs_table, 'batch_name', "ALTER TABLE $logs_table ADD COLUMN batch_name varchar(191) DEFAULT ''" );
        self::maybe_add_column( $logs_table, 'teacher_name', "ALTER TABLE $logs_table ADD COLUMN teacher_name varchar(191) DEFAULT ''" );
        self::maybe_add_column( $logs_table, 'action', "ALTER TABLE $logs_table ADD COLUMN action varchar(50) NOT NULL DEFAULT 'generated'" );
        self::maybe_add_column( $logs_table, 'serial', "ALTER TABLE $logs_table ADD COLUMN serial varchar(100) DEFAULT ''" );
        self::maybe_add_column( $logs_table, 'template_version', "ALTER TABLE $logs_table ADD COLUMN template_version varchar(100) DEFAULT ''" );
        self::maybe_add_column( $logs_table, 'verification_url', "ALTER TABLE $logs_table ADD COLUMN verification_url text" );
        self::maybe_add_column( $logs_table, 'status', "ALTER TABLE $logs_table ADD COLUMN status varchar(30) NOT NULL DEFAULT 'valid'" );

        self::maybe_add_column( $certs_table, 'user_id', "ALTER TABLE $certs_table ADD COLUMN user_id bigint(20) NOT NULL DEFAULT 0" );
        self::maybe_add_column( $certs_table, 'student_email', "ALTER TABLE $certs_table ADD COLUMN student_email varchar(191) DEFAULT ''" );
        self::maybe_add_column( $certs_table, 'student_phone', "ALTER TABLE $certs_table ADD COLUMN student_phone varchar(50) DEFAULT ''" );
        self::maybe_add_column( $certs_table, 'class_id', "ALTER TABLE $certs_table ADD COLUMN class_id mediumint(9) NOT NULL DEFAULT 0" );
        self::maybe_add_column( $certs_table, 'batch_id', "ALTER TABLE $certs_table ADD COLUMN batch_id mediumint(9) NOT NULL DEFAULT 0" );
        self::maybe_add_column( $certs_table, 'batch_name', "ALTER TABLE $certs_table ADD COLUMN batch_name varchar(191) DEFAULT ''" );
        self::maybe_add_column( $certs_table, 'teacher_name', "ALTER TABLE $certs_table ADD COLUMN teacher_name varchar(191) DEFAULT ''" );
        self::maybe_add_column( $certs_table, 'serial', "ALTER TABLE $certs_table ADD COLUMN serial varchar(100) DEFAULT ''" );
        self::maybe_add_column( $certs_table, 'template_version', "ALTER TABLE $certs_table ADD COLUMN template_version varchar(100) DEFAULT ''" );
        self::maybe_add_column( $certs_table, 'verification_url', "ALTER TABLE $certs_table ADD COLUMN verification_url text" );
        self::maybe_add_column( $certs_table, 'status', "ALTER TABLE $certs_table ADD COLUMN status varchar(30) NOT NULL DEFAULT 'valid'" );
        self::maybe_add_column( $certs_table, 'certificate_expires_at', "ALTER TABLE $certs_table ADD COLUMN certificate_expires_at datetime DEFAULT NULL" );
        self::maybe_add_column( $certs_table, 'revoked_at', "ALTER TABLE $certs_table ADD COLUMN revoked_at datetime DEFAULT NULL" );
        self::maybe_add_column( $certs_table, 'revoke_reason', "ALTER TABLE $certs_table ADD COLUMN revoke_reason text" );
        self::maybe_add_column( $certs_table, 'renewed_from_id', "ALTER TABLE $certs_table ADD COLUMN renewed_from_id mediumint(9) NOT NULL DEFAULT 0" );
        self::maybe_add_column( $certs_table, 'renewed_to_id', "ALTER TABLE $certs_table ADD COLUMN renewed_to_id mediumint(9) NOT NULL DEFAULT 0" );
        self::maybe_add_column( $certs_table, 'delivery_email_status', "ALTER TABLE $certs_table ADD COLUMN delivery_email_status varchar(30) NOT NULL DEFAULT ''" );
        self::maybe_add_column( $certs_table, 'delivery_whatsapp_status', "ALTER TABLE $certs_table ADD COLUMN delivery_whatsapp_status varchar(30) NOT NULL DEFAULT ''" );
        self::maybe_add_column( $certs_table, 'delivery_notes', "ALTER TABLE $certs_table ADD COLUMN delivery_notes text" );

        self::maybe_add_index( $codes_table, 'class_batch_code_unique', "ALTER TABLE $codes_table ADD UNIQUE KEY class_batch_code_unique (class_name(120), batch_id, code(50))" );
        self::maybe_add_index( $logs_table, 'certificate_id', "ALTER TABLE $logs_table ADD KEY certificate_id (certificate_id)" );
        self::maybe_add_index( $logs_table, 'user_id', "ALTER TABLE $logs_table ADD KEY user_id (user_id)" );
        self::maybe_add_index( $logs_table, 'serial', "ALTER TABLE $logs_table ADD KEY serial (serial)" );
        self::maybe_add_index( $certs_table, 'user_id', "ALTER TABLE $certs_table ADD KEY user_id (user_id)" );
        self::maybe_add_index( $certs_table, 'class_batch_code', "ALTER TABLE $certs_table ADD KEY class_batch_code (class_id, batch_id, code(50))" );
        self::maybe_add_index( $certs_table, 'certificate_expires_at', "ALTER TABLE $certs_table ADD KEY certificate_expires_at (certificate_expires_at)" );
        self::maybe_add_index( $certs_table, 'status', "ALTER TABLE $certs_table ADD KEY status (status)" );
        self::maybe_add_index( $certs_table, 'serial', "ALTER TABLE $certs_table ADD KEY serial (serial)" );
        self::maybe_add_index( $batches_table, 'class_id', "ALTER TABLE $batches_table ADD KEY class_id (class_id)" );

        self::remove_legacy_single_code_index( $codes_table );
        self::backfill_relationships();
        self::backfill_lifecycle_fields();
        self::ensure_default_batches();
    }

    private static function maybe_create_missing_tables() {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();
        $batches_table = $wpdb->prefix . 'smartcertify_batches';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '$batches_table'" ) !== $batches_table ) {
            $wpdb->query(
                "CREATE TABLE $batches_table (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    class_id mediumint(9) NOT NULL DEFAULT 0,
                    batch_name varchar(191) NOT NULL,
                    teacher_name varchar(191) DEFAULT '',
                    teacher_signature_url text,
                    teacher_signature_id bigint(20) NOT NULL DEFAULT 0,
                    is_active tinyint(1) NOT NULL DEFAULT 1,
                    created_at datetime NOT NULL,
                    PRIMARY KEY  (id),
                    KEY class_id (class_id),
                    KEY batch_name (batch_name)
                ) $charset"
            );
        }
    }

    private static function maybe_add_column( $table, $column, $sql ) {
        global $wpdb;

        if ( $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM $table LIKE %s", $column ) ) !== $column ) {
            $wpdb->query( $sql );
        }
    }

    private static function maybe_add_index( $table, $index_name, $sql ) {
        global $wpdb;

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW INDEX FROM $table WHERE Key_name = %s",
                $index_name
            )
        );

        if ( ! $exists ) {
            $wpdb->query( $sql );
        }
    }

    private static function remove_legacy_single_code_index( $table ) {
        global $wpdb;

        $indexes = $wpdb->get_results( "SHOW INDEX FROM $table" );
        if ( ! $indexes ) {
            return;
        }

        foreach ( $indexes as $index ) {
            if ( isset( $index->Key_name ) && 'code' === $index->Key_name && 1 === intval( $index->Seq_in_index ) ) {
                $wpdb->query( "ALTER TABLE $table DROP INDEX code" );
                break;
            }
        }
    }

    private static function backfill_relationships() {
        global $wpdb;

        $classes_table = $wpdb->prefix . 'smartcertify_classes';
        $batches_table = $wpdb->prefix . 'smartcertify_batches';
        $codes_table   = $wpdb->prefix . 'smartcertify_codes';
        $logs_table    = $wpdb->prefix . 'smartcertify_logs';
        $certs_table   = $wpdb->prefix . 'smartcertify_certificates';

        $wpdb->query(
            "UPDATE $codes_table c
             INNER JOIN $classes_table cl ON LOWER(TRIM(c.class_name)) = LOWER(TRIM(cl.class_name))
             SET c.class_id = cl.id
             WHERE c.class_id = 0"
        );

        $wpdb->query(
            "UPDATE $logs_table l
             INNER JOIN $classes_table cl ON LOWER(TRIM(l.class_name)) = LOWER(TRIM(cl.class_name))
             SET l.class_id = cl.id
             WHERE l.class_id = 0"
        );

        $wpdb->query(
            "UPDATE $certs_table c
             INNER JOIN $classes_table cl ON LOWER(TRIM(c.class_name)) = LOWER(TRIM(cl.class_name))
             SET c.class_id = cl.id
             WHERE c.class_id = 0"
        );

        $wpdb->query(
            "UPDATE $codes_table c
             INNER JOIN $batches_table b ON c.batch_id = b.id
             SET c.class_id = b.class_id
             WHERE c.batch_id > 0 AND c.class_id = 0"
        );

        $wpdb->query(
            "UPDATE $logs_table l
             INNER JOIN $batches_table b ON l.batch_id = b.id
             SET l.batch_name = b.batch_name
             WHERE l.batch_id > 0 AND (l.batch_name = '' OR l.batch_name IS NULL)"
        );
    }

    private static function backfill_lifecycle_fields() {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'smartcertify_logs';
        $certs_table = $wpdb->prefix . 'smartcertify_certificates';

        $wpdb->query(
            "UPDATE $certs_table
             SET certificate_expires_at = expires_at
             WHERE (certificate_expires_at IS NULL OR certificate_expires_at = '0000-00-00 00:00:00')
             AND expires_at IS NOT NULL"
        );

        $wpdb->query(
            "UPDATE $certs_table
             SET status = CASE
                 WHEN revoked_at IS NOT NULL THEN 'revoked'
                 WHEN renewed_to_id > 0 THEN 'renewed'
                 WHEN deleted_at IS NOT NULL THEN 'deleted'
                 WHEN certificate_expires_at IS NOT NULL AND certificate_expires_at < NOW() THEN 'expired'
                 ELSE 'valid'
             END"
        );

        $wpdb->query(
            "UPDATE $certs_table c
             INNER JOIN $logs_table l ON c.serial <> '' AND l.serial = c.serial
             SET c.teacher_name = l.teacher_name
             WHERE (c.teacher_name = '' OR c.teacher_name IS NULL)
             AND l.teacher_name <> ''"
        );

        $wpdb->query(
            "UPDATE $logs_table l
             INNER JOIN $certs_table c ON c.serial <> '' AND l.serial = c.serial
             SET l.certificate_id = c.id
             WHERE l.certificate_id = 0"
        );
    }

    public static function ensure_default_batches( $class_id = 0 ) {
        global $wpdb;

        $classes_table = $wpdb->prefix . 'smartcertify_classes';
        $query = "SELECT id, class_name FROM $classes_table";
        $params = array();

        if ( intval( $class_id ) > 0 ) {
            $query .= ' WHERE id = %d';
            $params[] = intval( $class_id );
        }

        $query .= ' ORDER BY id ASC';

        $classes = $params
            ? $wpdb->get_results(
                call_user_func_array(
                    array( $wpdb, 'prepare' ),
                    array_merge( array( $query ), $params )
                )
            )
            : $wpdb->get_results( $query );

        if ( ! $classes ) {
            return;
        }

        foreach ( $classes as $class ) {
            self::ensure_default_batch_for_class( $class );
        }
    }

    public static function get_table_map() {
        global $wpdb;

        return array(
            'classes'      => $wpdb->prefix . 'smartcertify_classes',
            'batches'      => $wpdb->prefix . 'smartcertify_batches',
            'codes'        => $wpdb->prefix . 'smartcertify_codes',
            'logs'         => $wpdb->prefix . 'smartcertify_logs',
            'certificates' => $wpdb->prefix . 'smartcertify_certificates',
        );
    }

    private static function ensure_default_batch_for_class( $class ) {
        global $wpdb;

        $class_id = intval( $class->id ?? 0 );
        $class_name = sanitize_text_field( $class->class_name ?? '' );

        if ( ! $class_id || '' === $class_name ) {
            return;
        }

        $batches_table = $wpdb->prefix . 'smartcertify_batches';
        $default_batch_name = SmartCert_Helpers::get_default_batch_name();
        $has_batches = intval(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $batches_table WHERE class_id = %d",
                    $class_id
                )
            )
        ) > 0;
        $legacy_rows = self::get_legacy_unassigned_row_count( $class_id, $class_name );

        if ( ! $has_batches && 0 === $legacy_rows ) {
            $legacy_rows = 1;
        }

        if ( 0 === $legacy_rows && $has_batches ) {
            return;
        }

        $batch = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $batches_table WHERE class_id = %d AND LOWER(TRIM(batch_name)) = LOWER(TRIM(%s)) LIMIT 1",
                $class_id,
                $default_batch_name
            )
        );

        $defaults = SmartCert_Helpers::get_default_batch_settings();

        if ( ! $batch ) {

            $wpdb->insert(
                $batches_table,
                array(
                    'class_id'             => $class_id,
                    'batch_name'           => $defaults['batch_name'],
                    'teacher_name'         => $defaults['teacher_name'],
                    'teacher_signature_url'=> $defaults['teacher_signature_url'],
                    'teacher_signature_id' => intval( $defaults['teacher_signature_id'] ),
                    'is_active'            => 1,
                    'created_at'           => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%s', '%s', '%d', '%d', '%s' )
            );

            $batch = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $batches_table WHERE class_id = %d AND LOWER(TRIM(batch_name)) = LOWER(TRIM(%s)) LIMIT 1",
                    $class_id,
                    $default_batch_name
                )
            );
        }

        if ( ! $batch ) {
            return;
        }

        $batch_updates = array();
        $batch_formats = array();

        if ( empty( $batch->teacher_name ) && ! empty( $defaults['teacher_name'] ) ) {
            $batch_updates['teacher_name'] = $defaults['teacher_name'];
            $batch_formats[] = '%s';
        }

        if ( empty( $batch->teacher_signature_url ) && ! empty( $defaults['teacher_signature_url'] ) ) {
            $batch_updates['teacher_signature_url'] = $defaults['teacher_signature_url'];
            $batch_formats[] = '%s';
        }

        if ( intval( $batch->teacher_signature_id ) <= 0 && intval( $defaults['teacher_signature_id'] ) > 0 ) {
            $batch_updates['teacher_signature_id'] = intval( $defaults['teacher_signature_id'] );
            $batch_formats[] = '%d';
        }

        if ( intval( $batch->is_active ) !== 1 ) {
            $batch_updates['is_active'] = 1;
            $batch_formats[] = '%d';
        }

        if ( $batch_updates ) {
            $wpdb->update(
                $batches_table,
                $batch_updates,
                array( 'id' => intval( $batch->id ) ),
                $batch_formats,
                array( '%d' )
            );

            $batch = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $batches_table WHERE id = %d LIMIT 1",
                    intval( $batch->id )
                )
            );
        }

        if ( $legacy_rows > 0 ) {
            self::assign_legacy_rows_to_batch( $class_id, $class_name, $batch );
        }
    }

    private static function get_legacy_unassigned_row_count( $class_id, $class_name ) {
        global $wpdb;

        $codes_table = $wpdb->prefix . 'smartcertify_codes';
        $logs_table = $wpdb->prefix . 'smartcertify_logs';
        $certs_table = $wpdb->prefix . 'smartcertify_certificates';

        $codes_count = intval(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $codes_table WHERE batch_id = 0 AND (class_id = %d OR (class_id = 0 AND class_name = %s))",
                    $class_id,
                    $class_name
                )
            )
        );

        $logs_count = intval(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $logs_table WHERE batch_id = 0 AND (class_id = %d OR (class_id = 0 AND class_name = %s))",
                    $class_id,
                    $class_name
                )
            )
        );

        $certs_count = intval(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $certs_table WHERE batch_id = 0 AND (class_id = %d OR class_name = %s)",
                    $class_id,
                    $class_name
                )
            )
        );

        return $codes_count + $logs_count + $certs_count;
    }

    private static function assign_legacy_rows_to_batch( $class_id, $class_name, $batch ) {
        global $wpdb;

        $codes_table = $wpdb->prefix . 'smartcertify_codes';
        $logs_table = $wpdb->prefix . 'smartcertify_logs';
        $certs_table = $wpdb->prefix . 'smartcertify_certificates';
        $batch_id = intval( $batch->id ?? 0 );
        $batch_name = sanitize_text_field( $batch->batch_name ?? SmartCert_Helpers::get_default_batch_name() );

        if ( ! $batch_id ) {
            return;
        }

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $codes_table
                 SET batch_id = %d, class_id = %d
                 WHERE batch_id = 0 AND (class_id = %d OR (class_id = 0 AND class_name = %s))",
                $batch_id,
                $class_id,
                $class_id,
                $class_name
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $logs_table
                 SET batch_id = %d, class_id = %d, batch_name = %s
                 WHERE batch_id = 0 AND (class_id = %d OR (class_id = 0 AND class_name = %s))",
                $batch_id,
                $class_id,
                $batch_name,
                $class_id,
                $class_name
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $certs_table
                 SET batch_id = %d, class_id = %d, batch_name = %s
                 WHERE batch_id = 0 AND (class_id = %d OR class_name = %s)",
                $batch_id,
                $class_id,
                $batch_name,
                $class_id,
                $class_name
            )
        );
    }
}
