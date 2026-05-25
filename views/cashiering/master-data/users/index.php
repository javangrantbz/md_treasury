<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.users.manage');
require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row align-items-center">
        <div class="col">
          <div class="page-pretitle">Cashiering — Master Data</div>
          <h2 class="page-title">Users</h2>
        </div>
        <div class="col-auto ms-auto d-flex gap-2">
          <a href="<?= url('views/cashiering/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">← Cashiering</a>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-add">
            Add User
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div class="d-flex gap-2 align-items-center">
            <input type="text" id="search-input" class="form-control form-control-sm w-auto" placeholder="Search...">
            <div id="stats-area" class="d-flex gap-2 ms-2">
              <span class="badge bg-success-lt">Active: 0</span>
              <span class="badge bg-secondary-lt">Inactive: 0</span>
            </div>
          </div>
        </div>
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
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="add-message" class="alert" style="display:none"></div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">First Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="add-first_name" placeholder="First name">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Last Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="add-last_name" placeholder="Last name">
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Username <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="add-username" placeholder="Username">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Email <span class="text-danger">*</span></label>
            <input type="email" class="form-control" id="add-email" placeholder="Email address">
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Password <span class="text-danger">*</span></label>
            <input type="password" class="form-control" id="add-password" placeholder="Password">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
            <input type="password" class="form-control" id="add-confirm_password" placeholder="Confirm password">
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Role</label>
            <select class="form-select" id="add-role_id">
              <option value="">— No Role —</option>
            </select>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">User Type</label>
            <select class="form-select" id="add-user_type">
              <option value="internal">Internal</option>
              <option value="external">External</option>
            </select>
          </div>
		   <div class="col-md-3 mb-3">
            <label class="form-label">Authentication Type</label>
            <select class="form-select" id="add-auth_source">
              <option value="local">Local</option>
              <option value="sso">SSO</option>
              <option value="microsoft">Microsoft SSO</option>
            </select>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Status</label>
            <select class="form-select" id="add-status">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="pending">Pending</option>
              <option value="locked">Locked</option>
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

<!-- VIEW modal -->
<div class="modal modal-blur fade" id="modal-view" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">User Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <dl class="row" id="view-body"><!-- populated by JS --></dl>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-link link-secondary me-auto" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="view-edit-btn">Edit</button>
      </div>
    </div>
  </div>
</div>

<!-- EDIT modal -->
<div class="modal modal-blur fade" id="modal-edit" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="edit-message" class="alert" style="display:none"></div>
        <input type="hidden" id="edit-id">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">First Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="edit-first_name" placeholder="First name">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Last Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="edit-last_name" placeholder="Last name">
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Username <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="edit-username" placeholder="Username">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Email <span class="text-danger">*</span></label>
            <input type="email" class="form-control" id="edit-email" placeholder="Email address">
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">New Password</label>
            <input type="password" class="form-control" id="edit-password" placeholder="Leave blank to keep current">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Confirm New Password</label>
            <input type="password" class="form-control" id="edit-confirm_password" placeholder="Leave blank to keep current">
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Role</label>
            <select class="form-select" id="edit-role_id">
              <option value="">— No Role —</option>
            </select>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">User Type</label>
            <select class="form-select" id="edit-user_type">
              <option value="internal">Internal</option>
              <option value="external">External</option>
            </select>
          </div>
		   <div class="col-md-3 mb-3">
            <label class="form-label">Authentication Type</label>
            <select class="form-select" id="edit-auth_source">
              <option value="local">Local</option>
              <option value="sso">SSO</option>
              <option value="microsoft">Microsoft SSO</option>
            </select>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Status</label>
            <select class="form-select" id="edit-status">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="pending">Pending</option>
              <option value="locked">Locked</option>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="edit-mfa_enabled">
            <span class="form-check-label">MFA Enabled</span>
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-link link-secondary me-auto" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="edit-save-btn">Update</button>
      </div>
    </div>
  </div>
</div>

<script>
const LIST_URL   = "<?= url('api/master-data/users/list.php') ?>";
const SHOW_URL   = "<?= url('api/master-data/users/show.php') ?>";
const CREATE_URL = "<?= url('api/master-data/users/create.php') ?>";
const UPDATE_URL = "<?= url('api/master-data/users/update.php') ?>";
const ROLES_URL  = "<?= url('api/master-data/roles/list.php') ?>";

async function loadRoleOptions() {
  try {
    const res = await apiGet(ROLES_URL + '?status=active');
    const options = '<option value="">— No Role —</option>' +
      (res.data || []).map(r => `<option value="${r.id}">${r.name}</option>`).join('');
    document.getElementById('add-role_id').innerHTML = options;
    document.getElementById('edit-role_id').innerHTML = options;
  } catch {}
}

let allRows = [];

function showMsg(id, msg, type = 'danger') {
  const el = document.getElementById(id);
  el.className = `alert alert-${type}`;
  el.textContent = msg;
  el.style.display = 'block';
}
function clearMsg(id) {
  const el = document.getElementById(id);
  el.textContent = '';
  el.style.display = 'none';
}
function statusBadge(val) {
  const ok = (val === 'active' || val === 1 || val === '1' || val === true);
  return ok
    ? '<span class="badge bg-success-lt text-success">Active</span>'
    : '<span class="badge bg-secondary-lt text-secondary">Inactive</span>';
}

async function loadRows(search = '') {
  const url = LIST_URL + (search ? `?search=${encodeURIComponent(search)}` : '');
  try {
    const res = await apiGet(url);
    allRows = res.data || [];
    renderTable(allRows);

    // Update stats
    const active = allRows.filter(r => r.status === 'active').length;
    const inactive = allRows.filter(r => r.status === 'inactive').length;
    document.getElementById('stats-area').innerHTML = `
      <span class="badge bg-success-lt">Active: ${active}</span>
      <span class="badge bg-secondary-lt">Inactive: ${inactive}</span>
    `;
  } catch (e) {
    showMsg('table-message', e.message);
  }
}

function renderRow(r) {
  return `<tr>
    <td>${(r.first_name ?? '') + ' ' + (r.last_name ?? '')}</td>
    <td>${r.username ?? ''}</td>
    <td>${r.email ?? ''}</td>
    <td>${r.role_name ?? '—'}</td>
    <td style="text-transform:capitalize">${r.user_type ?? ''}</td>
    <td>${statusBadge(r.status)}</td>
    <td>
      <div class="d-flex gap-1 justify-content-end">
        <button class="btn btn-sm btn-outline-secondary" onclick="openView(${r.id})">View</button>
        <button class="btn btn-sm btn-outline-primary" onclick="openEdit(${r.id})">Edit</button>
      </div>
    </td>
  </tr>`;
}

function renderTable(rows) {
  window.pager.setData(rows);
}

// View
async function openView(id) {
  document.getElementById('view-body').innerHTML = '<dd class="col-12 text-muted">Loading...</dd>';
  document.getElementById('view-edit-btn').onclick = () => { tabler.Modal.getInstance(document.getElementById('modal-view'))?.hide(); openEdit(id); };
  tabler.Modal.getOrCreateInstance(document.getElementById('modal-view')).show();
  try {
    const res = await apiGet(SHOW_URL + '?id=' + id);
    const r = res.data;
    const fullName = ((r.first_name ?? '') + ' ' + (r.last_name ?? '')).trim();
    const mfaVal = (r.mfa_enabled === 1 || r.mfa_enabled === '1' || r.mfa_enabled === true)
      ? '<span class="badge bg-success-lt text-success">Yes</span>'
      : '<span class="badge bg-secondary-lt text-secondary">No</span>';
    document.getElementById('view-body').innerHTML =
      `<dt class="col-sm-4">Full Name</dt><dd class="col-sm-8">${fullName || '—'}</dd>` +
      `<dt class="col-sm-4">Username</dt><dd class="col-sm-8">${r.username ?? '—'}</dd>` +
      `<dt class="col-sm-4">Email</dt><dd class="col-sm-8">${r.email ?? '—'}</dd>` +
      `<dt class="col-sm-4">Role</dt><dd class="col-sm-8">${r.role_name ?? '—'}</dd>` +
      `<dt class="col-sm-4">Type</dt><dd class="col-sm-8" style="text-transform:capitalize">${r.user_type ?? '—'}</dd>` +
      `<dt class="col-sm-4">MFA Enabled</dt><dd class="col-sm-8">${mfaVal}</dd>` +
      `<dt class="col-sm-4">Status</dt><dd class="col-sm-8">${statusBadge(r.status)}</dd>` +
      `<dt class="col-sm-4">Department</dt><dd class="col-sm-8">${r.department_name ?? '—'}</dd>` +
      `<dt class="col-sm-4">Sub-Treasury</dt><dd class="col-sm-8">${r.sub_treasury_name ?? '—'}</dd>` +
      `<dt class="col-sm-4">Register</dt><dd class="col-sm-8">${r.register_name ?? '—'}</dd>`;
  } catch (e) {
    document.getElementById('view-body').innerHTML = `<dd class="col-12 text-danger">${e.message}</dd>`;
  }
}

// Edit
async function openEdit(id) {
  clearMsg('edit-message');
  document.getElementById('edit-id').value = id;
  document.getElementById('edit-password').value = '';
  document.getElementById('edit-confirm_password').value = '';
  tabler.Modal.getOrCreateInstance(document.getElementById('modal-edit')).show();
  try {
    const res = await apiGet(SHOW_URL + '?id=' + id);
    const r = res.data;
    document.getElementById('edit-first_name').value = r.first_name ?? '';
    document.getElementById('edit-last_name').value  = r.last_name ?? '';
    document.getElementById('edit-username').value   = r.username ?? '';
    document.getElementById('edit-email').value      = r.email ?? '';
    document.getElementById('edit-role_id').value    = r.role_id ?? '';
    document.getElementById('edit-user_type').value  = r.user_type ?? 'internal';
    document.getElementById('edit-auth_source').value = r.auth_source ?? 'local';
    document.getElementById('edit-status').value     = r.status ?? 'active';
    document.getElementById('edit-mfa_enabled').checked =
      (r.mfa_enabled === 1 || r.mfa_enabled === '1' || r.mfa_enabled === true);
  } catch (e) {
    showMsg('edit-message', e.message);
  }
}

document.getElementById('edit-save-btn').addEventListener('click', async () => {
  clearMsg('edit-message');
  const btn = document.getElementById('edit-save-btn');
  btn.disabled = true; btn.textContent = 'Updating...';
  try {
    const pw = document.getElementById('edit-password').value;
    const payload = {
      id:         document.getElementById('edit-id').value,
      first_name: document.getElementById('edit-first_name').value,
      last_name:  document.getElementById('edit-last_name').value,
      username:   document.getElementById('edit-username').value,
      email:      document.getElementById('edit-email').value,
      role_id:    document.getElementById('edit-role_id').value,
      user_type:  document.getElementById('edit-user_type').value,
      auth_source: document.getElementById('edit-auth_source').value,
      status:     document.getElementById('edit-status').value,
      mfa_enabled: document.getElementById('edit-mfa_enabled').checked ? 1 : 0,
    };
    if (pw) {
      payload.password         = pw;
      payload.confirm_password = document.getElementById('edit-confirm_password').value;
    }
    await apiPost(UPDATE_URL, payload);
    tabler.Modal.getInstance(document.getElementById('modal-edit'))?.hide();
    loadRows(document.getElementById('search-input').value);
  } catch (e) {
    showMsg('edit-message', e.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Update';
  }
});

// Add
document.getElementById('add-save-btn').addEventListener('click', async () => {
  clearMsg('add-message');
  const btn = document.getElementById('add-save-btn');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    const payload = {
      first_name:       document.getElementById('add-first_name').value,
      last_name:        document.getElementById('add-last_name').value,
      username:         document.getElementById('add-username').value,
      email:            document.getElementById('add-email').value,
      password:         document.getElementById('add-password').value,
      confirm_password: document.getElementById('add-confirm_password').value,
      role_id:          document.getElementById('add-role_id').value,
      user_type:        document.getElementById('add-user_type').value,
      auth_source:      document.getElementById('add-auth_source').value,
      status:           document.getElementById('add-status').value,
    };
    await apiPost(CREATE_URL, payload);
    tabler.Modal.getInstance(document.getElementById('modal-add'))?.hide();
    loadRows();
  } catch (e) {
    showMsg('add-message', e.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Save';
  }
});

document.getElementById('search-input').addEventListener('input', function () {
  loadRows(this.value.trim());
});

document.getElementById('modal-add').addEventListener('hidden.bs.modal', () => {
  clearMsg('add-message');
  document.getElementById('modal-add').querySelectorAll('input,select,textarea').forEach(el => {
    el.value = el.tagName === 'SELECT' ? (el.options[0]?.value ?? '') : '';
  });
});

document.addEventListener('DOMContentLoaded', () => {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 7, renderRow });
  loadRows();
  loadRoleOptions();
});
</script>
<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
includes/layout-tabler-footer.php'; ?>
