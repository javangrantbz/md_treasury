<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.expenditure_types.manage');
require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row align-items-center">
        <div class="col">
          <div class="page-pretitle">Cashiering — Master Data</div>
          <h2 class="page-title">Expenditure Types</h2>
        </div>
        <div class="col-auto ms-auto d-flex gap-2">
          <a href="<?= url('views/cashiering/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">← Cashiering</a>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-add">
            Add Expenditure Type
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
                <th data-col="expenditure_code">Code</th>
                <th data-col="expenditure_name">Name</th>
                <th data-col="description">Description</th>
                <th data-col="is_active">Status</th>
                <th class="w-1"></th>
              </tr>
            </thead>
            <tbody id="table-body">
              <tr><td colspan="5" class="text-center py-4 text-muted">Loading...</td></tr>
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
        <h5 class="modal-title">Add Expenditure Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="add-message" class="alert" style="display:none"></div>
        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Expenditure Code <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="add-expenditure_code" placeholder="e.g. EXP-001">
          </div>
          <div class="col-md-8 mb-3">
            <label class="form-label">Expenditure Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="add-expenditure_name" placeholder="Expenditure type name">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea class="form-control" id="add-description" rows="3" placeholder="Optional description"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Status</label>
          <select class="form-select" id="add-is_active">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
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
        <h5 class="modal-title">Expenditure Type Details</h5>
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
        <h5 class="modal-title">Edit Expenditure Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="edit-message" class="alert" style="display:none"></div>
        <input type="hidden" id="edit-id">
        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Expenditure Code <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="edit-expenditure_code" placeholder="e.g. EXP-001">
          </div>
          <div class="col-md-8 mb-3">
            <label class="form-label">Expenditure Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="edit-expenditure_name" placeholder="Expenditure type name">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea class="form-control" id="edit-description" rows="3" placeholder="Optional description"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Status</label>
          <select class="form-select" id="edit-is_active">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
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
const LIST_URL   = "<?= url('api/master-data/expenditure-types/list.php') ?>";
const SHOW_URL   = "<?= url('api/master-data/expenditure-types/show.php') ?>";
const CREATE_URL = "<?= url('api/master-data/expenditure-types/create.php') ?>";
const UPDATE_URL = "<?= url('api/master-data/expenditure-types/update.php') ?>";

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
function isActiveBadge(val) {
  return Number(val) === 1
    ? '<span class="badge bg-success-lt text-success">Active</span>'
    : '<span class="badge bg-secondary-lt text-secondary">Inactive</span>';
}

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
    <td>${r.expenditure_code ?? ''}</td>
    <td>${r.expenditure_name ?? ''}</td>
    <td>${r.description ?? ''}</td>
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

// View
async function openView(id) {
  document.getElementById('view-body').innerHTML = '<dd class="col-12 text-muted">Loading...</dd>';
  document.getElementById('view-edit-btn').onclick = () => { tabler.Modal.getInstance(document.getElementById('modal-view'))?.hide(); openEdit(id); };
  tabler.Modal.getOrCreateInstance(document.getElementById('modal-view')).show();
  try {
    const res = await apiGet(SHOW_URL + '?id=' + id);
    const r = res.data;
    document.getElementById('view-body').innerHTML =
      `<dt class="col-sm-4">Code</dt><dd class="col-sm-8">${r.expenditure_code ?? '—'}</dd>` +
      `<dt class="col-sm-4">Name</dt><dd class="col-sm-8">${r.expenditure_name ?? '—'}</dd>` +
      `<dt class="col-sm-4">Description</dt><dd class="col-sm-8">${r.description ?? '—'}</dd>` +
      `<dt class="col-sm-4">Status</dt><dd class="col-sm-8">${isActiveBadge(r.is_active)}</dd>`;
  } catch (e) {
    document.getElementById('view-body').innerHTML = `<dd class="col-12 text-danger">${e.message}</dd>`;
  }
}

// Edit
async function openEdit(id) {
  clearMsg('edit-message');
  document.getElementById('edit-id').value = id;
  tabler.Modal.getOrCreateInstance(document.getElementById('modal-edit')).show();
  try {
    const res = await apiGet(SHOW_URL + '?id=' + id);
    const r = res.data;
    document.getElementById('edit-expenditure_code').value = r.expenditure_code ?? '';
    document.getElementById('edit-expenditure_name').value = r.expenditure_name ?? '';
    document.getElementById('edit-description').value      = r.description ?? '';
    document.getElementById('edit-is_active').value        = String(r.is_active) === '1' ? '1' : '0';
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
      expenditure_code: document.getElementById('edit-expenditure_code').value,
      expenditure_name: document.getElementById('edit-expenditure_name').value,
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

// Add
document.getElementById('add-save-btn').addEventListener('click', async () => {
  clearMsg('add-message');
  const btn = document.getElementById('add-save-btn');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    const payload = {
      expenditure_code: document.getElementById('add-expenditure_code').value,
      expenditure_name: document.getElementById('add-expenditure_name').value,
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
  document.getElementById('modal-add').querySelectorAll('input,select,textarea').forEach(el => {
    el.value = el.tagName === 'SELECT' ? (el.options[0]?.value ?? '') : '';
  });
});

document.addEventListener('DOMContentLoaded', () => {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 5, renderRow });
  loadRows();
});
</script>
<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
