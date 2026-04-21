<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartCert_Admin {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_post_smartcertify_save_master_template', array( $this, 'handle_save_master_template' ) );
        add_action( 'admin_post_smartcertify_activate_template_version', array( $this, 'handle_activate_template_version' ) );
        add_action( 'admin_post_smartcertify_preview_test_pdf', array( $this, 'handle_preview_test_pdf' ) );
        add_action( 'admin_post_smartcertify_export_template_layout', array( $this, 'handle_export_template_layout' ) );
        add_action( 'admin_post_smartcertify_import_template_layout', array( $this, 'handle_import_template_layout' ) );
        add_action( 'admin_post_smartcertify_export_full_data', array( $this, 'handle_export_full_data' ) );
        add_action( 'admin_post_smartcertify_import_full_data', array( $this, 'handle_import_full_data' ) );
        add_action( 'admin_post_smartcertify_save_class', array( $this, 'handle_save_class' ) );
        add_action( 'admin_post_smartcertify_delete_class', array( $this, 'handle_delete_class' ) );
        add_action( 'admin_post_smartcertify_save_batch', array( $this, 'handle_save_batch' ) );
        add_action( 'admin_post_smartcertify_delete_batch', array( $this, 'handle_delete_batch' ) );
        add_action( 'admin_post_smartcertify_add_codes', array( $this, 'handle_add_codes' ) );
        add_action( 'admin_post_smartcertify_delete_code', array( $this, 'handle_delete_code' ) );
        add_action( 'admin_post_smartcertify_bulk_generate', array( $this, 'handle_bulk_generate' ) );
        add_action( 'admin_post_smartcertify_manage_certificate', array( $this, 'handle_manage_certificate' ) );
        add_action( 'wp_ajax_smartcertify_update_download_limit', array( $this, 'ajax_update_download_limit' ) );
        add_action( 'wp_ajax_smartcertify_apply_default_limit', array( $this, 'ajax_apply_default_limit' ) );
    }

    public function register_menu() {
        add_menu_page( 'SmartCertify', 'SmartCertify', 'manage_options', 'smartcertify', array( $this, 'page_dashboard' ), 'dashicons-awards' );
        add_submenu_page( 'smartcertify', 'Dashboard', 'Dashboard', 'manage_options', 'smartcertify', array( $this, 'page_dashboard' ) );
        add_submenu_page( 'smartcertify', 'Classes & Template', 'Classes & Template', 'manage_options', 'smartcertify_upload', array( $this, 'page_upload_template' ) );
        add_submenu_page( 'smartcertify', 'Manage Batches', 'Manage Batches', 'manage_options', 'smartcertify_batches', array( $this, 'page_manage_batches' ) );
        add_submenu_page( 'smartcertify', 'Manage Codes', 'Manage Codes', 'manage_options', 'smartcertify_codes', array( $this, 'page_manage_codes' ) );
        add_submenu_page( 'smartcertify', 'Bulk Issue', 'Bulk Issue', 'manage_options', 'smartcertify_bulk_issue', array( $this, 'page_bulk_issue' ) );
        add_submenu_page( 'smartcertify', 'View Logs', 'View Logs', 'manage_options', 'smartcertify_logs', array( $this, 'page_view_logs' ) );
        add_submenu_page( 'smartcertify', 'Student History', 'Student History', 'manage_options', 'smartcertify_history', array( $this, 'page_student_history' ) );
        add_submenu_page( 'smartcertify', 'Analytics', 'Analytics', 'manage_options', 'smartcertify_analytics', array( $this, 'page_analytics' ) );
        add_submenu_page( 'smartcertify', 'Verify Certificate', 'Verify Certificate', 'manage_options', 'smartcertify_verify', array( $this, 'page_verify_certificate' ) );
        add_submenu_page( 'smartcertify', 'Template Designer', 'Template Designer', 'manage_options', 'smartcertify_designer', array( $this, 'page_template_designer' ) );
        add_submenu_page( 'smartcertify', 'Health Check', 'Health Check', 'manage_options', 'smartcertify_health', array( $this, 'page_health_check' ) );
        add_submenu_page( 'smartcertify', 'Backup & Transfer', 'Backup & Transfer', 'manage_options', 'smartcertify_backup', array( $this, 'page_backup_transfer' ) );
        add_submenu_page( 'smartcertify', 'Settings', 'Settings', 'manage_options', 'smartcertify_settings', array( $this, 'page_settings' ) );
    }

    public function ajax_update_download_limit() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
        }
        check_ajax_referer( 'smartcertify_update_download_limit' );

        global $wpdb;
        $id = intval( $_POST['code_id'] ?? 0 );
        $limit = max( 0, intval( $_POST['download_limit'] ?? 3 ) );

        if ( ! $id ) {
            wp_send_json_error( array( 'message' => 'Invalid code ID' ) );
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'smartcertify_codes',
            array( 'download_limit' => $limit ),
            array( 'id' => $id ),
            array( '%d' ),
            array( '%d' )
        );

        if ( false === $result ) {
            wp_send_json_error( array( 'message' => 'Failed to update download limit' ) );
        }

        $code_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT download_count, download_limit FROM {$wpdb->prefix}smartcertify_codes WHERE id = %d LIMIT 1",
                $id
            )
        );

        wp_send_json_success(
            array(
                'message'        => 'Download limit updated.',
                'download_count' => intval( $code_row->download_count ?? 0 ),
                'download_limit' => intval( $code_row->download_limit ?? $limit ),
            )
        );
    }

    public function ajax_apply_default_limit() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
        }
        check_ajax_referer( 'smartcertify_apply_default_limit' );

        global $wpdb;
        $new_default = max( 0, intval( $_POST['new_default'] ?? 3 ) );
        $old_default = max( 0, intval( $_POST['old_default'] ?? 3 ) );

        if ( $new_default === $old_default ) {
            wp_send_json_error( array( 'message' => 'No change detected.' ) );
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'smartcertify_codes',
            array( 'download_limit' => $new_default ),
            array( 'download_limit' => $old_default ),
            array( '%d' ),
            array( '%d' )
        );

        if ( false === $result ) {
            wp_send_json_error( array( 'message' => 'Failed to apply the default limit.' ) );
        }

        wp_send_json_success( array( 'message' => sprintf( 'Updated %d code(s).', intval( $result ) ) ) );
    }

    public function page_dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        $classes_table = $wpdb->prefix . 'smartcertify_classes';
        $batches_table = $wpdb->prefix . 'smartcertify_batches';
        $codes_table = $wpdb->prefix . 'smartcertify_codes';
        $logs_table = $wpdb->prefix . 'smartcertify_logs';
        $cert_stats = SmartCert_Cleanup::get_statistics();

        $total_classes = intval( $wpdb->get_var( "SELECT COUNT(*) FROM $classes_table" ) );
        $total_batches = intval( $wpdb->get_var( "SELECT COUNT(*) FROM $batches_table WHERE is_active = 1" ) );
        $total_codes = intval( $wpdb->get_var( "SELECT COUNT(*) FROM $codes_table" ) );
        $total_generated = intval( $wpdb->get_var( "SELECT COUNT(*) FROM $logs_table WHERE action = 'generated'" ) );

        $recent_logs = $wpdb->get_results(
            "SELECT student_name, class_name, batch_name, serial, timestamp
             FROM $logs_table
             ORDER BY timestamp DESC
             LIMIT 10"
        );
        ?>
        <div class="wrap">
            <h1>SmartCertify Dashboard</h1>
            <?php $this->render_notice_from_query(); ?>

            <div class="sc-dashboard-grid">
                <div class="sc-stat-card">
                    <h3>Total Classes</h3>
                    <p class="sc-stat-value"><?php echo esc_html( $total_classes ); ?></p>
                </div>
                <div class="sc-stat-card">
                    <h3>Total Batches</h3>
                    <p class="sc-stat-value"><?php echo esc_html( $total_batches ); ?></p>
                </div>
                <div class="sc-stat-card">
                    <h3>Total Codes</h3>
                    <p class="sc-stat-value"><?php echo esc_html( $total_codes ); ?></p>
                </div>
                <div class="sc-stat-card">
                    <h3>Certificates Generated</h3>
                    <p class="sc-stat-value"><?php echo esc_html( $total_generated ); ?></p>
                </div>
                <div class="sc-stat-card">
                    <h3>Valid Certificates</h3>
                    <p class="sc-stat-value"><?php echo esc_html( $cert_stats['active'] ?? 0 ); ?></p>
                </div>
                <div class="sc-stat-card">
                    <h3>Expired / Revoked</h3>
                    <p class="sc-stat-value"><?php echo esc_html( intval( $cert_stats['expired'] ?? 0 ) + intval( $cert_stats['revoked'] ?? 0 ) ); ?></p>
                </div>
            </div>

            <div style="margin-top:24px;background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;">
                <h2 style="margin-top:0;">What Changed In This Version</h2>
                <ul style="margin-left:18px;">
                    <li>Single master template for all classes</li>
                    <li>Class -> Batch selection on the frontend</li>
                    <li>Batch-based teacher name and signature rendering</li>
                    <li>QR verification for every generated certificate</li>
                    <li>Local-first QR generation with cached fallback</li>
                    <li>Bundled local QR library with no remote dependency</li>
                    <li>Signed expiring download links instead of nonce-based PDF URLs</li>
                    <li>Login-protected certificate downloads and account-based email delivery</li>
                    <li>Bulk issue for a full batch in one click</li>
                    <li>Revoke, reissue, renewal, and certificate validity lifecycle</li>
                    <li>Student search history, delivery tools, and analytics dashboard</li>
                    <li>Template versioning, health checks, test PDF preview, API support, and full export/import</li>
                </ul>
            </div>

            <div style="margin-top:24px;background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;">
                <h2 style="margin-top:0;">Recent Certificates</h2>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Batch</th>
                            <th>Serial</th>
                            <th>Issued At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( $recent_logs ) : ?>
                            <?php foreach ( $recent_logs as $log ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $log->student_name ); ?></td>
                                    <td><?php echo esc_html( $log->class_name ); ?></td>
                                    <td><?php echo esc_html( $log->batch_name ?: 'N/A' ); ?></td>
                                    <td><code><?php echo esc_html( $log->serial ); ?></code></td>
                                    <td><?php echo esc_html( $log->timestamp ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="5">No certificates generated yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function page_upload_template() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $classes = SmartCert_Helpers::get_classes();
        $template = SmartCert_Helpers::get_master_template();
        $template_versions = SmartCert_Helpers::get_template_versions();
        $active_template = SmartCert_Helpers::get_active_template_version();
        ?>
        <div class="wrap">
            <h1>Classes & Template</h1>
            <?php $this->render_notice_from_query(); ?>

            <div style="background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;margin-bottom:24px;">
                <h2 style="margin-top:0;">Master Certificate Template</h2>
                <p>Upload one master certificate template. The selected class name, batch data, QR code, and second teacher signature will be printed dynamically on this template.</p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'smartcertify_save_master_template' ); ?>
                    <input type="hidden" name="action" value="smartcertify_save_master_template" />
                    <input type="hidden" id="sc_master_template_id" name="master_template_id" value="<?php echo esc_attr( $template['attachment_id'] ); ?>" />
                    <input type="text" id="sc_master_template_url" name="master_template_url" value="<?php echo esc_attr( $template['url'] ); ?>" style="width:100%;max-width:650px;" placeholder="Choose PNG, JPG, WebP, or PDF template" />
                    <button type="button" class="button sc-media-button" data-target="#sc_master_template_url" data-target-id="#sc_master_template_id">Choose Template</button>
                    <?php submit_button( 'Save Master Template', 'primary', 'submit', false ); ?>
                </form>
                <p style="margin-top:12px;color:#666;">Supported files: PNG, JPG, JPEG, GIF, WebP, PDF.</p>
                <?php if ( $template['url'] ) : ?>
                    <p><a href="<?php echo esc_url( $template['url'] ); ?>" target="_blank" rel="noopener">View current master template</a></p>
                    <p>
                        <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=smartcertify_designer' ) ); ?>">Open Template Designer</a>
                        <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=smartcertify_health' ) ); ?>">Health Check</a>
                    </p>
                <?php endif; ?>
            </div>

            <div style="background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;margin-bottom:24px;">
                <h2 style="margin-top:0;">Template Versions</h2>
                <p>Every time you save a new master template, SmartCertify stores it as a version. You can activate an older version any time without losing the previous files.</p>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Version</th>
                            <th>File</th>
                            <th>Uploaded</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( $template_versions ) : ?>
                            <?php foreach ( $template_versions as $version ) : ?>
                                <tr>
                                    <td><code><?php echo esc_html( $version['id'] ); ?></code></td>
                                    <td><?php echo esc_html( $version['label'] ?: 'Template file' ); ?></td>
                                    <td><?php echo esc_html( $version['created_at'] ?: 'N/A' ); ?></td>
                                    <td><?php echo ! empty( $version['is_active'] ) ? 'Active' : 'Stored'; ?></td>
                                    <td>
                                        <?php if ( ! empty( $version['url'] ) ) : ?>
                                            <a class="button button-secondary" href="<?php echo esc_url( $version['url'] ); ?>" target="_blank" rel="noopener">View</a>
                                        <?php endif; ?>
                                        <?php if ( empty( $version['is_active'] ) ) : ?>
                                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
                                                <?php wp_nonce_field( 'smartcertify_activate_template_version' ); ?>
                                                <input type="hidden" name="action" value="smartcertify_activate_template_version" />
                                                <input type="hidden" name="version_id" value="<?php echo esc_attr( $version['id'] ); ?>" />
                                                <button type="submit" class="button button-secondary">Activate</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="5">No template versions stored yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ( ! empty( $active_template['id'] ) ) : ?>
                    <p style="margin-top:12px;">Current active template version: <code><?php echo esc_html( $active_template['id'] ); ?></code></p>
                <?php endif; ?>
            </div>

            <div style="background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;margin-bottom:24px;">
                <h2 style="margin-top:0;">PDF Preview Test</h2>
                <p>Generate one sample certificate PDF using the current master template, current layout, and demo content before students use the live form.</p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'smartcertify_preview_test_pdf' ); ?>
                    <input type="hidden" name="action" value="smartcertify_preview_test_pdf" />
                    <button type="submit" class="button button-primary">Generate Test PDF Preview</button>
                </form>
            </div>

            <div style="background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;">
                <h2 style="margin-top:0;">Manage Classes</h2>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:20px;">
                    <?php wp_nonce_field( 'smartcertify_save_class' ); ?>
                    <input type="hidden" name="action" value="smartcertify_save_class" />
                    <input type="text" name="class_name" placeholder="Enter class name" required style="width:100%;max-width:320px;" />
                    <?php submit_button( 'Add Class', 'secondary', 'submit', false ); ?>
                </form>

                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Class Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( $classes ) : ?>
                            <?php foreach ( $classes as $class ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $class->class_name ); ?></td>
                                    <td>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                                            <?php wp_nonce_field( 'smartcertify_delete_class' ); ?>
                                            <input type="hidden" name="action" value="smartcertify_delete_class" />
                                            <input type="hidden" name="class_id" value="<?php echo esc_attr( $class->id ); ?>" />
                                            <button type="submit" class="button button-secondary" onclick="return confirm('Delete this class and its batches/codes?');">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="2">No classes added yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php

        $this->render_media_picker_script();
    }

    public function handle_save_master_template() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'smartcertify_save_master_template' );

        $attachment_id = intval( $_POST['master_template_id'] ?? 0 );
        $url = esc_url_raw( $_POST['master_template_url'] ?? '' );

        if ( ! $this->is_valid_asset( $url ) ) {
            $this->redirect_with_notice( 'smartcertify_upload', 'Please choose a valid image or PDF template.', 'error' );
        }

        $stored = SmartCert_Helpers::store_template_version( $attachment_id, $url );
        if ( ! $stored ) {
            $this->redirect_with_notice( 'smartcertify_upload', 'Unable to save that template version.', 'error' );
        }

        $this->redirect_with_notice( 'smartcertify_upload', 'Master template saved successfully as a new version.' );
    }

    public function handle_activate_template_version() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'smartcertify_activate_template_version' );

        $version_id = sanitize_text_field( $_POST['version_id'] ?? '' );
        if ( ! SmartCert_Helpers::activate_template_version( $version_id ) ) {
            $this->redirect_with_notice( 'smartcertify_upload', 'Template version not found.', 'error' );
        }

        $this->redirect_with_notice( 'smartcertify_upload', 'Template version activated successfully.' );
    }

    public function handle_preview_test_pdf() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'smartcertify_preview_test_pdf' );

        $template = SmartCert_Helpers::resolve_template_reference();
        if ( empty( $template['attachment_id'] ) && empty( $template['url'] ) ) {
            $this->redirect_with_notice( 'smartcertify_upload', 'Upload a master template first.', 'error' );
        }

        $classes = SmartCert_Helpers::get_classes();
        $class = ! empty( $classes ) ? reset( $classes ) : null;
        $batches = $class ? SmartCert_Helpers::get_batches( intval( $class->id ) ) : array();
        $batch = ! empty( $batches ) ? reset( $batches ) : null;
        $default_batch = SmartCert_Helpers::get_default_batch_settings();
        $generator = new SmartCert_Generator();
        $sample_serial = 'SC-PREVIEW01';
        $sample_label = 'Preview - ' . SmartCert_Service::get_formatted_timestamp();
        $pdf_path = $generator->generate_certificate(
            $template,
            'Preview Student',
            $sample_serial,
            $sample_label,
            array(
                'class_name'            => $class ? $class->class_name : 'Preview Class',
                'class_id'              => $class ? intval( $class->id ) : 0,
                'batch_name'            => $batch ? $batch->batch_name : SmartCert_Helpers::get_default_batch_name(),
                'batch_id'              => $batch ? intval( $batch->id ) : 0,
                'teacher_name'          => $batch && ! empty( $batch->teacher_name ) ? $batch->teacher_name : ( $default_batch['teacher_name'] ?: 'Preview Teacher' ),
                'teacher_signature_id'  => $batch ? intval( $batch->teacher_signature_id ) : intval( $default_batch['teacher_signature_id'] ?? 0 ),
                'teacher_signature_url' => $batch && ! empty( $batch->teacher_signature_url ) ? $batch->teacher_signature_url : ( $default_batch['teacher_signature_url'] ?? '' ),
                'verification_url'      => SmartCert_Helpers::get_verify_url( $sample_serial ),
                'qr_payload'            => SmartCert_Helpers::get_qr_payload( $sample_serial ),
            )
        );

        if ( ! $pdf_path || ! file_exists( $pdf_path ) ) {
            $this->redirect_with_notice( 'smartcertify_upload', 'Preview PDF generation failed.', 'error' );
        }

        $uploads = wp_upload_dir();
        $preview_dir = trailingslashit( $uploads['basedir'] ) . 'smartcertify/previews';
        if ( ! file_exists( $preview_dir ) ) {
            wp_mkdir_p( $preview_dir );
        }

        $file_name = 'smartcertify-preview-' . gmdate( 'Ymd-His' ) . '.pdf';
        $target = trailingslashit( $preview_dir ) . $file_name;
        @copy( $pdf_path, $target );
        @unlink( $pdf_path );

        if ( ! file_exists( $target ) ) {
            $this->redirect_with_notice( 'smartcertify_upload', 'Preview PDF could not be saved.', 'error' );
        }

        $url = trailingslashit( $uploads['baseurl'] ) . 'smartcertify/previews/' . $file_name;
        wp_redirect(
            add_query_arg(
                array(
                    'page'           => 'smartcertify_upload',
                    'sc_notice'      => 'Preview PDF generated successfully.',
                    'sc_notice_type' => 'success',
                    'sc_link_url'    => esc_url_raw( $url ),
                    'sc_link_label'  => 'Open Preview PDF',
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public function handle_save_class() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'smartcertify_save_class' );

        global $wpdb;
        $class_name = sanitize_text_field( $_POST['class_name'] ?? '' );
        if ( '' === $class_name ) {
            $this->redirect_with_notice( 'smartcertify_upload', 'Class name is required.', 'error' );
        }

        $existing = SmartCert_Helpers::get_class_by_name( $class_name );
        if ( $existing ) {
            $this->redirect_with_notice( 'smartcertify_upload', 'That class already exists.', 'error' );
        }

        $wpdb->insert(
            $wpdb->prefix . 'smartcertify_classes',
            array(
                'class_name'           => $class_name,
                'certificate_template' => '',
                'created_at'           => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s' )
        );

        if ( $wpdb->insert_id ) {
            SmartCert_DB::ensure_default_batches( intval( $wpdb->insert_id ) );
        }

        $this->redirect_with_notice( 'smartcertify_upload', 'Class added successfully.' );
    }

    public function handle_delete_class() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'smartcertify_delete_class' );

        global $wpdb;
        $class_id = intval( $_POST['class_id'] ?? 0 );
        $class = SmartCert_Helpers::get_class( $class_id );

        if ( ! $class ) {
            $this->redirect_with_notice( 'smartcertify_upload', 'Class not found.', 'error' );
        }

        $wpdb->delete( $wpdb->prefix . 'smartcertify_batches', array( 'class_id' => $class_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'smartcertify_codes', array( 'class_id' => $class_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'smartcertify_classes', array( 'id' => $class_id ), array( '%d' ) );

        $this->redirect_with_notice( 'smartcertify_upload', 'Class deleted successfully.' );
    }

    public function page_manage_batches() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        $classes = SmartCert_Helpers::get_classes();
        $batches = $wpdb->get_results(
            "SELECT b.*, c.class_name
             FROM {$wpdb->prefix}smartcertify_batches b
             LEFT JOIN {$wpdb->prefix}smartcertify_classes c ON b.class_id = c.id
             ORDER BY c.class_name ASC, b.batch_name ASC"
        );
        ?>
        <div class="wrap">
            <h1>Manage Batches</h1>
            <?php $this->render_notice_from_query(); ?>

            <div style="background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;margin-bottom:24px;">
                <h2 style="margin-top:0;">Add New Batch</h2>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'smartcertify_save_batch' ); ?>
                    <input type="hidden" name="action" value="smartcertify_save_batch" />
                    <table class="form-table">
                        <tr>
                            <th>Class</th>
                            <td>
                                <select name="class_id" required>
                                    <option value="">Select class</option>
                                    <?php foreach ( $classes as $class ) : ?>
                                        <option value="<?php echo esc_attr( $class->id ); ?>"><?php echo esc_html( $class->class_name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Batch Name</th>
                            <td><input type="text" name="batch_name" required style="width:100%;max-width:320px;" /></td>
                        </tr>
                        <tr>
                            <th>Teacher Name</th>
                            <td><input type="text" name="teacher_name" style="width:100%;max-width:320px;" /></td>
                        </tr>
                        <tr>
                            <th>Teacher Signature</th>
                            <td>
                                <input type="hidden" id="sc_new_signature_id" name="teacher_signature_id" value="0" />
                                <input type="text" id="sc_new_signature_url" name="teacher_signature_url" style="width:100%;max-width:500px;" placeholder="Choose transparent signature image" />
                                <button type="button" class="button sc-media-button" data-target="#sc_new_signature_url" data-target-id="#sc_new_signature_id">Choose Signature</button>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( 'Save Batch' ); ?>
                </form>
            </div>

            <div style="background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;">
                <h2 style="margin-top:0;">Existing Batches</h2>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Batch</th>
                            <th>Teacher</th>
                            <th>Signature</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( $batches ) : ?>
                            <?php foreach ( $batches as $batch ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $batch->class_name ?: 'Unknown' ); ?></td>
                                    <td><?php echo esc_html( $batch->batch_name ); ?></td>
                                    <td><?php echo esc_html( $batch->teacher_name ?: 'N/A' ); ?></td>
                                    <td>
                                        <?php if ( $batch->teacher_signature_url ) : ?>
                                            <a href="<?php echo esc_url( $batch->teacher_signature_url ); ?>" target="_blank" rel="noopener">View Signature</a>
                                        <?php else : ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo intval( $batch->is_active ) ? 'Active' : 'Inactive'; ?></td>
                                    <td>
                                        <details>
                                            <summary>Edit</summary>
                                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
                                                <?php wp_nonce_field( 'smartcertify_save_batch' ); ?>
                                                <input type="hidden" name="action" value="smartcertify_save_batch" />
                                                <input type="hidden" name="batch_id" value="<?php echo esc_attr( $batch->id ); ?>" />
                                                <input type="hidden" name="class_id" value="<?php echo esc_attr( $batch->class_id ); ?>" />
                                                <input type="text" name="batch_name" value="<?php echo esc_attr( $batch->batch_name ); ?>" style="width:100%;margin-bottom:8px;" />
                                                <input type="text" name="teacher_name" value="<?php echo esc_attr( $batch->teacher_name ); ?>" style="width:100%;margin-bottom:8px;" />
                                                <input type="hidden" id="sc_signature_id_<?php echo esc_attr( $batch->id ); ?>" name="teacher_signature_id" value="<?php echo esc_attr( $batch->teacher_signature_id ); ?>" />
                                                <input type="text" id="sc_signature_url_<?php echo esc_attr( $batch->id ); ?>" name="teacher_signature_url" value="<?php echo esc_attr( $batch->teacher_signature_url ); ?>" style="width:100%;margin-bottom:8px;" />
                                                <button type="button" class="button sc-media-button" data-target="#sc_signature_url_<?php echo esc_attr( $batch->id ); ?>" data-target-id="#sc_signature_id_<?php echo esc_attr( $batch->id ); ?>">Choose Signature</button>
                                                <label style="display:block;margin:8px 0;">
                                                    <input type="checkbox" name="is_active" value="1" <?php checked( intval( $batch->is_active ), 1 ); ?> />
                                                    Active
                                                </label>
                                                <?php submit_button( 'Update Batch', 'secondary', 'submit', false ); ?>
                                            </form>
                                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
                                                <?php wp_nonce_field( 'smartcertify_delete_batch' ); ?>
                                                <input type="hidden" name="action" value="smartcertify_delete_batch" />
                                                <input type="hidden" name="batch_id" value="<?php echo esc_attr( $batch->id ); ?>" />
                                                <button type="submit" class="button button-secondary" onclick="return confirm('Delete this batch?');">Delete Batch</button>
                                            </form>
                                        </details>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="6">No batches created yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php

        $this->render_media_picker_script();
    }

    public function handle_save_batch() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'smartcertify_save_batch' );

        global $wpdb;
        $batch_id = intval( $_POST['batch_id'] ?? 0 );
        $class_id = intval( $_POST['class_id'] ?? 0 );
        $batch_name = sanitize_text_field( $_POST['batch_name'] ?? '' );
        $teacher_name = sanitize_text_field( $_POST['teacher_name'] ?? '' );
        $signature_id = intval( $_POST['teacher_signature_id'] ?? 0 );
        $signature_url = esc_url_raw( $_POST['teacher_signature_url'] ?? '' );
        $is_active = isset( $_POST['is_active'] ) ? 1 : ( $batch_id > 0 ? 0 : 1 );

        if ( ! $class_id || '' === $batch_name ) {
            $this->redirect_with_notice( 'smartcertify_batches', 'Class and batch name are required.', 'error' );
        }

        if ( $signature_url && ! $this->is_valid_image_asset( $signature_url ) ) {
            $this->redirect_with_notice( 'smartcertify_batches', 'Teacher signature must be an image file.', 'error' );
        }

        $payload = array(
            'class_id'             => $class_id,
            'batch_name'           => $batch_name,
            'teacher_name'         => $teacher_name,
            'teacher_signature_id' => $signature_id,
            'teacher_signature_url'=> $signature_url,
            'is_active'            => $is_active,
        );

        if ( $batch_id > 0 ) {
            $wpdb->update(
                $wpdb->prefix . 'smartcertify_batches',
                $payload,
                array( 'id' => $batch_id ),
                array( '%d', '%s', '%s', '%d', '%s', '%d' ),
                array( '%d' )
            );
            $notice = 'Batch updated successfully.';
        } else {
            $payload['created_at'] = current_time( 'mysql' );
            $wpdb->insert(
                $wpdb->prefix . 'smartcertify_batches',
                $payload,
                array( '%d', '%s', '%s', '%d', '%s', '%d', '%s' )
            );
            $notice = 'Batch added successfully.';
        }

        $this->redirect_with_notice( 'smartcertify_batches', $notice );
    }

    public function handle_delete_batch() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'smartcertify_delete_batch' );

        global $wpdb;
        $batch_id = intval( $_POST['batch_id'] ?? 0 );
        if ( ! $batch_id ) {
            $this->redirect_with_notice( 'smartcertify_batches', 'Batch not found.', 'error' );
        }

        $wpdb->delete( $wpdb->prefix . 'smartcertify_codes', array( 'batch_id' => $batch_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'smartcertify_batches', array( 'id' => $batch_id ), array( '%d' ) );

        $this->redirect_with_notice( 'smartcertify_batches', 'Batch deleted successfully.' );
    }

    public function page_manage_codes() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        $classes = SmartCert_Helpers::get_classes();
        $batches_by_class = SmartCert_Helpers::get_batches_grouped_by_class();
        $default_limit = intval( get_option( 'smartcertify_default_download_limit', 3 ) );

        $show_class = intval( $_GET['show_class'] ?? 0 );
        $codes_table = $wpdb->prefix . 'smartcertify_codes';
        $batches_table = $wpdb->prefix . 'smartcertify_batches';

        $where = '';
        if ( $show_class ) {
            $where = $wpdb->prepare( 'WHERE c.class_id = %d', $show_class );
        }

        $codes = $wpdb->get_results(
            "SELECT c.*, b.batch_name
             FROM $codes_table c
             LEFT JOIN $batches_table b ON c.batch_id = b.id
             $where
             ORDER BY c.id DESC
             LIMIT 250"
        );
        ?>
        <div class="wrap">
            <h1>Manage Codes</h1>
            <?php $this->render_notice_from_query(); ?>

            <div style="background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;margin-bottom:24px;">
                <h2 style="margin-top:0;">Add Codes</h2>
                <p>You can add manual codes, upload a CSV, or import mobile numbers. CSV headers supported: <code>code,batch,student_name,student_email,student_phone,download_limit,status</code>. Every code is now locked to the selected class and batch.</p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'smartcertify_add_codes' ); ?>
                    <input type="hidden" name="action" value="smartcertify_add_codes" />
                    <table class="form-table">
                        <tr>
                            <th>Class</th>
                            <td>
                                <select name="class_id" id="sc_code_class" required>
                                    <option value="">Select class</option>
                                    <?php foreach ( $classes as $class ) : ?>
                                        <option value="<?php echo esc_attr( $class->id ); ?>"><?php echo esc_html( $class->class_name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Batch</th>
                            <td>
                                <select name="batch_id" id="sc_code_batch" required disabled>
                                    <option value="">Select batch</option>
                                    <?php foreach ( $batches_by_class as $class_id => $batches ) : ?>
                                        <?php foreach ( $batches as $batch ) : ?>
                                            <option value="<?php echo esc_attr( $batch->id ); ?>" data-class-id="<?php echo esc_attr( $class_id ); ?>" style="display:none;">
                                                <?php echo esc_html( $batch->batch_name ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Codes (one per line)</th>
                            <td><textarea name="codes" rows="6" style="width:100%;max-width:500px;"></textarea></td>
                        </tr>
                        <tr>
                            <th>CSV Upload</th>
                            <td><input type="file" name="codes_csv" accept=".csv,text/csv" /></td>
                        </tr>
                        <tr>
                            <th>Mobile Numbers</th>
                            <td>
                                <textarea name="mobile_numbers" rows="4" style="width:100%;max-width:500px;" placeholder="Last 6 digits will be used as the code"></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th>Single Code</th>
                            <td><input type="text" name="single_code" style="width:100%;max-width:220px;" /></td>
                        </tr>
                        <tr>
                            <th>Default Student Name</th>
                            <td><input type="text" name="student_name" style="width:100%;max-width:320px;" placeholder="Optional name lock" /></td>
                        </tr>
                        <tr>
                            <th>Default Student Email</th>
                            <td><input type="email" name="student_email" style="width:100%;max-width:320px;" /></td>
                        </tr>
                        <tr>
                            <th>Default Student Phone</th>
                            <td><input type="text" name="student_phone" style="width:100%;max-width:320px;" /></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <select name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Download Limit</th>
                            <td><input type="number" min="0" name="download_limit" value="<?php echo esc_attr( $default_limit ); ?>" style="width:100%;max-width:120px;" /></td>
                        </tr>
                    </table>
                    <?php submit_button( 'Add Codes' ); ?>
                </form>
            </div>

            <form method="get" style="margin-bottom:16px;">
                <input type="hidden" name="page" value="smartcertify_codes" />
                <select name="show_class">
                    <option value="0">All classes</option>
                    <?php foreach ( $classes as $class ) : ?>
                        <option value="<?php echo esc_attr( $class->id ); ?>" <?php selected( $show_class, intval( $class->id ) ); ?>>
                            <?php echo esc_html( $class->class_name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="button">Filter</button>
            </form>

            <div style="background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;">
                <h2 style="margin-top:0;">Existing Codes</h2>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Class</th>
                            <th>Batch</th>
                            <th>Code</th>
                            <th>Student</th>
                            <th>Status</th>
                            <th>Downloads</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( $codes ) : ?>
                            <?php foreach ( $codes as $code ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $code->id ); ?></td>
                                    <td><?php echo esc_html( $code->class_name ); ?></td>
                                    <td><?php echo esc_html( $code->batch_name ?: 'N/A' ); ?></td>
                                    <td><strong><?php echo esc_html( $code->code ); ?></strong></td>
                                    <td><?php echo esc_html( $code->student_name ?: 'Unlocked' ); ?></td>
                                    <td><?php echo esc_html( ucfirst( $code->status ?: 'active' ) ); ?></td>
                                    <td>
                                        <span class="sc-limit-summary"><?php echo esc_html( intval( $code->download_count ) . '/' . intval( $code->download_limit ) ); ?></span>
                                        <div style="margin-top:8px;">
                                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" class="sc-limit-form">
                                                <?php wp_nonce_field( 'smartcertify_update_download_limit' ); ?>
                                                <input type="hidden" name="action" value="smartcertify_update_download_limit" />
                                                <input type="hidden" name="code_id" value="<?php echo esc_attr( $code->id ); ?>" />
                                                <input type="number" name="download_limit" value="<?php echo esc_attr( $code->download_limit ); ?>" min="0" style="width:70px;" />
                                                <button type="submit" class="button button-small sc-limit-btn">Set</button>
                                            </form>
                                        </div>
                                    </td>
                                    <td>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                            <?php wp_nonce_field( 'smartcertify_delete_code' ); ?>
                                            <input type="hidden" name="action" value="smartcertify_delete_code" />
                                            <input type="hidden" name="code_id" value="<?php echo esc_attr( $code->id ); ?>" />
                                            <button type="submit" class="button button-secondary" onclick="return confirm('Delete this code?');">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="8">No codes found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        (function(){
            var classSelect = document.getElementById('sc_code_class');
            var batchSelect = document.getElementById('sc_code_batch');
            if (classSelect && batchSelect) {
                function syncBatchOptions() {
                    var classId = classSelect.value;
                    var firstVisible = null;
                    Array.prototype.forEach.call(batchSelect.options, function(option, index){
                        if (index === 0) {
                            option.style.display = '';
                            return;
                        }
                        var show = option.getAttribute('data-class-id') === classId;
                        option.style.display = show ? '' : 'none';
                        if (show && !firstVisible) {
                            firstVisible = option.value;
                        }
                    });
                    batchSelect.disabled = !classId || !firstVisible;
                    batchSelect.value = firstVisible || '';
                }
                classSelect.addEventListener('change', syncBatchOptions);
                syncBatchOptions();
            }

            document.querySelectorAll('.sc-limit-form').forEach(function(form){
                form.addEventListener('submit', function(e){
                    e.preventDefault();
                    var button = form.querySelector('.sc-limit-btn');
                    var original = button.textContent;
                    var summary = form.closest('td').querySelector('.sc-limit-summary');
                    button.disabled = true;
                    button.textContent = 'Saving...';
                    fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
                        .then(function(response){ return response.json(); })
                        .then(function(json){
                            if (json.success) {
                                button.textContent = 'Saved';
                                if (summary && json.data) {
                                    summary.textContent = String(json.data.download_count || 0) + '/' + String(json.data.download_limit || 0);
                                }
                            } else {
                                button.textContent = (json.data && json.data.message) ? json.data.message : 'Failed';
                            }
                            setTimeout(function(){
                                button.textContent = original;
                                button.disabled = false;
                            }, 1400);
                        })
                        .catch(function(){
                            button.textContent = 'Failed';
                            setTimeout(function(){
                                button.textContent = original;
                                button.disabled = false;
                            }, 1200);
                        });
                });
            });
        })();
        </script>
        <?php
    }

    public function handle_add_codes() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'smartcertify_add_codes' );

        $class_id = intval( $_POST['class_id'] ?? 0 );
        $class = SmartCert_Helpers::get_class( $class_id );

        if ( ! $class ) {
            $this->redirect_with_notice( 'smartcertify_codes', 'Please choose a valid class.', 'error' );
        }

        $default_batch_id = intval( $_POST['batch_id'] ?? 0 );
        $default_batch = SmartCert_Helpers::get_batch( $default_batch_id );
        if ( ! $default_batch || intval( $default_batch->class_id ) !== intval( $class->id ) ) {
            $this->redirect_with_notice( 'smartcertify_codes', 'Please select a valid batch for this class before adding codes.', 'error' );
        }

        $default_limit = max( 0, intval( $_POST['download_limit'] ?? get_option( 'smartcertify_default_download_limit', 3 ) ) );
        $default_student = sanitize_text_field( $_POST['student_name'] ?? '' );
        $default_email = sanitize_email( $_POST['student_email'] ?? '' );
        $default_phone = sanitize_text_field( $_POST['student_phone'] ?? '' );
        $default_status = sanitize_text_field( $_POST['status'] ?? 'active' );

        $rows = $this->parse_code_rows_from_request(
            $class,
            $default_batch_id,
            $default_limit,
            $default_student,
            $default_email,
            $default_phone,
            $default_status
        );

        if ( empty( $rows ) ) {
            $this->redirect_with_notice( 'smartcertify_codes', 'No valid code rows found to import.', 'error' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'smartcertify_codes';
        $inserted = 0;
        $attempted = count( $rows );

        foreach ( $rows as $row ) {
            $code = SmartCert_Helpers::sanitize_code( $row['code'] );
            if ( '' === $code ) {
                continue;
            }

            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table WHERE class_name = %s AND batch_id = %d AND code = %s",
                    $class->class_name,
                    intval( $row['batch_id'] ),
                    $code
                )
            );

            if ( $existing ) {
                continue;
            }

            $result = $wpdb->insert(
                $table,
                array(
                    'class_id'          => intval( $class->id ),
                    'batch_id'          => intval( $row['batch_id'] ),
                    'class_name'        => $class->class_name,
                    'code'              => $code,
                    'student_name'      => $row['student_name'],
                    'student_email'     => $row['student_email'],
                    'student_phone'     => $row['student_phone'],
                    'status'            => $row['status'],
                    'download_count'    => 0,
                    'download_limit'    => intval( $row['download_limit'] ),
                    'created_at'        => current_time( 'mysql' ),
                ),
                array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
            );

            if ( $result ) {
                $inserted++;
            }
        }

        $message = sprintf( 'Imported %d of %d code row(s).', $inserted, $attempted );
        $this->redirect_with_notice( 'smartcertify_codes', $message, $inserted > 0 ? 'success' : 'error' );
    }

    public function handle_delete_code() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'smartcertify_delete_code' );

        global $wpdb;
        $code_id = intval( $_POST['code_id'] ?? 0 );
        if ( $code_id ) {
            $wpdb->delete( $wpdb->prefix . 'smartcertify_codes', array( 'id' => $code_id ), array( '%d' ) );
        }

        $this->redirect_with_notice( 'smartcertify_codes', 'Code deleted successfully.' );
    }

    public function page_view_logs() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'smartcertify_logs';
        $classes = SmartCert_Helpers::get_classes();
        $paged = max( 1, intval( $_GET['paged'] ?? 1 ) );
        $per_page = 25;
        $offset = ( $paged - 1 ) * $per_page;

        $where = '1=1';
        $params = array();

        $search = sanitize_text_field( $_GET['s'] ?? '' );
        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= ' AND (student_name LIKE %s OR class_name LIKE %s OR batch_name LIKE %s OR serial LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $class_filter = sanitize_text_field( $_GET['class'] ?? '' );
        if ( $class_filter ) {
            $where .= ' AND class_name = %s';
            $params[] = $class_filter;
        }

        if ( isset( $_GET['export'] ) && '1' === $_GET['export'] ) {
            $this->export_logs_csv( $where, $params );
        }

        if ( $params ) {
            $rows = $wpdb->get_results(
                call_user_func_array(
                    array( $wpdb, 'prepare' ),
                    array_merge(
                        array( "SELECT * FROM $table WHERE $where ORDER BY timestamp DESC LIMIT %d OFFSET %d" ),
                        $params,
                        array( $per_page, $offset )
                    )
                )
            );
            $total = intval(
                $wpdb->get_var(
                    call_user_func_array(
                        array( $wpdb, 'prepare' ),
                        array_merge( array( "SELECT COUNT(*) FROM $table WHERE $where" ), $params )
                    )
                )
            );
        } else {
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE $where ORDER BY timestamp DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
            $total = intval( $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where" ) );
        }

        $total_pages = max( 1, ceil( $total / $per_page ) );
        ?>
        <div class="wrap">
            <h1>Certificate Logs</h1>
            <?php $this->render_notice_from_query(); ?>

            <form method="get" style="margin-bottom:16px;background:#fff;padding:16px;border:1px solid var(--sc-border);border-radius:12px;">
                <input type="hidden" name="page" value="smartcertify_logs" />
                <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search student, class, batch, serial" style="width:100%;max-width:320px;" />
                <select name="class">
                    <option value="">All classes</option>
                    <?php foreach ( $classes as $class ) : ?>
                        <option value="<?php echo esc_attr( $class->class_name ); ?>" <?php selected( $class_filter, $class->class_name ); ?>>
                            <?php echo esc_html( $class->class_name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="button">Filter</button>
                <button class="button button-secondary" type="submit" name="export" value="1">Export CSV</button>
            </form>

            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Batch</th>
                        <th>Teacher</th>
                        <th>Code</th>
                        <th>Serial</th>
                        <th>Status</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $rows ) : ?>
                        <?php foreach ( $rows as $row ) : ?>
                            <tr>
                                <td><?php echo esc_html( $row->student_name ); ?></td>
                                <td><?php echo esc_html( $row->class_name ); ?></td>
                                <td><?php echo esc_html( $row->batch_name ?: 'N/A' ); ?></td>
                                <td><?php echo esc_html( $row->teacher_name ?: 'N/A' ); ?></td>
                                <td><?php echo esc_html( $row->code ); ?></td>
                                <td><code><?php echo esc_html( $row->serial ); ?></code></td>
                                <td><?php echo esc_html( ucfirst( $row->status ?: 'valid' ) ); ?></td>
                                <td><?php echo esc_html( $row->timestamp ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="8">No logs found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="tablenav" style="margin-top:16px;">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(
                        array(
                            'base'    => add_query_arg( array( 'page' => 'smartcertify_logs', 'paged' => '%#%', 's' => $search, 'class' => $class_filter ), admin_url( 'admin.php' ) ),
                            'format'  => '',
                            'current' => $paged,
                            'total'   => $total_pages,
                        )
                    );
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function page_bulk_issue() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        $classes = SmartCert_Helpers::get_classes();
        $batches_by_class = SmartCert_Helpers::get_batches_grouped_by_class();
        $selected_class = intval( $_GET['class_id'] ?? 0 );
        $selected_batch = intval( $_GET['batch_id'] ?? 0 );
        $stats = array(
            'total_codes'   => 0,
            'named_codes'   => 0,
            'active_codes'  => 0,
            'valid_issued'  => 0,
        );

        if ( $selected_batch > 0 ) {
            $stats['total_codes'] = intval(
                $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}smartcertify_codes WHERE batch_id = %d",
                        $selected_batch
                    )
                )
            );
            $stats['named_codes'] = intval(
                $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}smartcertify_codes WHERE batch_id = %d AND student_name <> ''",
                        $selected_batch
                    )
                )
            );
            $stats['active_codes'] = intval(
                $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}smartcertify_codes WHERE batch_id = %d AND status = 'active'",
                        $selected_batch
                    )
                )
            );
            $stats['valid_issued'] = intval(
                $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}smartcertify_certificates WHERE batch_id = %d AND deleted_at IS NULL AND status = 'valid'",
                        $selected_batch
                    )
                )
            );
        }
        ?>
        <div class="wrap">
            <h1>Bulk Issue Certificates</h1>
            <?php $this->render_notice_from_query(); ?>

            <div style="background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;margin-bottom:24px;">
                <h2 style="margin-top:0;">Generate A Full Batch In One Click</h2>
                <p>Bulk issue creates certificates for the selected batch using the saved student names in code records. Codes without a student name are skipped automatically.</p>
                <form method="get" style="margin-bottom:18px;">
                    <input type="hidden" name="page" value="smartcertify_bulk_issue" />
                    <select name="class_id" id="sc_bulk_class" required>
                        <option value="">Select class</option>
                        <?php foreach ( $classes as $class ) : ?>
                            <option value="<?php echo esc_attr( $class->id ); ?>" <?php selected( $selected_class, intval( $class->id ) ); ?>>
                                <?php echo esc_html( $class->class_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="batch_id" id="sc_bulk_batch" required <?php disabled( 0 === $selected_class ); ?>>
                        <option value="">Select batch</option>
                        <?php foreach ( $batches_by_class as $class_id => $batches ) : ?>
                            <?php foreach ( $batches as $batch ) : ?>
                                <option value="<?php echo esc_attr( $batch->id ); ?>" data-class-id="<?php echo esc_attr( $class_id ); ?>" style="display:none;" <?php selected( $selected_batch, intval( $batch->id ) ); ?>>
                                    <?php echo esc_html( $batch->batch_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>
                    <button class="button">Load Batch</button>
                </form>

                <?php if ( $selected_batch > 0 ) : ?>
                    <div class="sc-dashboard-grid" style="margin-bottom:0;">
                        <div class="sc-stat-card"><h3>Total Codes</h3><p class="sc-stat-value"><?php echo esc_html( $stats['total_codes'] ); ?></p></div>
                        <div class="sc-stat-card"><h3>Codes With Student Name</h3><p class="sc-stat-value"><?php echo esc_html( $stats['named_codes'] ); ?></p></div>
                        <div class="sc-stat-card"><h3>Active Codes</h3><p class="sc-stat-value"><?php echo esc_html( $stats['active_codes'] ); ?></p></div>
                        <div class="sc-stat-card"><h3>Valid Certificates Already Issued</h3><p class="sc-stat-value"><?php echo esc_html( $stats['valid_issued'] ); ?></p></div>
                    </div>
                <?php endif; ?>
            </div>

            <div style="background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;">
                <h2 style="margin-top:0;">Start Bulk Issue</h2>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'smartcertify_bulk_generate' ); ?>
                    <input type="hidden" name="action" value="smartcertify_bulk_generate" />
                    <input type="hidden" name="class_id" value="<?php echo esc_attr( $selected_class ); ?>" />
                    <input type="hidden" name="batch_id" value="<?php echo esc_attr( $selected_batch ); ?>" />
                    <label style="display:block;margin-bottom:10px;">
                        <input type="checkbox" name="skip_existing" value="1" checked />
                        Skip students who already have a valid certificate in this batch
                    </label>
                    <label style="display:block;margin-bottom:10px;">
                        <input type="checkbox" name="trigger_delivery" value="1" checked />
                        Trigger email / WhatsApp delivery settings after issuing
                    </label>
                    <label style="display:block;margin-bottom:18px;">
                        <input type="checkbox" name="only_active" value="1" checked />
                        Use only active codes
                    </label>
                    <button type="submit" class="button button-primary" <?php disabled( $selected_batch <= 0 ); ?>>Generate Certificates For This Batch</button>
                </form>
            </div>
        </div>

        <script>
        (function(){
            var classSelect = document.getElementById('sc_bulk_class');
            var batchSelect = document.getElementById('sc_bulk_batch');
            if (!classSelect || !batchSelect) return;

            function syncBatchOptions() {
                var classId = classSelect.value;
                var currentValue = batchSelect.value;
                var firstVisible = '';

                Array.prototype.forEach.call(batchSelect.options, function(option, index){
                    if (index === 0) {
                        option.style.display = '';
                        return;
                    }
                    var show = option.getAttribute('data-class-id') === classId;
                    option.style.display = show ? '' : 'none';
                    if (show && !firstVisible) {
                        firstVisible = option.value;
                    }
                });

                batchSelect.disabled = !classId || !firstVisible;
                if (!classId) {
                    batchSelect.value = '';
                } else {
                    var selectedOption = batchSelect.options[batchSelect.selectedIndex];
                    var currentVisible = selectedOption && selectedOption.getAttribute('data-class-id') === classId && selectedOption.style.display !== 'none';
                    if (!currentValue || !currentVisible) {
                        batchSelect.value = firstVisible || '';
                    }
                }
            }

            classSelect.addEventListener('change', syncBatchOptions);
            syncBatchOptions();
        })();
        </script>
        <?php
    }

    public function handle_bulk_generate() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'smartcertify_bulk_generate' );

        global $wpdb;
        @set_time_limit( 0 );
        wp_raise_memory_limit( 'admin' );

        $class_id = intval( $_POST['class_id'] ?? 0 );
        $batch_id = intval( $_POST['batch_id'] ?? 0 );
        $skip_existing = ! empty( $_POST['skip_existing'] );
        $trigger_delivery = ! empty( $_POST['trigger_delivery'] );
        $only_active = ! empty( $_POST['only_active'] );

        $class = SmartCert_Helpers::get_class( $class_id );
        $batch = SmartCert_Helpers::get_batch( $batch_id );
        if ( ! $class || ! $batch || intval( $batch->class_id ) !== intval( $class->id ) ) {
            $this->redirect_with_notice( 'smartcertify_bulk_issue', 'Please choose a valid class and batch.', 'error' );
        }

        if ( $only_active ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}smartcertify_codes
                     WHERE class_id = %d AND batch_id = %d AND status = 'active'
                     ORDER BY student_name ASC, id ASC",
                    intval( $class->id ),
                    intval( $batch->id )
                ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}smartcertify_codes
                     WHERE class_id = %d AND batch_id = %d
                     ORDER BY student_name ASC, id ASC",
                    intval( $class->id ),
                    intval( $batch->id )
                ),
                ARRAY_A
            );
        }

        if ( empty( $rows ) ) {
            $this->redirect_with_notice( 'smartcertify_bulk_issue', 'No eligible codes were found for that batch.', 'error' );
        }

        $zip_path = '';
        $zip_url = '';
        $zip = null;
        if ( class_exists( 'ZipArchive' ) ) {
            $uploads = wp_upload_dir();
            $export_dir = trailingslashit( $uploads['basedir'] ) . 'smartcertify_exports';
            if ( ! file_exists( $export_dir ) ) {
                wp_mkdir_p( $export_dir );
            }
            $zip_name = 'smartcertify-' . sanitize_title( $class->class_name . '-' . $batch->batch_name ) . '-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 8, false, false ) . '.zip';
            $zip_path = trailingslashit( $export_dir ) . $zip_name;
            $zip_url = trailingslashit( $uploads['baseurl'] ) . 'smartcertify_exports/' . $zip_name;
            $zip = new ZipArchive();
            if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
                $zip = null;
                $zip_path = '';
                $zip_url = '';
            }
        }

        $generated = 0;
        $skipped_existing = 0;
        $skipped_missing_name = 0;
        $failed = 0;
        $last_issue = null;

        foreach ( $rows as $row ) {
            if ( '' === trim( (string) ( $row['student_name'] ?? '' ) ) ) {
                $skipped_missing_name++;
                continue;
            }

            if ( $skip_existing ) {
                $existing_valid = intval(
                    $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}smartcertify_certificates
                             WHERE class_id = %d
                             AND batch_id = %d
                             AND code = %s
                             AND student_name = %s
                             AND deleted_at IS NULL
                             AND status = 'valid'",
                            intval( $class->id ),
                            intval( $batch->id ),
                            sanitize_text_field( $row['code'] ),
                            sanitize_text_field( $row['student_name'] )
                        )
                    )
                );

                if ( $existing_valid > 0 ) {
                    $skipped_existing++;
                    continue;
                }
            }

            $issue = SmartCert_Service::issue_certificate(
                array(
                    'class'                    => $class,
                    'batch'                    => $batch,
                    'student_name'             => $row['student_name'],
                    'student_email'            => $row['student_email'] ?? '',
                    'student_phone'            => $row['student_phone'] ?? '',
                    'code'                     => $row['code'],
                    'teacher_name'             => $batch->teacher_name,
                    'teacher_signature_id'     => intval( $batch->teacher_signature_id ),
                    'teacher_signature_url'    => $batch->teacher_signature_url,
                    'status'                   => 'valid',
                    'increment_download_count' => false,
                    'code_row_id'              => intval( $row['id'] ),
                    'trigger_auto_delivery'    => $trigger_delivery,
                )
            );

            if ( is_wp_error( $issue ) ) {
                $failed++;
                continue;
            }

            $generated++;
            $last_issue = $issue;

            if ( $zip && ! empty( $issue['file_path'] ) && file_exists( $issue['file_path'] ) ) {
                $zip->addFile( $issue['file_path'], basename( $issue['file_path'] ) );
            }
        }

        if ( $zip ) {
            $zip->close();
            if ( ! file_exists( $zip_path ) ) {
                $zip_url = '';
            }
        }

        $message = sprintf(
            'Bulk issue finished. Generated: %1$d, skipped existing: %2$d, skipped without student name: %3$d, failed: %4$d.',
            $generated,
            $skipped_existing,
            $skipped_missing_name,
            $failed
        );

        $args = array(
            'page'           => 'smartcertify_bulk_issue',
            'class_id'       => intval( $class->id ),
            'batch_id'       => intval( $batch->id ),
            'sc_notice'      => $message,
            'sc_notice_type' => $generated > 0 ? 'success' : 'warning',
        );

        if ( $zip_url ) {
            $args['sc_link_url'] = $zip_url;
            $args['sc_link_label'] = 'Download ZIP';
        } elseif ( ! empty( $last_issue['url'] ) ) {
            $args['sc_link_url'] = $last_issue['url'];
            $args['sc_link_label'] = 'View Last Certificate';
        }

        wp_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }

    public function page_student_history() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        SmartCert_Cleanup::refresh_certificate_statuses();

        $classes = SmartCert_Helpers::get_classes();
        $paged = max( 1, intval( $_GET['paged'] ?? 1 ) );
        $per_page = 20;
        $offset = ( $paged - 1 ) * $per_page;
        $search = sanitize_text_field( $_GET['s'] ?? '' );
        $status_filter = sanitize_text_field( $_GET['status'] ?? '' );
        $class_filter = intval( $_GET['class_id'] ?? 0 );

        $where = array( '1=1' );
        $params = array();

        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where[] = '(student_name LIKE %s OR student_email LIKE %s OR student_phone LIKE %s OR serial LIKE %s OR code LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ( $status_filter ) {
            $where[] = 'status = %s';
            $params[] = $status_filter;
        }

        if ( $class_filter > 0 ) {
            $where[] = 'class_id = %d';
            $params[] = $class_filter;
        }

        $where_sql = implode( ' AND ', $where );
        $certs_table = $wpdb->prefix . 'smartcertify_certificates';

        if ( $params ) {
            $rows = $wpdb->get_results(
                call_user_func_array(
                    array( $wpdb, 'prepare' ),
                    array_merge(
                        array( "SELECT * FROM $certs_table WHERE $where_sql ORDER BY generated_at DESC LIMIT %d OFFSET %d" ),
                        $params,
                        array( $per_page, $offset )
                    )
                ),
                ARRAY_A
            );
            $total = intval(
                $wpdb->get_var(
                    call_user_func_array(
                        array( $wpdb, 'prepare' ),
                        array_merge( array( "SELECT COUNT(*) FROM $certs_table WHERE $where_sql" ), $params )
                    )
                )
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM $certs_table WHERE $where_sql ORDER BY generated_at DESC LIMIT %d OFFSET %d", $per_page, $offset ),
                ARRAY_A
            );
            $total = intval( $wpdb->get_var( "SELECT COUNT(*) FROM $certs_table WHERE $where_sql" ) );
        }

        $total_pages = max( 1, ceil( $total / $per_page ) );
        ?>
        <div class="wrap">
            <h1>Student Search & Certificate History</h1>
            <?php $this->render_notice_from_query(); ?>

            <form method="get" style="margin-bottom:16px;background:#fff;padding:16px;border:1px solid var(--sc-border);border-radius:12px;">
                <input type="hidden" name="page" value="smartcertify_history" />
                <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search student, email, phone, serial, code" style="width:100%;max-width:320px;" />
                <select name="status">
                    <option value="">All statuses</option>
                    <?php foreach ( array( 'valid', 'expired', 'revoked', 'renewed', 'deleted' ) as $status_value ) : ?>
                        <option value="<?php echo esc_attr( $status_value ); ?>" <?php selected( $status_filter, $status_value ); ?>>
                            <?php echo esc_html( ucfirst( $status_value ) ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="class_id">
                    <option value="0">All classes</option>
                    <?php foreach ( $classes as $class ) : ?>
                        <option value="<?php echo esc_attr( $class->id ); ?>" <?php selected( $class_filter, intval( $class->id ) ); ?>>
                            <?php echo esc_html( $class->class_name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="button">Filter</button>
            </form>

            <div style="background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;">
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class / Batch</th>
                            <th>Serial</th>
                            <th>Status</th>
                            <th>Validity</th>
                            <th>Delivery</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( $rows ) : ?>
                            <?php foreach ( $rows as $row ) : ?>
                                <?php $whatsapp_url = ! empty( $row['student_phone'] ) ? SmartCert_Service::get_whatsapp_link( intval( $row['id'] ) ) : ''; ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $row['student_name'] ); ?></strong><br />
                                        <?php if ( ! empty( $row['student_email'] ) ) : ?>
                                            <span><?php echo esc_html( $row['student_email'] ); ?></span><br />
                                        <?php endif; ?>
                                        <?php if ( ! empty( $row['student_phone'] ) ) : ?>
                                            <span><?php echo esc_html( $row['student_phone'] ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html( $row['class_name'] ); ?></strong><br />
                                        <span><?php echo esc_html( $row['batch_name'] ?: 'N/A' ); ?></span>
                                    </td>
                                    <td>
                                        <code><?php echo esc_html( $row['serial'] ); ?></code><br />
                                        <span><?php echo esc_html( $row['generated_at'] ); ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html( ucfirst( $row['status'] ?: 'valid' ) ); ?></strong>
                                        <?php if ( 'revoked' === $row['status'] && ! empty( $row['revoke_reason'] ) ) : ?>
                                            <br /><span><?php echo esc_html( $row['revoke_reason'] ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span>Until: <?php echo esc_html( $row['certificate_expires_at'] ?: 'N/A' ); ?></span><br />
                                        <span>Temp file: <?php echo esc_html( $row['expires_at'] ?: 'N/A' ); ?></span>
                                    </td>
                                    <td>
                                        <span>Email: <?php echo esc_html( ucfirst( $row['delivery_email_status'] ?: 'pending' ) ); ?></span><br />
                                        <span>WhatsApp: <?php echo esc_html( ucfirst( $row['delivery_whatsapp_status'] ?: 'pending' ) ); ?></span>
                                    </td>
                                    <td>
                                        <?php if ( empty( $row['deleted_at'] ) && 'valid' === ( $row['status'] ?: 'valid' ) ) : ?>
                                            <p><a class="button button-secondary" href="<?php echo esc_url( SmartCert_Helpers::get_public_certificate_url( $row['file_name'] ) ); ?>" target="_blank" rel="noopener">View PDF</a></p>
                                        <?php endif; ?>
                                        <p><a class="button button-secondary" href="<?php echo esc_url( SmartCert_Helpers::get_verify_url( $row['serial'] ) ); ?>" target="_blank" rel="noopener">Verify</a></p>
                                        <details>
                                            <summary>Manage</summary>
                                            <?php if ( empty( $row['deleted_at'] ) && 'revoked' !== $row['status'] && 'renewed' !== $row['status'] ) : ?>
                                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
                                                    <?php wp_nonce_field( 'smartcertify_manage_certificate' ); ?>
                                                    <input type="hidden" name="action" value="smartcertify_manage_certificate" />
                                                    <input type="hidden" name="task" value="revoke" />
                                                    <input type="hidden" name="certificate_id" value="<?php echo esc_attr( $row['id'] ); ?>" />
                                                    <input type="text" name="reason" placeholder="Revoke reason" style="width:100%;margin-bottom:8px;" />
                                                    <button type="submit" class="button button-secondary" onclick="return confirm('Revoke this certificate?');">Revoke</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ( empty( $row['deleted_at'] ) && 'revoked' !== $row['status'] && 'renewed' !== $row['status'] ) : ?>
                                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
                                                    <?php wp_nonce_field( 'smartcertify_manage_certificate' ); ?>
                                                    <input type="hidden" name="action" value="smartcertify_manage_certificate" />
                                                    <input type="hidden" name="task" value="renew" />
                                                    <input type="hidden" name="certificate_id" value="<?php echo esc_attr( $row['id'] ); ?>" />
                                                    <button type="submit" class="button button-secondary">Renew Validity</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ( empty( $row['deleted_at'] ) && 'renewed' !== $row['status'] ) : ?>
                                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
                                                    <?php wp_nonce_field( 'smartcertify_manage_certificate' ); ?>
                                                    <input type="hidden" name="action" value="smartcertify_manage_certificate" />
                                                    <input type="hidden" name="task" value="reissue" />
                                                    <input type="hidden" name="certificate_id" value="<?php echo esc_attr( $row['id'] ); ?>" />
                                                    <button type="submit" class="button button-secondary">Reissue Certificate</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ( ! empty( $row['student_email'] ) ) : ?>
                                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
                                                    <?php wp_nonce_field( 'smartcertify_manage_certificate' ); ?>
                                                    <input type="hidden" name="action" value="smartcertify_manage_certificate" />
                                                    <input type="hidden" name="task" value="send_email" />
                                                    <input type="hidden" name="certificate_id" value="<?php echo esc_attr( $row['id'] ); ?>" />
                                                    <button type="submit" class="button button-secondary">Send Email</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ( $whatsapp_url ) : ?>
                                                <p style="margin-top:10px;"><a class="button button-secondary" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener">Open WhatsApp</a></p>
                                            <?php endif; ?>
                                        </details>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="7">No certificate history found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="tablenav" style="margin-top:16px;">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(
                        array(
                            'base'    => add_query_arg(
                                array(
                                    'page'    => 'smartcertify_history',
                                    'paged'   => '%#%',
                                    's'       => $search,
                                    'status'  => $status_filter,
                                    'class_id'=> $class_filter,
                                ),
                                admin_url( 'admin.php' )
                            ),
                            'format'  => '',
                            'current' => $paged,
                            'total'   => $total_pages,
                        )
                    );
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function handle_manage_certificate() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'smartcertify_manage_certificate' );

        $task = sanitize_key( $_POST['task'] ?? '' );
        $certificate_id = intval( $_POST['certificate_id'] ?? 0 );
        $certificate = SmartCert_Cleanup::get_certificate_by_id( $certificate_id );

        if ( ! $certificate ) {
            $this->redirect_with_notice( 'smartcertify_history', 'Certificate not found.', 'error' );
        }

        $message = 'Certificate updated.';
        $type = 'success';
        $link_url = '';
        $link_label = '';

        switch ( $task ) {
            case 'revoke':
                $reason = sanitize_text_field( $_POST['reason'] ?? '' );
                if ( ! SmartCert_Cleanup::revoke_certificate( $certificate_id, $reason ) ) {
                    $this->redirect_with_notice( 'smartcertify_history', 'Failed to revoke the certificate.', 'error' );
                }
                $updated_certificate = SmartCert_Cleanup::get_certificate_by_id( $certificate_id );
                SmartCert_Logger::log(
                    $certificate['student_name'],
                    $certificate['class_name'],
                    $certificate['code'],
                    'revoked',
                    $certificate['serial'],
                    array(
                        'certificate_id' => intval( $certificate['id'] ),
                        'class_id'       => intval( $certificate['class_id'] ),
                        'batch_id'       => intval( $certificate['batch_id'] ),
                        'batch_name'     => $certificate['batch_name'],
                        'teacher_name'   => $certificate['teacher_name'],
                        'verification_url' => $certificate['verification_url'],
                        'status'         => 'revoked',
                    )
                );
                if ( class_exists( 'SmartCert_Integrations' ) && $updated_certificate ) {
                    SmartCert_Integrations::dispatch_webhook( 'certificate.revoked', $updated_certificate, array( 'reason' => $reason ) );
                }
                $message = 'Certificate revoked successfully.';
                break;

            case 'renew':
                $new_expiry = SmartCert_Cleanup::renew_certificate_expiry( $certificate_id, SmartCert_Cleanup::get_validity_days() );
                if ( ! $new_expiry ) {
                    $this->redirect_with_notice( 'smartcertify_history', 'Failed to renew certificate validity.', 'error' );
                }
                $updated_certificate = SmartCert_Cleanup::get_certificate_by_id( $certificate_id );
                SmartCert_Logger::log(
                    $certificate['student_name'],
                    $certificate['class_name'],
                    $certificate['code'],
                    'renewed',
                    $certificate['serial'],
                    array(
                        'certificate_id' => intval( $certificate['id'] ),
                        'class_id'       => intval( $certificate['class_id'] ),
                        'batch_id'       => intval( $certificate['batch_id'] ),
                        'batch_name'     => $certificate['batch_name'],
                        'teacher_name'   => $certificate['teacher_name'],
                        'verification_url' => $certificate['verification_url'],
                        'status'         => 'valid',
                    )
                );
                if ( class_exists( 'SmartCert_Integrations' ) && $updated_certificate ) {
                    SmartCert_Integrations::dispatch_webhook( 'certificate.renewed', $updated_certificate, array( 'new_expiry' => $new_expiry ) );
                }
                $message = 'Certificate validity renewed until ' . $new_expiry . '.';
                break;

            case 'reissue':
                $result = SmartCert_Service::reissue_certificate(
                    $certificate_id,
                    array(
                        'trigger_auto_delivery' => true,
                    )
                );
                if ( is_wp_error( $result ) ) {
                    $this->redirect_with_notice( 'smartcertify_history', $result->get_error_message(), 'error' );
                }
                SmartCert_Logger::log(
                    $certificate['student_name'],
                    $certificate['class_name'],
                    $certificate['code'],
                    'reissued',
                    $result['serial'],
                    array(
                        'certificate_id' => intval( $result['certificate_id'] ),
                        'class_id'       => intval( $certificate['class_id'] ),
                        'batch_id'       => intval( $certificate['batch_id'] ),
                        'batch_name'     => $certificate['batch_name'],
                        'teacher_name'   => $certificate['teacher_name'],
                        'verification_url' => $result['verification_url'],
                        'status'         => 'valid',
                    )
                );
                if ( class_exists( 'SmartCert_Integrations' ) && ! empty( $result['certificate'] ) ) {
                    SmartCert_Integrations::dispatch_webhook( 'certificate.reissued', $result['certificate'], array( 'new_serial' => $result['serial'] ) );
                }
                $message = 'Certificate reissued successfully with new serial ' . $result['serial'] . '.';
                $link_url = $result['url'];
                $link_label = 'View New Certificate';
                break;

            case 'send_email':
                $delivery = SmartCert_Service::send_certificate_email( $certificate_id );
                if ( is_wp_error( $delivery ) ) {
                    $this->redirect_with_notice( 'smartcertify_history', $delivery->get_error_message(), 'error' );
                }
                SmartCert_Logger::log(
                    $certificate['student_name'],
                    $certificate['class_name'],
                    $certificate['code'],
                    'email_sent',
                    $certificate['serial'],
                    array(
                        'certificate_id' => intval( $certificate['id'] ),
                        'class_id'       => intval( $certificate['class_id'] ),
                        'batch_id'       => intval( $certificate['batch_id'] ),
                        'batch_name'     => $certificate['batch_name'],
                        'teacher_name'   => $certificate['teacher_name'],
                        'verification_url' => $certificate['verification_url'],
                        'status'         => $certificate['status'],
                    )
                );
                $message = 'Certificate email sent successfully.';
                break;

            default:
                $this->redirect_with_notice( 'smartcertify_history', 'Unknown certificate action requested.', 'error' );
        }

        $args = array(
            'page'           => 'smartcertify_history',
            'sc_notice'      => $message,
            'sc_notice_type' => $type,
        );

        if ( $link_url ) {
            $args['sc_link_url'] = $link_url;
            $args['sc_link_label'] = $link_label ?: 'Open';
        }

        wp_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }

    public function page_analytics() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        SmartCert_Cleanup::refresh_certificate_statuses();
        $stats = SmartCert_Cleanup::get_statistics();

        $batch_rows = $wpdb->get_results(
            "SELECT
                class_name,
                batch_name,
                COUNT(*) AS total_issued,
                SUM(CASE WHEN status = 'valid' THEN 1 ELSE 0 END) AS valid_total,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) AS expired_total,
                SUM(CASE WHEN status = 'revoked' THEN 1 ELSE 0 END) AS revoked_total,
                SUM(CASE WHEN status = 'renewed' THEN 1 ELSE 0 END) AS renewed_total
             FROM {$wpdb->prefix}smartcertify_certificates
             WHERE deleted_at IS NULL
             GROUP BY class_name, batch_name
             ORDER BY class_name ASC, batch_name ASC"
        );

        $download_rows = $wpdb->get_results(
            "SELECT
                c.class_name,
                b.batch_name,
                COUNT(*) AS total_codes,
                SUM(c.download_count) AS total_downloads,
                AVG(c.download_count) AS average_downloads
             FROM {$wpdb->prefix}smartcertify_codes c
             LEFT JOIN {$wpdb->prefix}smartcertify_batches b ON c.batch_id = b.id
             GROUP BY c.class_name, c.batch_id
             ORDER BY c.class_name ASC, b.batch_name ASC"
        );
        ?>
        <div class="wrap">
            <h1>Batch-wise Analytics Dashboard</h1>
            <?php $this->render_notice_from_query(); ?>

            <div class="sc-dashboard-grid">
                <div class="sc-stat-card"><h3>Total Certificates</h3><p class="sc-stat-value"><?php echo esc_html( $stats['total'] ); ?></p></div>
                <div class="sc-stat-card"><h3>Valid</h3><p class="sc-stat-value"><?php echo esc_html( $stats['active'] ); ?></p></div>
                <div class="sc-stat-card"><h3>Expired</h3><p class="sc-stat-value"><?php echo esc_html( $stats['expired'] ); ?></p></div>
                <div class="sc-stat-card"><h3>Revoked</h3><p class="sc-stat-value"><?php echo esc_html( $stats['revoked'] ?? 0 ); ?></p></div>
                <div class="sc-stat-card"><h3>Reissued / Renewed</h3><p class="sc-stat-value"><?php echo esc_html( $stats['renewed'] ?? 0 ); ?></p></div>
                <div class="sc-stat-card"><h3>Deleted Files</h3><p class="sc-stat-value"><?php echo esc_html( $stats['deleted'] ); ?></p></div>
            </div>

            <div style="background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;margin-bottom:24px;">
                <h2 style="margin-top:0;">Issued Certificates By Batch</h2>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Batch</th>
                            <th>Issued</th>
                            <th>Valid</th>
                            <th>Expired</th>
                            <th>Revoked</th>
                            <th>Reissued</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( $batch_rows ) : ?>
                            <?php foreach ( $batch_rows as $row ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $row->class_name ); ?></td>
                                    <td><?php echo esc_html( $row->batch_name ?: 'N/A' ); ?></td>
                                    <td><?php echo esc_html( intval( $row->total_issued ) ); ?></td>
                                    <td><?php echo esc_html( intval( $row->valid_total ) ); ?></td>
                                    <td><?php echo esc_html( intval( $row->expired_total ) ); ?></td>
                                    <td><?php echo esc_html( intval( $row->revoked_total ) ); ?></td>
                                    <td><?php echo esc_html( intval( $row->renewed_total ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="7">No analytics data available yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div style="background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;">
                <h2 style="margin-top:0;">Code Download Behaviour By Batch</h2>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Batch</th>
                            <th>Total Codes</th>
                            <th>Total Downloads</th>
                            <th>Average Downloads / Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( $download_rows ) : ?>
                            <?php foreach ( $download_rows as $row ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $row->class_name ); ?></td>
                                    <td><?php echo esc_html( $row->batch_name ?: 'N/A' ); ?></td>
                                    <td><?php echo esc_html( intval( $row->total_codes ) ); ?></td>
                                    <td><?php echo esc_html( intval( $row->total_downloads ) ); ?></td>
                                    <td><?php echo esc_html( number_format_i18n( floatval( $row->average_downloads ), 2 ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="5">No code analytics available yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function page_verify_certificate() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $serial = '';
        $name = '';
        $result = '';

        if ( isset( $_POST['sc_verify'] ) ) {
            check_admin_referer( 'smartcertify_verify' );
            $serial = sanitize_text_field( $_POST['serial'] ?? '' );
            $name = sanitize_text_field( $_POST['student_name'] ?? '' );
            $result = $this->render_verification_result_card( $serial, $name );
        }
        ?>
        <div class="wrap">
            <h1>Verify Certificate</h1>
            <?php $this->render_notice_from_query(); ?>
            <?php echo $result; ?>
            <div class="sc-admin-verify-grid">
                <div class="sc-verify-card sc-verify-card--scanner">
                    <h2 class="sc-verify-title">Scan QR Code</h2>
                    <p class="sc-verify-copy">Use your camera or upload a QR image to fill the serial automatically and verify the certificate faster.</p>
                    <?php
                    echo SmartCert_Helpers::get_qr_scanner_markup(
                        array(
                            'target_input'    => 'input[name="serial"]',
                            'submit_on_scan'  => true,
                            'redirect_on_url' => false,
                            'title'           => 'Admin QR Scanner',
                            'copy'            => 'Start the camera or upload a QR code image from the certificate.',
                        )
                    );
                    ?>
                </div>

                <div class="sc-verify-card">
                    <h2 class="sc-verify-title">Manual Verification</h2>
                    <p class="sc-verify-copy">Enter the certificate serial number and, if needed, the student name to cross-check the record.</p>
                    <form method="post" class="sc-verify-form">
                        <?php wp_nonce_field( 'smartcertify_verify' ); ?>
                        <div class="sc-form-group">
                            <label class="sc-form-label">Serial Number</label>
                            <input type="text" class="sc-form-input" name="serial" value="<?php echo esc_attr( $serial ); ?>" placeholder="Enter serial number" required />
                        </div>
                        <div class="sc-form-group">
                            <label class="sc-form-label">Student Name (optional)</label>
                            <input type="text" class="sc-form-input" name="student_name" value="<?php echo esc_attr( $name ); ?>" placeholder="Enter student name" />
                        </div>
                        <?php submit_button( 'Verify Certificate', 'primary', 'sc_verify', false ); ?>
                    </form>
                    <p style="margin-top:16px;">Public verify URL format: <code><?php echo esc_html( home_url( '/?smartcertify_verify=SERIAL' ) ); ?></code></p>
                </div>
            </div>
        </div>
        <?php
    }

    public function page_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['sc_save_settings'] ) ) {
            check_admin_referer( 'smartcertify_settings' );
            $default_batch_signature_url = esc_url_raw( trim( (string) wp_unslash( $_POST['default_batch_signature_url'] ?? '' ) ) );
            if ( $default_batch_signature_url && ! $this->is_valid_image_asset( $default_batch_signature_url ) ) {
                $this->redirect_with_notice( 'smartcertify_settings', 'Default batch signature must be an image file.', 'error' );
            }

            update_option( 'smartcertify_default_download_limit', max( 0, intval( $_POST['default_download_limit'] ?? 3 ) ) );
            SmartCert_Cleanup::set_expiry_hours( intval( $_POST['certificate_expiry_hours'] ?? 24 ) );
            SmartCert_Cleanup::set_validity_days( intval( $_POST['certificate_validity_days'] ?? SmartCert_Cleanup::get_validity_days() ) );
            update_option( 'smartcertify_verify_button_url', esc_url_raw( trim( (string) wp_unslash( $_POST['verify_button_url'] ?? '' ) ) ) );
            update_option( 'smartcertify_default_batch_teacher_name', sanitize_text_field( wp_unslash( $_POST['default_batch_teacher_name'] ?? '' ) ) );
            update_option( 'smartcertify_default_batch_signature_id', intval( $_POST['default_batch_signature_id'] ?? 0 ) );
            update_option( 'smartcertify_default_batch_signature_url', $default_batch_signature_url );
            update_option( 'smartcertify_require_login', ! empty( $_POST['require_login'] ) ? 1 : 0 );
            update_option( 'smartcertify_download_token_minutes', max( 5, intval( $_POST['download_token_minutes'] ?? SmartCert_Helpers::get_download_token_lifetime_minutes() ) ) );
            update_option( 'smartcertify_duplicate_rule', sanitize_key( $_POST['duplicate_rule'] ?? SmartCert_Helpers::get_duplicate_rule() ) );
            update_option( 'smartcertify_auto_email_delivery', ! empty( $_POST['auto_email_delivery'] ) ? 1 : 0 );
            update_option( 'smartcertify_auto_whatsapp_delivery', ! empty( $_POST['auto_whatsapp_delivery'] ) ? 1 : 0 );
            update_option( 'smartcertify_email_subject', sanitize_text_field( wp_unslash( $_POST['email_subject'] ?? '' ) ) );
            update_option( 'smartcertify_email_message', sanitize_textarea_field( wp_unslash( $_POST['email_message'] ?? '' ) ) );
            update_option( 'smartcertify_whatsapp_message', sanitize_textarea_field( wp_unslash( $_POST['whatsapp_message'] ?? '' ) ) );
            update_option( 'smartcertify_webhook_url', esc_url_raw( trim( (string) wp_unslash( $_POST['webhook_url'] ?? '' ) ) ) );
            update_option( 'smartcertify_webhook_secret', sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ?? '' ) ) );
            $api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
            update_option( 'smartcertify_api_key', '' !== $api_key ? $api_key : wp_generate_password( 32, false, false ) );

            $layout = SmartCert_Helpers::sanitize_layout_settings( wp_unslash( $_POST ) );
            foreach ( array_keys( SmartCert_Helpers::get_layout_defaults() ) as $key ) {
                if ( isset( $layout[ $key ] ) ) {
                    update_option( 'smartcertify_' . $key, $layout[ $key ] );
                }
            }

            SmartCert_DB::ensure_default_batches();

            $this->redirect_with_notice( 'smartcertify_settings', 'Settings saved successfully.' );
        }

        $default_limit = intval( get_option( 'smartcertify_default_download_limit', 3 ) );
        $expiry_hours = SmartCert_Cleanup::get_expiry_hours();
        $validity_days = SmartCert_Cleanup::get_validity_days();
        $verify_button_url = trim( (string) get_option( 'smartcertify_verify_button_url', '' ) );
        $default_batch_settings = SmartCert_Helpers::get_default_batch_settings();
        $delivery_settings = SmartCert_Helpers::get_delivery_settings();
        $require_login = SmartCert_Helpers::is_login_required_for_download();
        $download_token_minutes = SmartCert_Helpers::get_download_token_lifetime_minutes();
        $duplicate_rule = SmartCert_Helpers::get_duplicate_rule();
        $webhook_settings = SmartCert_Helpers::get_webhook_settings();
        $api_settings = SmartCert_Helpers::get_api_settings();
        $layout = SmartCert_Helpers::get_layout_settings();
        ?>
        <div class="wrap">
            <h1>Settings</h1>
            <?php $this->render_notice_from_query(); ?>

            <form method="post">
                <?php wp_nonce_field( 'smartcertify_settings' ); ?>
                <div style="background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;margin-bottom:24px;">
                    <h2 style="margin-top:0;">General Settings</h2>
                    <table class="form-table">
                        <tr>
                            <th>Default Download Limit</th>
                            <td><input type="number" name="default_download_limit" min="0" value="<?php echo esc_attr( $default_limit ); ?>" /></td>
                        </tr>
                        <tr>
                            <th>Certificate Expiry (Hours)</th>
                            <td><input type="number" name="certificate_expiry_hours" min="1" value="<?php echo esc_attr( $expiry_hours ); ?>" /></td>
                        </tr>
                        <tr>
                            <th>Certificate Validity (Days)</th>
                            <td>
                                <input type="number" name="certificate_validity_days" min="1" value="<?php echo esc_attr( $validity_days ); ?>" />
                                <p class="description">Controls how long a certificate stays valid on the verification page before it becomes expired.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Verify Button Link</th>
                            <td>
                                <input type="url" name="verify_button_url" value="<?php echo esc_attr( $verify_button_url ); ?>" style="width:100%;max-width:520px;" placeholder="https://example.com/verify-certificate/" />
                                <p class="description">Paste the public verification page URL. This link will be used for the button shown in the left instruction box on the certificate download page.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Require Login For Download</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="require_login" value="1" <?php checked( $require_login, true ); ?> />
                                    Force students to log in before generating and downloading a certificate
                                </label>
                                <p class="description">When enabled, SmartCertify uses the logged-in account email for certificate delivery and signed download links.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Download Link Lifetime (Minutes)</th>
                            <td>
                                <input type="number" name="download_token_minutes" min="5" value="<?php echo esc_attr( $download_token_minutes ); ?>" />
                                <p class="description">Signed download links automatically expire after this many minutes.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Duplicate Certificate Rule</th>
                            <td>
                                <select name="duplicate_rule">
                                    <option value="reuse_active" <?php selected( $duplicate_rule, 'reuse_active' ); ?>>Reuse active certificate</option>
                                    <option value="block" <?php selected( $duplicate_rule, 'block' ); ?>>Block duplicate issue</option>
                                    <option value="always_new" <?php selected( $duplicate_rule, 'always_new' ); ?>>Always create new certificate</option>
                                </select>
                                <p class="description">Recommended: reuse the existing active certificate for the same class, batch, code, and student instead of generating duplicates.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Default Batch 1 Teacher</th>
                            <td>
                                <input type="text" name="default_batch_teacher_name" value="<?php echo esc_attr( $default_batch_settings['teacher_name'] ); ?>" style="width:100%;max-width:320px;" placeholder="Teacher name for Batch 1" />
                                <p class="description">Used when the plugin auto-creates the default <strong>Batch 1</strong> for older classes or for newly added classes.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Default Batch 1 Signature</th>
                            <td>
                                <input type="hidden" id="sc_default_batch_signature_id" name="default_batch_signature_id" value="<?php echo esc_attr( intval( $default_batch_settings['teacher_signature_id'] ) ); ?>" />
                                <input type="text" id="sc_default_batch_signature_url" name="default_batch_signature_url" value="<?php echo esc_attr( $default_batch_settings['teacher_signature_url'] ); ?>" style="width:100%;max-width:520px;" placeholder="Choose transparent signature image" />
                                <button type="button" class="button sc-media-button" data-target="#sc_default_batch_signature_url" data-target-id="#sc_default_batch_signature_id">Choose Signature</button>
                                <p class="description">This signature is copied into auto-created <strong>Batch 1</strong> entries.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div style="background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;margin-bottom:24px;">
                    <h2 style="margin-top:0;">Delivery Settings</h2>
                    <table class="form-table">
                        <tr>
                            <th>Auto Email Delivery</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="auto_email_delivery" value="1" <?php checked( intval( $delivery_settings['auto_email'] ), 1 ); ?> />
                                    Send certificate links by email automatically after generation when a student email is available
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th>Auto WhatsApp Delivery</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="auto_whatsapp_delivery" value="1" <?php checked( intval( $delivery_settings['auto_whatsapp'] ), 1 ); ?> />
                                    Prepare a WhatsApp share link automatically after generation when a student phone number is available
                                </label>
                                <p class="description">This version prepares a direct WhatsApp link. If you want fully automatic WhatsApp sending through an API provider later, we can add that in the next update.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Email Subject</th>
                            <td>
                                <input type="text" name="email_subject" value="<?php echo esc_attr( $delivery_settings['email_subject'] ); ?>" style="width:100%;max-width:680px;" />
                                <p class="description">Available tokens: <code>{student_name}</code>, <code>{class_name}</code>, <code>{batch_name}</code>, <code>{certificate_url}</code>, <code>{verification_url}</code>, <code>{serial}</code>, <code>{site_name}</code>.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Email Message</th>
                            <td>
                                <textarea name="email_message" rows="6" style="width:100%;max-width:680px;"><?php echo esc_textarea( $delivery_settings['email_message'] ); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th>WhatsApp Message</th>
                            <td>
                                <textarea name="whatsapp_message" rows="4" style="width:100%;max-width:680px;"><?php echo esc_textarea( $delivery_settings['whatsapp_message'] ); ?></textarea>
                            </td>
                        </tr>
                    </table>
                </div>

                <div style="background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;margin-bottom:24px;">
                    <h2 style="margin-top:0;">API & Webhooks</h2>
                    <table class="form-table">
                        <tr>
                            <th>API Key</th>
                            <td>
                                <input type="text" name="api_key" value="<?php echo esc_attr( $api_settings['key'] ); ?>" style="width:100%;max-width:520px;" />
                                <p class="description">Use this key in the <code>X-SmartCertify-Key</code> header for protected REST API requests.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Webhook URL</th>
                            <td>
                                <input type="url" name="webhook_url" value="<?php echo esc_attr( $webhook_settings['url'] ); ?>" style="width:100%;max-width:680px;" placeholder="https://example.com/webhooks/smartcertify" />
                                <p class="description">SmartCertify will POST JSON payloads here for issue, reuse, and email events.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Webhook Secret</th>
                            <td>
                                <input type="text" name="webhook_secret" value="<?php echo esc_attr( $webhook_settings['secret'] ); ?>" style="width:100%;max-width:520px;" />
                                <p class="description">If set, outgoing webhooks include <code>X-SmartCertify-Signature</code> with an HMAC SHA-256 signature.</p>
                            </td>
                        </tr>
                    </table>
                    <p style="margin-top:16px;"><strong>REST API endpoints</strong></p>
                    <p><code><?php echo esc_html( $api_settings['verify_url'] ); ?></code></p>
                    <p><code><?php echo esc_html( $api_settings['issue_url'] ); ?></code></p>
                    <p><code><?php echo esc_html( $api_settings['health_url'] ); ?></code></p>
                </div>

                <div style="background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;">
                    <h2 style="margin-top:0;">Template Designer</h2>
                    <p>Open the visual designer to drag the student name, class name, batch name, teacher signature, teacher name, QR code, and meta text directly on your certificate template.</p>
                    <p style="margin:16px 0;">
                        <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=smartcertify_designer' ) ); ?>">Open Template Designer</a>
                    </p>

                    <details style="margin-top:16px;">
                        <summary style="cursor:pointer;font-weight:600;">Advanced manual layout values</summary>
                        <p style="margin-top:12px;">Use percentages for positions, for example <code>50%</code>. These values are the same ones updated by the drag-and-drop designer.</p>
                        <table class="form-table">
                            <?php foreach ( $layout as $key => $value ) : ?>
                                <tr>
                                    <th><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></th>
                                    <td>
                                        <?php if ( false !== strpos( $key, '_font_family' ) ) : ?>
                                            <select name="<?php echo esc_attr( $key ); ?>" style="width:100%;max-width:220px;">
                                                <?php foreach ( SmartCert_Helpers::get_font_choices() as $font_key => $font_label ) : ?>
                                                    <option value="<?php echo esc_attr( $font_key ); ?>" <?php selected( $value, $font_key ); ?>><?php echo esc_html( $font_label ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php else : ?>
                                            <input type="text" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" style="width:100%;max-width:220px;" />
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </details>
                </div>

                <?php submit_button( 'Save Settings', 'primary', 'sc_save_settings' ); ?>
            </form>

            <div style="margin-top:20px;background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;">
                <h2 style="margin-top:0;">Apply New Default Limit To Existing Codes</h2>
                <button id="sc_apply_default_limit" class="button button-primary">Apply Default Limit</button>
                <p id="sc_apply_status" style="margin-top:12px;"></p>
            </div>

            <script>
            (function(){
                var button = document.getElementById('sc_apply_default_limit');
                var status = document.getElementById('sc_apply_status');
                if (!button) return;

                button.addEventListener('click', function(){
                    var formData = new FormData();
                    formData.append('action', 'smartcertify_apply_default_limit');
                    formData.append('_wpnonce', '<?php echo esc_js( wp_create_nonce( 'smartcertify_apply_default_limit' ) ); ?>');
                    formData.append('old_default', '<?php echo esc_js( $default_limit ); ?>');
                    formData.append('new_default', document.querySelector('input[name="default_download_limit"]').value);
                    button.disabled = true;
                    status.textContent = 'Processing...';

                    fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', body: formData, credentials: 'same-origin' })
                        .then(function(response){ return response.json(); })
                        .then(function(json){
                            status.textContent = json.success ? json.data.message : json.data.message;
                            button.disabled = false;
                        })
                        .catch(function(){
                            status.textContent = 'Failed to apply default limit.';
                            button.disabled = false;
                        });
                });
            })();
            </script>
        </div>
        <?php

        $this->render_media_picker_script();
    }

    public function page_template_designer() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['sc_save_template_layout'] ) ) {
            check_admin_referer( 'smartcertify_template_designer' );
            $this->save_layout_settings_from_request();
            $this->redirect_with_notice( 'smartcertify_designer', 'Template layout saved successfully.' );
        }

        $template = SmartCert_Helpers::get_master_template();
        $layout = SmartCert_Helpers::get_layout_settings();
        $defaults = SmartCert_Helpers::get_layout_defaults();
        $fields = $this->get_layout_editor_fields();
        $preview = $this->get_template_preview_data();
        ?>
        <div class="wrap">
            <h1>Template Designer</h1>
            <?php $this->render_notice_from_query(); ?>

            <div class="sc-designer-header-card">
                <div>
                    <p class="sc-designer-header-copy">Drag each label or box directly on the template preview. The positions update automatically in the form, and saving this page updates the live certificate generator.</p>
                    <p class="sc-designer-header-copy" style="margin-top:8px;">Tip: drag the element first, then fine-tune the value from the control panel if you want a very exact position.</p>
                </div>
                <div class="sc-designer-header-actions">
                    <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=smartcertify_settings' ) ); ?>">Back To Settings</a>
                    <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=smartcertify_upload' ) ); ?>">Manage Template</a>
                    <?php if ( ! empty( $template['url'] ) ) : ?>
                        <a class="button button-secondary" href="<?php echo esc_url( $template['url'] ); ?>" target="_blank" rel="noopener">View Original Template</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sc-designer-transfer-card">
                <div>
                    <h2>Design Settings Transfer</h2>
                    <p>Export the current template layout as a JSON file, or upload a saved layout file to restore the designer quickly on another site or after resetting the defaults.</p>
                </div>
                <div class="sc-designer-transfer-actions">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'smartcertify_export_template_layout' ); ?>
                        <input type="hidden" name="action" value="smartcertify_export_template_layout" />
                        <button type="submit" class="button button-secondary">Export Settings</button>
                    </form>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="sc-designer-import-form">
                        <?php wp_nonce_field( 'smartcertify_import_template_layout' ); ?>
                        <input type="hidden" name="action" value="smartcertify_import_template_layout" />
                        <label class="sc-designer-import-label">
                            <span class="screen-reader-text">Upload template settings file</span>
                            <input type="file" name="layout_settings_file" accept=".json,application/json" required />
                        </label>
                        <button type="submit" class="button button-primary">Upload Settings</button>
                    </form>
                </div>
            </div>

            <?php if ( empty( $template['url'] ) && empty( $template['attachment_id'] ) ) : ?>
                <div class="notice notice-warning"><p>Please upload a master certificate template first, then open this designer again.</p></div>
            <?php elseif ( empty( $preview['url'] ) ) : ?>
                <div class="notice notice-warning"><p><?php echo esc_html( $preview['warning'] ?: 'Template preview is not available yet. Please try a PNG/JPG template or enable Imagick for PDF previews.' ); ?></p></div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field( 'smartcertify_template_designer' ); ?>

                <div class="sc-designer-shell">
                    <div class="sc-designer-main">
                        <?php if ( ! empty( $preview['url'] ) ) : ?>
                            <div
                                class="sc-designer-stage"
                                id="sc_template_designer"
                                data-defaults="<?php echo esc_attr( wp_json_encode( $defaults ) ); ?>"
                                data-font-preview-map="<?php echo esc_attr( wp_json_encode( $this->get_font_preview_map() ) ); ?>"
                            >
                                <div class="sc-designer-stage-inner">
                                    <img
                                        src="<?php echo esc_url( $preview['url'] ); ?>"
                                        alt="Template preview"
                                        class="sc-designer-image"
                                        <?php if ( ! empty( $preview['width'] ) ) : ?>
                                            data-natural-width="<?php echo esc_attr( intval( $preview['width'] ) ); ?>"
                                        <?php endif; ?>
                                        <?php if ( ! empty( $preview['height'] ) ) : ?>
                                            data-natural-height="<?php echo esc_attr( intval( $preview['height'] ) ); ?>"
                                        <?php endif; ?>
                                    />
                                    <div class="sc-designer-overlay">
                                        <div class="sc-designer-guide sc-designer-guide--vertical" aria-hidden="true"></div>
                                        <div class="sc-designer-guide sc-designer-guide--horizontal" aria-hidden="true"></div>
                                        <?php foreach ( $fields as $field_id => $field ) : ?>
                                            <?php
                                            $field_classes = array( 'sc-designer-node' );
                                            $field_classes[] = 'sc-designer-node--' . $field['node_type'];
                                            if ( ! empty( $field['accent'] ) ) {
                                                $field_classes[] = 'sc-designer-node--' . $field['accent'];
                                            }
                                            ?>
                                            <button
                                                type="button"
                                                class="<?php echo esc_attr( implode( ' ', $field_classes ) ); ?>"
                                                data-field-id="<?php echo esc_attr( $field_id ); ?>"
                                                data-anchor="<?php echo esc_attr( $field['anchor'] ); ?>"
                                                data-x-key="<?php echo esc_attr( $field['x_key'] ); ?>"
                                                data-y-key="<?php echo esc_attr( $field['y_key'] ); ?>"
                                                <?php if ( ! empty( $field['font_key'] ) ) : ?>data-font-key="<?php echo esc_attr( $field['font_key'] ); ?>"<?php endif; ?>
                                                <?php if ( ! empty( $field['font_family_key'] ) ) : ?>data-font-family-key="<?php echo esc_attr( $field['font_family_key'] ); ?>"<?php endif; ?>
                                                <?php if ( ! empty( $field['width_key'] ) ) : ?>data-width-key="<?php echo esc_attr( $field['width_key'] ); ?>"<?php endif; ?>
                                                <?php if ( ! empty( $field['height_key'] ) ) : ?>data-height-key="<?php echo esc_attr( $field['height_key'] ); ?>"<?php endif; ?>
                                                <?php if ( ! empty( $field['size_key'] ) ) : ?>data-size-key="<?php echo esc_attr( $field['size_key'] ); ?>"<?php endif; ?>
                                            >
                                                <span class="sc-designer-node-label"><?php echo esc_html( $field['sample'] ); ?></span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="sc-designer-sidebar">
                        <div class="sc-designer-panel">
                            <div class="sc-designer-panel-header">
                                <h2>Element Controls</h2>
                                <button type="button" class="button button-secondary" id="sc_designer_reset">Reset Recommended Values</button>
                            </div>

                            <div class="sc-designer-switcher">
                                <?php foreach ( $fields as $field_id => $field ) : ?>
                                    <button type="button" class="sc-designer-switcher-button" data-field-id="<?php echo esc_attr( $field_id ); ?>">
                                        <?php echo esc_html( $field['label'] ); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>

                            <div class="sc-designer-control-list">
                                <?php foreach ( $fields as $field_id => $field ) : ?>
                                    <section class="sc-designer-control-card" data-field-id="<?php echo esc_attr( $field_id ); ?>">
                                        <div class="sc-designer-control-title-row">
                                            <h3><?php echo esc_html( $field['label'] ); ?></h3>
                                            <button type="button" class="button-link sc-designer-focus" data-field-id="<?php echo esc_attr( $field_id ); ?>">Highlight</button>
                                        </div>
                                        <p class="sc-designer-control-help"><?php echo esc_html( $field['description'] ); ?></p>
                                        <div class="sc-designer-input-grid">
                                            <label>
                                                <span>X Position</span>
                                                <input type="text" name="<?php echo esc_attr( $field['x_key'] ); ?>" value="<?php echo esc_attr( $layout[ $field['x_key'] ] ); ?>" data-layout-key="<?php echo esc_attr( $field['x_key'] ); ?>" />
                                            </label>
                                            <label>
                                                <span>Y Position</span>
                                                <input type="text" name="<?php echo esc_attr( $field['y_key'] ); ?>" value="<?php echo esc_attr( $layout[ $field['y_key'] ] ); ?>" data-layout-key="<?php echo esc_attr( $field['y_key'] ); ?>" />
                                            </label>

                                            <?php if ( ! empty( $field['font_key'] ) ) : ?>
                                                <label>
                                                    <span>Font Size</span>
                                                    <input type="text" name="<?php echo esc_attr( $field['font_key'] ); ?>" value="<?php echo esc_attr( $layout[ $field['font_key'] ] ); ?>" data-layout-key="<?php echo esc_attr( $field['font_key'] ); ?>" />
                                                </label>
                                            <?php endif; ?>

                                            <?php if ( ! empty( $field['font_family_key'] ) ) : ?>
                                                <label>
                                                    <span>Font</span>
                                                    <select name="<?php echo esc_attr( $field['font_family_key'] ); ?>" data-layout-key="<?php echo esc_attr( $field['font_family_key'] ); ?>">
                                                        <?php foreach ( SmartCert_Helpers::get_font_choices() as $font_key => $font_label ) : ?>
                                                            <option value="<?php echo esc_attr( $font_key ); ?>" <?php selected( $layout[ $field['font_family_key'] ], $font_key ); ?>><?php echo esc_html( $font_label ); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </label>
                                            <?php endif; ?>

                                            <?php if ( ! empty( $field['size_key'] ) ) : ?>
                                                <label>
                                                    <span><?php echo esc_html( $field['size_label'] ); ?></span>
                                                    <input type="text" name="<?php echo esc_attr( $field['size_key'] ); ?>" value="<?php echo esc_attr( $layout[ $field['size_key'] ] ); ?>" data-layout-key="<?php echo esc_attr( $field['size_key'] ); ?>" />
                                                </label>
                                            <?php endif; ?>

                                            <?php if ( ! empty( $field['width_key'] ) ) : ?>
                                                <label>
                                                    <span>Width</span>
                                                    <input type="text" name="<?php echo esc_attr( $field['width_key'] ); ?>" value="<?php echo esc_attr( $layout[ $field['width_key'] ] ); ?>" data-layout-key="<?php echo esc_attr( $field['width_key'] ); ?>" />
                                                </label>
                                            <?php endif; ?>

                                            <?php if ( ! empty( $field['height_key'] ) ) : ?>
                                                <label>
                                                    <span>Height</span>
                                                    <input type="text" name="<?php echo esc_attr( $field['height_key'] ); ?>" value="<?php echo esc_attr( $layout[ $field['height_key'] ] ); ?>" data-layout-key="<?php echo esc_attr( $field['height_key'] ); ?>" />
                                                </label>
                                            <?php endif; ?>
                                        </div>
                                    </section>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <p style="margin-top:20px;">
                    <button type="submit" class="button button-primary button-large" name="sc_save_template_layout" value="1">Save Template Layout</button>
                </p>
            </form>
        </div>
        <?php
    }

    public function page_health_check() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $checks = $this->get_health_checks();
        ?>
        <div class="wrap">
            <h1>Health Check</h1>
            <?php $this->render_notice_from_query(); ?>
            <p>Use this page to confirm that the server, storage paths, QR engine, template setup, mail path, and API configuration are ready for certificate generation.</p>

            <div class="sc-dashboard-grid" style="margin-top:24px;">
                <?php foreach ( $checks as $check ) : ?>
                    <div class="sc-stat-card">
                        <h3><?php echo esc_html( $check['label'] ); ?></h3>
                        <p class="sc-stat-value" style="font-size:20px;color:<?php echo esc_attr( ! empty( $check['healthy'] ) ? '#047857' : '#b91c1c' ); ?>;">
                            <?php echo ! empty( $check['healthy'] ) ? 'Ready' : 'Needs Attention'; ?>
                        </p>
                        <p style="margin-top:10px;color:#475569;"><?php echo esc_html( $check['details'] ); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top:24px;background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;">
                <h2 style="margin-top:0;">Quick Actions</h2>
                <p>
                    <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=smartcertify_upload' ) ); ?>">Open Template Manager</a>
                    <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=smartcertify_designer' ) ); ?>">Open Template Designer</a>
                    <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=smartcertify_backup' ) ); ?>">Open Backup & Transfer</a>
                </p>
            </div>
        </div>
        <?php
    }

    public function page_backup_transfer() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>Backup & Transfer</h1>
            <?php $this->render_notice_from_query(); ?>
            <div style="background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;margin-bottom:24px;">
                <h2 style="margin-top:0;">Export Full Plugin Data</h2>
                <p>Export classes, batches, codes, certificates, logs, template versions, layout settings, delivery settings, login rules, and integration settings as one JSON backup file.</p>
                <p class="description">This export stores SmartCertify data and media references. It does not package uploaded image/PDF files inside the JSON itself.</p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'smartcertify_export_full_data' ); ?>
                    <input type="hidden" name="action" value="smartcertify_export_full_data" />
                    <button type="submit" class="button button-primary">Export Full Data</button>
                </form>
            </div>

            <div style="background:#fff;padding:20px;border:1px solid var(--sc-border);border-radius:12px;">
                <h2 style="margin-top:0;">Import Full Plugin Data</h2>
                <p>Import a full SmartCertify backup JSON. This process replaces the current SmartCertify data tables and settings on this website.</p>
                <p class="description">If the backup came from another site, upload the same template/signature files there as well so media references stay valid.</p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'smartcertify_import_full_data' ); ?>
                    <input type="hidden" name="action" value="smartcertify_import_full_data" />
                    <p><input type="file" name="smartcertify_backup_file" accept=".json,application/json" required /></p>
                    <p>
                        <label>
                            <input type="checkbox" name="replace_existing" value="1" required />
                            I understand this will replace the current SmartCertify data and settings on this site.
                        </label>
                    </p>
                    <button type="submit" class="button button-primary">Import Full Data</button>
                </form>
            </div>
        </div>
        <?php
    }

    public function handle_export_full_data() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'smartcertify_export_full_data' );

        global $wpdb;
        $tables = SmartCert_DB::get_table_map();
        $options = array();

        foreach ( SmartCert_Helpers::get_export_option_names() as $option_name ) {
            $options[ $option_name ] = get_option( $option_name );
        }

        $payload = array(
            'plugin'      => 'SmartCertify',
            'version'     => defined( 'SMARTCERTIFY_VERSION' ) ? SMARTCERTIFY_VERSION : '4.0.0',
            'exported_at' => current_time( 'mysql' ),
            'site_url'    => home_url( '/' ),
            'options'     => $options,
            'tables'      => array(),
        );

        foreach ( $tables as $slug => $table_name ) {
            $payload['tables'][ $slug ] = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id ASC", ARRAY_A );
        }

        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }

        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="smartcertify-full-backup-' . gmdate( 'Y-m-d-H-i-s' ) . '.json"' );

        echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        exit;
    }

    public function handle_import_full_data() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'smartcertify_import_full_data' );

        if ( empty( $_POST['replace_existing'] ) ) {
            $this->redirect_with_notice( 'smartcertify_backup', 'Please confirm that the import should replace current SmartCertify data.', 'error' );
        }

        if ( empty( $_FILES['smartcertify_backup_file']['tmp_name'] ) || ! empty( $_FILES['smartcertify_backup_file']['error'] ) ) {
            $this->redirect_with_notice( 'smartcertify_backup', 'Please choose a valid SmartCertify backup JSON file.', 'error' );
        }

        $raw_payload = file_get_contents( $_FILES['smartcertify_backup_file']['tmp_name'] );
        if ( false === $raw_payload || '' === trim( $raw_payload ) ) {
            $this->redirect_with_notice( 'smartcertify_backup', 'The uploaded backup file is empty.', 'error' );
        }

        $decoded = json_decode( $raw_payload, true );
        if ( ! is_array( $decoded ) || empty( $decoded['tables'] ) || empty( $decoded['options'] ) ) {
            $this->redirect_with_notice( 'smartcertify_backup', 'This backup file is not a valid SmartCertify full export.', 'error' );
        }

        global $wpdb;
        SmartCert_DB::create_tables();
        $tables = SmartCert_DB::get_table_map();

        foreach ( array( 'logs', 'certificates', 'codes', 'batches', 'classes' ) as $slug ) {
            if ( isset( $tables[ $slug ] ) ) {
                $wpdb->query( "DELETE FROM {$tables[$slug]}" );
            }
        }

        foreach ( SmartCert_Helpers::get_export_option_names() as $option_name ) {
            delete_option( $option_name );
        }

        foreach ( $decoded['options'] as $option_name => $option_value ) {
            if ( 0 !== strpos( (string) $option_name, 'smartcertify_' ) ) {
                continue;
            }
            update_option( sanitize_text_field( $option_name ), $option_value );
        }

        foreach ( array( 'classes', 'batches', 'codes', 'certificates', 'logs' ) as $slug ) {
            if ( empty( $decoded['tables'][ $slug ] ) || empty( $tables[ $slug ] ) ) {
                continue;
            }

            foreach ( $decoded['tables'][ $slug ] as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }
                $wpdb->insert( $tables[ $slug ], $row );
            }
        }

        SmartCert_DB::update_schema();
        SmartCert_DB::ensure_default_batches();

        $this->redirect_with_notice( 'smartcertify_backup', 'SmartCertify full backup imported successfully.' );
    }

    private function export_logs_csv( $where, $params ) {
        global $wpdb;
        $table = $wpdb->prefix . 'smartcertify_logs';

        if ( $params ) {
            $rows = $wpdb->get_results(
                call_user_func_array(
                    array( $wpdb, 'prepare' ),
                    array_merge( array( "SELECT * FROM $table WHERE $where ORDER BY timestamp DESC" ), $params )
                )
            );
        } else {
            $rows = $wpdb->get_results( "SELECT * FROM $table WHERE $where ORDER BY timestamp DESC" );
        }

        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="smartcertify_logs_' . date( 'Y-m-d_H-i-s' ) . '.csv"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
        $out = fopen( 'php://output', 'w' );
        fwrite( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, array( 'Student', 'Class', 'Batch', 'Teacher', 'Code', 'Serial', 'Status', 'Timestamp', 'Verification URL' ) );

        foreach ( $rows as $row ) {
            fputcsv(
                $out,
                array(
                    $row->student_name,
                    $row->class_name,
                    $row->batch_name,
                    $row->teacher_name,
                    $this->format_csv_text_cell( $row->code ),
                    $this->format_csv_text_cell( $row->serial ),
                    $row->status,
                    $row->timestamp,
                    $this->format_csv_text_cell( $row->verification_url ),
                )
            );
        }

        fclose( $out );
        exit;
    }

    private function parse_code_rows_from_request( $class, $default_batch_id, $default_limit, $default_student, $default_email, $default_phone, $default_status ) {
        $rows = array();

        if ( ! empty( $_FILES['codes_csv']['tmp_name'] ) ) {
            $csv_rows = array_map( 'str_getcsv', file( $_FILES['codes_csv']['tmp_name'] ) );
            if ( ! empty( $csv_rows ) ) {
                $headers = array_map( array( $this, 'normalize_header' ), $csv_rows[0] );
                $has_headers = in_array( 'code', $headers, true );

                if ( $has_headers ) {
                    array_shift( $csv_rows );
                    foreach ( $csv_rows as $row ) {
                        $assoc = array();
                        foreach ( $headers as $index => $header ) {
                            $assoc[ $header ] = trim( $row[ $index ] ?? '' );
                        }
                        $rows[] = $this->build_code_row_from_assoc( $class, $assoc, $default_batch_id, $default_limit, $default_student, $default_email, $default_phone, $default_status );
                    }
                } else {
                    foreach ( $csv_rows as $row ) {
                        if ( isset( $row[0] ) ) {
                            $rows[] = array(
                                'code'          => trim( $row[0] ),
                                'batch_id'      => $default_batch_id,
                                'download_limit'=> $default_limit,
                                'student_name'  => $default_student,
                                'student_email' => $default_email,
                                'student_phone' => $default_phone,
                                'status'        => $default_status,
                            );
                        }
                    }
                }
            }
        }

        $codes_text = sanitize_textarea_field( $_POST['codes'] ?? '' );
        if ( $codes_text ) {
            foreach ( preg_split( '/\r?\n/', $codes_text ) as $code ) {
                if ( '' !== trim( $code ) ) {
                    $rows[] = array(
                        'code'          => trim( $code ),
                        'batch_id'      => $default_batch_id,
                        'download_limit'=> $default_limit,
                        'student_name'  => $default_student,
                        'student_email' => $default_email,
                        'student_phone' => $default_phone,
                        'status'        => $default_status,
                    );
                }
            }
        }

        if ( ! empty( $_POST['single_code'] ) ) {
            $rows[] = array(
                'code'          => sanitize_text_field( $_POST['single_code'] ),
                'batch_id'      => $default_batch_id,
                'download_limit'=> $default_limit,
                'student_name'  => $default_student,
                'student_email' => $default_email,
                'student_phone' => $default_phone,
                'status'        => $default_status,
            );
        }

        $mobile_numbers = sanitize_textarea_field( $_POST['mobile_numbers'] ?? '' );
        if ( $mobile_numbers ) {
            foreach ( preg_split( '/\r?\n/', $mobile_numbers ) as $mobile ) {
                $mobile = trim( $mobile );
                if ( '' !== $mobile ) {
                    $rows[] = array(
                        'code'          => $this->mobile_to_code( $mobile ),
                        'batch_id'      => $default_batch_id,
                        'download_limit'=> $default_limit,
                        'student_name'  => $default_student,
                        'student_email' => $default_email,
                        'student_phone' => $default_phone,
                        'status'        => $default_status,
                    );
                }
            }
        }

        return array_values( array_filter( $rows ) );
    }

    private function build_code_row_from_assoc( $class, $assoc, $default_batch_id, $default_limit, $default_student, $default_email, $default_phone, $default_status ) {
        $batch_id = $default_batch_id;
        if ( ! empty( $assoc['batch'] ) ) {
            $batch = SmartCert_Helpers::get_batch_by_name( $class->id, $assoc['batch'] );
            if ( $batch ) {
                $batch_id = intval( $batch->id );
            }
        }

        return array(
            'code'           => $assoc['code'] ?? '',
            'batch_id'       => $batch_id,
            'download_limit' => max( 0, intval( $assoc['download_limit'] ?? $default_limit ) ),
            'student_name'   => sanitize_text_field( $assoc['student_name'] ?? $default_student ),
            'student_email'  => sanitize_email( $assoc['student_email'] ?? $default_email ),
            'student_phone'  => sanitize_text_field( $assoc['student_phone'] ?? $default_phone ),
            'status'         => sanitize_text_field( $assoc['status'] ?? $default_status ),
        );
    }

    private function normalize_header( $value ) {
        return strtolower( preg_replace( '/[^a-z0-9_]/', '', str_replace( ' ', '_', trim( (string) $value ) ) ) );
    }

    public function handle_export_template_layout() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'smartcertify_export_template_layout' );

        $payload = array(
            'plugin'      => 'SmartCertify',
            'version'     => defined( 'SMARTCERTIFY_VERSION' ) ? SMARTCERTIFY_VERSION : '4.0.0',
            'exported_at' => current_time( 'mysql' ),
            'layout'      => SmartCert_Helpers::get_layout_settings(),
        );

        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }

        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="smartcertify-template-layout-' . gmdate( 'Y-m-d-H-i-s' ) . '.json"' );

        echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        exit;
    }

    public function handle_import_template_layout() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'smartcertify_import_template_layout' );

        if ( empty( $_FILES['layout_settings_file']['tmp_name'] ) || ! empty( $_FILES['layout_settings_file']['error'] ) ) {
            $this->redirect_with_notice( 'smartcertify_designer', 'Please choose a valid JSON settings file to upload.', 'error' );
        }

        $raw_payload = file_get_contents( $_FILES['layout_settings_file']['tmp_name'] );
        if ( false === $raw_payload || '' === trim( $raw_payload ) ) {
            $this->redirect_with_notice( 'smartcertify_designer', 'The uploaded settings file is empty.', 'error' );
        }

        $decoded = json_decode( $raw_payload, true );
        if ( ! is_array( $decoded ) ) {
            $this->redirect_with_notice( 'smartcertify_designer', 'The uploaded settings file is not valid JSON.', 'error' );
        }

        $layout_payload = isset( $decoded['layout'] ) && is_array( $decoded['layout'] ) ? $decoded['layout'] : $decoded;
        $sanitized_layout = SmartCert_Helpers::sanitize_layout_settings( $layout_payload );

        if ( empty( $sanitized_layout ) ) {
            $this->redirect_with_notice( 'smartcertify_designer', 'No usable layout values were found in that settings file.', 'error' );
        }

        $final_layout = array_merge( SmartCert_Helpers::get_layout_settings(), $sanitized_layout );
        foreach ( $final_layout as $key => $value ) {
            update_option( 'smartcertify_' . $key, $value );
        }

        $this->redirect_with_notice( 'smartcertify_designer', 'Template layout imported successfully.' );
    }

    private function save_layout_settings_from_request() {
        $layout = SmartCert_Helpers::sanitize_layout_settings( wp_unslash( $_POST ) );
        foreach ( array_keys( SmartCert_Helpers::get_layout_defaults() ) as $key ) {
            if ( isset( $layout[ $key ] ) ) {
                update_option( 'smartcertify_' . $key, $layout[ $key ] );
            }
        }
    }

    private function get_layout_editor_fields() {
        return array(
            'name' => array(
                'label'       => 'Student Name',
                'description' => 'Main student name printed in the center of the certificate.',
                'sample'      => 'Student Name',
                'node_type'   => 'text',
                'accent'      => 'primary',
                'anchor'      => 'center-bottom',
                'x_key'       => 'name_x',
                'y_key'       => 'name_y',
                'font_key'    => 'name_font_size',
                'font_family_key' => 'name_font_family',
            ),
            'class' => array(
                'label'       => 'Class Name',
                'description' => 'Class name printed below the certificate title.',
                'sample'      => 'Class Name',
                'node_type'   => 'text',
                'accent'      => 'dark',
                'anchor'      => 'center-bottom',
                'x_key'       => 'class_x',
                'y_key'       => 'class_y',
                'font_key'    => 'class_font_size',
                'font_family_key' => 'class_font_family',
            ),
            'batch' => array(
                'label'       => 'Batch Name',
                'description' => 'Batch text printed under the class name when a batch is selected.',
                'sample'      => 'Batch Name',
                'node_type'   => 'text',
                'accent'      => 'muted',
                'anchor'      => 'center-bottom',
                'x_key'       => 'batch_x',
                'y_key'       => 'batch_y',
                'font_key'    => 'batch_font_size',
                'font_family_key' => 'batch_font_family',
            ),
            'teacher_signature' => array(
                'label'       => 'Teacher Signature',
                'description' => 'Second teacher signature image area for the selected batch.',
                'sample'      => 'Teacher Signature',
                'node_type'   => 'box',
                'accent'      => 'gold',
                'anchor'      => 'top-left',
                'x_key'       => 'teacher_signature_x',
                'y_key'       => 'teacher_signature_y',
                'width_key'   => 'teacher_signature_width',
                'height_key'  => 'teacher_signature_height',
            ),
            'teacher_name' => array(
                'label'       => 'Teacher Name',
                'description' => 'Teacher name shown below the second signature area.',
                'sample'      => 'Teacher Name',
                'node_type'   => 'text',
                'accent'      => 'dark',
                'anchor'      => 'center-bottom',
                'x_key'       => 'teacher_name_x',
                'y_key'       => 'teacher_name_y',
                'font_key'    => 'teacher_name_font_size',
                'font_family_key' => 'teacher_name_font_family',
            ),
            'qr' => array(
                'label'       => 'QR Code',
                'description' => 'Verification QR code box printed in the scan area.',
                'sample'      => 'QR Code',
                'node_type'   => 'box',
                'accent'      => 'dark',
                'anchor'      => 'top-left',
                'x_key'       => 'qr_x',
                'y_key'       => 'qr_y',
                'size_key'    => 'qr_size',
                'size_label'  => 'Box Size',
            ),
            'meta' => array(
                'label'       => 'Meta Text',
                'description' => 'Serial number and generated date shown near the QR code.',
                'sample'      => 'SN: ABC123',
                'node_type'   => 'text',
                'accent'      => 'muted',
                'anchor'      => 'right-bottom',
                'x_key'       => 'meta_x',
                'y_key'       => 'meta_y',
                'font_key'    => 'meta_font_size',
                'font_family_key' => 'meta_font_family',
            ),
        );
    }

    private function get_font_preview_map() {
        return array(
            'sans'             => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif',
            'montserrat_bold'  => '"MontserratBold", "Montserrat", Arial, sans-serif',
            'serif'            => 'Georgia, "Times New Roman", serif',
            'samarata'         => '"Samarata", cursive',
        );
    }

    private function get_template_preview_data() {
        $template = SmartCert_Helpers::get_master_template();
        $preview = array(
            'url'     => '',
            'width'   => 0,
            'height'  => 0,
            'warning' => '',
        );

        if ( empty( $template['attachment_id'] ) && empty( $template['url'] ) ) {
            $preview['warning'] = 'Upload a master template first.';
            return $preview;
        }

        $source = SmartCert_Helpers::resolve_media_source( $template['attachment_id'], $template['url'] );
        $probe = ! empty( $source['path'] ) ? $source['path'] : $source['url'];
        $ext = strtolower( pathinfo( strtok( (string) $probe, '?' ), PATHINFO_EXTENSION ) );
        $is_pdf = 'application/pdf' === ( $source['mime'] ?? '' ) || 'pdf' === $ext;

        if ( ! $is_pdf ) {
            $preview['url'] = $source['url'];
            if ( ! empty( $source['path'] ) && file_exists( $source['path'] ) ) {
                $image_size = @getimagesize( $source['path'] );
                if ( $image_size ) {
                    $preview['width'] = intval( $image_size[0] );
                    $preview['height'] = intval( $image_size[1] );
                }
            }
            return $preview;
        }

        if ( ! class_exists( 'Imagick' ) ) {
            $preview['warning'] = 'PDF template preview requires Imagick on the server. Upload a PNG/JPG template or enable Imagick to use the drag-and-drop designer.';
            return $preview;
        }

        $rendered = $this->render_pdf_preview_image( $source );
        if ( $rendered ) {
            return $rendered;
        }

        $preview['warning'] = 'Unable to build a preview image from the PDF template.';
        return $preview;
    }

    private function render_pdf_preview_image( $source ) {
        $pdf_path = '';
        $cleanup = false;

        if ( ! empty( $source['path'] ) && file_exists( $source['path'] ) ) {
            $pdf_path = $source['path'];
        } elseif ( ! empty( $source['url'] ) ) {
            $response = wp_remote_get(
                $source['url'],
                array(
                    'timeout'   => 20,
                    'sslverify' => false,
                )
            );

            if ( is_wp_error( $response ) ) {
                return false;
            }

            $body = wp_remote_retrieve_body( $response );
            if ( empty( $body ) ) {
                return false;
            }

            $uploads = wp_upload_dir();
            $tmp_dir = trailingslashit( $uploads['basedir'] ) . 'smartcertify/previews';
            if ( ! file_exists( $tmp_dir ) ) {
                wp_mkdir_p( $tmp_dir );
            }

            $pdf_path = trailingslashit( $tmp_dir ) . 'remote_preview_' . md5( $source['url'] ) . '.pdf';
            file_put_contents( $pdf_path, $body );
            $cleanup = true;
        }

        if ( ! $pdf_path || ! file_exists( $pdf_path ) ) {
            return false;
        }

        $uploads = wp_upload_dir();
        $preview_dir = trailingslashit( $uploads['basedir'] ) . 'smartcertify/previews';
        if ( ! file_exists( $preview_dir ) ) {
            wp_mkdir_p( $preview_dir );
        }

        $version_token = file_exists( $pdf_path ) ? filemtime( $pdf_path ) : time();
        $hash = md5( $pdf_path . '|' . $version_token . '|designer-preview-v2' );
        $preview_path = trailingslashit( $preview_dir ) . 'template_preview_' . $hash . '.png';
        $preview_url = trailingslashit( $uploads['baseurl'] ) . 'smartcertify/previews/template_preview_' . $hash . '.png';

        if ( ! file_exists( $preview_path ) ) {
            try {
                $imagick = new Imagick();
                $imagick->setResolution( 170, 170 );
                $imagick->readImage( $pdf_path . '[0]' );
                $imagick->setImageBackgroundColor( 'white' );
                $imagick = $imagick->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );
                $imagick->setImageFormat( 'png' );
                $imagick->writeImage( $preview_path );
                $imagick->clear();
            } catch ( Exception $e ) {
                if ( $cleanup && file_exists( $pdf_path ) ) {
                    @unlink( $pdf_path );
                }
                return false;
            }
        }

        if ( $cleanup && file_exists( $pdf_path ) ) {
            @unlink( $pdf_path );
        }

        $image_size = @getimagesize( $preview_path );

        return array(
            'url'     => file_exists( $preview_path ) ? $preview_url : '',
            'width'   => $image_size ? intval( $image_size[0] ) : 0,
            'height'  => $image_size ? intval( $image_size[1] ) : 0,
            'warning' => '',
        );
    }

    private function mobile_to_code( $mobile ) {
        $digits = preg_replace( '/\D/', '', (string) $mobile );
        return strlen( $digits ) > 6 ? substr( $digits, -6 ) : $digits;
    }

    private function format_csv_text_cell( $value ) {
        $value = (string) $value;

        if ( '' === $value ) {
            return '';
        }

        return "\t" . $value;
    }

    private function get_health_checks() {
        $uploads = wp_upload_dir();
        $smartcert_dir = trailingslashit( $uploads['basedir'] ) . 'smartcertify';
        $public_dir = trailingslashit( $uploads['basedir'] ) . 'smartcertify_public';
        $preview_dir = trailingslashit( $uploads['basedir'] ) . 'smartcertify/previews';
        $template = SmartCert_Helpers::get_master_template();
        $api_settings = SmartCert_Helpers::get_api_settings();

        return array(
            array(
                'label'   => 'Bundled Local QR Library',
                'healthy' => class_exists( 'SmartCert_Local_QR' ),
                'details' => class_exists( 'SmartCert_Local_QR' ) ? 'The bundled QR engine is available for local certificate QR generation.' : 'The bundled QR engine could not be loaded.',
            ),
            array(
                'label'   => 'GD / Image Support',
                'healthy' => function_exists( 'imagecreatetruecolor' ),
                'details' => function_exists( 'imagecreatetruecolor' ) ? 'GD image functions are available for QR and certificate rendering.' : 'GD image functions are missing on this server.',
            ),
            array(
                'label'   => 'PDF Preview Engine',
                'healthy' => class_exists( 'Imagick' ),
                'details' => class_exists( 'Imagick' ) ? 'Imagick is available for PDF template preview rendering.' : 'Imagick is not available. PDF template previews will be limited.',
            ),
            array(
                'label'   => 'SmartCertify Upload Folder',
                'healthy' => wp_mkdir_p( $smartcert_dir ) && is_writable( $smartcert_dir ),
                'details' => $smartcert_dir,
            ),
            array(
                'label'   => 'Public PDF Folder',
                'healthy' => wp_mkdir_p( $public_dir ) && is_writable( $public_dir ),
                'details' => $public_dir,
            ),
            array(
                'label'   => 'Preview Folder',
                'healthy' => wp_mkdir_p( $preview_dir ) && is_writable( $preview_dir ),
                'details' => $preview_dir,
            ),
            array(
                'label'   => 'Master Template',
                'healthy' => ! empty( $template['url'] ) || ! empty( $template['attachment_id'] ),
                'details' => ! empty( $template['label'] ) ? 'Active template: ' . $template['label'] : 'Upload a master template to enable certificate generation.',
            ),
            array(
                'label'   => 'Cleanup Cron',
                'healthy' => (bool) wp_next_scheduled( SmartCert_Cleanup::CRON_HOOK ),
                'details' => wp_next_scheduled( SmartCert_Cleanup::CRON_HOOK ) ? 'WordPress cron is scheduled for automatic certificate cleanup.' : 'Cleanup cron is not scheduled right now.',
            ),
            array(
                'label'   => 'Login Protection',
                'healthy' => SmartCert_Helpers::is_login_required_for_download(),
                'details' => SmartCert_Helpers::is_login_required_for_download() ? 'Students must log in before generating and downloading certificates.' : 'Login protection is currently disabled.',
            ),
            array(
                'label'   => 'Site Logo For Email',
                'healthy' => '' !== SmartCert_Helpers::get_site_logo_url(),
                'details' => SmartCert_Helpers::get_site_logo_url() ? 'The site logo will be used in certificate emails.' : 'No site logo or site icon was found for branded certificate emails.',
            ),
            array(
                'label'   => 'Mail Path',
                'healthy' => function_exists( 'wp_mail' ),
                'details' => function_exists( 'wp_mail' ) ? 'SmartCertify sends through wp_mail, so any SMTP plugin connected to WordPress will also handle certificate emails.' : 'wp_mail is not available in this environment.',
            ),
            array(
                'label'   => 'REST API Key',
                'healthy' => ! empty( $api_settings['key'] ),
                'details' => ! empty( $api_settings['key'] ) ? 'Protected API routes are ready. Verify URL: ' . $api_settings['verify_url'] : 'API key is not configured.',
            ),
        );
    }

    private function render_notice_from_query() {
        $message = sanitize_text_field( $_GET['sc_notice'] ?? '' );
        if ( '' === $message ) {
            return;
        }

        $type = sanitize_text_field( $_GET['sc_notice_type'] ?? 'success' );
        $class = 'notice-success';
        if ( 'error' === $type ) {
            $class = 'notice-error';
        } elseif ( 'warning' === $type ) {
            $class = 'notice-warning';
        }

        $link_url = esc_url_raw( $_GET['sc_link_url'] ?? '' );
        $link_label = sanitize_text_field( $_GET['sc_link_label'] ?? 'Open' );

        echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p>';
        if ( $link_url ) {
            echo '<p><a class="button button-secondary" href="' . esc_url( $link_url ) . '" target="_blank" rel="noopener">' . esc_html( $link_label ) . '</a></p>';
        }
        echo '</div>';
    }

    private function render_verification_result_card( $serial, $student_name = '' ) {
        $result = SmartCert_Service::verify_certificate( $serial, $student_name );
        $certificate = $result['certificate'] ?? array();
        $status = $result['status'] ?? 'not_found';
        $class = in_array( $status, array( 'valid' ), true ) ? 'sc-verify-success' : ( in_array( $status, array( 'expired', 'renewed' ), true ) ? 'sc-verify-warning' : 'sc-verify-error' );

        ob_start();
        ?>
        <div class="sc-verify-result <?php echo esc_attr( $class ); ?>" style="margin-bottom:20px;">
            <h3><?php echo esc_html( $result['title'] ?? 'Verification Result' ); ?></h3>
            <p><?php echo esc_html( $result['message'] ?? '' ); ?></p>
            <?php if ( ! empty( $certificate ) ) : ?>
                <ul class="sc-verify-list">
                    <li><strong>Student:</strong> <?php echo esc_html( $certificate['student_name'] ?? '' ); ?></li>
                    <li><strong>Class:</strong> <?php echo esc_html( $certificate['class_name'] ?? '' ); ?></li>
                    <li><strong>Batch:</strong> <?php echo esc_html( ! empty( $certificate['batch_name'] ) ? $certificate['batch_name'] : 'N/A' ); ?></li>
                    <li><strong>Teacher:</strong> <?php echo esc_html( ! empty( $certificate['teacher_name'] ) ? $certificate['teacher_name'] : 'N/A' ); ?></li>
                    <li><strong>Serial:</strong> <?php echo esc_html( $certificate['serial'] ?? '' ); ?></li>
                    <li><strong>Issued:</strong> <?php echo esc_html( $certificate['generated_at'] ?? '' ); ?></li>
                    <li><strong>Valid Until:</strong> <?php echo esc_html( ! empty( $certificate['certificate_expires_at'] ) ? $certificate['certificate_expires_at'] : 'N/A' ); ?></li>
                    <li><strong>Status:</strong> <?php echo esc_html( ucfirst( $status ) ); ?></li>
                    <?php if ( 'revoked' === $status && ! empty( $certificate['revoke_reason'] ) ) : ?>
                        <li><strong>Reason:</strong> <?php echo esc_html( $certificate['revoke_reason'] ); ?></li>
                    <?php endif; ?>
                    <?php if ( ! empty( $result['replacement'] ) ) : ?>
                        <li><strong>Latest Serial:</strong> <?php echo esc_html( $result['replacement']['serial'] ?? '' ); ?></li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function redirect_with_notice( $page, $message, $type = 'success' ) {
        wp_redirect(
            add_query_arg(
                array(
                    'page'           => $page,
                    'sc_notice'      => $message,
                    'sc_notice_type' => $type,
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    private function is_valid_asset( $url ) {
        return SmartCert_Helpers::is_valid_template_value( $url );
    }

    private function is_valid_image_asset( $url ) {
        $ext = strtolower( pathinfo( strtok( (string) $url, '?' ), PATHINFO_EXTENSION ) );
        return in_array( $ext, array( 'png', 'jpg', 'jpeg', 'gif', 'webp' ), true );
    }

    private function render_media_picker_script() {
        ?>
        <script>
        jQuery(function($){
            $(document).on('click', '.sc-media-button', function(e){
                e.preventDefault();
                var target = $(this).data('target');
                var targetId = $(this).data('target-id');
                var frame = wp.media({
                    title: 'Choose file',
                    button: { text: 'Use this file' },
                    multiple: false
                });

                frame.on('select', function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    if (target) {
                        $(target).val(attachment.url);
                    }
                    if (targetId) {
                        $(targetId).val(attachment.id || 0);
                    }
                });

                frame.open();
            });
        });
        </script>
        <?php
    }
}

new SmartCert_Admin();
