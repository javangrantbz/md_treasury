<?php
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/helpers.php';
Auth::requireAuth();
require_once __DIR__ . '/../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../includes/layout-tabler-sidebar.php';
?>

<div class="page-header d-print-none">
  <div class="container-xl">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">NSB &mdash; Customers</div>
        <h2 class="page-title">Add Customer</h2>
      </div>
      <div class="col-auto ms-auto">
        <a href="<?= url('views/nsb/customers/list.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Customer List</a>
      </div>
    </div>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    <div id="form-message" class="alert mb-3" style="display:none;"></div>

    <div class="row g-4 align-items-start">

      <!-- ── FORM — 3/4 ─────────────────────────────────────────────── -->
      <div class="col-lg-9">
        <form id="customer-form" autocomplete="off">

          <!-- Auth Method -->
          <div class="card mb-3">
            <div class="card-header">
              <div class="card-title">Authentication Method</div>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-selectgroup-item flex-fill">
                    <input type="radio" name="auth_source" value="local" class="form-selectgroup-input" checked>
                    <div class="form-selectgroup-label d-flex align-items-center p-3">
                      <div class="me-3">
                        <span class="form-selectgroup-check"></span>
                      </div>
                      <div>
                        <div class="fw-semibold" style="font-size:.88rem;">Local Account</div>
                        <div class="text-muted" style="font-size:.78rem;">Username &amp; password login. Managed directly in this system.</div>
                      </div>
                    </div>
                  </label>
                </div>
                <div class="col-md-6">
                  <label class="form-selectgroup-item flex-fill">
                    <input type="radio" name="auth_source" value="microsoft" class="form-selectgroup-input">
                    <div class="form-selectgroup-label d-flex align-items-center p-3">
                      <div class="me-3">
                        <span class="form-selectgroup-check"></span>
                      </div>
                      <div>
                        <div class="fw-semibold d-flex align-items-center gap-2" style="font-size:.88rem;">
                          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 21 21"><rect x="1" y="1" width="9" height="9" fill="#F25022"/><rect x="11" y="1" width="9" height="9" fill="#7FBA00"/><rect x="1" y="11" width="9" height="9" fill="#00A4EF"/><rect x="11" y="11" width="9" height="9" fill="#FFB900"/></svg>
                          Microsoft / Entra ID
                        </div>
                        <div class="text-muted" style="font-size:.78rem;">Single sign-on via Microsoft 365. Profile syncs on first login.</div>
                      </div>
                    </div>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Identity -->
          <div class="card mb-3">
            <div class="card-header">
              <div class="card-title">Identity</div>
            </div>
            <div class="card-body">

              <!-- Local fields -->
              <div id="local-fields">
                <div class="row g-3 mb-3">
                  <div class="col-md-6">
                    <label class="form-label required">First Name</label>
                    <input type="text" class="form-control" name="first_name" id="first_name" placeholder="First name">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label required">Last Name</label>
                    <input type="text" class="form-control" name="last_name" id="last_name" placeholder="Last name">
                  </div>
                </div>
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label required">Username</label>
                    <div class="input-group">
                      <span class="input-group-text">@</span>
                      <input type="text" class="form-control" name="username" id="username" placeholder="username" autocomplete="off">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label required">Email Address</label>
                    <input type="email" class="form-control" name="email" id="email-local" placeholder="email@example.com">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-control" name="phone" id="phone" placeholder="+501 xxx-xxxx">
                  </div>
                </div>
              </div>

              <!-- Microsoft fields -->
              <div id="ms-fields" style="display:none;">
                <div class="alert alert-info d-flex gap-2 py-2 mb-3">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon text-info flex-shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 9h.01M11 12h1v4h1"/></svg>
                  <span style="font-size:.83rem;">Name, username, and profile photo will be populated automatically when the user first signs in via Microsoft.</span>
                </div>
                <div class="col-md-6">
                  <label class="form-label required">Microsoft / Entra Email</label>
                  <input type="email" class="form-control" name="email_ms" id="email-ms" placeholder="user@organization.gov.bz">
                </div>
              </div>

            </div>
          </div>

          <!-- Credentials (local only) -->
          <div class="card mb-3" id="credentials-card">
            <div class="card-header">
              <div class="card-title">Credentials</div>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label required">Password</label>
                  <input type="password" class="form-control" name="password" id="password" autocomplete="new-password" placeholder="Minimum 8 characters">
                </div>
                <div class="col-md-6">
                  <label class="form-label required">Confirm Password</label>
                  <input type="password" class="form-control" name="confirm_password" id="confirm-password" autocomplete="new-password" placeholder="Repeat password">
                </div>
              </div>
            </div>
          </div>

          <!-- Assignment -->
          <div class="card mb-3">
            <div class="card-header">
              <div class="card-title">Assignment</div>
              <div class="card-options text-muted" style="font-size:.78rem;">All optional</div>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Department</label>
                  <select class="form-select" name="department_id" id="department_id">
                    <option value="">— None —</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Branch</label>
                  <select class="form-select" name="branch_id" id="branch_id">
                    <option value="">— None —</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Sub-Treasury</label>
                  <select class="form-select" name="sub_treasury_id" id="sub_treasury_id">
                    <option value="">— None —</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- Access -->
          <div class="card">
            <div class="card-header">
              <div class="card-title">Access &amp; Status</div>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-3">
                  <label class="form-label">User Type</label>
                  <select class="form-select" name="user_type">
                    <option value="internal">Internal</option>
                    <option value="external">External</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Role</label>
                  <select class="form-select" name="role_id" id="role_id">
                    <option value="">— No Role —</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Status</label>
                  <select class="form-select" name="status">
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="inactive">Inactive</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">MFA</label>
                  <select class="form-select" name="mfa_enabled">
                    <option value="0">Disabled</option>
                    <option value="1">Enabled</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="card-footer d-flex gap-2">
              <button type="submit" class="btn btn-primary" id="submit-btn">Create Customer</button>
              <button type="reset" class="btn btn-link">Reset</button>
            </div>
          </div>

        </form>
      </div>

      <!-- ── SIDEBAR — 1/4 ──────────────────────────────────────────── -->
      <div class="col-lg-3">

        <!-- Guidelines -->
        <div class="card mb-3">
          <div class="card-header" style="background:#f8fafc;">
            <div class="card-title" style="font-size:.82rem;">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1 text-primary" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 9h.01M11 12h1v4h1"/></svg>
              Guidelines
            </div>
          </div>
          <div class="card-body p-0">

            <div class="px-3 py-2 border-bottom">
              <div class="text-uppercase fw-bold text-muted mb-2" style="font-size:.63rem;letter-spacing:.08em;">Required Fields</div>
              <?php foreach ([
                'First &amp; last name (local accounts)',
                'Unique username (@ prefix auto-added)',
                'Valid email address',
                'Password (min. 8 characters)',
              ] as $item): ?>
              <div class="d-flex align-items-start gap-2 mb-1" style="font-size:.8rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--tblr-success)" stroke-width="3" style="flex-shrink:0;margin-top:3px;"><polyline points="20 6 9 17 4 12"/></svg>
                <span><?= $item ?></span>
              </div>
              <?php endforeach; ?>
            </div>

            <div class="px-3 py-2 border-bottom">
              <div class="text-uppercase fw-bold text-muted mb-2" style="font-size:.63rem;letter-spacing:.08em;">Auth Methods</div>
              <div class="mb-2">
                <div class="fw-semibold mb-1" style="font-size:.78rem;">Local</div>
                <div style="font-size:.76rem;color:#6b7280;">Password stored securely in this system. User logs in with username and password.</div>
              </div>
              <div>
                <div class="fw-semibold mb-1 d-flex align-items-center gap-1" style="font-size:.78rem;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 21 21"><rect x="1" y="1" width="9" height="9" fill="#F25022"/><rect x="11" y="1" width="9" height="9" fill="#7FBA00"/><rect x="1" y="11" width="9" height="9" fill="#00A4EF"/><rect x="11" y="11" width="9" height="9" fill="#FFB900"/></svg>
                  Microsoft / Entra
                </div>
                <div style="font-size:.76rem;color:#6b7280;">Only email is needed. Name, photo, and profile sync automatically on first login.</div>
              </div>
            </div>

            <div class="px-3 py-2">
              <div class="text-uppercase fw-bold text-muted mb-2" style="font-size:.63rem;letter-spacing:.08em;">User Types</div>
              <div class="mb-2">
                <div class="fw-semibold mb-1" style="font-size:.78rem;">Internal</div>
                <div style="font-size:.76rem;color:#6b7280;">Treasury staff with full system access based on assigned role.</div>
              </div>
              <div>
                <div class="fw-semibold mb-1" style="font-size:.78rem;">External</div>
                <div style="font-size:.76rem;color:#6b7280;">Outside users such as vendors or contractors. Limited access.</div>
              </div>
            </div>

          </div>
        </div>

        <!-- Recently Added -->
        <div class="card">
          <div class="card-header" style="background:#f8fafc;">
            <div class="card-title" style="font-size:.82rem;">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1 text-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              Recently Added
            </div>
          </div>
          <div id="recent-users-body" class="card-body p-0">
            <div class="text-center text-muted py-3" style="font-size:.8rem;">Loading&hellip;</div>
          </div>
          <div class="card-footer py-2 text-end" style="background:#f8fafc;">
            <a href="<?= url('views/nsb/customers/list.php') ?>" style="font-size:.78rem;color:var(--tblr-primary);font-weight:600;">View all &rarr;</a>
          </div>
        </div>

      </div><!-- /sidebar -->

    </div><!-- /row -->
  </div>
</div>

<script>
var CREATE_URL     = "<?= url('api/master-data/users/create.php') ?>";
var USERS_URL      = "<?= url('api/master-data/users/list.php') ?>";
var ROLES_URL      = "<?= url('api/master-data/roles/list.php') ?>";
var BRANCHES_URL   = "<?= url('api/master-data/branches/list.php') ?>?status=active";
var DEPTS_URL      = "<?= url('api/master-data/departments/list.php') ?>?status=active";
var ST_URL         = "<?= url('api/master-data/sub-treasuries/list.php') ?>?status=active";
var PROFILE_URL    = "<?= url('views/nsb/customers/profile.php') ?>";
var LIST_URL       = "<?= url('views/nsb/customers/list.php') ?>";

// ── Auth source toggle ────────────────────────────────────────────────────
function applyAuthToggle(source) {
    var isMs = source === 'microsoft';
    document.getElementById('local-fields').style.display      = isMs ? 'none' : '';
    document.getElementById('ms-fields').style.display         = isMs ? '' : 'none';
    document.getElementById('credentials-card').style.display  = isMs ? 'none' : '';

    // required attributes
    var localRequired = ['first_name', 'last_name', 'username', 'password', 'confirm_password'];
    localRequired.forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.required = !isMs;
    });
    document.getElementById('email-local').required = !isMs;
    document.getElementById('email-ms').required    = isMs;
}

document.querySelectorAll('input[name="auth_source"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        applyAuthToggle(this.value);
    });
});

// ── Load dropdowns ────────────────────────────────────────────────────────
function populateSelect(selectId, url, labelFn) {
    var sel = document.getElementById(selectId);
    apiGet(url).then(function(res) {
        (res.data || []).forEach(function(item) {
            var opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = labelFn(item);
            sel.appendChild(opt);
        });
    }).catch(function() {
        var opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'Error loading';
        sel.appendChild(opt);
    });
}

// ── Recently added ────────────────────────────────────────────────────────
function loadRecentUsers() {
    var container = document.getElementById('recent-users-body');
    apiGet(USERS_URL).then(function(res) {
        var rows = (res.data || []).slice().sort(function(a, b) {
            return (b.created_at || '').localeCompare(a.created_at || '');
        }).slice(0, 5);

        if (!rows.length) {
            container.innerHTML = '<div class="text-center text-muted py-3" style="font-size:.8rem;">No users yet.</div>';
            return;
        }
        var html = '';
        rows.forEach(function(u, i) {
            var name     = ((u.first_name || '') + ' ' + (u.last_name || '')).trim() || u.username || u.email || '—';
            var initials = name.replace(/[^A-Za-z ]/g, '').split(' ').map(function(w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase();
            var statusCls = u.status === 'active' ? 'bg-success-lt text-success' : 'bg-secondary-lt text-secondary';
            var border   = i < rows.length - 1 ? 'border-bottom' : '';
            html += '<div class="px-3 py-2 ' + border + ' d-flex align-items-center gap-2">'
                + '<div style="width:28px;height:28px;border-radius:50%;background:var(--tblr-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:800;flex-shrink:0;">' + escHtml(initials) + '</div>'
                + '<div style="min-width:0;flex:1;">'
                + '<div class="text-truncate fw-semibold" style="font-size:.8rem;" title="' + escAttr(name) + '">' + escHtml(name) + '</div>'
                + '<div style="font-size:.72rem;color:#9ca3af;">' + escHtml(u.email || u.username || '') + '</div>'
                + '</div>'
                + '<span class="badge ' + statusCls + '" style="font-size:.62rem;flex-shrink:0;">' + cap(u.status || '') + '</span>'
                + '</div>';
        });
        container.innerHTML = html;
    }).catch(function() {
        container.innerHTML = '<div class="text-center text-muted py-3" style="font-size:.8rem;">Unable to load.</div>';
    });
}

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function escAttr(s) {
    if (!s) return '';
    return String(s).replace(/"/g, '&quot;');
}
function cap(s) {
    if (!s) return '';
    return s.charAt(0).toUpperCase() + s.slice(1);
}

// ── Form submit ───────────────────────────────────────────────────────────
document.getElementById('customer-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('submit-btn');
    clearMessage('form-message');

    var authSource = document.querySelector('input[name="auth_source"]:checked').value;
    var isMs = authSource === 'microsoft';

    var payload = {
        auth_source:     authSource,
        user_type:       this.elements['user_type'].value,
        role_id:         this.elements['role_id'].value,
        status:          this.elements['status'].value,
        mfa_enabled:     this.elements['mfa_enabled'].value,
        department_id:   this.elements['department_id'].value,
        branch_id:       this.elements['branch_id'].value,
        sub_treasury_id: this.elements['sub_treasury_id'].value,
    };

    if (isMs) {
        payload.email = document.getElementById('email-ms').value.trim();
    } else {
        payload.first_name       = document.getElementById('first_name').value.trim();
        payload.last_name        = document.getElementById('last_name').value.trim();
        payload.username         = document.getElementById('username').value.trim();
        payload.email            = document.getElementById('email-local').value.trim();
        payload.phone            = document.getElementById('phone').value.trim();
        payload.password         = document.getElementById('password').value;
        payload.confirm_password = document.getElementById('confirm-password').value;
    }

    btn.disabled = true;
    btn.textContent = 'Creating…';

    apiPost(CREATE_URL, payload).then(function(res) {
        showMessage('form-message', res.message || 'Customer created successfully.', 'success');
        loadRecentUsers();
        setTimeout(function() {
            window.location.href = PROFILE_URL + '?id=' + res.id;
        }, 1200);
    }).catch(function(err) {
        showMessage('form-message', err.message || 'Failed to create customer.', 'danger');
        btn.disabled = false;
        btn.textContent = 'Create Customer';
    });
});

// ── Init ──────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    applyAuthToggle('local');

    populateSelect('role_id', ROLES_URL, function(r) {
        return r.name + (r.code ? ' (' + r.code + ')' : '');
    });
    populateSelect('branch_id', BRANCHES_URL, function(b) {
        return b.code + ' — ' + b.name;
    });
    populateSelect('department_id', DEPTS_URL, function(d) {
        return d.name;
    });
    populateSelect('sub_treasury_id', ST_URL, function(s) {
        return s.sub_treasury_name + (s.sub_treasury_code ? ' (' + s.sub_treasury_code + ')' : '');
    });

    loadRecentUsers();
});
</script>

<?php require_once __DIR__ . '/../../../includes/layout-tabler-footer.php'; ?>
