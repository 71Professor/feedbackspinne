# Security Headers and SRI Implementation

This document describes the application-level security headers and Subresource Integrity (SRI) implementation added to protect against various web security threats.

## Overview

The application now implements defense-in-depth security measures through:

1. **HTTP Security Headers** - Configured via PHP
2. **Content Security Policy (CSP)** - Restricts resource loading
3. **Subresource Integrity (SRI)** - Validates CDN script integrity
4. **CDN Version Pinning** - Locked library versions

## Security Headers Implemented

### 1. Content-Security-Policy

**Purpose**: Controls which resources the browser is allowed to load, preventing XSS and data injection attacks.

**Configuration**:
```
Content-Security-Policy:
  default-src 'self';
  script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com;
  style-src 'self' 'unsafe-inline';
  img-src 'self' data:;
  font-src 'self';
  connect-src 'self' https://cdnjs.cloudflare.com;
  frame-ancestors 'none';
  base-uri 'self';
  form-action 'self'
```

**Key Directives**:
- `script-src 'unsafe-inline'`: Required for inline chart initialization scripts (server-generated, input-sanitized)
- `script-src https://cdnjs.cloudflare.com`: Allows external CDN scripts with SRI validation
- `style-src 'unsafe-inline'`: Required for dynamic color theming (acceptable risk for this use case)
- `img-src data:`: Allows data URIs for chart export functionality
- `connect-src https://cdnjs.cloudflare.com`: Allows loading source maps from CDN (development aid)
- `frame-ancestors 'none'`: Prevents the application from being embedded in iframes (clickjacking protection)

### 2. X-Content-Type-Options

**Purpose**: Prevents MIME-sniffing attacks by forcing browsers to respect declared content types.

**Configuration**:
```
X-Content-Type-Options: nosniff
```

### 3. X-Frame-Options

**Purpose**: Defense-in-depth protection against clickjacking (redundant with CSP frame-ancestors).

**Configuration**:
```
X-Frame-Options: DENY
```

### 4. Referrer-Policy

**Purpose**: Controls how much referrer information is shared with other sites.

**Configuration**:
```
Referrer-Policy: strict-origin-when-cross-origin
```

**Behavior**:
- Same-origin requests: Full URL is sent
- Cross-origin HTTPS→HTTPS: Only origin is sent
- HTTPS→HTTP: No referrer sent (prevents information leakage)

### 5. Permissions-Policy

**Purpose**: Restricts browser features to minimize attack surface.

**Configuration**:
```
Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()
```

**Disabled Features**:
- Geolocation API
- Microphone access
- Camera access
- Payment Request API

The application doesn't need these features, so they're completely disabled.

## Subresource Integrity (SRI)

### What is SRI?

SRI allows browsers to verify that files from CDNs haven't been tampered with. The browser computes a cryptographic hash of the downloaded file and compares it to the expected hash.

### CDN Scripts with SRI

All external scripts now include integrity attributes:

#### 1. Chart.js 4.4.1
```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"
        integrity="sha512-CQBWl4fJHWbryGE+Pc7UAxWMUMNMWzWxF4SQo9CgkJIN1kx6djDQZjh3Y8SZ1d+6I+1zze6Z7kHXO7q3UyZAWw=="
        crossorigin="anonymous"
        referrerpolicy="no-referrer"></script>
```

**Used in**: `session.php`, `admin/results.php`

#### 2. html2canvas 1.4.1
```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"
        integrity="sha512-BNaRQnYJYiPSqHHDb58B0yaPfCu+Wgds8Gp/gU33kqBtgNS4tSPHuGibyoeqMV/TJlSKda6FXzoEyYGjTe+vXA=="
        crossorigin="anonymous"
        referrerpolicy="no-referrer"></script>
```

**Used in**: `admin/results.php`

#### 3. jsPDF 2.5.1
```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"
        integrity="sha512-qZvrmS2ekKPF2mSznTQsxqPgnpkI4DNTlrdUmTzrDgektczlKNRRhy5X5AAOnx5S09ydFYWWNSfcEqDTTHgtNA=="
        crossorigin="anonymous"
        referrerpolicy="no-referrer"></script>
```

**Used in**: `admin/results.php`

### SRI Attributes Explained

- `integrity="sha512-..."`: The expected cryptographic hash of the file
- `crossorigin="anonymous"`: Required for SRI to work with CORS
- `referrerpolicy="no-referrer"`: Additional privacy protection

## Updating CDN Script Versions

When you need to update a CDN script to a new version:

### Option 1: Use cdnjs.com (Recommended)

1. Visit https://cdnjs.com/libraries/[library-name]
2. Select the version you need
3. Copy the complete `<script>` tag with integrity hash
4. Replace the old tag in the PHP file
5. Update the CSP if using a different CDN domain

### Option 2: Generate SRI Hash Manually

Use the provided `tools/generate-sri-hashes.js` script:

```bash
node tools/generate-sri-hashes.js
```

This will:
1. Fetch all CDN scripts
2. Calculate SHA-384 and SHA-512 hashes
3. Output ready-to-use HTML snippets

**Requirements**: Internet connection, Node.js

### Option 3: Online Tools

Use https://www.srihash.org/:
1. Paste the CDN URL
2. Copy the generated integrity hash
3. Add to your `<script>` tag

## Implementation Details

### Location in Code

**Security headers function**: `config.php` → `setSecurityHeaders()`

**Called from**:
- `session.php` (line 5)
- `admin/results.php` (line 5)

### HTTPS Detection

The security headers function automatically detects if HTTPS is active and:
- Sets `upgrade-insecure-requests` in CSP (HTTPS only)
- Adjusts `Referrer-Policy` behavior

Detection includes support for:
- Direct HTTPS connections
- Reverse proxies (X-Forwarded-Proto header)
- Load balancers

### Testing CSP

To verify CSP is working:

1. Open browser DevTools (F12)
2. Go to Console tab
3. Try to execute: `eval('alert(1)')`
4. You should see a CSP violation error

To test script blocking:

1. Modify the CSP to remove cdnjs.cloudflare.com
2. Reload the page
3. Charts should fail to load
4. Console will show CSP violation

## Security Trade-offs

### Inline Scripts (`'unsafe-inline'` in script-src)

**Why**: The application uses inline JavaScript for chart initialization and configuration.

**Risk**: Allows any inline scripts, including malicious ones from XSS attacks.

**Mitigation**:
- All user input is sanitized with `htmlspecialchars()`
- Chart data is JSON-encoded and properly escaped
- Color values are validated with `sanitizeChartColor()`
- Scripts are server-generated with controlled, sanitized data only
- No direct user input is ever passed to inline scripts

**Future improvement**: Extract inline scripts to separate .js files or use CSP nonces for inline scripts.

### Inline Styles (`'unsafe-inline'` in style-src)

**Why**: The application uses dynamic color theming via PHP-generated inline styles.

**Risk**: Allows any inline styles, including malicious ones from XSS.

**Mitigation**:
- All user input is sanitized with `htmlspecialchars()`
- Color values are validated with `sanitizeChartColor()`
- This is acceptable for this use case

**Future improvement**: Use CSS custom properties set via inline style on `:root` only.

### Data URIs for Images

**Why**: Chart export functionality converts canvas to data URIs.

**Risk**: Data URIs can contain embedded scripts in SVG format.

**Mitigation**: Only used for PNG export (not SVG).

## Browser Compatibility

All implemented security features are supported in:

- Chrome 64+
- Firefox 60+
- Safari 11.1+
- Edge 79+

Older browsers will ignore unknown headers gracefully.

## Monitoring and Maintenance

### Regular Tasks

1. **Quarterly**: Check for CDN library updates and security advisories
2. **When updating**: Regenerate SRI hashes using the provided script
3. **After deployment**: Monitor browser console for CSP violations

### CSP Violation Reporting

Consider adding CSP reporting in production:

```php
$csp[] = "report-uri /csp-report.php";
```

This allows you to collect CSP violations and identify:
- Potential attacks
- Legitimate resources being blocked
- Configuration issues

## References

- [MDN: Content Security Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP)
- [MDN: Subresource Integrity](https://developer.mozilla.org/en-US/docs/Web/Security/Defenses/Subresource_Integrity)
- [OWASP: Secure Headers Project](https://owasp.org/www-project-secure-headers/)
- [CSP Evaluator](https://csp-evaluator.withgoogle.com/)

## Issue Resolution

This implementation resolves **Issue #7** from the security audit:

> ### 7) Missing application-level security headers and CSP policy (Medium)
>
> The app includes external scripts from CDNs and does not set a strict Content Security Policy or related headers in PHP code.

**Status**: ✅ **RESOLVED**

**Changes made**:
1. ✅ Added comprehensive security headers via `setSecurityHeaders()`
2. ✅ Implemented strict Content Security Policy
3. ✅ Added SRI attributes to all CDN scripts
4. ✅ Pinned CDN script versions (already done, verified)
5. ✅ Created tooling for SRI hash generation
6. ✅ Documented implementation and maintenance procedures
