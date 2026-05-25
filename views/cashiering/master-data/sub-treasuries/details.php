<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.sub_treasuries.manage');

$id = (int)($_GET['id'] ?? 0);

require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-body">
    <div class="container-xl">

      <?php if ($id <= 0): ?>
        <div class="alert alert-danger">Invalid sub-treasury ID.</div>
      <?php else: ?>

        <!-- Page identity card -->
        <div class="card mb-3" style="border-left: 4px solid var(--tblr-primary);">
          <div class="card-body py-3">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.68rem;letter-spacing:.1em;">Cashiering &middot; Master Data &middot; Sub-Treasuries</div>
                <div class="fw-bold" id="page-subtitle" style="font-size:1.05rem;line-height:1.2;">Loading...</div>
              </div>
              <a href="<?= url('views/cashiering/master-data/sub-treasuries/index.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Back to Sub-Treasuries</a>
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
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Code</dt>
              <dd class="col-sm-9 mb-2" id="v-sub_treasury_code">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Name</dt>
              <dd class="col-sm-9 mb-2" id="v-sub_treasury_name">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Department</dt>
              <dd class="col-sm-9 mb-2" id="v-department_name">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">District</dt>
              <dd class="col-sm-9 mb-2" id="v-district">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Address</dt>
              <dd class="col-sm-9 mb-2" id="v-address_line">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Phone</dt>
              <dd class="col-sm-9 mb-2" id="v-contact_phone">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Email</dt>
              <dd class="col-sm-9 mb-2" id="v-contact_email">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Status</dt>
              <dd class="col-sm-9 mb-0" id="v-is_active">—</dd>
            </dl>

            <!-- Edit mode -->
            <div id="edit-mode" style="display:none;">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Sub-Treasury Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="edit-sub_treasury_name" placeholder="Sub-treasury name">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Sub-Treasury Code</label>
                  <input type="text" class="form-control" id="edit-sub_treasury_code" placeholder="e.g. ST-001">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Department</label>
                  <select class="form-select" id="edit-department_id">
                    <option value="">— Select Department —</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">District</label>
                  <input type="text" class="form-control" id="edit-district" placeholder="District">
                </div>
                <div class="col-12">
                  <label class="form-label">Address</label>
                  <input type="text" class="form-control" id="edit-address_line" placeholder="Street address">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Contact Phone</label>
                  <input type="text" class="form-control" id="edit-contact_phone" placeholder="Phone number">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Contact Email</label>
                  <input type="email" class="form-control" id="edit-contact_email" placeholder="Email address">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Status</label>
                  <select class="form-select" id="edit-is_active">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                  </select>
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
var SHOW_URL   = "<?= url('api/master-data/sub-treasuries/show.php') ?>";
var UPDATE_URL = "<?= url('api/master-data/sub-treasuries/update.php') ?>";
var DEPT_URL   = "<?= url('api/master-data/departments/list.php') ?>";

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
    document.getElementById('record-heading').textContent       = r.sub_treasury_name || 'Sub-Treasury';
    document.getElementById('page-subtitle').textContent        = r.sub_treasury_name || 'Sub-Treasury Details';
    document.getElementById('v-sub_treasury_code').textContent  = r.sub_treasury_code || '—';
    document.getElementById('v-sub_treasury_name').textContent  = r.sub_treasury_name || '—';
    document.getElementById('v-department_name').textContent    = r.department_name || '—';
    document.getElementById('v-district').textContent           = r.district || '—';
    document.getElementById('v-address_line').textContent       = r.address_line || '—';
    document.getElementById('v-contact_phone').textContent      = r.contact_phone || '—';
    document.getElementById('v-contact_email').textContent      = r.contact_email || '—';
    document.getElementById('v-is_active').innerHTML            = isActiveBadge(r.is_active);
  } catch (e) {
    showMsg('page-message', e.message);
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

var editToggleBtn = document.getElementById('edit-toggle-btn');
var viewMode      = document.getElementById('view-mode');
var editMode      = document.getElementById('edit-mode');

function enterEditMode() {
  if (recordData) {
    document.getElementById('edit-sub_treasury_name').value = recordData.sub_treasury_name || '';
    document.getElementById('edit-sub_treasury_code').value = recordData.sub_treasury_code || '';
    document.getElementById('edit-district').value          = recordData.district || '';
    document.getElementById('edit-address_line').value      = recordData.address_line || '';
    document.getElementById('edit-contact_phone').value     = recordData.contact_phone || '';
    document.getElementById('edit-contact_email').value     = recordData.contact_email || '';
    document.getElementById('edit-is_active').value         = String(recordData.is_active) === '1' ? '1' : '0';
    loadDepts(recordData.department_id);
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
      id:                RECORD_ID,
      sub_treasury_name: document.getElementById('edit-sub_treasury_name').value,
      sub_treasury_code: document.getElementById('edit-sub_treasury_code').value,
      department_id:     document.getElementById('edit-department_id').value,
      district:          document.getElementById('edit-district').value,
      address_line:      document.getElementById('edit-address_line').value,
      contact_phone:     document.getElementById('edit-contact_phone').value,
      contact_email:     document.getElementById('edit-contact_email').value,
      is_active:         document.getElementById('edit-is_active').value,
    });
    exitEditMode();
    await loadRecord();
    showMsg('page-message', 'Sub-treasury updated successfully.', 'success');
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
