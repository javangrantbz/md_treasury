<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.users.manage');
require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';
?>

<style>
#table-body td { padding-top: .3rem; padding-bottom: .3rem; font-size: .875rem; }
</style>

  <div class="page-body">
    <div class="container-xl">

      <!-- Page identity card -->
      <div class="card mb-3" style="border-left: 4px solid var(--tblr-primary);">
        <div class="card-body py-3">
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="me-auto">
              <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.68rem;letter-spacing:.1em;">Cashiering &middot; Master Data</div>
              <div class="fw-bold" style="font-size:1.05rem;line-height:1.2;">Users</div>
            </div>
            <input type="text" id="search-input" class="form-control form-control-sm" style="max-width:200px;" placeholder="Search...">
            <div id="stats-area" class="d-flex gap-2">
              <span class="badge bg-success-lt">Active: 0</span>
              <span class="badge bg-secondary-lt">Inactive: 0</span>
            </div>
            <a href="<?= url('views/cashiering/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Cashiering</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-add">Add User</button>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-body p-0">
          <div id="table-message" class="alert m-3" style="display:none"></div>
          <table class="table table-vcenter table-hover card-table">
            <thead>
              <tr>
                <th data-col="first_name">Name</th>
                <th data-col="username">Username</th>
                <th data-col="email">Email</th>
                <th data-col="role_name">Role</th>
                <th data-col="user_type">Type</th>
                <th data-col="status">Status</th>
                <th class="w-1"></th>
              </tr>
            </thead>
            <tbody id="table-body">
              <tr><td colspan="7" class="text-center py-4 text-muted">Loading...</td></tr>
            </tbody>
          </table>
          <div id="table-pagination"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ADD modal -->
<div class="modal modal-blur fade" id="modal-add" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add User</h5>
        <label class="form-check d-flex align-items-center gap-1 me-2 mb-0">
          <input class="form-check-input m-0" type="checkbox" id="add-active-check" checked>
          <span class="form-check-label" style="font-size:.8125rem;">Active</span>
        </label>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="add-message" class="alert" style="display:none"></div>

        <!-- Auth -->
        <div class="d-flex align-items-center gap-3 mb-2">
          <div class="text-uppercase fw-semibold text-muted" style="font-size:.63rem;letter-spacing:.08em;white-space:nowrap;">Auth Type</div>
          <select class="form-select form-select-sm" id="add-auth_source" style="max-width:160px;">
            <option value="local">Local</option>
            <option value="microsoft">Microsoft SSO</option>
          </select>
          <div id="add-ms-notice" style="display:none;font-size:.78rem;" class="text-info d-flex align-items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 21 21" style="flex-shrink:0"><rect x="1" y="1" width="9" height="9" fill="#F25022"/><rect x="11" y="1" width="9" height="9" fill="#7FBA00"/><rect x="1" y="11" width="9" height="9" fill="#00A4EF"/><rect x="11" y="11" width="9" height="9" fill="#FFB900"/></svg>
            Name &amp; username sync on first login
          </div>
        </div>

        <hr style="margin:.5rem 0 .65rem;">

        <!-- Identity (local only) -->
        <div id="add-local-fields">
          <div class="text-uppercase fw-semibold text-muted mb-2" style="font-size:.63rem;letter-spacing:.08em;">Identity</div>
          <div class="row g-2">
            <div class="col-3">
              <label class="form-label">First Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="add-first_name" placeholder="First name">
            </div>
            <div class="col-3">
              <label class="form-label">Last Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="add-last_name" placeholder="Last name">
            </div>
            <div class="col-3">
              <label class="form-label">Username <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="add-username" placeholder="Username">
            </div>
            <div class="col-3">
              <label class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="add-email" placeholder="Email">
            </div>
          </div>
          <hr style="margin:.65rem 0;">
        </div>

        <!-- Email (microsoft only) -->
        <div id="add-ms-email-field" style="display:none;">
          <div class="text-uppercase fw-semibold text-muted mb-2" style="font-size:.63rem;letter-spacing:.08em;">Identity</div>
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="add-email-ms" placeholder="Entra email address">
            </div>
          </div>
          <hr style="margin:.65rem 0;">
        </div>

        <!-- Credentials (local only) -->
        <div id="add-password-fields">
          <div class="text-uppercase fw-semibold text-muted mb-2" style="font-size:.63rem;letter-spacing:.08em;">Credentials</div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Password <span class="text-danger">*</span></label>
              <input type="password" class="form-control" id="add-password" placeholder="Password">
            </div>
            <div class="col-6">
              <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
              <input type="password" class="form-control" id="add-confirm_password" placeholder="Confirm password">
            </div>
          </div>
          <hr style="margin:.65rem 0;">
        </div>

        <!-- Access -->
        <div class="text-uppercase fw-semibold text-muted mb-2" style="font-size:.63rem;letter-spacing:.08em;">Access</div>
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">Role</label>
            <select class="form-select" id="add-role_id">
              <option value="">— No Role —</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">User Type</label>
            <select class="form-select" id="add-user_type">
              <option value="internal">Internal</option>
              <option value="external">External</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-link link-secondary me-auto" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="add-save-btn">Save</button>
      </div>
    </div>
  </div>
</div>

<script>
const LIST_URL     = "<?= url('api/master-data/users/list.php') ?>";
const CREATE_URL   = "<?= url('api/master-data/users/create.php') ?>";
const ROLES_URL    = "<?= url('api/master-data/roles/list.php') ?>";
const DETAILS_PAGE = "<?= url('views/cashiering/master-data/users/details.php') ?>";

let allRows = [];

function showMsg(id, msg, type) {
  type = type || 'danger';
  var el = document.getElementById(id);
  el.className = 'alert alert-' + type;
  el.textContent = msg;
  el.style.display = 'block';
}
function clearMsg(id) {
  var el = document.getElementById(id);
  el.textContent = '';
  el.style.display = 'none';
}
function statusBadge(val) {
  var ok = (val === 'active' || val === 1 || val === '1' || val === true);
  return ok
    ? '<span class="badge bg-success-lt text-success">Active</span>'
    : '<span class="badge bg-secondary-lt text-secondary">Inactive</span>';
}

function applyAuthToggle(authSource) {
  var isMs = authSource === 'microsoft';
  document.getElementById('add-local-fields').style.display    = isMs ? 'none' : '';
  document.getElementById('add-password-fields').style.display = isMs ? 'none' : '';
  document.getElementById('add-ms-notice').style.display       = isMs ? '' : 'none';
  document.getElementById('add-ms-email-field').style.display  = isMs ? '' : 'none';
}

document.getElementById('add-auth_source').addEventListener('change', function() {
  applyAuthToggle(this.value);
});

async function loadRoleOptions() {
  try {
    var res = await apiGet(ROLES_URL + '?status=active');
    var options = '<option value="">— No Role —</option>' +
      (res.data || []).map(function(r) { return '<option value="' + r.id + '">' + r.name + '</option>'; }).join('');
    document.getElementById('add-role_id').innerHTML = options;
  } catch(e) {}
}

async function loadRows(search) {
  search = search || '';
  var url = LIST_URL + (search ? '?search=' + encodeURIComponent(search) : '');
  try {
    var res = await apiGet(url);
    allRows = res.data || [];
    window.pager.setData(allRows);

    var active   = allRows.filter(function(r) { return r.status === 'active'; }).length;
    var inactive = allRows.filter(function(r) { return r.status === 'inactive'; }).length;
    document.getElementById('stats-area').innerHTML =
      '<span class="badge bg-success-lt">Active: ' + active + '</span>' +
      '<span class="badge bg-secondary-lt">Inactive: ' + inactive + '</span>';
  } catch (e) {
    showMsg('table-message', e.message);
  }
}

function renderRow(r) {
  return '<tr>' +
    '<td>' + (r.first_name || '') + ' ' + (r.last_name || '') + '</td>' +
    '<td>' + (r.username || '') + '</td>' +
    '<td>' + (r.email || '') + '</td>' +
    '<td>' + (r.role_name || '—') + '</td>' +
    '<td style="text-transform:capitalize">' + (r.user_type || '') + '</td>' +
    '<td>' + statusBadge(r.status) + '</td>' +
    '<td><a href="' + DETAILS_PAGE + '?id=' + r.id + '" class="btn btn-sm btn-outline-secondary">Open &#8594;</a></td>' +
    '</tr>';
}

document.getElementById('search-input').addEventListener('input', function() {
  loadRows(this.value.trim());
});

document.getElementById('add-save-btn').addEventListener('click', async function() {
  clearMsg('add-message');
  var btn = document.getElementById('add-save-btn');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    var isMs = document.getElementById('add-auth_source').value === 'microsoft';
    await apiPost(CREATE_URL, {
      first_name:       isMs ? '' : document.getElementById('add-first_name').value,
      last_name:        isMs ? '' : document.getElementById('add-last_name').value,
      username:         isMs ? '' : document.getElementById('add-username').value,
      email:            isMs ? document.getElementById('add-email-ms').value : document.getElementById('add-email').value,
      password:         isMs ? '' : document.getElementById('add-password').value,
      confirm_password: isMs ? '' : document.getElementById('add-confirm_password').value,
      role_id:          document.getElementById('add-role_id').value,
      user_type:        document.getElementById('add-user_type').value,
      auth_source:      document.getElementById('add-auth_source').value,
      status:           document.getElementById('add-active-check').checked ? 'active' : 'inactive',
    });
    tabler.Modal.getInstance(document.getElementById('modal-add')).hide();
    loadRows();
  } catch (e) {
    showMsg('add-message', e.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Save';
  }
});

document.getElementById('modal-add').addEventListener('hidden.bs.modal', function() {
  clearMsg('add-message');
  document.getElementById('add-active-check').checked = true;
  document.getElementById('modal-add').querySelectorAll('input,select,textarea').forEach(function(el) {
    if (el.type === 'checkbox') return;
    el.value = el.tagName === 'SELECT' ? (el.options[0] ? el.options[0].value : '') : '';
  });
  document.getElementById('add-email-ms').value = '';
  applyAuthToggle('local');
});

document.addEventListener('DOMContentLoaded', function() {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 7, renderRow: renderRow });
  loadRows();
  loadRoleOptions();
});
</script>
<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
