<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.registers.manage');
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
              <div class="fw-bold" style="font-size:1.05rem;line-height:1.2;">Registers</div>
            </div>
            <input type="text" id="search-input" class="form-control form-control-sm" style="max-width:200px;" placeholder="Search...">
            <div id="stats-area" class="d-flex gap-2">
              <span class="badge bg-success-lt">Active: 0</span>
              <span class="badge bg-secondary-lt">Inactive: 0</span>
            </div>
            <a href="<?= url('views/cashiering/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Cashiering</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-add">Add Register</button>
          </div>
        </div>
      </div>

      <div class="card">
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
        <label class="form-check d-flex align-items-center gap-1 me-2 mb-0">
          <input class="form-check-input m-0" type="checkbox" id="add-active-check" checked>
          <span class="form-check-label" style="font-size:.8125rem;">Active</span>
        </label>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="add-message" class="alert" style="display:none"></div>

        <div class="text-uppercase fw-semibold text-muted mb-2" style="font-size:.63rem;letter-spacing:.08em;">Register</div>
        <div class="row g-2">
          <div class="col-12">
            <label class="form-label">Register Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="add-register_name" placeholder="Register name">
          </div>
        </div>

        <hr style="margin:.65rem 0;">

        <div class="text-uppercase fw-semibold text-muted mb-2" style="font-size:.63rem;letter-spacing:.08em;">Location</div>
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">Department <span class="text-danger">*</span></label>
            <select class="form-select" id="add-department_id">
              <option value="">— Select Department —</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Sub-Treasury</label>
            <select class="form-select" id="add-sub_treasury_id" disabled>
              <option value="">— Select Department first —</option>
            </select>
          </div>
        </div>

        <hr style="margin:.65rem 0;">

        <div class="text-uppercase fw-semibold text-muted mb-2" style="font-size:.63rem;letter-spacing:.08em;">Assignment</div>
        <div class="row g-2">
          <div class="col-12">
            <label class="form-label">Assigned User</label>
            <select class="form-select" id="add-assigned_user_id">
              <option value="">— None —</option>
            </select>
          </div>
        </div>

        <hr style="margin:.65rem 0;">

        <div class="text-uppercase fw-semibold text-muted mb-2" style="font-size:.63rem;letter-spacing:.08em;">Notes</div>
        <textarea class="form-control" id="add-description" rows="2" placeholder="Optional description"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-link link-secondary me-auto" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="add-save-btn">Save</button>
      </div>
    </div>
  </div>
</div>

<script>
const LIST_URL     = "<?= url('api/master-data/registers/list.php') ?>";
const CREATE_URL   = "<?= url('api/master-data/registers/create.php') ?>";
const DEPT_URL     = "<?= url('api/master-data/departments/list.php') ?>";
const ST_URL       = "<?= url('api/master-data/sub-treasuries/list.php') ?>";
const USERS_URL    = "<?= url('api/master-data/users/list.php') ?>";
const DETAILS_PAGE = "<?= url('views/cashiering/master-data/registers/details.php') ?>";

let allRows = [], allDepartments = [], allSubTreasuries = [], allUsers = [];

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

async function loadReferenceData() {
  var results = await Promise.all([
    apiGet(DEPT_URL + '?status=active'),
    apiGet(ST_URL),
    apiGet(USERS_URL + '?status=active'),
  ]);
  allDepartments   = results[0].data || [];
  allSubTreasuries = results[1].data || [];
  allUsers         = results[2].data || [];
  populateDeptSelects();
  populateUserSelects();
}

function populateDeptSelects() {
  var opts = '<option value="">— Select Department —</option>' +
    allDepartments.map(function(d) { return '<option value="' + d.id + '">' + d.name + '</option>'; }).join('');
  document.getElementById('add-department_id').innerHTML = opts;
}

function populateSubTreasurySelect(prefix, deptId, selectedId) {
  var sel = document.getElementById(prefix + '-sub_treasury_id');
  var filtered = allSubTreasuries.filter(function(st) { return String(st.department_id) === String(deptId); });
  if (!deptId || !filtered.length) {
    sel.innerHTML = '<option value="">' + (deptId ? '— No sub-treasuries for this department —' : '— Select Department first —') + '</option>';
    sel.disabled = !deptId;
    return;
  }
  sel.disabled = false;
  sel.innerHTML = '<option value="">— Select Sub-Treasury —</option>' +
    filtered.map(function(st) {
      return '<option value="' + st.id + '"' + (String(st.id) === String(selectedId) ? ' selected' : '') + '>' + st.sub_treasury_name + '</option>';
    }).join('');
}

function populateUserSelects() {
  var opts = '<option value="">— None —</option>' +
    allUsers.map(function(u) {
      return '<option value="' + u.id + '">' + u.first_name + ' ' + u.last_name + ' (' + u.username + ')</option>';
    }).join('');
  document.getElementById('add-assigned_user_id').innerHTML = opts;
}

document.getElementById('add-department_id').addEventListener('change', function() {
  populateSubTreasurySelect('add', this.value);
});

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
  var userName = r.assigned_user_name ? r.assigned_user_name.trim() : '<span class="text-muted">—</span>';
  return '<tr>' +
    '<td class="text-muted" style="font-size:.82rem;font-family:monospace;">' + (r.register_code || '') + '</td>' +
    '<td>' + (r.register_name || '') + '</td>' +
    '<td>' + (r.department_name || '') + '</td>' +
    '<td>' + (r.sub_treasury_name || '') + '</td>' +
    '<td>' + userName + '</td>' +
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
      register_name:    document.getElementById('add-register_name').value,
      department_id:    document.getElementById('add-department_id').value,
      sub_treasury_id:  document.getElementById('add-sub_treasury_id').value,
      assigned_user_id: document.getElementById('add-assigned_user_id').value,
      description:      document.getElementById('add-description').value,
      is_active:        document.getElementById('add-active-check').checked ? 1 : 0,
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
  document.getElementById('add-active-check').checked = true;
  document.getElementById('add-register_name').value = '';
  document.getElementById('add-description').value   = '';
  document.getElementById('add-department_id').value = '';
  document.getElementById('add-assigned_user_id').value = '';
  populateSubTreasurySelect('add', '');
});

document.addEventListener('DOMContentLoaded', async function() {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 7, renderRow: renderRow });
  await loadReferenceData();
  loadRows();
});
</script>
<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
