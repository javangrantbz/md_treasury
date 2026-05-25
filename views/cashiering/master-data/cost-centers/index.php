<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.cost_centers.manage');
require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row align-items-center">
        <div class="col">
          <div class="page-pretitle">Cashiering — Master Data</div>
          <h2 class="page-title">Cost Centers</h2>
        </div>
        <div class="col-auto ms-auto d-flex gap-2">
          <a href="<?= url('views/cashiering/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">← Cashiering</a>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-add">
            Add Cost Center
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
                <th data-col="code">Code</th>
                <th data-col="name">Name</th>
                <th data-col="department_name">Department</th>
                <th data-col="sub_treasury_name">Sub-Treasury</th>
                <th data-col="bank_count" class="text-center">Banks</th>
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
        <h5 class="modal-title">Add Cost Center</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="add-message" class="alert" style="display:none"></div>
        <div class="row">
          <div class="col-md-8 mb-3">
            <label class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="add-name" placeholder="Cost center name">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Code</label>
            <input type="text" class="form-control" id="add-code" placeholder="e.g. CC-001">
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Department</label>
            <select class="form-select" id="add-department_id">
              <option value="">— Select Department —</option>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Sub-Treasury</label>
            <select class="form-select" id="add-sub_treasury_id">
              <option value="">— Select Department first —</option>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea class="form-control" id="add-description" rows="3" placeholder="Optional description"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Status</label>
          <select class="form-select" id="add-status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
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
  <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Cost Center Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <ul class="nav nav-tabs px-3 border-bottom">
          <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" data-bs-target="#view-tab-details">Details</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" data-bs-target="#view-tab-banks">Banks</a>
          </li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane active show p-3" id="view-tab-details">
            <dl class="row mb-0" id="view-body"><!-- populated by JS --></dl>
          </div>
          <div class="tab-pane p-3" id="view-tab-banks">
            <div class="mb-3">
              <label class="form-label fw-bold">Search &amp; Add Bank Account</label>
              <input type="text" id="view-bank-search" class="form-control" placeholder="Type bank name or account name...">
              <div id="view-bank-results" class="list-group mt-1" style="max-height:200px;overflow-y:auto"></div>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-vcenter mb-0">
                <thead>
                  <tr>
                    <th>Bank</th>
                    <th>Account Name</th>
                    <th>Account No.</th>
                    <th>Type</th>
                    <th>Currency</th>
                    <th class="w-1"></th>
                  </tr>
                </thead>
                <tbody id="view-banks-body">
                  <tr><td colspan="6" class="text-center text-muted py-3">Loading...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
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
  <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Cost Center</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <ul class="nav nav-tabs px-3 border-bottom">
          <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" data-bs-target="#edit-tab-details">Details</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" data-bs-target="#edit-tab-banks">Banks</a>
          </li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane active show p-3" id="edit-tab-details">
            <div id="edit-message" class="alert" style="display:none"></div>
            <input type="hidden" id="edit-id">
            <div class="row">
              <div class="col-md-8 mb-3">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="edit-name" placeholder="Cost center name">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Code</label>
                <input type="text" class="form-control" id="edit-code" placeholder="e.g. CC-001">
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Department</label>
                <select class="form-select" id="edit-department_id">
                  <option value="">— Select Department —</option>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Sub-Treasury</label>
                <select class="form-select" id="edit-sub_treasury_id">
                  <option value="">— None —</option>
                </select>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea class="form-control" id="edit-description" rows="3" placeholder="Optional description"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Status</label>
              <select class="form-select" id="edit-status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
          <div class="tab-pane p-3" id="edit-tab-banks">
            <div class="mb-3">
              <label class="form-label fw-bold">Search &amp; Add Bank Account</label>
              <input type="text" id="edit-bank-search" class="form-control" placeholder="Type bank name or account name...">
              <div id="edit-bank-results" class="list-group mt-1" style="max-height:200px;overflow-y:auto"></div>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-vcenter mb-0">
                <thead>
                  <tr>
                    <th>Bank</th>
                    <th>Account Name</th>
                    <th>Account No.</th>
                    <th>Type</th>
                    <th>Currency</th>
                    <th class="w-1"></th>
                  </tr>
                </thead>
                <tbody id="edit-banks-body">
                  <tr><td colspan="6" class="text-center text-muted py-3">Loading...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
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
const LIST_URL   = "<?= url('api/master-data/cost-centers/list.php') ?>";
const SHOW_URL   = "<?= url('api/master-data/cost-centers/show.php') ?>";
const CREATE_URL = "<?= url('api/master-data/cost-centers/create.php') ?>";
const UPDATE_URL = "<?= url('api/master-data/cost-centers/update.php') ?>";
const BANKS_URL  = "<?= url('api/master-data/cost-centers/banks.php') ?>";
const ASSIGN_URL = "<?= url('api/master-data/cost-centers/assign-bank.php') ?>";
const REMOVE_URL = "<?= url('api/master-data/cost-centers/remove-bank.php') ?>";
const DEPT_URL   = "<?= url('api/master-data/departments/list.php') ?>";
const ST_URL     = "<?= url('api/master-data/sub-treasuries/list.php') ?>";
const BA_URL     = "<?= url('api/master-data/bank-accounts/list.php') ?>";

let allRows = [], allDepartments = [], allSubTreasuries = [], allBankAccounts = [];
let currentCCId = null;

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

// ── Reference data ──────────────────────────────────────────────────────────

async function loadReferenceData() {
  try {
    const [depts, sts, banks] = await Promise.all([
      apiGet(DEPT_URL),
      apiGet(ST_URL),
      apiGet(BA_URL),
    ]);
    allDepartments   = depts.data  || [];
    allSubTreasuries = sts.data    || [];
    allBankAccounts  = banks.data  || [];
    populateDeptSelect('add');
    populateDeptSelect('edit');
  } catch(e) {
    console.error('Failed to load reference data', e);
  }
}

function populateDeptSelect(prefix, selectedId = null) {
  const sel = document.getElementById(prefix + '-department_id');
  sel.innerHTML = '<option value="">— Select Department —</option>';
  allDepartments.forEach(d => {
    const opt = document.createElement('option');
    opt.value = d.id;
    opt.textContent = d.name;
    if (selectedId && String(d.id) === String(selectedId)) opt.selected = true;
    sel.appendChild(opt);
  });
}

function populateSubTreasurySelect(prefix, deptId, selectedId = null) {
  const sel = document.getElementById(prefix + '-sub_treasury_id');
  const filtered = deptId
    ? allSubTreasuries.filter(st => String(st.department_id) === String(deptId))
    : [];
  sel.innerHTML = '<option value="">— None —</option>';
  if (filtered.length === 0 && deptId) {
    const opt = document.createElement('option');
    opt.value = '';
    opt.disabled = true;
    opt.textContent = 'No sub-treasuries for this department';
    sel.appendChild(opt);
  }
  filtered.forEach(st => {
    const opt = document.createElement('option');
    opt.value = st.id;
    opt.textContent = st.sub_treasury_name;
    if (selectedId && String(st.id) === String(selectedId)) opt.selected = true;
    sel.appendChild(opt);
  });
}

// ── Table ────────────────────────────────────────────────────────────────────

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

function bankCountBadge(r) {
  const n = parseInt(r.bank_count ?? 0, 10);
  if (n === 0) return '<span class="text-muted">—</span>';
  const safeName = (r.name ?? '').replace(/"/g, '&quot;');
  return `<a href="#" class="badge bg-blue-lt text-blue text-decoration-none" data-cc-id="${r.id}" data-cc-name="${safeName}" onclick="openView(this.dataset.ccId, true);return false;">${n}</a>`;
}

function renderRow(r) {
  return `<tr>
    <td>${r.code ?? ''}</td>
    <td>${r.name ?? ''}</td>
    <td>${r.department_name ?? ''}</td>
    <td>${r.sub_treasury_name ?? ''}</td>
    <td class="text-center">${bankCountBadge(r)}</td>
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

// ── Banks management ─────────────────────────────────────────────────────────

async function loadBanks(ccId, prefix) {
  const tbody = document.getElementById(prefix + '-banks-body');
  tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Loading...</td></tr>';
  try {
    const res = await apiGet(BANKS_URL + '?cost_center_id=' + ccId);
    const rows = res.data ?? [];
    if (rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No banks assigned yet.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(b => {
      const typeBadge = b.account_type_name
        ? `<span class="badge bg-azure-lt text-azure">${b.account_type_name}</span>`
        : '—';
      const st = (b.status === 'active' || b.status === '1' || b.status === 1)
        ? '<span class="badge bg-success-lt text-success">Active</span>'
        : '<span class="badge bg-secondary-lt text-secondary">Inactive</span>';
      return `<tr data-ba-id="${b.id}">
        <td>${b.bank_name ?? '—'}</td>
        <td>${b.account_name ?? '—'}</td>
        <td><code>${b.account_masked ?? '—'}</code></td>
        <td>${typeBadge}</td>
        <td>${b.currency_code ?? '—'}</td>
        <td><button class="btn btn-sm btn-ghost-danger px-2" title="Remove" onclick="removeBank(${b.assignment_id}, '${prefix}')">✕</button></td>
      </tr>`;
    }).join('');
  } catch(e) {
    tbody.innerHTML = `<tr><td colspan="6" class="text-danger p-3">${e.message}</td></tr>`;
  }
}

function renderBankSearchResults(prefix) {
  const query = (document.getElementById(prefix + '-bank-search').value ?? '').toLowerCase().trim();
  const container = document.getElementById(prefix + '-bank-results');
  if (!query) { container.innerHTML = ''; return; }

  const assignedIds = new Set(
    Array.from(document.querySelectorAll('#' + prefix + '-banks-body tr[data-ba-id]'))
         .map(tr => tr.dataset.baId)
  );

  const results = allBankAccounts.filter(ba => {
    if (assignedIds.has(String(ba.id))) return false;
    return (ba.bank_name     ?? '').toLowerCase().includes(query) ||
           (ba.account_name  ?? '').toLowerCase().includes(query) ||
           (ba.account_masked ?? '').toLowerCase().includes(query);
  }).slice(0, 15);

  if (results.length === 0) {
    container.innerHTML = '<div class="list-group-item text-muted py-2">No matching bank accounts</div>';
    return;
  }
  container.innerHTML = results.map(ba => `
    <button type="button" class="list-group-item list-group-item-action py-2"
            onclick="assignBank('${prefix}', ${ba.id})">
      <div class="d-flex justify-content-between align-items-center">
        <span><strong>${ba.bank_name ?? ''}</strong> — ${ba.account_name ?? ''}</span>
        <small class="text-muted ms-2">${ba.account_masked ?? ''} · ${ba.currency_code ?? ''}</small>
      </div>
    </button>
  `).join('');
}

async function assignBank(prefix, bankAccountId) {
  if (!currentCCId) return;
  try {
    await apiPost(ASSIGN_URL, { cost_center_id: currentCCId, bank_account_id: bankAccountId });
    document.getElementById(prefix + '-bank-search').value = '';
    document.getElementById(prefix + '-bank-results').innerHTML = '';
    await loadBanks(currentCCId, prefix);
    loadRows(document.getElementById('search-input').value);
  } catch(e) {
    alert(e.message);
  }
}

async function removeBank(assignmentId, prefix) {
  if (!confirm('Remove this bank account from the cost center?')) return;
  try {
    await apiPost(REMOVE_URL, { id: assignmentId });
    await loadBanks(currentCCId, prefix);
    loadRows(document.getElementById('search-input').value);
  } catch(e) {
    alert(e.message);
  }
}

// ── View modal ───────────────────────────────────────────────────────────────

async function openView(id, focusBanks = false) {
  currentCCId = id;
  document.getElementById('view-body').innerHTML = '<dd class="col-12 text-muted">Loading...</dd>';
  document.getElementById('view-bank-search').value = '';
  document.getElementById('view-bank-results').innerHTML = '';
  document.getElementById('view-edit-btn').onclick = () => {
    tabler.Modal.getInstance(document.getElementById('modal-view'))?.hide();
    openEdit(id);
  };

  const modal = tabler.Modal.getOrCreateInstance(document.getElementById('modal-view'));
  modal.show();

  if (focusBanks) {
    setTimeout(() => {
      document.querySelector('#modal-view [data-bs-target="#view-tab-banks"]')?.click();
    }, 50);
  }

  try {
    const res = await apiGet(SHOW_URL + '?id=' + id);
    const r = res.data;
    document.getElementById('view-body').innerHTML =
      `<dt class="col-sm-4">Code</dt><dd class="col-sm-8">${r.code ?? '—'}</dd>` +
      `<dt class="col-sm-4">Name</dt><dd class="col-sm-8">${r.name ?? '—'}</dd>` +
      `<dt class="col-sm-4">Department</dt><dd class="col-sm-8">${r.department_name ?? '—'}</dd>` +
      `<dt class="col-sm-4">Sub-Treasury</dt><dd class="col-sm-8">${r.sub_treasury_name ?? '—'}</dd>` +
      `<dt class="col-sm-4">Description</dt><dd class="col-sm-8">${r.description ?? '—'}</dd>` +
      `<dt class="col-sm-4">Status</dt><dd class="col-sm-8">${statusBadge(r.status)}</dd>`;
  } catch(e) {
    document.getElementById('view-body').innerHTML = `<dd class="col-12 text-danger">${e.message}</dd>`;
  }

  loadBanks(id, 'view');
}

// ── Edit modal ───────────────────────────────────────────────────────────────

async function openEdit(id) {
  currentCCId = id;
  clearMsg('edit-message');
  document.getElementById('edit-id').value = id;
  document.getElementById('edit-bank-search').value = '';
  document.getElementById('edit-bank-results').innerHTML = '';
  tabler.Modal.getOrCreateInstance(document.getElementById('modal-edit')).show();

  try {
    const res = await apiGet(SHOW_URL + '?id=' + id);
    const r = res.data;
    document.getElementById('edit-name').value        = r.name        ?? '';
    document.getElementById('edit-code').value        = r.code        ?? '';
    document.getElementById('edit-description').value = r.description ?? '';
    document.getElementById('edit-status').value      = r.status      ?? 'active';
    populateDeptSelect('edit', r.department_id);
    populateSubTreasurySelect('edit', r.department_id, r.sub_treasury_id);
  } catch(e) {
    showMsg('edit-message', e.message);
  }

  loadBanks(id, 'edit');
}

document.getElementById('edit-save-btn').addEventListener('click', async () => {
  clearMsg('edit-message');
  const btn = document.getElementById('edit-save-btn');
  btn.disabled = true; btn.textContent = 'Updating...';
  try {
    const payload = {
      id:              document.getElementById('edit-id').value,
      name:            document.getElementById('edit-name').value,
      code:            document.getElementById('edit-code').value,
      department_id:   document.getElementById('edit-department_id').value,
      sub_treasury_id: document.getElementById('edit-sub_treasury_id').value,
      description:     document.getElementById('edit-description').value,
      status:          document.getElementById('edit-status').value,
    };
    await apiPost(UPDATE_URL, payload);
    tabler.Modal.getInstance(document.getElementById('modal-edit'))?.hide();
    loadRows(document.getElementById('search-input').value);
  } catch(e) {
    showMsg('edit-message', e.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Update';
  }
});

// ── Add modal ────────────────────────────────────────────────────────────────

document.getElementById('add-save-btn').addEventListener('click', async () => {
  clearMsg('add-message');
  const btn = document.getElementById('add-save-btn');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    const payload = {
      name:            document.getElementById('add-name').value,
      code:            document.getElementById('add-code').value,
      department_id:   document.getElementById('add-department_id').value,
      sub_treasury_id: document.getElementById('add-sub_treasury_id').value,
      description:     document.getElementById('add-description').value,
      status:          document.getElementById('add-status').value,
    };
    await apiPost(CREATE_URL, payload);
    tabler.Modal.getInstance(document.getElementById('modal-add'))?.hide();
    loadRows();
  } catch(e) {
    showMsg('add-message', e.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Save';
  }
});

// ── Cascade: department → sub-treasury ───────────────────────────────────────

document.getElementById('add-department_id').addEventListener('change', function () {
  populateSubTreasurySelect('add', this.value);
});
document.getElementById('edit-department_id').addEventListener('change', function () {
  populateSubTreasurySelect('edit', this.value);
});

// ── Bank search inputs ────────────────────────────────────────────────────────

document.getElementById('view-bank-search').addEventListener('input', function () {
  renderBankSearchResults('view');
});
document.getElementById('edit-bank-search').addEventListener('input', function () {
  renderBankSearchResults('edit');
});

// ── Search & modal cleanup ────────────────────────────────────────────────────

document.getElementById('search-input').addEventListener('input', function () {
  loadRows(this.value.trim());
});

document.getElementById('modal-add').addEventListener('hidden.bs.modal', () => {
  clearMsg('add-message');
  document.getElementById('modal-add').querySelectorAll('input,textarea').forEach(el => { el.value = ''; });
  document.getElementById('add-status').value = 'active';
  document.getElementById('add-department_id').value = '';
  document.getElementById('add-sub_treasury_id').innerHTML = '<option value="">— Select Department first —</option>';
});

document.getElementById('modal-view').addEventListener('hidden.bs.modal', () => {
  // reset to details tab for next open
  document.querySelector('#modal-view [data-bs-target="#view-tab-details"]')?.click();
});

document.addEventListener('DOMContentLoaded', () => {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 7, renderRow });
  loadReferenceData();
  loadRows();
});
</script>
<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
includes/layout-tabler-footer.php'; ?>
