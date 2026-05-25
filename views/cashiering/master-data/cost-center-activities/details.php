<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.cost_center_activities.manage');

$id = (int)($_GET['id'] ?? 0);

require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-body">
    <div class="container-xl">

      <?php if ($id <= 0): ?>
        <div class="alert alert-danger">Invalid activity ID.</div>
      <?php else: ?>

        <!-- Page identity card -->
        <div class="card mb-3" style="border-left: 4px solid var(--tblr-primary);">
          <div class="card-body py-3">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.68rem;letter-spacing:.1em;">Cashiering &middot; Master Data &middot; Cost Center Activities</div>
                <div class="fw-bold" id="page-subtitle" style="font-size:1.05rem;line-height:1.2;">Loading...</div>
              </div>
              <a href="<?= url('views/cashiering/master-data/cost-center-activities/index.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Back to Activities</a>
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
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Activity Code</dt>
              <dd class="col-sm-9 mb-2" id="v-activity_code">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Activity Name</dt>
              <dd class="col-sm-9 mb-2" id="v-activity_name">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Cost Center</dt>
              <dd class="col-sm-9 mb-2" id="v-cost_center">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Revenue Code</dt>
              <dd class="col-sm-9 mb-2" id="v-revenue_code">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Default Amount</dt>
              <dd class="col-sm-9 mb-2" id="v-default_amount">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Description</dt>
              <dd class="col-sm-9 mb-2" id="v-description">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Status</dt>
              <dd class="col-sm-9 mb-0" id="v-is_active">—</dd>
            </dl>

            <!-- Edit mode -->
            <div id="edit-mode" style="display:none;">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Activity Code <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="edit-activity_code" placeholder="e.g. ACT-001">
                </div>
                <div class="col-md-8">
                  <label class="form-label">Activity Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="edit-activity_name" placeholder="Activity name">
                </div>
                <div class="col-md-8">
                  <label class="form-label">Cost Center <span class="text-danger">*</span></label>
                  <select class="form-select" id="edit-cost_center_id">
                    <option value="">— Select Cost Center —</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Revenue Code</label>
                  <input type="text" class="form-control" id="edit-revenue_code" placeholder="Revenue code">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Default Amount</label>
                  <input type="number" step="0.01" class="form-control" id="edit-default_amount" placeholder="0.00">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Status</label>
                  <select class="form-select" id="edit-is_active">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label">Description</label>
                  <textarea class="form-control" id="edit-description" rows="3" placeholder="Optional description"></textarea>
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
var SHOW_URL   = "<?= url('api/master-data/cost-center-activities/show.php') ?>";
var UPDATE_URL = "<?= url('api/master-data/cost-center-activities/update.php') ?>";
var CC_URL     = "<?= url('api/master-data/cost-centers/list.php') ?>";

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

var recordData = null;

async function loadRecord() {
  try {
    var res = await apiGet(SHOW_URL + '?id=' + RECORD_ID);
    var r = res.data;
    recordData = r;
    document.getElementById('record-heading').textContent  = r.activity_name || 'Activity';
    document.getElementById('page-subtitle').textContent   = r.activity_name || 'Activity Details';
    document.getElementById('v-activity_code').textContent = r.activity_code || '—';
    document.getElementById('v-activity_name').textContent = r.activity_name || '—';
    var ccLabel = r.cost_center_code && r.cost_center_name
      ? r.cost_center_code + ' — ' + r.cost_center_name
      : (r.cost_center_name || '—');
    document.getElementById('v-cost_center').textContent   = ccLabel;
    document.getElementById('v-revenue_code').textContent  = r.revenue_code || '—';
    document.getElementById('v-default_amount').textContent = r.default_amount ? parseFloat(r.default_amount).toFixed(2) : '—';
    document.getElementById('v-description').textContent   = r.description || '—';
    document.getElementById('v-is_active').innerHTML       = isActiveBadge(r.is_active);
  } catch (e) {
    showMsg('page-message', e.message);
  }
}

async function loadCostCenters(selectedId) {
  try {
    var res = await apiGet(CC_URL);
    var sel = document.getElementById('edit-cost_center_id');
    sel.innerHTML = '<option value="">— Select Cost Center —</option>';
    (res.data || []).forEach(function(c) {
      var opt = document.createElement('option');
      opt.value = c.id;
      opt.textContent = c.code ? c.code + ' — ' + c.name : c.name;
      if (selectedId && String(c.id) === String(selectedId)) opt.selected = true;
      sel.appendChild(opt);
    });
  } catch(e) {}
}

var editToggleBtn = document.getElementById('edit-toggle-btn');
var viewMode      = document.getElementById('view-mode');
var editMode      = document.getElementById('edit-mode');

function enterEditMode() {
  if (recordData) {
    document.getElementById('edit-activity_code').value  = recordData.activity_code || '';
    document.getElementById('edit-activity_name').value  = recordData.activity_name || '';
    document.getElementById('edit-revenue_code').value   = recordData.revenue_code || '';
    document.getElementById('edit-default_amount').value = recordData.default_amount || '';
    document.getElementById('edit-description').value    = recordData.description || '';
    document.getElementById('edit-is_active').value      = String(recordData.is_active) === '1' ? '1' : '0';
    loadCostCenters(recordData.cost_center_id);
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
      id:             RECORD_ID,
      activity_code:  document.getElementById('edit-activity_code').value,
      activity_name:  document.getElementById('edit-activity_name').value,
      cost_center_id: document.getElementById('edit-cost_center_id').value,
      revenue_code:   document.getElementById('edit-revenue_code').value,
      default_amount: document.getElementById('edit-default_amount').value,
      description:    document.getElementById('edit-description').value,
      is_active:      document.getElementById('edit-is_active').value,
    });
    exitEditMode();
    await loadRecord();
    showMsg('page-message', 'Activity updated successfully.', 'success');
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
