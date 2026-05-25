<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.users.manage');

$id = (int)($_GET['id'] ?? 0);

require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-body">
    <div class="container-xl">

      <?php if ($id <= 0): ?>
        <div class="alert alert-danger">Invalid user ID.</div>
      <?php else: ?>

        <!-- Page identity card -->
        <div class="card mb-3" style="border-left: 4px solid var(--tblr-primary);">
          <div class="card-body py-3">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.68rem;letter-spacing:.1em;">Cashiering &middot; Master Data &middot; Users</div>
                <div class="fw-bold" id="page-subtitle" style="font-size:1.05rem;line-height:1.2;">Loading...</div>
              </div>
              <a href="<?= url('views/cashiering/master-data/users/index.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Back to Users</a>
            </div>
          </div>
        </div>

        <div id="page-message" class="alert mb-3" style="display:none;"></div>

        <!-- Profile card -->
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
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Name</dt>
              <dd class="col-sm-9 mb-2" id="v-name">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Username</dt>
              <dd class="col-sm-9 mb-2" id="v-username">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Email</dt>
              <dd class="col-sm-9 mb-2" id="v-email">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Phone</dt>
              <dd class="col-sm-9 mb-2" id="v-phone">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Role</dt>
              <dd class="col-sm-9 mb-2" id="v-role">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">User Type</dt>
              <dd class="col-sm-9 mb-2" id="v-user_type">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Auth Source</dt>
              <dd class="col-sm-9 mb-2" id="v-auth_source">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">MFA</dt>
              <dd class="col-sm-9 mb-2" id="v-mfa_enabled">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Status</dt>
              <dd class="col-sm-9 mb-0" id="v-status">—</dd>
            </dl>

            <!-- Edit mode -->
            <div id="edit-mode" style="display:none;">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">First Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="edit-first_name" placeholder="First name">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Last Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="edit-last_name" placeholder="Last name">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Username</label>
                  <input type="text" class="form-control" id="edit-username" placeholder="Username">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" id="edit-email" placeholder="Email address">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Phone</label>
                  <input type="text" class="form-control" id="edit-phone" placeholder="Phone number">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Role</label>
                  <select class="form-select" id="edit-role_id">
                    <option value="">— Select Role —</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">User Type</label>
                  <select class="form-select" id="edit-user_type">
                    <option value="internal">Internal</option>
                    <option value="external">External</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Auth Source</label>
                  <select class="form-select" id="edit-auth_source">
                    <option value="local">Local</option>
                    <option value="microsoft">Microsoft</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Status</label>
                  <select class="form-select" id="edit-status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="pending">Pending</option>
                    <option value="locked">Locked</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">MFA Enabled</label>
                  <select class="form-select" id="edit-mfa_enabled">
                    <option value="0">No</option>
                    <option value="1">Yes</option>
                  </select>
                </div>
              </div>

              <!-- Password change section -->
              <hr class="my-3">
              <div class="text-muted small fw-semibold mb-2">Change Password (leave blank to keep current)</div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">New Password</label>
                  <input type="password" class="form-control" id="edit-new_password" placeholder="New password" autocomplete="new-password">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Confirm Password</label>
                  <input type="password" class="form-control" id="edit-confirm_password" placeholder="Confirm password" autocomplete="new-password">
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
var SHOW_URL   = "<?= url('api/master-data/users/show.php') ?>";
var UPDATE_URL = "<?= url('api/master-data/users/update.php') ?>";
var ROLES_URL  = "<?= url('api/master-data/roles/list.php') ?>";

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
  var map = {
    active:   '<span class="badge bg-success-lt text-success">Active</span>',
    inactive: '<span class="badge bg-secondary-lt text-secondary">Inactive</span>',
    pending:  '<span class="badge bg-warning-lt text-warning">Pending</span>',
    locked:   '<span class="badge bg-danger-lt text-danger">Locked</span>',
  };
  return map[val] || '<span class="badge bg-secondary-lt text-secondary">' + (val || '—') + '</span>';
}

var recordData = null;

async function loadRecord() {
  try {
    var res = await apiGet(SHOW_URL + '?id=' + RECORD_ID);
    var r = res.data;
    recordData = r;
    var fullName = ((r.first_name || '') + ' ' + (r.last_name || '')).trim() || r.username || 'User';
    document.getElementById('record-heading').textContent = fullName;
    document.getElementById('page-subtitle').textContent  = fullName;
    document.getElementById('v-name').textContent         = fullName;
    document.getElementById('v-username').textContent     = r.username || '—';
    document.getElementById('v-email').textContent        = r.email || '—';
    document.getElementById('v-phone').textContent        = r.phone || '—';
    var role = r.role_name ? r.role_name + (r.role_code ? ' (' + r.role_code + ')' : '') : '—';
    document.getElementById('v-role').textContent         = role;
    document.getElementById('v-user_type').textContent    = r.user_type ? (r.user_type.charAt(0).toUpperCase() + r.user_type.slice(1)) : '—';
    document.getElementById('v-auth_source').textContent  = r.auth_source ? (r.auth_source.charAt(0).toUpperCase() + r.auth_source.slice(1)) : '—';
    document.getElementById('v-mfa_enabled').innerHTML    = Number(r.mfa_enabled) === 1
      ? '<span class="badge bg-success-lt text-success">Enabled</span>'
      : '<span class="badge bg-secondary-lt text-secondary">Disabled</span>';
    document.getElementById('v-status').innerHTML         = statusBadge(r.status);
  } catch (e) {
    showMsg('page-message', e.message);
  }
}

async function loadRoles(selectedId) {
  try {
    var res = await apiGet(ROLES_URL);
    var sel = document.getElementById('edit-role_id');
    sel.innerHTML = '<option value="">— Select Role —</option>';
    (res.data || []).forEach(function(r) {
      var opt = document.createElement('option');
      opt.value = r.id;
      opt.textContent = r.name + ' (' + r.code + ')';
      if (selectedId && String(r.id) === String(selectedId)) opt.selected = true;
      sel.appendChild(opt);
    });
  } catch(e) {}
}

var editToggleBtn = document.getElementById('edit-toggle-btn');
var viewMode      = document.getElementById('view-mode');
var editMode      = document.getElementById('edit-mode');

function enterEditMode() {
  if (recordData) {
    document.getElementById('edit-first_name').value  = recordData.first_name || '';
    document.getElementById('edit-last_name').value   = recordData.last_name || '';
    document.getElementById('edit-username').value    = recordData.username || '';
    document.getElementById('edit-email').value       = recordData.email || '';
    document.getElementById('edit-phone').value       = recordData.phone || '';
    document.getElementById('edit-user_type').value   = recordData.user_type || 'internal';
    document.getElementById('edit-auth_source').value = recordData.auth_source || 'local';
    document.getElementById('edit-status').value      = recordData.status || 'active';
    document.getElementById('edit-mfa_enabled').value = String(recordData.mfa_enabled) === '1' ? '1' : '0';
    document.getElementById('edit-new_password').value     = '';
    document.getElementById('edit-confirm_password').value = '';
    loadRoles(recordData.role_id);
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
  var newPass    = document.getElementById('edit-new_password').value;
  var confirmPass = document.getElementById('edit-confirm_password').value;
  if (newPass && newPass !== confirmPass) {
    showMsg('edit-message', 'Passwords do not match.');
    return;
  }
  var btn = document.getElementById('save-btn');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    var payload = {
      id:          RECORD_ID,
      first_name:  document.getElementById('edit-first_name').value,
      last_name:   document.getElementById('edit-last_name').value,
      username:    document.getElementById('edit-username').value,
      email:       document.getElementById('edit-email').value,
      phone:       document.getElementById('edit-phone').value,
      role_id:     document.getElementById('edit-role_id').value,
      user_type:   document.getElementById('edit-user_type').value,
      auth_source: document.getElementById('edit-auth_source').value,
      status:      document.getElementById('edit-status').value,
      mfa_enabled: document.getElementById('edit-mfa_enabled').value,
    };
    if (newPass) payload.password = newPass;
    await apiPost(UPDATE_URL, payload);
    exitEditMode();
    await loadRecord();
    showMsg('page-message', 'User updated successfully.', 'success');
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
