# Security Policy

## Supported Versions

Currently supported versions with security updates:

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |

## Security Features

### Input Validation & Sanitization

- All user inputs are sanitized using WordPress functions
- API keys validated with regex pattern
- URL inputs sanitized with `esc_url_raw()`
- Text fields sanitized with `sanitize_text_field()`

### Authentication & Authorization

- Capability checks: `manage_options` required for all admin functions
- REST API endpoints protected with `check_permissions()`
- Nonce verification on all form submissions
- Settings page verifies user capabilities before rendering

### Data Protection

- Options use `autoload=false` to prevent unnecessary queries
- Sensitive data (API keys) stored securely in wp_options
- No plaintext credentials in code
- Proper escaping on output: `esc_html()`, `esc_attr()`, `esc_url()`

### API Security

- Google PageSpeed API key optional (not hardcoded)
- API requests use WordPress `wp_remote_get()` with timeout limits
- Error messages don't expose sensitive information
- API responses validated before processing

### Database Operations

- Uses WordPress database abstraction layer
- Prepared statements through WordPress functions
- No direct SQL queries
- Data sanitized before database operations

### File Security

- Direct access prevention: `if (!defined('ABSPATH')) exit;`
- Uninstall cleanup in dedicated uninstall.php
- No file upload/download functionality
- No eval() or dynamic code execution

## Known Limitations

1. **API Key Storage**: Stored in database (wp_options table). Consider using WordPress constants for production.
2. **No Rate Limiting**: Plugin doesn't implement rate limiting on API calls.
3. **External API Dependency**: Relies on Google PageSpeed Insights API availability.

## Reporting a Vulnerability

If you discover a security vulnerability, please follow these steps:

1. **DO NOT** open a public GitHub issue
2. Email security details to: [Your Email Here]
3. Include:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if any)

### Response Timeline

- **Initial Response**: Within 48 hours
- **Vulnerability Assessment**: Within 7 days
- **Patch Release**: Within 14 days for critical issues

### Disclosure Policy

- We follow responsible disclosure principles
- Vulnerabilities kept confidential until patched
- Credit given to reporters (if desired)
- Security advisories published on GitHub

## Best Practices for Users

### Installation

1. Download from official repository only
2. Verify file integrity before installation
3. Use HTTPS for WordPress admin
4. Keep WordPress core updated

### API Key Management

```php
// Recommended: Add to wp-config.php instead of database
define('GOOGLE_PAGESPEED_API_KEY', 'your-api-key-here');
```

### Server Configuration

- Use PHP 7.4+ for better security
- Enable HTTPS/SSL on your site
- Restrict wp-content/uploads permissions
- Use security plugins (Wordfence, Sucuri, etc.)

### Regular Maintenance

- Update plugin when new versions released
- Review API usage in Google Cloud Console
- Monitor for suspicious activity
- Backup database before updates

## Security Checklist

- [x] Input validation on all user inputs
- [x] Output escaping on all dynamic content
- [x] Capability checks on admin functions
- [x] Nonce verification on forms
- [x] Sanitization before database operations
- [x] REST API authentication
- [x] No SQL injection vulnerabilities
- [x] No XSS vulnerabilities
- [x] No CSRF vulnerabilities
- [x] Secure data storage
- [x] Error handling without information disclosure
- [x] Uninstall cleanup

## Secure Development

### Code Review

All code changes reviewed for:
- Input validation
- Output escaping
- SQL injection prevention
- XSS prevention
- CSRF protection
- Authentication/authorization

### Testing

- Manual security testing
- WordPress VIP code standards
- PHP_CodeSniffer with WordPress rules
- PHPStan static analysis (planned)

## References

- [WordPress Plugin Security Best Practices](https://developer.wordpress.org/plugins/security/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)

## Contact

For security concerns: [GitHub Issues](https://github.com/wikiwyrhead/wiki-seo-page-monitor/issues) (non-sensitive)

For sensitive security issues: Create a private security advisory on GitHub

---

Last Updated: November 20, 2025
