<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.bank_accounts.manage');
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
              <div class="fw-bold" style="font-size:1.05rem;line-height:1.2;">Bank Accounts</div>
            </div>
            <input type="text" id="search-input" class="form-control form-control-sm" style="max-width:200px;" placeholder="Search...">
            <div id="stats-area" class="d-flex gap-2">
              <span class="badge bg-success-lt">Active: 0</span>
              <span class="badge bg-secondary-lt">Inactive: 0</span>
            </div>
            <a href="<?= url('views/cashiering/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Cashiering</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-add">Add Bank Account</button>
          </div>
        </div>
      </div>

      <div class="card">
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

<script>
const LIST_URL     = "<?= url('api/master-data/bank-accounts/list.php') ?>";
const CREATE_URL   = "<?= url('api/master-data/bank-accounts/create.php') ?>";
const TYPES_URL    = "<?= url('api/master-data/bank-accounts/types.php') ?>";
const DETAILS_PAGE = "<?= url('views/cashiering/master-data/bank-accounts/details.php') ?>";

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

async function loadAccountTypes() {
  try {
    var res  = await apiGet(TYPES_URL);
    var types = res.data || [];
    var opts = '<option value="">— Select Type —</option>' +
      types.map(function(t) { return '<option value="' + t.id + '">' + t.name + '</option>'; }).join('');
    document.getElementById('add-account_type_id').innerHTML = opts;
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
  var typeBadge = r.account_type_name
    ? '<span class="badge bg-azure-lt text-azure">' + r.account_type_name + '</span>'
    : '<span class="text-muted">—</span>';
  return '<tr>' +
    '<td>' + (r.bank_name || '') + '</td>' +
    '<td>' + (r.account_name || '') + '</td>' +
    '<td class="text-muted" style="font-family:monospace;font-size:.85rem;">' + (r.account_masked || r.account_number || '') + '</td>' +
    '<td class="text-muted" style="font-size:.85rem;">' + (r.sof_number || '—') + '</td>' +
    '<td>' + (r.currency_code || '') + '</td>' +
    '<td>' + typeBadge + '</td>' +
    '<td>' + statusBadge(r.status) + '</td>' +
    '<td><a href="' + DETAILS_PAGE + '?id=' + r.id + '" class="btn btn-sm btn-outline-secondary">Open &#8594;</a></td>' +
    '</tr>';
}

document.getElementById('add-save-btn').addEventListener('click', async function() {
  clearMsg('add-message');
  var btn = document.getElementById('add-save-btn');
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
  document.getElementById('modal-add').querySelectorAll('input').forEach(function(el) { el.value = ''; });
  document.getElementById('add-currency_code').value   = 'BZD';
  document.getElementById('add-account_type_id').value = '';
  document.getElementById('add-status').value          = 'active';
});

document.addEventListener('DOMContentLoaded', function() {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 8, renderRow: renderRow });
  loadAccountTypes();
  loadRows();
});
</script>
<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
