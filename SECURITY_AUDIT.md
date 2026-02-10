# Security Audit: Feedbackspinne

**Date:** 2026-02-10
**Scope:** Source code review of PHP application under `/workspace/feedbackspinne` (config, public entry points, admin area).
**Method:** Manual static review (no dynamic penetration test).

---

## Executive Summary

The project already uses several good baseline controls:

- Prepared statements for database access.
- Password hashes with `password_verify`.
- CSRF checks in admin create/edit/delete and login.
- Ownership checks on admin-managed session resources.

However, there are still relevant weaknesses around session hardening, rate limiting robustness, header policy, and input validation. None of the findings below are immediate SQL injection issues, but several are practical hardening gaps that should be addressed before public/production deployment.

**Overall rating:** **Medium risk** (can be improved to low with focused hardening).

---

## Findings & Recommendations

## 1) Session cookie hardening is not enforced (**High**)

### Why it matters
`session_start()` is called globally, but secure cookie flags are not explicitly set before startup. That can leave behavior dependent on php.ini defaults and deployment config.

### Evidence
- `config.php` starts the session directly with `session_start()` without setting secure cookie params in code.

### Risk
- Session cookie theft/replay risk increases if `HttpOnly`, `Secure`, or strict `SameSite` are missing.

### Recommendation
Set session parameters before `session_start()` in `config.php`:

- `session.cookie_httponly = 1`
- `session.cookie_secure = 1` (in HTTPS)
- `session.cookie_samesite = Lax` (or `Strict` if UX allows)
- `session.use_strict_mode = 1`

Also rotate session IDs after privilege changes (already done on login—good).

---

## 2) Session timeout constant exists but is not enforced (**Medium**)

### Why it matters
`SESSION_TIMEOUT` and `last_activity` are defined/set, but there is no enforcement in `requireAdmin()`.

### Evidence
- `SESSION_TIMEOUT` is defined in `config.php`.
- `$_SESSION['last_activity']` is set in `admin/index.php` on successful login.
- `requireAdmin()` only checks boolean login state and does not expire inactive sessions.

### Risk
- Stale admin sessions remain valid longer than intended.

### Recommendation
Add inactivity enforcement in `requireAdmin()`:

1. Check `last_activity` against `SESSION_TIMEOUT`.
2. Destroy session + redirect to login if expired.
3. Refresh `last_activity` on each authenticated admin request.

---

## 3) Rate limiting is session-based and can be bypassed (**Medium**)

### Why it matters
Rate limit counters are stored in PHP session state. An attacker can often reset this by restarting session context (new cookie / distributed attempts), which weakens brute-force protection.

### Evidence
- `checkRateLimit`, `incrementRateLimit`, `resetRateLimit` use `$_SESSION[...]` buckets.

### Risk
- Reduced resistance against automated guessing for admin login and 4-digit session codes.

### Recommendation
Move rate limit state to a server-side shared store (DB/Redis/files with lock), keyed by:

- Client IP + route + username (for admin login), and
- Client IP + session code endpoint (for participant entry).

Keep existing UX messaging, but make backend state independent from user session cookies.

---

## 4) Missing CSRF protection on participant submission form (**Medium**)

### Why it matters
The participant form in `session.php` accepts POST submissions without CSRF token validation.

### Evidence
- `session.php` processes `$_POST['values']` without CSRF check.

### Risk
- Cross-site submissions are possible in participant browser sessions.
- Could pollute workshop data with forged entries.

### Recommendation
Add CSRF hidden token + `validateCSRFToken` check for participant submissions as well.

---

## 5) Unvalidated `chart_color` is rendered into CSS context (**Medium**)

### Why it matters
`chart_color` from DB/admin form is rendered directly into `<style>` values.

### Evidence
- `session.php` and `admin/results.php` place `$chartColor` directly into CSS variables and JS literals.
- `admin/create.php` and `admin/edit.php` accept `chart_color` without strict format validation.

### Risk
- CSS/HTML context breakouts are possible if malicious values reach DB.
- At minimum causes rendering corruption; in worst-case could become script injection depending on payload/context.

### Recommendation
Whitelist strict hex color format before storing and before rendering:

- Accept only `^#[0-9A-Fa-f]{6}$`.
- Fallback to safe default if invalid.

---

## 6) Error details may leak internal information in admin UI (**Low/Medium**)

### Why it matters
`admin/create.php` and `admin/edit.php` include raw exception messages in user-visible errors.

### Evidence
- Catch blocks concatenate `$e->getMessage()` into UI text.

### Risk
- SQL schema or internal path information can leak to authenticated users.

### Recommendation
Show generic UI errors and log detailed exceptions server-side only.

---

## 7) Missing application-level security headers and CSP policy (**Medium**)

### Why it matters
The app includes external scripts from CDNs and does not set a strict Content Security Policy or related headers in PHP code.

### Evidence
- `session.php` and `admin/results.php` load JS from jsdelivr CDN.
- No central response header hardening in `config.php`.

### Risk
- Increased impact from XSS or third-party script compromise.

### Recommendation
Add defense-in-depth headers (via PHP or `.htaccess`):

- `Content-Security-Policy` (explicitly whitelist required CDNs)
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY` (or CSP frame-ancestors)
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy` minimal profile

If CDN usage remains, add SRI attributes and pin versions.

---

## 8) .env / sensitive file protection should be verified in webserver config (**Medium**)

### Why it matters
The project expects secrets in `.env`. If webserver file protections are missing or misconfigured, secrets may be retrievable.

### Evidence
- `.env` is expected/required by `config.php`.
- `.env` is ignored in `.gitignore` (good), but runtime web access controls are deployment-dependent.

### Recommendation
In `.htaccess` (Apache) ensure explicit deny rules for:

- `.env`
- `.git`
- `*.sql`, backup files, logs
- hidden dotfiles in general

Also disable directory listing and enforce HTTPS redirection.

---

## Priority Action Plan

### Immediate (this sprint)
1. Enforce session cookie flags + strict mode.
2. Implement admin inactivity timeout in `requireAdmin()`.
3. Validate `chart_color` via strict regex (create/edit/read fallback).
4. Remove raw exception messages from UI.

### Next (short term)
5. Move rate limiting to server-side shared storage.
6. Add participant CSRF token validation in `session.php`.
7. Add security headers/CSP and SRI for external assets.

### Ongoing
8. Add periodic dependency/version review for CDN libraries.
9. Add automated security checks (linting and basic SAST rules) in CI.

---

## Notes

- This audit is based on source review only. For higher assurance, add a dynamic test pass (authenticated/unauthenticated) and deployment config verification on the target server.
- You mentioned `.env` and `.htaccess` already exist. They were not present in the checked-in repository snapshot, so deployment-level verification should be performed directly on the server.
