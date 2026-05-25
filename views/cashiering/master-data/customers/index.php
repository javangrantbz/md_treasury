<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.customers.manage');
require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row align-items-center">
        <div class="col">
          <div class="page-pretitle">Cashiering — Master Data</div>
          <h2 class="page-title">Customers</h2>
        </div>
        <div class="col-auto ms-auto d-flex gap-2">
          <a href="<?= url('views/cashiering/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">← Cashiering</a>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-add">
            Add Customer
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
                <th data-col="customer_type">Type</th>
                <th data-col="tax_id">TIN</th>
                <th data-col="email">Email</th>
                <th data-col="phone">Phone</th>
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
        <h5 class="modal-title">Add Customer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="add-message" class="alert" style="display:none"></div>
        <div class="mb-3">
          <label class="form-label">Customer Type</label>
          <select class="form-select" id="add-customer_type">
            <option value="individual">Individual</option>
            <option value="organization">Organization</option>
          </select>
        </div>
        <div id="add-individual-fields">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">First Name</label>
              <input type="text" class="form-control" id="add-first_name" placeholder="First name">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Last Name</label>
              <input type="text" class="form-control" id="add-last_name" placeholder="Last name">
            </div>
          </div>
        </div>
        <div id="add-org-fields" style="display:none">
          <div class="mb-3">
            <label class="form-label">Organization Name</label>
            <input type="text" class="form-control" id="add-organization_name" placeholder="Organization name">
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">TIN</label>
            <input type="text" class="form-control" id="add-tax_id" placeholder="Tax ID / TIN">
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
        <h5 class="modal-title">Customer Details</h5>
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
        <h5 class="modal-title">Edit Customer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="edit-message" class="alert" style="display:none"></div>
        <input type="hidden" id="edit-id">
        <div class="mb-3">
          <label class="form-label">Customer Type</label>
          <select class="form-select" id="edit-customer_type">
            <option value="individual">Individual</option>
            <option value="organization">Organization</option>
          </select>
        </div>
        <div id="edit-individual-fields">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">First Name</label>
              <input type="text" class="form-control" id="edit-first_name" placeholder="First name">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Last Name</label>
              <input type="text" class="form-control" id="edit-last_name" placeholder="Last name">
            </div>
          </div>
        </div>
        <div id="edit-org-fields" style="display:none">
          <div class="mb-3">
            <label class="form-label">Organization Name</label>
            <input type="text" class="form-control" id="edit-organization_name" placeholder="Organization name">
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">TIN</label>
            <input type="text" class="form-control" id="edit-tax_id" placeholder="Tax ID / TIN">
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
const LIST_URL   = "<?= url('api/master-data/customers/list.php') ?>";
const SHOW_URL   = "<?= url('api/master-data/customers/show.php') ?>";
const CREATE_URL = "<?= url('api/master-data/customers/create.php') ?>";
const UPDATE_URL = "<?= url('api/master-data/customers/update.php') ?>";

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

// Toggle individual/org fields
function applyTypeToggle(prefix, type) {
  const isOrg = type === 'organization';
  document.getElementById(prefix + '-individual-fields').style.display = isOrg ? 'none' : '';
  document.getElementById(prefix + '-org-fields').style.display        = isOrg ? '' : 'none';
}

document.getElementById('add-customer_type').addEventListener('change', function () {
  applyTypeToggle('add', this.value);
});
document.getElementById('edit-customer_type').addEventListener('change', function () {
  applyTypeToggle('edit', this.value);
});

async function loadRows() {
  try {
    const res = await apiGet(LIST_URL);
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
  const fullName = `${r.first_name ?? ''} ${r.last_name ?? ''}`.trim();
  const orgName  = r.organization_name ?? '';
  const nameCell = r.customer_type === 'organization' && orgName
    ? `${fullName} <span class="text-muted">(${orgName})</span>`
    : fullName;
  return `<tr>
    <td>${nameCell}</td>
    <td style="text-transform:capitalize">${r.customer_type ?? ''}</td>
    <td>${r.tax_id ?? ''}</td>
    <td>${r.email ?? ''}</td>
    <td>${r.phone ?? ''}</td>
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

// Client-side search
document.getElementById('search-input').addEventListener('input', function () {
  const q = this.value.toLowerCase();
  if (!q) {
    window.pager.setData(allRows);
    return;
  }
  window.pager.setData(allRows.filter(r => JSON.stringify(r).toLowerCase().includes(q)));
});

// View
async function openView(id) {
  document.getElementById('view-body').innerHTML = '<dd class="col-12 text-muted">Loading...</dd>';
  document.getElementById('view-edit-btn').onclick = () => { tabler.Modal.getInstance(document.getElementById('modal-view'))?.hide(); openEdit(id); };
  tabler.Modal.getOrCreateInstance(document.getElementById('modal-view')).show();
  try {
    const res = await apiGet(SHOW_URL + '?id=' + id);
    const r = res.data;
    const fullName = ((r.first_name ?? '') + ' ' + (r.last_name ?? '')).trim();
    const nameDisplay = r.customer_type === 'organization' && r.organization_name
      ? `${fullName} (${r.organization_name})`
      : fullName || r.organization_name || '—';
    const addr = [r.address_line_1, r.address_line_2].filter(Boolean).join(', ') || '—';
    document.getElementById('view-body').innerHTML =
      `<dt class="col-sm-4">Type</dt><dd class="col-sm-8" style="text-transform:capitalize">${r.customer_type ?? '—'}</dd>` +
      `<dt class="col-sm-4">Name</dt><dd class="col-sm-8">${nameDisplay}</dd>` +
      `<dt class="col-sm-4">TIN</dt><dd class="col-sm-8">${r.tax_id ?? '—'}</dd>` +
      `<dt class="col-sm-4">Email</dt><dd class="col-sm-8">${r.email ?? '—'}</dd>` +
      `<dt class="col-sm-4">Phone</dt><dd class="col-sm-8">${r.phone ?? '—'}</dd>` +
      `<dt class="col-sm-4">Address</dt><dd class="col-sm-8">${addr}</dd>` +
      `<dt class="col-sm-4">District</dt><dd class="col-sm-8">${r.district ?? '—'}</dd>` +
      `<dt class="col-sm-4">Country</dt><dd class="col-sm-8">${r.country ?? '—'}</dd>` +
      `<dt class="col-sm-4">Notes</dt><dd class="col-sm-8">${r.notes ?? '—'}</dd>` +
      `<dt class="col-sm-4">Status</dt><dd class="col-sm-8">${statusBadge(r.status)}</dd>`;
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
    const type = r.customer_type ?? 'individual';
    document.getElementById('edit-customer_type').value    = type;
    applyTypeToggle('edit', type);
    document.getElementById('edit-first_name').value       = r.first_name ?? '';
    document.getElementById('edit-last_name').value        = r.last_name ?? '';
    document.getElementById('edit-organization_name').value = r.organization_name ?? '';
    document.getElementById('edit-tax_id').value           = r.tax_id ?? '';
    document.getElementById('edit-email').value            = r.email ?? '';
    document.getElementById('edit-phone').value            = r.phone ?? '';
    document.getElementById('edit-address_line_1').value   = r.address_line_1 ?? '';
    document.getElementById('edit-address_line_2').value   = r.address_line_2 ?? '';
    document.getElementById('edit-district').value         = r.district ?? '';
    document.getElementById('edit-country').value          = r.country ?? '';
    document.getElementById('edit-notes').value            = r.notes ?? '';
    document.getElementById('edit-status').value           = r.status ?? 'active';
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
      id:                document.getElementById('edit-id').value,
      customer_type:     document.getElementById('edit-customer_type').value,
      first_name:        document.getElementById('edit-first_name').value,
      last_name:         document.getElementById('edit-last_name').value,
      organization_name: document.getElementById('edit-organization_name').value,
      tax_id:            document.getElementById('edit-tax_id').value,
      email:             document.getElementById('edit-email').value,
      phone:             document.getElementById('edit-phone').value,
      address_line_1:    document.getElementById('edit-address_line_1').value,
      address_line_2:    document.getElementById('edit-address_line_2').value,
      district:          document.getElementById('edit-district').value,
      country:           document.getElementById('edit-country').value,
      notes:             document.getElementById('edit-notes').value,
      status:            document.getElementById('edit-status').value,
    };
    await apiPost(UPDATE_URL, payload);
    tabler.Modal.getInstance(document.getElementById('modal-edit'))?.hide();
    loadRows();
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
      customer_type:     document.getElementById('add-customer_type').value,
      first_name:        document.getElementById('add-first_name').value,
      last_name:         document.getElementById('add-last_name').value,
      organization_name: document.getElementById('add-organization_name').value,
      tax_id:            document.getElementById('add-tax_id').value,
      email:             document.getElementById('add-email').value,
      phone:             document.getElementById('add-phone').value,
      address_line_1:    document.getElementById('add-address_line_1').value,
      address_line_2:    document.getElementById('add-address_line_2').value,
      district:          document.getElementById('add-district').value,
      country:           document.getElementById('add-country').value,
      notes:             document.getElementById('add-notes').value,
      status:            document.getElementById('add-status').value,
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

document.getElementById('modal-add').addEventListener('hidden.bs.modal', () => {
  clearMsg('add-message');
  document.getElementById('modal-add').querySelectorAll('input,select,textarea').forEach(el => {
    el.value = el.tagName === 'SELECT' ? (el.options[0]?.value ?? '') : '';
  });
  applyTypeToggle('add', 'individual');
});

document.addEventListener('DOMContentLoaded', () => {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 7, renderRow });
  loadRows();
});
</script>
<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
includes/layout-tabler-footer.php'; ?>
