<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.cost_centers.manage');
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
              <div class="fw-bold" style="font-size:1.05rem;line-height:1.2;">Cost Centers</div>
            </div>
            <input type="text" id="search-input" class="form-control form-control-sm" style="max-width:200px;" placeholder="Search...">
            <div id="stats-area" class="d-flex gap-2">
              <span class="badge bg-success-lt">Active: 0</span>
              <span class="badge bg-secondary-lt">Inactive: 0</span>
            </div>
            <a href="<?= url('views/cashiering/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Cashiering</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-add">Add Cost Center</button>
          </div>
        </div>
      </div>

      <div class="card">
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

<script>
const LIST_URL     = "<?= url('api/master-data/cost-centers/list.php') ?>";
const CREATE_URL   = "<?= url('api/master-data/cost-centers/create.php') ?>";
const DEPT_URL     = "<?= url('api/master-data/departments/list.php') ?>";
const ST_URL       = "<?= url('api/master-data/sub-treasuries/list.php') ?>";
const DETAILS_PAGE = "<?= url('views/cashiering/master-data/cost-centers/details.php') ?>";

let allRows = [], allDepartments = [], allSubTreasuries = [];

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

async function loadReferenceData() {
  try {
    var results = await Promise.all([apiGet(DEPT_URL), apiGet(ST_URL)]);
    allDepartments   = results[0].data || [];
    allSubTreasuries = results[1].data || [];
    populateDeptSelect('add');
  } catch(e) {}
}

function populateDeptSelect(prefix, selectedId) {
  var sel = document.getElementById(prefix + '-department_id');
  sel.innerHTML = '<option value="">— Select Department —</option>';
  allDepartments.forEach(function(d) {
    var opt = document.createElement('option');
    opt.value = d.id;
    opt.textContent = d.name;
    if (selectedId && String(d.id) === String(selectedId)) opt.selected = true;
    sel.appendChild(opt);
  });
}

function populateSubTreasurySelect(prefix, deptId, selectedId) {
  var sel = document.getElementById(prefix + '-sub_treasury_id');
  var filtered = deptId
    ? allSubTreasuries.filter(function(st) { return String(st.department_id) === String(deptId); })
    : [];
  sel.innerHTML = '<option value="">— None —</option>';
  if (!filtered.length && deptId) {
    var opt = document.createElement('option');
    opt.value = ''; opt.disabled = true;
    opt.textContent = 'No sub-treasuries for this department';
    sel.appendChild(opt);
  }
  filtered.forEach(function(st) {
    var opt = document.createElement('option');
    opt.value = st.id;
    opt.textContent = st.sub_treasury_name;
    if (selectedId && String(st.id) === String(selectedId)) opt.selected = true;
    sel.appendChild(opt);
  });
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
  var n = parseInt(r.bank_count) || 0;
  var bankCell = n > 0
    ? '<span class="badge bg-primary-lt text-primary">' + n + '</span>'
    : '<span class="text-muted">—</span>';
  return '<tr>' +
    '<td>' + (r.code || '') + '</td>' +
    '<td>' + (r.name || '') + '</td>' +
    '<td>' + (r.department_name || '') + '</td>' +
    '<td>' + (r.sub_treasury_name || '') + '</td>' +
    '<td class="text-center">' + bankCell + '</td>' +
    '<td>' + statusBadge(r.status) + '</td>' +
    '<td><a href="' + DETAILS_PAGE + '?id=' + r.id + '" class="btn btn-sm btn-outline-secondary">Open &#8594;</a></td>' +
    '</tr>';
}

document.getElementById('add-department_id').addEventListener('change', function() {
  populateSubTreasurySelect('add', this.value);
});

document.getElementById('add-save-btn').addEventListener('click', async function() {
  clearMsg('add-message');
  var btn = document.getElementById('add-save-btn');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    await apiPost(CREATE_URL, {
      name:            document.getElementById('add-name').value,
      code:            document.getElementById('add-code').value,
      department_id:   document.getElementById('add-department_id').value,
      sub_treasury_id: document.getElementById('add-sub_treasury_id').value,
      description:     document.getElementById('add-description').value,
      status:          document.getElementById('add-status').value,
    });
    tabler.Modal.getInstance(document.getElementById('modal-add')).hide();
    loadRows();
  } catch(e) {
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
  document.getElementById('modal-add').querySelectorAll('input,textarea').forEach(function(el) { el.value = ''; });
  document.getElementById('add-status').value = 'active';
  document.getElementById('add-department_id').value = '';
  document.getElementById('add-sub_treasury_id').innerHTML = '<option value="">— Select Department first —</option>';
});

document.addEventListener('DOMContentLoaded', async function() {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 7, renderRow: renderRow });
  await loadReferenceData();
  loadRows();
});
</script>
<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
