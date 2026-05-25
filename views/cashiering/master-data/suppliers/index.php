<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.suppliers.manage');
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
              <div class="fw-bold" style="font-size:1.05rem;line-height:1.2;">Suppliers</div>
            </div>
            <input type="text" id="search-input" class="form-control form-control-sm" style="max-width:200px;" placeholder="Search...">
            <div id="stats-area" class="d-flex gap-2">
              <span class="badge bg-success-lt">Active: 0</span>
              <span class="badge bg-secondary-lt">Inactive: 0</span>
            </div>
            <a href="<?= url('views/cashiering/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Cashiering</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-add">Add Supplier</button>
          </div>
        </div>
      </div>

      <div class="card">
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

<script>
const LIST_URL     = "<?= url('api/master-data/suppliers/list.php') ?>";
const CREATE_URL   = "<?= url('api/master-data/suppliers/create.php') ?>";
const DETAILS_PAGE = "<?= url('views/cashiering/master-data/suppliers/details.php') ?>";

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
function isActiveBadge(val) {
  return Number(val) === 1
    ? '<span class="badge bg-success-lt text-success">Active</span>'
    : '<span class="badge bg-secondary-lt text-secondary">Inactive</span>';
}

async function loadRows(search) {
  search = search || '';
  var url = LIST_URL + (search ? '?search=' + encodeURIComponent(search) : '');
  try {
    var res = await apiGet(url);
    allRows = res.data || [];
    window.pager.setData(allRows);

    var active   = allRows.filter(function(r) { return Number(r.is_active) === 1; }).length;
    var inactive = allRows.filter(function(r) { return Number(r.is_active) === 0; }).length;
    document.getElementById('stats-area').innerHTML =
      '<span class="badge bg-success-lt">Active: ' + active + '</span>' +
      '<span class="badge bg-secondary-lt">Inactive: ' + inactive + '</span>';
  } catch (e) {
    showMsg('table-message', e.message);
  }
}

function renderRow(r) {
  return '<tr>' +
    '<td>' + (r.supplier_name || '') + '</td>' +
    '<td>' + (r.tax_id || '') + '</td>' +
    '<td>' + (r.contact_name || '') + '</td>' +
    '<td>' + (r.email || '') + '</td>' +
    '<td>' + (r.phone || '') + '</td>' +
    '<td>' + isActiveBadge(r.is_active) + '</td>' +
    '<td><a href="' + DETAILS_PAGE + '?id=' + r.id + '" class="btn btn-sm btn-outline-secondary">Open &#8594;</a></td>' +
    '</tr>';
}

document.getElementById('add-save-btn').addEventListener('click', async function() {
  clearMsg('add-message');
  var btn = document.getElementById('add-save-btn');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    await apiPost(CREATE_URL, {
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
    });
    tabler.Modal.getInstance(document.getElementById('modal-add')).hide();
    loadRows();
  } catch (e) {
    showMsg('add-message', e.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Save';
  }
});

document.getElementById('search-input').addEventListener('input', function() {
  loadRows(this.value.trim());
});

document.getElementById('modal-add').addEventListener('hidden.bs.modal', function() {
  clearMsg('add-message');
  document.getElementById('modal-add').querySelectorAll('input,select,textarea').forEach(function(el) {
    el.value = el.tagName === 'SELECT' ? (el.options[0] ? el.options[0].value : '') : '';
  });
});

document.addEventListener('DOMContentLoaded', function() {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 7, renderRow: renderRow });
  loadRows();
});
</script>
<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
