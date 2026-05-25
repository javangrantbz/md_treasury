<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.bank_accounts.manage');
require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row align-items-center">
        <div class="col">
          <div class="page-pretitle">Cashiering — Master Data</div>
          <h2 class="page-title">Bank Accounts</h2>
        </div>
        <div class="col-auto ms-auto d-flex gap-2">
          <a href="<?= url('views/cashiering/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">← Cashiering</a>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-add">
            Add Bank Account
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
                <th data-col="bank_name">Bank</th>
                <th data-col="account_name">Account Name</th>
                <th data-col="account_number">Account No.</th>
                <th data-col="sof_number">SOF No.</th>
                <th data-col="currency_code">Currency</th>
                <th data-col="account_type_name">Type</th>
                <th data-col="status">Status</th>
                <th class="w-1"></th>
              </tr>
            </thead>
            <tbody id="table-body">
              <tr><td colspan="8" class="text-center py-4 text-muted">Loading...</td></tr>
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
        <h5 class="modal-title">Add Bank Account</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="add-message" class="alert" style="display:none"></div>
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Bank Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="add-bank_name" placeholder="Bank name">
          </div>
          <div class="col-md-6">
            <label class="form-label">Account Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="add-account_name" placeholder="Account name">
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-5">
            <label class="form-label">Account Number <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="add-account_number" placeholder="Account number">
          </div>
          <div class="col-md-3">
            <label class="form-label">Currency</label>
            <select class="form-select" id="add-currency_code">
              <option value="BZD">BZD</option>
              <option value="USD">USD</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Account Type</label>
            <select class="form-select" id="add-account_type_id">
              <option value="">— Select Type —</option>
            </select>
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Item Number</label>
            <input type="text" class="form-control" id="add-item_number" placeholder="e.g. 76003">
          </div>
          <div class="col-md-4">
            <label class="form-label">SOF Number</label>
            <input type="text" class="form-control" id="add-sof_number" placeholder="e.g. 750142">
          </div>
          <div class="col-md-4">
            <label class="form-label">Branch Name</label>
            <input type="text" class="form-control" id="add-branch_name" placeholder="Branch">
          </div>
        </div>
        <div class="mb-2">
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
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Bank Account Details</h5>
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
        <h5 class="modal-title">Edit Bank Account</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="edit-message" class="alert" style="display:none"></div>
        <input type="hidden" id="edit-id">
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Bank Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="edit-bank_name" placeholder="Bank name">
          </div>
          <div class="col-md-6">
            <label class="form-label">Account Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="edit-account_name" placeholder="Account name">
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-5">
            <label class="form-label">Account Number <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="edit-account_number" placeholder="Account number">
          </div>
          <div class="col-md-3">
            <label class="form-label">Currency</label>
            <select class="form-select" id="edit-currency_code">
              <option value="BZD">BZD</option>
              <option value="USD">USD</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Account Type</label>
            <select class="form-select" id="edit-account_type_id">
              <option value="">— Select Type —</option>
            </select>
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Item Number</label>
            <input type="text" class="form-control" id="edit-item_number" placeholder="e.g. 76003">
          </div>
          <div class="col-md-4">
            <label class="form-label">SOF Number</label>
            <input type="text" class="form-control" id="edit-sof_number" placeholder="e.g. 750142">
          </div>
          <div class="col-md-4">
            <label class="form-label">Branch Name</label>
            <input type="text" class="form-control" id="edit-branch_name" placeholder="Branch">
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label">Status</label>
          <select class="form-select" id="edit-status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
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
const LIST_URL   = "<?= url('api/master-data/bank-accounts/list.php') ?>";
const SHOW_URL   = "<?= url('api/master-data/bank-accounts/show.php') ?>";
const CREATE_URL = "<?= url('api/master-data/bank-accounts/create.php') ?>";
const UPDATE_URL = "<?= url('api/master-data/bank-accounts/update.php') ?>";
const TYPES_URL  = "<?= url('api/master-data/bank-accounts/types.php') ?>";

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

// ── Account types ─────────────────────────────────────────────────────────
async function loadAccountTypes() {
  try {
    const res  = await apiGet(TYPES_URL);
    const types = res.data || [];
    const opts  = '<option value="">— Select Type —</option>' +
      types.map(t => `<option value="${t.id}">${t.name}</option>`).join('');
    document.getElementById('add-account_type_id').innerHTML = opts;
    document.getElementById('edit-account_type_id').innerHTML = opts;
  } catch {}
}

// ── Table ─────────────────────────────────────────────────────────────────
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
    <td>${r.bank_name ?? ''}</td>
    <td>${r.account_name ?? ''}</td>
    <td class="text-muted" style="font-family:monospace;font-size:.85rem;">${r.account_masked ?? r.account_number ?? ''}</td>
    <td class="text-muted" style="font-size:.85rem;">${r.sof_number ?? '—'}</td>
    <td>${r.currency_code ?? ''}</td>
    <td>${r.account_type_name ? `<span class="badge bg-azure-lt text-azure">${r.account_type_name}</span>` : '<span class="text-muted">—</span>'}</td>
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

// ── View ──────────────────────────────────────────────────────────────────
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
    document.getElementById('view-body').innerHTML =
      `<dt class="col-sm-4">Bank</dt><dd class="col-sm-8">${r.bank_name ?? '—'}</dd>` +
      `<dt class="col-sm-4">Account Name</dt><dd class="col-sm-8">${r.account_name ?? '—'}</dd>` +
      `<dt class="col-sm-4">Account Number</dt><dd class="col-sm-8"><code>${r.account_number ?? '—'}</code></dd>` +
      `<dt class="col-sm-4">Account Type</dt><dd class="col-sm-8">${r.account_type_name ?? '—'}</dd>` +
      `<dt class="col-sm-4">Currency</dt><dd class="col-sm-8">${r.currency_code ?? '—'}</dd>` +
      `<dt class="col-sm-4">Item No.</dt><dd class="col-sm-8">${r.item_number ?? '—'}</dd>` +
      `<dt class="col-sm-4">SOF No.</dt><dd class="col-sm-8">${r.sof_number ?? '—'}</dd>` +
      `<dt class="col-sm-4">Branch</dt><dd class="col-sm-8">${r.branch_name ?? '—'}</dd>` +
      `<dt class="col-sm-4">Status</dt><dd class="col-sm-8">${statusBadge(r.status)}</dd>`;
  } catch (e) {
    document.getElementById('view-body').innerHTML = `<dd class="col-12 text-danger">${e.message}</dd>`;
  }
}

// ── Edit ──────────────────────────────────────────────────────────────────
async function openEdit(id) {
  clearMsg('edit-message');
  document.getElementById('edit-id').value = id;
  tabler.Modal.getOrCreateInstance(document.getElementById('modal-edit')).show();
  try {
    const res = await apiGet(SHOW_URL + '?id=' + id);
    const r = res.data;
    document.getElementById('edit-bank_name').value      = r.bank_name ?? '';
    document.getElementById('edit-account_name').value   = r.account_name ?? '';
    document.getElementById('edit-account_number').value = r.account_number ?? '';
    document.getElementById('edit-currency_code').value  = r.currency_code ?? 'BZD';
    document.getElementById('edit-account_type_id').value = r.account_type_id ?? '';
    document.getElementById('edit-item_number').value    = r.item_number ?? '';
    document.getElementById('edit-sof_number').value     = r.sof_number ?? '';
    document.getElementById('edit-branch_name').value    = r.branch_name ?? '';
    document.getElementById('edit-status').value         = r.status ?? 'active';
  } catch (e) {
    showMsg('edit-message', e.message);
  }
}

document.getElementById('edit-save-btn').addEventListener('click', async () => {
  clearMsg('edit-message');
  const btn = document.getElementById('edit-save-btn');
  btn.disabled = true; btn.textContent = 'Updating...';
  try {
    await apiPost(UPDATE_URL, {
      id:              document.getElementById('edit-id').value,
      bank_name:       document.getElementById('edit-bank_name').value,
      account_name:    document.getElementById('edit-account_name').value,
      account_number:  document.getElementById('edit-account_number').value,
      currency_code:   document.getElementById('edit-currency_code').value,
      account_type_id: document.getElementById('edit-account_type_id').value,
      item_number:     document.getElementById('edit-item_number').value,
      sof_number:      document.getElementById('edit-sof_number').value,
      branch_name:     document.getElementById('edit-branch_name').value,
      status:          document.getElementById('edit-status').value,
    });
    tabler.Modal.getInstance(document.getElementById('modal-edit'))?.hide();
    loadRows(document.getElementById('search-input').value);
  } catch (e) {
    showMsg('edit-message', e.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Update';
  }
});

// ── Add ───────────────────────────────────────────────────────────────────
document.getElementById('add-save-btn').addEventListener('click', async () => {
  clearMsg('add-message');
  const btn = document.getElementById('add-save-btn');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    await apiPost(CREATE_URL, {
      bank_name:       document.getElementById('add-bank_name').value,
      account_name:    document.getElementById('add-account_name').value,
      account_number:  document.getElementById('add-account_number').value,
      currency_code:   document.getElementById('add-currency_code').value,
      account_type_id: document.getElementById('add-account_type_id').value,
      item_number:     document.getElementById('add-item_number').value,
      sof_number:      document.getElementById('add-sof_number').value,
      branch_name:     document.getElementById('add-branch_name').value,
      status:          document.getElementById('add-status').value,
    });
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
  document.getElementById('modal-add').querySelectorAll('input').forEach(el => { el.value = ''; });
  document.getElementById('add-currency_code').value   = 'BZD';
  document.getElementById('add-account_type_id').value = '';
  document.getElementById('add-status').value          = 'active';
});

document.addEventListener('DOMContentLoaded', () => {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 8, renderRow });
  loadAccountTypes();
  loadRows();
});
</script>
<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
