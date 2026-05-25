<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.registers.manage');
require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row align-items-center">
        <div class="col">
          <div class="page-pretitle">Cashiering — Master Data</div>
          <h2 class="page-title">Registers</h2>
        </div>
        <div class="col-auto ms-auto d-flex gap-2">
          <a href="<?= url('views/cashiering/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">← Cashiering</a>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-add">
            Add Register
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
                <th data-col="register_code">Code</th>
                <th data-col="register_name">Name</th>
                <th data-col="department_name">Department</th>
                <th data-col="sub_treasury_name">Sub-Treasury</th>
                <th data-col="assigned_user_name">Assigned User</th>
                <th data-col="is_active">Status</th>
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
        <h5 class="modal-title">Add Register</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="add-message" class="alert" style="display:none"></div>
        <div class="row mb-3">
          <div class="col-md-8">
            <label class="form-label">Register Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="add-register_name" placeholder="Register name">
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select class="form-select" id="add-is_active">
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Department <span class="text-danger">*</span></label>
            <select class="form-select" id="add-department_id">
              <option value="">— Select Department —</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Sub-Treasury</label>
            <select class="form-select" id="add-sub_treasury_id" disabled>
              <option value="">— Select Department first —</option>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Assigned User</label>
          <select class="form-select" id="add-assigned_user_id">
            <option value="">— None —</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Description</label>
          <textarea class="form-control" id="add-description" rows="2" placeholder="Optional description"></textarea>
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
        <h5 class="modal-title">Register Details</h5>
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
        <h5 class="modal-title">Edit Register</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="edit-message" class="alert" style="display:none"></div>
        <input type="hidden" id="edit-id">
        <div class="row mb-3">
          <div class="col-md-3">
            <label class="form-label text-muted" style="font-size:.8rem;">Code</label>
            <input type="text" class="form-control bg-light text-muted" id="edit-register_code" readonly tabindex="-1" style="font-family:monospace;font-size:.85rem;">
          </div>
          <div class="col-md-6">
            <label class="form-label">Register Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="edit-register_name" placeholder="Register name">
          </div>
          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select class="form-select" id="edit-is_active">
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Department <span class="text-danger">*</span></label>
            <select class="form-select" id="edit-department_id">
              <option value="">— Select Department —</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Sub-Treasury</label>
            <select class="form-select" id="edit-sub_treasury_id">
              <option value="">— Select Department first —</option>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Assigned User</label>
          <select class="form-select" id="edit-assigned_user_id">
            <option value="">— None —</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Description</label>
          <textarea class="form-control" id="edit-description" rows="2" placeholder="Optional description"></textarea>
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
const LIST_URL   = "<?= url('api/master-data/registers/list.php') ?>";
const SHOW_URL   = "<?= url('api/master-data/registers/show.php') ?>";
const CREATE_URL = "<?= url('api/master-data/registers/create.php') ?>";
const UPDATE_URL = "<?= url('api/master-data/registers/update.php') ?>";
const DEPT_URL   = "<?= url('api/master-data/departments/list.php') ?>";
const ST_URL     = "<?= url('api/master-data/sub-treasuries/list.php') ?>";
const USERS_URL  = "<?= url('api/master-data/users/list.php') ?>";

let allRows        = [];
let allDepartments = [];
let allSubTreasuries = [];
let allUsers       = [];

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
function isActiveBadge(val) {
  return Number(val) === 1
    ? '<span class="badge bg-success-lt text-success">Active</span>'
    : '<span class="badge bg-secondary-lt text-secondary">Inactive</span>';
}

// ── Reference data ─────────────────────────────────────────────────────────
async function loadReferenceData() {
  const [dRes, stRes, uRes] = await Promise.all([
    apiGet(DEPT_URL + '?status=active'),
    apiGet(ST_URL),
    apiGet(USERS_URL + '?status=active'),
  ]);
  allDepartments   = dRes.data  || [];
  allSubTreasuries = stRes.data || [];
  allUsers         = uRes.data  || [];

  populateDeptSelects();
  populateUserSelects();
}

function populateDeptSelects() {
  const opts = '<option value="">— Select Department —</option>' +
    allDepartments.map(d => `<option value="${d.id}">${d.name}</option>`).join('');
  document.getElementById('add-department_id').innerHTML = opts;
  document.getElementById('edit-department_id').innerHTML = opts;
}

function populateSubTreasurySelect(prefix, deptId, selectedId = null) {
  const sel = document.getElementById(prefix + '-sub_treasury_id');
  const filtered = allSubTreasuries.filter(st => String(st.department_id) === String(deptId));
  if (!deptId || !filtered.length) {
    sel.innerHTML = `<option value="">${deptId ? '— No sub-treasuries for this department —' : '— Select Department first —'}</option>`;
    sel.disabled = !deptId;
    return;
  }
  sel.disabled = false;
  sel.innerHTML = '<option value="">— Select Sub-Treasury —</option>' +
    filtered.map(st => `<option value="${st.id}" ${String(st.id) === String(selectedId) ? 'selected' : ''}>${st.sub_treasury_name}</option>`).join('');
}

function populateUserSelects(selectedAddId = null, selectedEditId = null) {
  const opts = '<option value="">— None —</option>' +
    allUsers.map(u => `<option value="${u.id}">${u.first_name} ${u.last_name} (${u.username})</option>`).join('');
  document.getElementById('add-assigned_user_id').innerHTML = opts;
  document.getElementById('edit-assigned_user_id').innerHTML = opts;
  if (selectedAddId)  document.getElementById('add-assigned_user_id').value  = selectedAddId;
  if (selectedEditId) document.getElementById('edit-assigned_user_id').value = selectedEditId;
}

// Wire department → sub-treasury cascades
document.getElementById('add-department_id').addEventListener('change', function () {
  populateSubTreasurySelect('add', this.value);
});
document.getElementById('edit-department_id').addEventListener('change', function () {
  populateSubTreasurySelect('edit', this.value);
});

// ── Table ──────────────────────────────────────────────────────────────────
async function loadRows(search = '') {
  const url = LIST_URL + (search ? `?search=${encodeURIComponent(search)}` : '');
  try {
    const res = await apiGet(url);
    allRows = res.data || [];
    renderTable(allRows);

    // Update stats (uses is_active 1/0)
    const active = allRows.filter(r => Number(r.is_active) === 1).length;
    const inactive = allRows.filter(r => Number(r.is_active) === 0).length;
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
    <td class="text-muted" style="font-size:.82rem;font-family:monospace;">${r.register_code ?? ''}</td>
    <td>${r.register_name ?? ''}</td>
    <td>${r.department_name ?? ''}</td>
    <td>${r.sub_treasury_name ?? ''}</td>
    <td>${r.assigned_user_name ? r.assigned_user_name.trim() : '<span class="text-muted">—</span>'}</td>
    <td>${isActiveBadge(r.is_active)}</td>
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

// ── View ───────────────────────────────────────────────────────────────────
async function openView(id) {
  document.getElementById('view-body').innerHTML = '<dd class="col-12 text-muted">Loading...</dd>';
  document.getElementById('view-edit-btn').onclick = () => {
    tabler.Modal.getInstance(document.getElementById('modal-view'))?.hide();
    openEdit(id);
  };
  tabler.Modal.getOrCreateInstance(document.getElementById('modal-view')).show();
  try {
    const res = await apiGet(SHOW_URL + '?id=' + id);
    const r = res.data;
    const userName = r.assigned_user_name ? r.assigned_user_name.trim() + (r.assigned_username ? ` (${r.assigned_username})` : '') : '—';
    document.getElementById('view-body').innerHTML =
      `<dt class="col-sm-4">Code</dt><dd class="col-sm-8"><code>${r.register_code ?? '—'}</code></dd>` +
      `<dt class="col-sm-4">Name</dt><dd class="col-sm-8">${r.register_name ?? '—'}</dd>` +
      `<dt class="col-sm-4">Department</dt><dd class="col-sm-8">${r.department_name ?? '—'}</dd>` +
      `<dt class="col-sm-4">Sub-Treasury</dt><dd class="col-sm-8">${r.sub_treasury_name ?? '—'}</dd>` +
      `<dt class="col-sm-4">Assigned User</dt><dd class="col-sm-8">${userName}</dd>` +
      `<dt class="col-sm-4">Description</dt><dd class="col-sm-8">${r.description ?? '—'}</dd>` +
      `<dt class="col-sm-4">Status</dt><dd class="col-sm-8">${isActiveBadge(r.is_active)}</dd>`;
  } catch (e) {
    document.getElementById('view-body').innerHTML = `<dd class="col-12 text-danger">${e.message}</dd>`;
  }
}

// ── Edit ───────────────────────────────────────────────────────────────────
async function openEdit(id) {
  clearMsg('edit-message');
  document.getElementById('edit-id').value = id;
  tabler.Modal.getOrCreateInstance(document.getElementById('modal-edit')).show();
  try {
    const res = await apiGet(SHOW_URL + '?id=' + id);
    const r = res.data;
    document.getElementById('edit-register_code').value = r.register_code ?? '';
    document.getElementById('edit-register_name').value = r.register_name ?? '';
    document.getElementById('edit-description').value   = r.description ?? '';
    document.getElementById('edit-is_active').value     = String(r.is_active) === '1' ? '1' : '0';

    // Set department, then populate and set sub-treasury
    document.getElementById('edit-department_id').value = r.department_id ?? '';
    populateSubTreasurySelect('edit', r.department_id, r.sub_treasury_id);

    // Set assigned user
    document.getElementById('edit-assigned_user_id').value = r.assigned_user_id ?? '';
  } catch (e) {
    showMsg('edit-message', e.message);
  }
}

document.getElementById('edit-save-btn').addEventListener('click', async () => {
  clearMsg('edit-message');
  const btn = document.getElementById('edit-save-btn');
  btn.disabled = true; btn.textContent = 'Updating...';
  try {
    const payload = {
      id:               document.getElementById('edit-id').value,
      register_name:    document.getElementById('edit-register_name').value,
      department_id:    document.getElementById('edit-department_id').value,
      sub_treasury_id:  document.getElementById('edit-sub_treasury_id').value,
      assigned_user_id: document.getElementById('edit-assigned_user_id').value,
      description:      document.getElementById('edit-description').value,
      is_active:        document.getElementById('edit-is_active').value,
    };
    await apiPost(UPDATE_URL, payload);
    tabler.Modal.getInstance(document.getElementById('modal-edit'))?.hide();
    loadRows(document.getElementById('search-input').value);
  } catch (e) {
    showMsg('edit-message', e.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Update';
  }
});

// ── Add ────────────────────────────────────────────────────────────────────
document.getElementById('add-save-btn').addEventListener('click', async () => {
  clearMsg('add-message');
  const btn = document.getElementById('add-save-btn');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    const payload = {
      register_name:    document.getElementById('add-register_name').value,
      department_id:    document.getElementById('add-department_id').value,
      sub_treasury_id:  document.getElementById('add-sub_treasury_id').value,
      assigned_user_id: document.getElementById('add-assigned_user_id').value,
      description:      document.getElementById('add-description').value,
      is_active:        document.getElementById('add-is_active').value,
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
  document.getElementById('add-register_name').value = '';
  document.getElementById('add-description').value   = '';
  document.getElementById('add-department_id').value = '';
  document.getElementById('add-is_active').value     = '1';
  document.getElementById('add-assigned_user_id').value = '';
  populateSubTreasurySelect('add', '');
});

document.addEventListener('DOMContentLoaded', async () => {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 7, renderRow });
  await loadReferenceData();
  loadRows();
});
</script>
<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
