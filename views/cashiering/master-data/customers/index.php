<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.customers.manage');
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
              <div class="fw-bold" style="font-size:1.05rem;line-height:1.2;">Customers</div>
            </div>
            <input type="text" id="search-input" class="form-control form-control-sm" style="max-width:200px;" placeholder="Search...">
            <div id="stats-area" class="d-flex gap-2">
              <span class="badge bg-success-lt">Active: 0</span>
              <span class="badge bg-secondary-lt">Inactive: 0</span>
            </div>
            <a href="<?= url('views/cashiering/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Cashiering</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-add">Add Customer</button>
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

<script>
const LIST_URL     = "<?= url('api/master-data/customers/list.php') ?>";
const CREATE_URL   = "<?= url('api/master-data/customers/create.php') ?>";
const DETAILS_PAGE = "<?= url('views/cashiering/master-data/customers/details.php') ?>";

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

function applyTypeToggle(prefix, type) {
  var isOrg = type === 'organization';
  document.getElementById(prefix + '-individual-fields').style.display = isOrg ? 'none' : '';
  document.getElementById(prefix + '-org-fields').style.display        = isOrg ? '' : 'none';
}

document.getElementById('add-customer_type').addEventListener('change', function() {
  applyTypeToggle('add', this.value);
});

async function loadRows() {
  try {
    var res = await apiGet(LIST_URL);
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
  var fullName = ((r.first_name || '') + ' ' + (r.last_name || '')).trim();
  var nameCell = (r.customer_type === 'organization' && r.organization_name)
    ? fullName + ' <span class="text-muted">(' + r.organization_name + ')</span>'
    : fullName;
  return '<tr>' +
    '<td>' + nameCell + '</td>' +
    '<td style="text-transform:capitalize">' + (r.customer_type || '') + '</td>' +
    '<td>' + (r.tax_id || '') + '</td>' +
    '<td>' + (r.email || '') + '</td>' +
    '<td>' + (r.phone || '') + '</td>' +
    '<td>' + statusBadge(r.status) + '</td>' +
    '<td><a href="' + DETAILS_PAGE + '?id=' + r.id + '" class="btn btn-sm btn-outline-secondary">Open &#8594;</a></td>' +
    '</tr>';
}

document.getElementById('search-input').addEventListener('input', function() {
  var q = this.value.toLowerCase();
  if (!q) { window.pager.setData(allRows); return; }
  window.pager.setData(allRows.filter(function(r) {
    return JSON.stringify(r).toLowerCase().indexOf(q) !== -1;
  }));
});

document.getElementById('add-save-btn').addEventListener('click', async function() {
  clearMsg('add-message');
  var btn = document.getElementById('add-save-btn');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    await apiPost(CREATE_URL, {
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
  document.getElementById('modal-add').querySelectorAll('input,select,textarea').forEach(function(el) {
    el.value = el.tagName === 'SELECT' ? (el.options[0] ? el.options[0].value : '') : '';
  });
  applyTypeToggle('add', 'individual');
});

document.addEventListener('DOMContentLoaded', function() {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 7, renderRow: renderRow });
  loadRows();
});
</script>
<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
