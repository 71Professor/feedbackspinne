/**
 * Feedbackspinne – Auth App.js
 * Vanilla JS – no frameworks required.
 */
'use strict';

// ── API client ────────────────────────────────────────────────────────────────
const API_BASE = 'api.php';

async function apiCall(action, body = null, method = 'POST') {
  const opts = {
    method,
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
  };
  if (body) opts.body = JSON.stringify(body);
  const url = `${API_BASE}?action=${action}`;
  const res  = await fetch(url, opts);
  return res.json();
}

// ── View router ───────────────────────────────────────────────────────────────
const views = {
  login:          document.getElementById('view-login'),
  register:       document.getElementById('view-register'),
  forgot:         document.getElementById('view-forgot'),
  reset:          document.getElementById('view-reset'),
  changePassword: document.getElementById('view-change-password'),
  dashboard:      document.getElementById('view-dashboard'),
};

function showView(name) {
  Object.values(views).forEach(v => v && v.classList.remove('active'));
  if (views[name]) views[name].classList.add('active');
}

// ── Alert helpers ─────────────────────────────────────────────────────────────
function showAlert(id, msg, type = 'error') {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent  = msg;
  el.className    = `alert alert-${type} show`;
}
function clearAlert(id) {
  const el = document.getElementById(id);
  if (el) { el.textContent = ''; el.classList.remove('show'); }
}

// ── Button loading state ──────────────────────────────────────────────────────
function setLoading(btn, loading) {
  if (loading) {
    btn.dataset.origText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner"></span>';
    btn.disabled  = true;
  } else {
    btn.innerHTML = btn.dataset.origText || btn.innerHTML;
    btn.disabled  = false;
  }
}

// ── Password strength meter ───────────────────────────────────────────────────
function passwordStrength(pw) {
  let score = 0;
  if (pw.length >= 8)          score++;
  if (/[A-Z]/.test(pw))        score++;
  if (/[a-z]/.test(pw))        score++;
  if (/[0-9]/.test(pw))        score++;
  if (/[\W_]/.test(pw))        score++;
  return score; // 0-5
}

function updateMeter(pw, fillId) {
  const fill = document.getElementById(fillId);
  if (!fill) return;
  const s     = passwordStrength(pw);
  const pct   = s * 20;
  const color = s < 2 ? '#dc2626' : s < 4 ? '#f59e0b' : '#7ab800';
  fill.style.width      = pct + '%';
  fill.style.background = color;
}

// ── Session/user state ────────────────────────────────────────────────────────
let currentUser = null;

async function checkSession() {
  try {
    const params = new URLSearchParams(window.location.search);
    const data = await apiCall('me', null, 'GET');
    if (data.success && params.get('view') !== 'register') {
      // Bereits eingeloggt: direkt zum Admin-Bereich weiterleiten
      window.location.href = '../admin/dashboard.php';
    } else {
      // E-Mail-Verifikationslink verarbeiten
      const verifyToken = params.get('verify_email');
      if (verifyToken) {
        showView('login');
        const vRes = await apiCall('verifyEmail', { token: verifyToken });
        showAlert('login-alert', vRes.message, vRes.success ? 'success' : 'error');
        // Token aus URL entfernen
        window.history.replaceState({}, '', window.location.pathname);
        return;
      }

      // Passwort-Reset-Link verarbeiten
      if (params.get('page') === 'reset' && params.get('token')) {
        document.getElementById('reset-token-input').value = params.get('token');
        showView('reset');
      } else if (params.get('view') === 'register') {
        window.history.replaceState({}, '', window.location.pathname);
        showView('register');
      } else {
        showView('login');
      }
    }
  } catch {
    showView('login');
  }
}

function renderDashboard() {
  document.getElementById('dash-username').textContent = currentUser.username;
  document.getElementById('dash-email').textContent    = currentUser.email;
}

// ── REGISTER ──────────────────────────────────────────────────────────────────
document.getElementById('form-register').addEventListener('submit', async e => {
  e.preventDefault();
  clearAlert('register-alert');
  const btn = e.target.querySelector('button[type=submit]');
  setLoading(btn, true);

  const res = await apiCall('register', {
    username: e.target.username.value.trim(),
    email:    e.target.email.value.trim(),
    password: e.target.password.value,
  });

  setLoading(btn, false);
  if (res.success) {
    showAlert('register-alert', res.message, 'success');
    setTimeout(() => showView('login'), 5000);
  } else {
    showAlert('register-alert', res.message, 'error');
  }
});

// Password meter on register form
document.getElementById('reg-password').addEventListener('input', function () {
  updateMeter(this.value, 'reg-pw-fill');
});

// ── LOGIN ─────────────────────────────────────────────────────────────────────
document.getElementById('form-login').addEventListener('submit', async e => {
  e.preventDefault();
  clearAlert('login-alert');
  const btn = e.target.querySelector('button[type=submit]');
  setLoading(btn, true);

  const res = await apiCall('login', {
    identifier: e.target.identifier.value.trim(),
    password:   e.target.password.value,
  });

  setLoading(btn, false);
  if (res.success) {
    // Nach erfolgreichem Login zum Admin-Bereich weiterleiten
    window.location.href = '../admin/dashboard.php';
  } else {
    showAlert('login-alert', res.message, 'error');
    // Bei unverifizierter E-Mail Resend-Sektion einblenden
    if (res.email_unverified) {
      document.getElementById('resend-email-input').value = res.email || '';
      document.getElementById('resend-section').style.display = 'block';
    } else {
      document.getElementById('resend-section').style.display = 'none';
    }
  }
});

// ── RESEND VERIFICATION EMAIL ─────────────────────────────────────────────────
document.getElementById('form-resend').addEventListener('submit', async e => {
  e.preventDefault();
  clearAlert('resend-alert');
  const btn   = e.target.querySelector('button[type=submit]');
  const email = document.getElementById('resend-email-input').value;
  setLoading(btn, true);

  const res = await apiCall('resendVerificationEmail', { email });

  setLoading(btn, false);
  showAlert('resend-alert', res.message, 'success');
});

// ── LOGOUT ────────────────────────────────────────────────────────────────────
document.getElementById('btn-logout').addEventListener('click', async () => {
  await apiCall('logout');
  currentUser = null;
  showView('login');
});

// ── FORGOT PASSWORD ───────────────────────────────────────────────────────────
document.getElementById('form-forgot').addEventListener('submit', async e => {
  e.preventDefault();
  clearAlert('forgot-alert');
  const btn = e.target.querySelector('button[type=submit]');
  setLoading(btn, true);

  const res = await apiCall('forgot_password', {
    email: e.target.email.value.trim(),
  });

  setLoading(btn, false);
  // Always show success (anti-enumeration)
  showAlert('forgot-alert', res.message, 'success');
  e.target.reset();
});

// ── RESET PASSWORD ────────────────────────────────────────────────────────────
document.getElementById('form-reset').addEventListener('submit', async e => {
  e.preventDefault();
  clearAlert('reset-alert');
  const btn = e.target.querySelector('button[type=submit]');
  setLoading(btn, true);

  const res = await apiCall('reset_password', {
    token:    document.getElementById('reset-token-input').value,
    password: e.target.password.value,
  });

  setLoading(btn, false);
  if (res.success) {
    showAlert('reset-alert', res.message + ' Du wirst weitergeleitet…', 'success');
    setTimeout(() => {
      // Clean URL
      window.history.replaceState({}, '', window.location.pathname);
      showView('login');
    }, 2500);
  } else {
    showAlert('reset-alert', res.message, 'error');
  }
});

document.getElementById('reset-password').addEventListener('input', function () {
  updateMeter(this.value, 'reset-pw-fill');
});

// ── CHANGE PASSWORD ───────────────────────────────────────────────────────────
document.getElementById('form-change-password').addEventListener('submit', async e => {
  e.preventDefault();
  clearAlert('change-alert');
  const btn = e.target.querySelector('button[type=submit]');
  setLoading(btn, true);

  const res = await apiCall('change_password', {
    old_password: e.target.old_password.value,
    new_password: e.target.new_password.value,
  });

  setLoading(btn, false);
  if (res.success) {
    showAlert('change-alert', res.message, 'success');
    e.target.reset();
    updateMeter('', 'change-pw-fill');
    setTimeout(() => showView('dashboard'), 2000);
  } else {
    showAlert('change-alert', res.message, 'error');
  }
});

document.getElementById('change-new-password').addEventListener('input', function () {
  updateMeter(this.value, 'change-pw-fill');
});

// ── Navigation links (inline buttons acting as links) ─────────────────────────
document.querySelectorAll('[data-goto]').forEach(el => {
  el.addEventListener('click', () => showView(el.dataset.goto));
});

// ── Init ──────────────────────────────────────────────────────────────────────
checkSession();
