<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.registers.manage');

$id = (int)($_GET['id'] ?? 0);

require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-body">
    <div class="container-xl">

      <?php if ($id <= 0): ?>
        <div class="alert alert-danger">Invalid register ID.</div>
      <?php else: ?>

        <!-- Page identity card -->
        <div class="card mb-3" style="border-left: 4px solid var(--tblr-primary);">
          <div class="card-body py-2">
            <div class="text-uppercase fw-semibold text-muted mb-2" style="font-size:.63rem;letter-spacing:.1em;">Cashiering &middot; Master Data &middot; Registers</div>
            <div class="d-flex align-items-center gap-3 flex-wrap">

              <!-- Code + Name -->
              <div style="min-width:140px;">
                <div class="text-muted" style="font-size:.72rem;font-family:monospace;" id="h-register_code">—</div>
                <div class="fw-bold" style="font-size:1rem;line-height:1.25;" id="h-register_name">Loading...</div>
              </div>

              <div style="width:1px;align-self:stretch;background:var(--tblr-border-color);"></div>

              <!-- Department / Sub-Treasury / Status -->
              <div class="d-flex gap-3 flex-wrap">
                <div>
                  <div class="text-uppercase text-muted fw-semibold" style="font-size:.6rem;letter-spacing:.07em;">Department</div>
                  <div style="font-size:.85rem;" id="h-department_name">—</div>
                </div>
                <div>
                  <div class="text-uppercase text-muted fw-semibold" style="font-size:.6rem;letter-spacing:.07em;">Sub-Treasury</div>
                  <div style="font-size:.85rem;" id="h-sub_treasury_name">—</div>
                </div>
                <div>
                  <div class="text-uppercase text-muted fw-semibold" style="font-size:.6rem;letter-spacing:.07em;">Status</div>
                  <div id="h-is_active">—</div>
                </div>
              </div>

              <div style="width:1px;align-self:stretch;background:var(--tblr-border-color);"></div>

              <!-- User count -->
              <div>
                <div class="text-uppercase text-muted fw-semibold" style="font-size:.6rem;letter-spacing:.07em;">Users</div>
                <div id="h-user_count" style="font-size:.85rem;">—</div>
              </div>

              <a href="<?= url('views/cashiering/master-data/registers/index.php') ?>" class="btn btn-outline-secondary btn-sm ms-auto">&#8592; Back to Registers</a>
            </div>
          </div>
        </div>

        <div id="page-message" class="alert mb-3" style="display:none;"></div>

        <!-- Register info -->
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title">Register Details</h3>
            <div class="card-options">
              <button class="btn btn-sm btn-primary" id="edit-toggle-btn">Edit</button>
            </div>
          </div>
          <div class="card-body">
            <div id="edit-message" class="alert mb-3" style="display:none;"></div>

            <!-- View mode -->
            <div id="view-mode">
              <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.6rem;letter-spacing:.07em;">Description</div>
              <div id="v-description" class="text-muted" style="font-size:.875rem;">—</div>
            </div>

            <!-- Edit mode -->
            <div id="edit-mode" style="display:none;">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Register Code</label>
                  <input type="text" class="form-control" id="edit-register_code" readonly>
                  <div class="form-text">Code is auto-generated and cannot be changed.</div>
                </div>
                <div class="col-md-8">
                  <label class="form-label">Register Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="edit-register_name" placeholder="Register name">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Department <span class="text-danger">*</span></label>
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
                  <select class="form-select" id="edit-is_active">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
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

        <!-- Assigned Users -->
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title">Assigned Users</h3>
          </div>
          <div class="card-body">
            <div id="users-message" class="alert mb-3" style="display:none;"></div>
            <div class="row g-2 align-items-end mb-3">
              <div class="col-md-8">
                <label class="form-label form-label-sm">User</label>
                <select class="form-select form-select-sm" id="user-select">
                  <option value="">Select user...</option>
                </select>
              </div>
              <div class="col-md-4">
                <button class="btn btn-primary btn-sm w-100" id="assign-user-btn">Assign</button>
              </div>
            </div>
            <table class="table table-sm table-vcenter table-hover card-table" style="font-size:.85rem;">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Username</th>
                  <th class="w-1"></th>
                </tr>
              </thead>
              <tbody id="users-tbody">
                <tr><td colspan="3" class="text-muted text-center py-3">Loading...</td></tr>
              </tbody>
            </table>
          </div>
        </div>

      <?php endif; ?>
    </div>
  </div>

<script>
var RECORD_ID        = <?= $id ?>;
var SHOW_URL         = "<?= url('api/master-data/registers/show.php') ?>";
var UPDATE_URL       = "<?= url('api/master-data/registers/update.php') ?>";
var DEPT_URL         = "<?= url('api/master-data/departments/list.php') ?>";
var ST_URL           = "<?= url('api/master-data/sub-treasuries/list.php') ?>";
var USERS_URL        = "<?= url('api/master-data/users/list.php') ?>";
var ASSIGN_USER_URL  = "<?= url('api/master-data/registers/assign-user.php') ?>";
var REMOVE_USER_URL  = "<?= url('api/master-data/registers/remove-user.php') ?>";

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
    var users = r.users || [];
    // Header card
    document.getElementById('h-register_code').textContent     = r.register_code || '—';
    document.getElementById('h-register_name').textContent     = r.register_name || 'Register';
    document.getElementById('h-department_name').textContent   = r.department_name || '—';
    document.getElementById('h-sub_treasury_name').textContent = r.sub_treasury_name || '—';
    document.getElementById('h-is_active').innerHTML           = isActiveBadge(r.is_active);
    document.getElementById('h-user_count').textContent        = users.length > 0
      ? users.length + ' user' + (users.length !== 1 ? 's' : '')
      : '—';
    // Body
    document.getElementById('v-description').textContent = r.description || '—';
    renderAssignedUsers(users);
  } catch (e) {
    showMsg('page-message', e.message);
  }
}

function renderAssignedUsers(users) {
  var tbody = document.getElementById('users-tbody');
  if (!users.length) {
    tbody.innerHTML = '<tr><td colspan="3" class="text-muted text-center py-3">No users assigned.</td></tr>';
    return;
  }
  tbody.innerHTML = users.map(function(u) {
    var fullName = ((u.first_name || '') + ' ' + (u.last_name || '')).trim() || '—';
    return '<tr>' +
      '<td>' + fullName + '</td>' +
      '<td class="text-muted" style="font-family:monospace;">' + (u.username || '—') + '</td>' +
      '<td><button class="btn btn-sm btn-ghost-danger" data-remove-user="' + u.id + '" title="Remove">' +
        '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>' +
      '</button></td>' +
    '</tr>';
  }).join('');
  tbody.querySelectorAll('[data-remove-user]').forEach(function(btn) {
    btn.addEventListener('click', async function() {
      if (!confirm('Remove this user from the register?')) return;
      clearMsg('users-message');
      try {
        await apiPost(REMOVE_USER_URL, { register_id: RECORD_ID, user_id: btn.dataset.removeUser });
        await loadRecord();
      } catch (e) { showMsg('users-message', e.message); }
    });
  });
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
    document.getElementById('edit-register_code').value = recordData.register_code || '';
    document.getElementById('edit-register_name').value = recordData.register_name || '';
    document.getElementById('edit-description').value   = recordData.description || '';
    document.getElementById('edit-is_active').value     = String(recordData.is_active) === '1' ? '1' : '0';
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
      register_name:   document.getElementById('edit-register_name').value,
      department_id:   document.getElementById('edit-department_id').value,
      sub_treasury_id: document.getElementById('edit-sub_treasury_id').value,
      description:     document.getElementById('edit-description').value,
      is_active:       document.getElementById('edit-is_active').value,
    });
    exitEditMode();
    await loadRecord();
    showMsg('page-message', 'Register updated successfully.', 'success');
    setTimeout(function() { clearMsg('page-message'); }, 3000);
  } catch (e) {
    showMsg('edit-message', e.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Save Changes';
  }
});

document.getElementById('assign-user-btn').addEventListener('click', async function() {
  var userId = document.getElementById('user-select').value;
  if (!userId) { showMsg('users-message', 'Please select a user.', 'warning'); return; }
  clearMsg('users-message');
  var btn = document.getElementById('assign-user-btn');
  btn.disabled = true; btn.textContent = 'Assigning...';
  try {
    await apiPost(ASSIGN_USER_URL, { register_id: RECORD_ID, user_id: userId });
    showMsg('users-message', 'User assigned.', 'success');
    await loadRecord();
    setTimeout(function() { clearMsg('users-message'); }, 2500);
  } catch (e) {
    showMsg('users-message', e.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Assign';
  }
});

document.addEventListener('DOMContentLoaded', async function() {
  if (!RECORD_ID) return;
  try {
    var usersRes = await apiGet(USERS_URL + '?status=active');
    var userSelect = document.getElementById('user-select');
    userSelect.innerHTML = '<option value="">Select user...</option>';
    (usersRes.data || []).forEach(function(u) {
      var opt = document.createElement('option');
      opt.value = u.id;
      opt.textContent = ((u.first_name || '') + ' ' + (u.last_name || '')).trim() + ' (' + u.username + ')';
      userSelect.appendChild(opt);
    });
    await loadRecord();
  } catch (e) {
    showMsg('page-message', e.message);
  }
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
