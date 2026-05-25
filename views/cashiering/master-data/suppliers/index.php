<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.suppliers.manage');
require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row align-items-center">
        <div class="col">
          <div class="page-pretitle">Cashiering — Master Data</div>
          <h2 class="page-title">Suppliers</h2>
        </div>
        <div class="col-auto ms-auto d-flex gap-2">
          <a href="<?= url('views/cashiering/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">← Cashiering</a>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-add">
            Add Supplier
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
                <th data-col="supplier_name">Name</th>
                <th data-col="tax_id">TIN</th>
                <th data-col="contact_name">Contact</th>
                <th data-col="email">Email</th>
                <th data-col="phone">Phone</th>
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
        <h5 class="modal-title">Add Supplier</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="add-message" class="alert" style="display:none"></div>
        <div class="row">
          <div class="col-md-8 mb-3">
            <label class="form-label">Supplier Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="add-supplier_name" placeholder="Supplier name">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">TIN</label>
            <input type="text" class="form-control" id="add-tax_id" placeholder="Tax ID / TIN">
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Contact Name</label>
            <input type="text" class="form-control" id="add-contact_name" placeholder="Contact person">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" id="add-email" placeholder="Email address">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Phone</label>
          <input type="text" class="form-control" id="add-phone" placeholder="Phone number">
        </div>
        <div class="mb-3">
          <label class="form-label">Address Line 1</label>
          <input type="text" class="form-control" id="add-address_line_1" placeholder="Street address">
        </div>
        <div class="mb-3">
          <label class="form-label">Address Line 2</label>
          <input type="text" class="form-control" id="add-address_line_2" placeholder="Apt, suite, etc.">
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">District</label>
            <input type="text" class="form-control" id="add-district" placeholder="District">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Country</label>
            <input type="text" class="form-control" id="add-country" placeholder="Country">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea class="form-control" id="add-notes" rows="3" placeholder="Optional notes"></textarea>
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
        <h5 class="modal-title">Supplier Details</h5>
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
        <h5 class="modal-title">Edit Supplier</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="edit-message" class="alert" style="display:none"></div>
        <input type="hidden" id="edit-id">
        <div class="row">
          <div class="col-md-8 mb-3">
            <label class="form-label">Supplier Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="edit-supplier_name" placeholder="Supplier name">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">TIN</label>
            <input type="text" class="form-control" id="edit-tax_id" placeholder="Tax ID / TIN">
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Contact Name</label>
            <input type="text" class="form-control" id="edit-contact_name" placeholder="Contact person">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" id="edit-email" placeholder="Email address">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Phone</label>
          <input type="text" class="form-control" id="edit-phone" placeholder="Phone number">
        </div>
        <div class="mb-3">
          <label class="form-label">Address Line 1</label>
          <input type="text" class="form-control" id="edit-address_line_1" placeholder="Street address">
        </div>
        <div class="mb-3">
          <label class="form-label">Address Line 2</label>
          <input type="text" class="form-control" id="edit-address_line_2" placeholder="Apt, suite, etc.">
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">District</label>
            <input type="text" class="form-control" id="edit-district" placeholder="District">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Country</label>
            <input type="text" class="form-control" id="edit-country" placeholder="Country">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea class="form-control" id="edit-notes" rows="3" placeholder="Optional notes"></textarea>
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
const LIST_URL   = "<?= url('api/master-data/suppliers/list.php') ?>";
const SHOW_URL   = "<?= url('api/master-data/suppliers/show.php') ?>";
const CREATE_URL = "<?= url('api/master-data/suppliers/create.php') ?>";
const UPDATE_URL = "<?= url('api/master-data/suppliers/update.php') ?>";

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
    <td>${r.supplier_name ?? ''}</td>
    <td>${r.tax_id ?? ''}</td>
    <td>${r.contact_name ?? ''}</td>
    <td>${r.email ?? ''}</td>
    <td>${r.phone ?? ''}</td>
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
    const addr = [r.address_line_1, r.address_line_2].filter(Boolean).join(', ') || '—';
    document.getElementById('view-body').innerHTML =
      `<dt class="col-sm-4">Name</dt><dd class="col-sm-8">${r.supplier_name ?? '—'}</dd>` +
      `<dt class="col-sm-4">TIN</dt><dd class="col-sm-8">${r.tax_id ?? '—'}</dd>` +
      `<dt class="col-sm-4">Contact</dt><dd class="col-sm-8">${r.contact_name ?? '—'}</dd>` +
      `<dt class="col-sm-4">Email</dt><dd class="col-sm-8">${r.email ?? '—'}</dd>` +
      `<dt class="col-sm-4">Phone</dt><dd class="col-sm-8">${r.phone ?? '—'}</dd>` +
      `<dt class="col-sm-4">Address</dt><dd class="col-sm-8">${addr}</dd>` +
      `<dt class="col-sm-4">District</dt><dd class="col-sm-8">${r.district ?? '—'}</dd>` +
      `<dt class="col-sm-4">Country</dt><dd class="col-sm-8">${r.country ?? '—'}</dd>` +
      `<dt class="col-sm-4">Notes</dt><dd class="col-sm-8">${r.notes ?? '—'}</dd>` +
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
    document.getElementById('edit-supplier_name').value = r.supplier_name ?? '';
    document.getElementById('edit-tax_id').value        = r.tax_id ?? '';
    document.getElementById('edit-contact_name').value  = r.contact_name ?? '';
    document.getElementById('edit-email').value         = r.email ?? '';
    document.getElementById('edit-phone').value         = r.phone ?? '';
    document.getElementById('edit-address_line_1').value = r.address_line_1 ?? '';
    document.getElementById('edit-address_line_2').value = r.address_line_2 ?? '';
    document.getElementById('edit-district').value      = r.district ?? '';
    document.getElementById('edit-country').value       = r.country ?? '';
    document.getElementById('edit-notes').value         = r.notes ?? '';
    document.getElementById('edit-is_active').value     = String(r.is_active) === '1' ? '1' : '0';
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
      id:             document.getElementById('edit-id').value,
      supplier_name:  document.getElementById('edit-supplier_name').value,
      tax_id:         document.getElementById('edit-tax_id').value,
      contact_name:   document.getElementById('edit-contact_name').value,
      email:          document.getElementById('edit-email').value,
      phone:          document.getElementById('edit-phone').value,
      address_line_1: document.getElementById('edit-address_line_1').value,
      address_line_2: document.getElementById('edit-address_line_2').value,
      district:       document.getElementById('edit-district').value,
      country:        document.getElementById('edit-country').value,
      notes:          document.getElementById('edit-notes').value,
      is_active:      document.getElementById('edit-is_active').value,
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
      supplier_name:  document.getElementById('add-supplier_name').value,
      tax_id:         document.getElementById('add-tax_id').value,
      contact_name:   document.getElementById('add-contact_name').value,
      email:          document.getElementById('add-email').value,
      phone:          document.getElementById('add-phone').value,
      address_line_1: document.getElementById('add-address_line_1').value,
      address_line_2: document.getElementById('add-address_line_2').value,
      district:       document.getElementById('add-district').value,
      country:        document.getElementById('add-country').value,
      notes:          document.getElementById('add-notes').value,
      is_active:      document.getElementById('add-is_active').value,
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
});
</script>
<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
