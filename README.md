# SmartCertify - Certificate Plugin for WordPress

A WordPress certificate plugin for managing classes, batches, QR-verified certificates, secure downloads, delivery, lifecycle actions, integrations, and analytics from one admin panel.

## Features

✅ **Single Master Template** - One template for all classes with dynamic class and batch printing  
✅ **Class + Batch Workflow** - Issue certificates based on exact class, batch, and code matching  
✅ **Bundled Local QR** - Every certificate includes a locally generated verification QR code  
✅ **Signed Secure Downloads** - Expiring token-based PDF links instead of nonce-only links  
✅ **Login Protected Access** - Students can be required to log in before generating and downloading  
✅ **Bulk Batch Issue** - Generate a full batch of certificates in one action  
✅ **Certificate Lifecycle** - Revoke, reissue, renew, and track certificate validity  
✅ **Student History** - Search students and review full certificate history  
✅ **Delivery Tools** - Send branded HTML emails and prepare WhatsApp share links  
✅ **Analytics Dashboard** - Track usage, status counts, and batch-wise totals  
✅ **Health + Backup Tools** - Health checks, template versioning, PDF preview test, and full export/import  
✅ **API + Webhooks** - Protected REST API routes and outbound webhook events  

## Installation

1. Download or clone this plugin folder
2. Upload to `/wp-content/plugins/smartcertify/`
3. Activate the plugin in WordPress admin panel
4. Go to **SmartCertify** in the admin menu to manage certificates, template versions, backups, and settings

## Quick Start

### 1. Setup Your Certificate Template

- Go to **SmartCertify > Classes & Template** in the WordPress admin
- Upload your certificate template image (PNG, JPG, or PDF)
- Use **Template Designer** to position text, QR, and signature areas
- Use **Generate Test PDF Preview** before going live

### 2. Add Batches And Codes

- Go to **SmartCertify > Manage Batches** to create batches and assign teacher details
- Go to **SmartCertify > Manage Codes** to add or import class-batch codes
- Optionally lock codes to a student name, email, and phone number

### 3. Display Certificate Form

- Use the shortcode `[smartcertify_form]` on any page or post
- If login protection is enabled, students log in first and SmartCertify uses their account email for certificate delivery

## Usage Guide

### For Administrators

**Managing Classes & Batches:**
1. Add your class in **SmartCertify > Classes & Template**
2. Create one or more batches in **SmartCertify > Manage Batches**
3. Set the second teacher name and signature for each batch

**Managing Codes:**
1. Open **SmartCertify > Manage Codes**
2. Select the class and batch
3. Add codes manually or import from CSV
4. Optionally store student name, email, phone, and download limit

**Bulk Issue & History:**
- Use **SmartCertify > Bulk Issue** to create certificates for a whole batch
- Use **SmartCertify > Student History** to search, revoke, reissue, renew, and resend
- Use **SmartCertify > Analytics** to view batch-wise totals and status counts
- Use **SmartCertify > Health Check** before launch or after server changes
- Use **SmartCertify > Backup & Transfer** to export or import all SmartCertify data

### For End Users

Users follow these simple steps:

1. **Select Class** - Choose their course/class from the dropdown
2. **Login First** - If required by admin, students log in with their website account
3. **Enter Name** - Type their full name as it should appear on certificate
4. **Enter Code** - Input the certificate code provided to them
5. **Get Certificate** - Click the button
6. **Download/View** - Two options appear:
   - **Download Certificate** - Direct download to their device
   - **View in Browser** - Opens PDF in a new tab

## Shortcode Reference

### smartcertify_form

Display the certificate download form on any page.

**Basic usage:**
```
[smartcertify_form]
```

**With attributes (future extensibility):**
```
[smartcertify_form class="Course101" show_instructions="true"]
```

## Hooks & Filters

SmartCertify provides WordPress hooks for customization by developers:

### Actions

**`smartcertify_before_form_render`**
Fires before the certificate form is rendered.
```php
add_action('smartcertify_before_form_render', function() {
    // Custom code here
});
```

**`smartcertify_after_certificate_generated`**
Fires after a certificate is successfully generated.
```php
add_action('smartcertify_after_certificate_generated', function($student_name, $class_name, $pdf_path) {
    // Send email notification, log to external service, etc.
}, 10, 3);
```

**`smartcertify_download_limit_exceeded`**
Fires when a user tries to download beyond their limit.
```php
add_action('smartcertify_download_limit_exceeded', function($class, $code) {
    // Log attempt, send admin notification, etc.
}, 10, 2);
```

### Filters

**`smartcertify_certificate_filename`**
Filter the generated certificate filename.
```php
add_filter('smartcertify_certificate_filename', function($filename, $student_name, $class_name) {
    return $student_name . '_' . $class_name . '.pdf';
}, 10, 3);
```

**`smartcertify_allowed_classes`**
Filter which classes are displayed in the form.
```php
add_filter('smartcertify_allowed_classes', function($classes) {
    return $classes; // Modify and return
}, 10, 1);
```

**`smartcertify_download_limit`**
Filter the download limit for a specific code.
```php
add_filter('smartcertify_download_limit', function($limit, $class, $code) {
    return $limit; // Modify based on your logic
}, 10, 3);
```

## Database Schema

SmartCertify creates the following tables in your WordPress database:

### `wp_smartcertify_classes`
Stores certificate class information.
- `id` - Unique class ID
- `class_name` - Class name/title
- `certificate_template` - Path to template image
- `created_at` - Creation timestamp

### `wp_smartcertify_codes`
Stores enrollment codes and download tracking.
- `id` - Unique code ID
- `class_name` - Associated class
- `code` - 6-digit enrollment code
- `download_count` - Number of times downloaded
- `download_limit` - Maximum downloads allowed (0 = unlimited)
- `created_at` - Creation timestamp

### `wp_smartcertify_downloads`
Audit log of all certificate downloads.
- `id` - Unique record ID
- `student_name` - Name on certificate
- `class_name` - Class name
- `code` - Code used
- `download_count` - Count at time of download
- `serial` - Unique certificate serial number
- `user_ip` - IP address of downloader
- `user_agent` - Browser/device info
- `downloaded_at` - Timestamp

## Troubleshooting

### Form Not Showing

**Problem:** Shortcode `[smartcertify_form]` displays nothing

**Solutions:**
1. Verify plugin is activated in **Plugins** menu
2. Check that jQuery is not disabled on the page
3. Ensure Bootstrap 5 CSS is loaded (plugin includes it via CDN)
4. Check browser console for JavaScript errors

### Certificate Not Generating

**Problem:** Form shows error "Invalid code or class"

**Solutions:**
1. Verify the enrollment code exists - check **SmartCertify > Codes**
2. Confirm the class name matches exactly (case-sensitive)
3. Check download limit hasn't been exceeded
4. Check server error logs for PHP errors

### Download Not Working

**Problem:** Download button doesn't work or PDF is empty

**Solutions:**
1. Verify PDF file exists on server at configured path
2. Check server permissions (755+ for directories, 644+ for files)
3. Ensure sufficient disk space available
4. Check server error logs for file access errors
5. Try opening PDF directly in browser using full URL

### Performance Issues

**Solutions:**
1. Limit number of visible classes using filters
2. Archive old download logs to reduce database size
3. Use a caching plugin (SmartCertify respects WordPress caching)
4. Ensure PHP memory limit is at least 128MB

## Security Features

✅ **Nonce Verification** - CSRF protection on form submissions  
✅ **Code Validation** - Enrollments codes are verified server-side  
✅ **File Protection** - PDFs served with security checks, not direct access  
✅ **Rate Limiting** - Download limits prevent abuse  
✅ **Input Sanitization** - All user inputs sanitized and escaped  
✅ **SQL Prepared Statements** - SQL injection protection  
✅ **Permission Checks** - Admin functions require WordPress capabilities  

## Performance

SmartCertify is optimized for performance:

- **Lightweight** - ~50KB total plugin size
- **Database Efficient** - Minimal queries, proper indexing
- **CSS/JS** - Single consolidated stylesheet and script
- **Image Optimization** - Supports WebP and modern image formats
- **Caching Friendly** - Works with popular WordPress caching plugins

## Browser Compatibility

✅ Chrome/Edge (latest)  
✅ Firefox (latest)  
✅ Safari (latest)  
✅ Mobile browsers (iOS Safari, Chrome Mobile)  

## Configuration

### Custom CSS

To customize certificate form styling, add CSS to your theme's `style.css` or use a custom CSS plugin:

```css
.smartcertify-container {
    /* Your custom styles */
}

.btn-primary {
    /* Custom button styling */
}
```

### Custom Text

To change button text or labels, use WordPress filters in your theme's `functions.php`:

```php
add_filter('smartcertify_button_text', function() {
    return 'Download My Certificate';
});
```

## Requirements

- **WordPress** 5.0 or higher
- **PHP** 7.2 or higher
- **MySQL** 5.6+ or MariaDB 10.1+
- **Web Server** Apache, Nginx, or LiteSpeed

## Support & Contributing

For issues, feature requests, or to contribute improvements:

1. Check the troubleshooting section above
2. Review browser console for errors (F12 > Console tab)
3. Check server logs for PHP errors
4. Contact your hosting provider if experiencing server errors

## License

This plugin is provided as-is for use on your WordPress site.

## Changelog

### Version 1.7.3
- Removed iframe-based PDF preview system
- Simplified UX with direct download and view buttons
- Improved CSS styling with modern design
- Reduced JavaScript payload significantly
- Enhanced mobile responsiveness
- Consolidated documentation

### Version 1.7.2
- Fixed PDF inline preview with proper byte-range support
- Added Accept-Ranges header for streaming
- Improved Content-Type header handling
- Better error messages for preview failures

### Version 1.7.1
- Initial release
- Basic certificate generation and download
- Code management system
- Audit logging

## Credits

Built with WordPress best practices and optimized for performance and security.

---

**Last Updated:** 2024  
**Plugin Version:** 1.7.3  
**Requires:** WordPress 5.0+, PHP 7.2+
