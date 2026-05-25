<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.bank_accounts.manage');

$id = (int)($_GET['id'] ?? 0);

require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-body">
    <div class="container-xl">

      <?php if ($id <= 0): ?>
        <div class="alert alert-danger">Invalid bank account ID.</div>
      <?php else: ?>

        <!-- Page identity card -->
        <div class="card mb-3" style="border-left: 4px solid var(--tblr-primary);">
          <div class="card-body py-3">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.68rem;letter-spacing:.1em;">Cashiering &middot; Master Data &middot; Bank Accounts</div>
                <div class="fw-bold" id="page-subtitle" style="font-size:1.05rem;line-height:1.2;">Loading...</div>
              </div>
              <a href="<?= url('views/cashiering/master-data/bank-accounts/index.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Back to Bank Accounts</a>
            </div>
          </div>
        </div>

        <div id="page-message" class="alert mb-3" style="display:none;"></div>

        <div class="card">
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
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Bank Name</dt>
              <dd class="col-sm-9 mb-2" id="v-bank_name">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Account Name</dt>
              <dd class="col-sm-9 mb-2" id="v-account_name">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Account Number</dt>
              <dd class="col-sm-9 mb-2" id="v-account_number">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Branch</dt>
              <dd class="col-sm-9 mb-2" id="v-branch_name">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Currency</dt>
              <dd class="col-sm-9 mb-2" id="v-currency_code">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Account Type</dt>
              <dd class="col-sm-9 mb-2" id="v-account_type">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Item Number</dt>
              <dd class="col-sm-9 mb-2" id="v-item_number">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">SOF Number</dt>
              <dd class="col-sm-9 mb-2" id="v-sof_number">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Status</dt>
              <dd class="col-sm-9 mb-0" id="v-status">—</dd>
            </dl>

            <!-- Edit mode -->
            <div id="edit-mode" style="display:none;">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Bank Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="edit-bank_name" placeholder="Bank name">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Account Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="edit-account_name" placeholder="Account name">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Account Number</label>
                  <input type="text" class="form-control" id="edit-account_number" placeholder="Account number">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Branch</label>
                  <input type="text" class="form-control" id="edit-branch_name" placeholder="Branch name">
                </div>
                <div class="col-md-4">
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
                <div class="col-md-4">
                  <label class="form-label">Status</label>
                  <select class="form-select" id="edit-status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Item Number</label>
                  <input type="text" class="form-control" id="edit-item_number" placeholder="Item number">
                </div>
                <div class="col-md-6">
                  <label class="form-label">SOF Number</label>
                  <input type="text" class="form-control" id="edit-sof_number" placeholder="SOF number">
                </div>
              </div>
              <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary" id="save-btn">Save Changes</button>
                <button class="btn btn-outline-secondary" id="cancel-btn">Cancel</button>
              </div>
            </div>
          </div>
        </div>

      <?php endif; ?>
    </div>
  </div>

<script>
var RECORD_ID  = <?= $id ?>;
var SHOW_URL   = "<?= url('api/master-data/bank-accounts/show.php') ?>";
var UPDATE_URL = "<?= url('api/master-data/bank-accounts/update.php') ?>";
var TYPES_URL  = "<?= url('api/master-data/bank-accounts/types.php') ?>";

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

async function loadRecord() {
  try {
    var res = await apiGet(SHOW_URL + '?id=' + RECORD_ID);
    var r = res.data;
    recordData = r;
    var heading = r.bank_name ? r.bank_name + ' — ' + r.account_name : 'Bank Account';
    document.getElementById('record-heading').textContent  = heading;
    document.getElementById('page-subtitle').textContent   = heading;
    document.getElementById('v-bank_name').textContent     = r.bank_name || '—';
    document.getElementById('v-account_name').textContent  = r.account_name || '—';
    document.getElementById('v-account_number').textContent = r.account_number || r.account_masked || '—';
    document.getElementById('v-branch_name').textContent   = r.branch_name || '—';
    document.getElementById('v-currency_code').textContent = r.currency_code || '—';
    document.getElementById('v-account_type').textContent  = r.account_type_name || '—';
    document.getElementById('v-item_number').textContent   = r.item_number || '—';
    document.getElementById('v-sof_number').textContent    = r.sof_number || '—';
    document.getElementById('v-status').innerHTML          = statusBadge(r.status);
  } catch (e) {
    showMsg('page-message', e.message);
  }
}

async function loadTypes(selectedId) {
  try {
    var res = await apiGet(TYPES_URL);
    var sel = document.getElementById('edit-account_type_id');
    sel.innerHTML = '<option value="">— Select Type —</option>';
    (res.data || []).forEach(function(t) {
      var opt = document.createElement('option');
      opt.value = t.id;
      opt.textContent = t.name;
      if (selectedId && String(t.id) === String(selectedId)) opt.selected = true;
      sel.appendChild(opt);
    });
  } catch(e) {}
}

var editToggleBtn = document.getElementById('edit-toggle-btn');
var viewMode      = document.getElementById('view-mode');
var editMode      = document.getElementById('edit-mode');

function enterEditMode() {
  if (recordData) {
    document.getElementById('edit-bank_name').value    = recordData.bank_name || '';
    document.getElementById('edit-account_name').value = recordData.account_name || '';
    document.getElementById('edit-account_number').value = recordData.account_number || '';
    document.getElementById('edit-branch_name').value  = recordData.branch_name || '';
    document.getElementById('edit-currency_code').value = recordData.currency_code || 'BZD';
    document.getElementById('edit-item_number').value  = recordData.item_number || '';
    document.getElementById('edit-sof_number').value   = recordData.sof_number || '';
    document.getElementById('edit-status').value       = recordData.status || 'active';
    loadTypes(recordData.account_type_id);
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
      bank_name:       document.getElementById('edit-bank_name').value,
      account_name:    document.getElementById('edit-account_name').value,
      account_number:  document.getElementById('edit-account_number').value,
      branch_name:     document.getElementById('edit-branch_name').value,
      currency_code:   document.getElementById('edit-currency_code').value,
      account_type_id: document.getElementById('edit-account_type_id').value,
      item_number:     document.getElementById('edit-item_number').value,
      sof_number:      document.getElementById('edit-sof_number').value,
      status:          document.getElementById('edit-status').value,
    });
    exitEditMode();
    await loadRecord();
    showMsg('page-message', 'Bank account updated successfully.', 'success');
    setTimeout(function() { clearMsg('page-message'); }, 3000);
  } catch (e) {
    showMsg('edit-message', e.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Save Changes';
  }
});

document.addEventListener('DOMContentLoaded', function() {
  if (!RECORD_ID) return;
  loadRecord();
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
