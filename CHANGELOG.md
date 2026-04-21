# SmartCertify Changelog

## 4.0.0 - March 28, 2026

- Bundled a real local QR library inside the plugin for certificate QR generation.
- Replaced nonce-based PDF download links with signed expiring download tokens.
- Added login-protected certificate generation and account-based delivery flow.
- Added template version history with activate / rollback support.
- Added admin health check page.
- Added PDF preview test button for template validation.
- Added duplicate-certificate prevention rules.
- Added outbound webhook support and protected REST API endpoints.
- Added full plugin export / import for settings and data tables.
- Upgraded certificate emails to branded HTML delivery using the WordPress mail path.

## 3.0.0 - March 28, 2026

- Added local-first QR generation with cached fallback support.
- Added bulk certificate generation for a full batch in one click.
- Added certificate lifecycle controls: revoke, reissue, renewal, expiry tracking.
- Added student history search with certificate actions and delivery tracking.
- Added batch-wise analytics dashboard.
- Added auto email delivery settings and WhatsApp share-link delivery flow.
- Added certificate validity settings and improved verification status handling.
- Moved public and admin verification to certificate records instead of log-only checks.
- Improved generation reliability and reused cached template assets for faster output.

## 2.x

- Earlier versions introduced the master template workflow, batch support, template designer, QR verification, log export improvements, and generation stability fixes.
