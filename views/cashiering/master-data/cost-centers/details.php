<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.cost_centers.manage');

$id = (int)($_GET['id'] ?? 0);

require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-body">
    <div class="container-xl">

      <?php if ($id <= 0): ?>
        <div class="alert alert-danger">Invalid cost center ID.</div>
      <?php else: ?>

        <!-- Page identity card -->
        <div class="card mb-3" style="border-left: 4px solid var(--tblr-primary);">
          <div class="card-body py-3">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.68rem;letter-spacing:.1em;">Cashiering &middot; Master Data &middot; Cost Centers</div>
                <div class="fw-bold" id="page-subtitle" style="font-size:1.05rem;line-height:1.2;">Loading...</div>
              </div>
              <a href="<?= url('views/cashiering/master-data/cost-centers/index.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Back to Cost Centers</a>
            </div>
          </div>
        </div>

        <div id="page-message" class="alert mb-3" style="display:none;"></div>

        <!-- Cost center info -->
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title" id="record-heading">Loading...</h3>
            <div class="card-options">
              <button class="btn btn-sm btn-primary" id="edit-toggle-btn">Edit</button>
            </div>
          </div>
          <div class="card-body">
            <div id="edit-message" class="alert mb-3" style="display:none;"></div>

            <!-- View mode -->
            <dl class="row mb-0" id="view-mode">
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Code</dt>
              <dd class="col-sm-9 mb-2" id="v-code">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Name</dt>
              <dd class="col-sm-9 mb-2" id="v-name">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Department</dt>
              <dd class="col-sm-9 mb-2" id="v-department">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Sub-Treasury</dt>
              <dd class="col-sm-9 mb-2" id="v-sub_treasury">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Description</dt>
              <dd class="col-sm-9 mb-2" id="v-description">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Status</dt>
              <dd class="col-sm-9 mb-0" id="v-status">—</dd>
            </dl>

            <!-- Edit mode -->
            <div id="edit-mode" style="display:none;">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Code <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="edit-code" placeholder="e.g. CC-001">
                </div>
                <div class="col-md-8">
                  <label class="form-label">Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="edit-name" placeholder="Cost center name">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Department</label>
                  <select class="form-select" id="edit-department_id">
                    <option value="">— Select Department —</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Sub-Treasury</label>
                  <select class="form-select" id="edit-sub_treasury_id">
                    <option value="">— Select Sub-Treasury —</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Status</label>
                  <select class="form-select" id="edit-status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label">Description</label>
                  <textarea class="form-control" id="edit-description" rows="2" placeholder="Optional description"></textarea>
                </div>
              </div>
              <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary" id="save-btn">Save Changes</button>
                <button class="btn btn-outline-secondary" id="cancel-btn">Cancel</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Bank Accounts -->
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title">Bank Accounts</h3>
          </div>
          <div class="card-body">
            <div id="bank-message" class="alert mb-3" style="display:none;"></div>
            <div class="row g-2 align-items-end mb-3">
              <div class="col-md-8">
                <label class="form-label form-label-sm">Bank Account</label>
                <select class="form-select form-select-sm" id="bank-select">
                  <option value="">Select bank account...</option>
                </select>
              </div>
              <div class="col-md-4">
                <button class="btn btn-primary btn-sm w-100" id="assign-bank-btn">Assign</button>
              </div>
            </div>
            <table class="table table-sm table-vcenter table-hover card-table" style="font-size:.85rem;">
              <thead>
                <tr>
                  <th>Bank</th>
                  <th>Account Name</th>
                  <th>Account Number</th>
                  <th>Currency</th>
                  <th>Type</th>
                  <th class="w-1"></th>
                </tr>
              </thead>
              <tbody id="bank-tbody">
                <tr><td colspan="6" class="text-muted text-center py-3">Loading...</td></tr>
              </tbody>
            </table>
          </div>
        </div>

      <?php endif; ?>
    </div>
  </div>

<script>
var RECORD_ID        = <?= $id ?>;
var SHOW_URL         = "<?= url('api/master-data/cost-centers/show.php') ?>";
var UPDATE_URL       = "<?= url('api/master-data/cost-centers/update.php') ?>";
var BANKS_URL        = "<?= url('api/master-data/cost-centers/banks.php') ?>";
var BA_LIST_URL      = "<?= url('api/master-data/bank-accounts/list.php') ?>";
var ASSIGN_BANK_URL  = "<?= url('api/master-data/cost-centers/assign-bank.php') ?>";
var REMOVE_BANK_URL  = "<?= url('api/master-data/cost-centers/remove-bank.php') ?>";
var DEPT_URL         = "<?= url('api/master-data/departments/list.php') ?>";
var ST_URL           = "<?= url('api/master-data/sub-treasuries/list.php') ?>";

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
  return val === 'active'
    ? '<span class="badge bg-success-lt text-success">Active</span>'
    : '<span class="badge bg-secondary-lt text-secondary">Inactive</span>';
}

var recordData = null;
var allSubTreasuries = [];

async function loadRecord() {
  try {
    var res = await apiGet(SHOW_URL + '?id=' + RECORD_ID);
    var r = res.data;
    recordData = r;
    document.getElementById('record-heading').textContent = r.name || 'Cost Center';
    document.getElementById('page-subtitle').textContent  = r.code ? r.code + ' — ' + r.name : (r.name || 'Cost Center Details');
    document.getElementById('v-code').textContent         = r.code || '—';
    document.getElementById('v-name').textContent         = r.name || '—';
    document.getElementById('v-department').textContent   = r.department_name || '—';
    document.getElementById('v-sub_treasury').textContent = r.sub_treasury_name || '—';
    document.getElementById('v-description').textContent  = r.description || '—';
    document.getElementById('v-status').innerHTML         = statusBadge(r.status);
  } catch (e) {
    showMsg('page-message', e.message);
  }
}

async function loadBankAccounts() {
  try {
    var res = await apiGet(BANKS_URL + '?cost_center_id=' + RECORD_ID);
    var banks = res.data || [];
    var tbody = document.getElementById('bank-tbody');
    if (!banks.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-muted text-center py-3">No bank accounts assigned.</td></tr>';
      return;
    }
    tbody.innerHTML = banks.map(function(b) {
      return '<tr>' +
        '<td>' + (b.bank_name || '—') + '</td>' +
        '<td>' + (b.account_name || '—') + '</td>' +
        '<td style="font-family:monospace;">' + (b.account_masked || '—') + '</td>' +
        '<td>' + (b.currency_code || '—') + '</td>' +
        '<td>' + (b.account_type_name || '—') + '</td>' +
        '<td><button class="btn btn-sm btn-ghost-danger" data-remove-bank="' + b.assignment_id + '" title="Remove">' +
          '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>' +
        '</button></td>' +
      '</tr>';
    }).join('');
    tbody.querySelectorAll('[data-remove-bank]').forEach(function(btn) {
      btn.addEventListener('click', async function() {
        if (!confirm('Remove this bank account from the cost center?')) return;
        try {
          await apiPost(REMOVE_BANK_URL, { id: btn.dataset.removeBank });
          await loadBankAccounts();
        } catch (e) { showMsg('bank-message', e.message); }
      });
    });
  } catch (e) {
    showMsg('bank-message', e.message);
  }
}

async function loadDepts(selectedId) {
  try {
    var res = await apiGet(DEPT_URL);
    var sel = document.getElementById('edit-department_id');
    sel.innerHTML = '<option value="">— Select Department —</option>';
    (res.data || []).forEach(function(d) {
      var opt = document.createElement('option');
      opt.value = d.id;
      opt.textContent = d.name;
      if (selectedId && String(d.id) === String(selectedId)) opt.selected = true;
      sel.appendChild(opt);
    });
  } catch(e) {}
}

async function loadSubTreasuries(deptId, selectedId) {
  var sel = document.getElementById('edit-sub_treasury_id');
  sel.innerHTML = '<option value="">— Select Sub-Treasury —</option>';
  if (!deptId) return;
  try {
    var res = await apiGet(ST_URL + '?department_id=' + deptId);
    (res.data || []).forEach(function(s) {
      var opt = document.createElement('option');
      opt.value = s.id;
      opt.textContent = s.sub_treasury_name;
      if (selectedId && String(s.id) === String(selectedId)) opt.selected = true;
      sel.appendChild(opt);
    });
  } catch(e) {}
}

var editToggleBtn = document.getElementById('edit-toggle-btn');
var viewMode      = document.getElementById('view-mode');
var editMode      = document.getElementById('edit-mode');

function enterEditMode() {
  if (recordData) {
    document.getElementById('edit-code').value        = recordData.code || '';
    document.getElementById('edit-name').value        = recordData.name || '';
    document.getElementById('edit-status').value      = recordData.status || 'active';
    document.getElementById('edit-description').value = recordData.description || '';
    loadDepts(recordData.department_id).then(function() {
      if (recordData.department_id) {
        loadSubTreasuries(recordData.department_id, recordData.sub_treasury_id);
      }
    });
  }
  viewMode.style.display = 'none';
  editMode.style.display = '';
  editToggleBtn.textContent = 'Cancel';
  editToggleBtn.className = 'btn btn-sm btn-outline-secondary';
}

function exitEditMode() {
  editMode.style.display = 'none';
  viewMode.style.display = '';
  editToggleBtn.textContent = 'Edit';
  editToggleBtn.className = 'btn btn-sm btn-primary';
  clearMsg('edit-message');
}

document.getElementById('edit-department_id').addEventListener('change', function() {
  loadSubTreasuries(this.value, null);
});

editToggleBtn.addEventListener('click', function() {
  editMode.style.display !== 'none' ? exitEditMode() : enterEditMode();
});
document.getElementById('cancel-btn').addEventListener('click', exitEditMode);

document.getElementById('save-btn').addEventListener('click', async function() {
  clearMsg('edit-message');
  var btn = document.getElementById('save-btn');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    await apiPost(UPDATE_URL, {
      id:              RECORD_ID,
      code:            document.getElementById('edit-code').value,
      name:            document.getElementById('edit-name').value,
      department_id:   document.getElementById('edit-department_id').value,
      sub_treasury_id: document.getElementById('edit-sub_treasury_id').value,
      status:          document.getElementById('edit-status').value,
      description:     document.getElementById('edit-description').value,
    });
    exitEditMode();
    await loadRecord();
    showMsg('page-message', 'Cost center updated successfully.', 'success');
    setTimeout(function() { clearMsg('page-message'); }, 3000);
  } catch (e) {
    showMsg('edit-message', e.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Save Changes';
  }
});

document.getElementById('assign-bank-btn').addEventListener('click', async function() {
  var bankId = document.getElementById('bank-select').value;
  if (!bankId) { showMsg('bank-message', 'Please select a bank account.', 'warning'); return; }
  clearMsg('bank-message');
  var btn = document.getElementById('assign-bank-btn');
  btn.disabled = true; btn.textContent = 'Assigning...';
  try {
    await apiPost(ASSIGN_BANK_URL, { cost_center_id: RECORD_ID, bank_account_id: bankId });
    showMsg('bank-message', 'Bank account assigned.', 'success');
    await loadBankAccounts();
    setTimeout(function() { clearMsg('bank-message'); }, 2500);
  } catch (e) {
    showMsg('bank-message', e.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Assign';
  }
});

document.addEventListener('DOMContentLoaded', async function() {
  if (!RECORD_ID) return;
  try {
    var baRes = await apiGet(BA_LIST_URL + '?status=active');
    var bankSelect = document.getElementById('bank-select');
    bankSelect.innerHTML = '<option value="">Select bank account...</option>';
    (baRes.data || []).forEach(function(b) {
      var opt = document.createElement('option');
      opt.value = b.id;
      opt.textContent = b.bank_name + ' — ' + b.account_name + ' (' + b.account_number + ')';
      bankSelect.appendChild(opt);
    });
    await loadRecord();
    await loadBankAccounts();
  } catch (e) {
    showMsg('page-message', e.message);
  }
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
