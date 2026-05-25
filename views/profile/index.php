<?php
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::requireAuth();

$u          = Auth::user();
$fullName   = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
$initials   = strtoupper(substr($u['first_name'] ?? 'U', 0, 1) . substr($u['last_name'] ?? '', 0, 1));
$role       = !empty($u['role_names']) ? $u['role_names'][0] : null;
$dept       = $u['department_name'] ?? null;
$lastLogin  = $u['last_login_at'] ?? null;
$lastLoginFmt = $lastLogin ? date('d M Y, g:i A', strtotime($lastLogin)) : 'First session';
$sessionType  = ($u['user_type'] ?? 'internal') === 'internal' ? 'Internal' : 'External';
$isMicrosoft  = ($u['auth_source'] ?? '') === 'microsoft';

require_once __DIR__ . '/../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../includes/layout-tabler-sidebar.php';
?>

  <?php if (!empty($_SESSION['_debug_ms_claims'])): ?>
  <div class="container-xl mt-3">
    <div class="alert alert-warning py-2" style="font-size:.8rem;">
      <strong>DEBUG — Microsoft token claims:</strong> <?= h(implode(', ', $_SESSION['_debug_ms_claims'])) ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row align-items-center">
        <div class="col">
          <div class="page-pretitle">Treasury Revenue System</div>
          <h2 class="page-title">My Profile</h2>
        </div>
        <div class="col-auto">
          <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">← Back</a>
        </div>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">
      <div class="row g-4">

        <!-- LEFT: Identity card -->
        <div class="col-lg-3">

          <!-- Avatar & name -->
          <div class="card mb-3" style="border-top:3px solid var(--tblr-primary);">
            <div class="card-body text-center py-4">
              <div class="avatar avatar-xl bg-primary text-white fw-bold mx-auto mb-3"
                   style="width:4rem;height:4rem;font-size:1.4rem;line-height:4rem;text-align:center;border-radius:50%;">
                <?= h($initials) ?>
              </div>
              <div class="fw-bold fs-4 mb-0"><?= h($fullName) ?></div>
              <?php if ($role): ?>
                <div class="text-muted mb-1" style="font-size:.85rem;"><?= h($role) ?></div>
              <?php endif; ?>
              <?php if ($dept): ?>
                <div class="text-muted" style="font-size:.8rem;"><?= h($dept) ?></div>
              <?php endif; ?>
              <div class="mt-2">
                <span class="badge bg-success-lt text-success text-uppercase" style="font-size:.7rem;">
                  <?= h(ucfirst($u['status'] ?? 'active')) ?>
                </span>
              </div>
            </div>
          </div>

          <!-- Session details -->
          <div class="card">
            <div class="card-header py-2">
              <h4 class="card-title" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:var(--tblr-secondary);">Session Details</h4>
            </div>
            <div class="list-group list-group-flush" style="font-size:.82rem;">
              <div class="list-group-item py-2 d-flex justify-content-between">
                <span class="text-muted">Username</span>
                <span class="fw-medium"><?= h($u['username'] ?? '—') ?></span>
              </div>
              <div class="list-group-item py-2 d-flex justify-content-between">
                <span class="text-muted">Network</span>
                <span class="fw-medium"><?= h($sessionType) ?></span>
              </div>
              <div class="list-group-item py-2 d-flex justify-content-between">
                <span class="text-muted">Auth Source</span>
                <span class="fw-medium"><?= h(ucfirst($u['auth_source'] ?? 'local')) ?></span>
              </div>
              <div class="list-group-item py-2">
                <span class="text-muted d-block">Last Login</span>
                <span class="fw-medium"><?= h($lastLoginFmt) ?></span>
              </div>
            </div>
          </div>

        </div>

        <!-- RIGHT: Editable sections -->
        <div class="col-lg-9">

          <!-- Personal Information -->
          <div class="card mb-4">
            <div class="card-header">
              <h3 class="card-title">Personal Information</h3>
              <div class="card-options">
                <?php if ($isMicrosoft): ?>
                  <span class="badge bg-azure-lt text-azure" style="font-size:.72rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1" width="14" height="14" viewBox="0 0 21 21"><rect x="1" y="1" width="9" height="9" fill="#F25022"/><rect x="11" y="1" width="9" height="9" fill="#7FBA00"/><rect x="1" y="11" width="9" height="9" fill="#00A4EF"/><rect x="11" y="11" width="9" height="9" fill="#FFB900"/></svg>
                    Managed by Microsoft Entra ID
                  </span>
                <?php else: ?>
                  <span class="text-muted" style="font-size:.8rem;">Fields marked read-only require administrator access to change</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="card-body">
              <?php if (!$isMicrosoft): ?>
              <div id="profile-message" class="alert mb-3" style="display:none"></div>
              <?php endif; ?>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label d-flex align-items-center gap-1">
                    First Name
                    <?php if ($isMicrosoft): ?><span class="badge bg-secondary-lt text-secondary ms-1" style="font-size:.68rem;">Read-only</span><?php endif; ?>
                  </label>
                  <input type="text" class="form-control<?= $isMicrosoft ? ' bg-light' : '' ?>" id="first_name" value="<?= h($u['first_name'] ?? '') ?>"<?= $isMicrosoft ? ' readonly tabindex="-1"' : '' ?>>
                </div>
                <div class="col-md-6">
                  <label class="form-label d-flex align-items-center gap-1">
                    Last Name
                    <?php if ($isMicrosoft): ?><span class="badge bg-secondary-lt text-secondary ms-1" style="font-size:.68rem;">Read-only</span><?php endif; ?>
                  </label>
                  <input type="text" class="form-control<?= $isMicrosoft ? ' bg-light' : '' ?>" id="last_name" value="<?= h($u['last_name'] ?? '') ?>"<?= $isMicrosoft ? ' readonly tabindex="-1"' : '' ?>>
                </div>
                <?php if (!$isMicrosoft): ?>
                <div class="col-md-6">
                  <label class="form-label">Phone</label>
                  <input type="text" class="form-control" id="phone" value="<?= h($u['phone'] ?? '') ?>" placeholder="e.g. +501 600 0000">
                </div>
                <?php endif; ?>
                <div class="col-md-6">
                  <label class="form-label d-flex align-items-center gap-1">
                    Email
                    <span class="badge bg-secondary-lt text-secondary ms-1" style="font-size:.68rem;">Read-only</span>
                  </label>
                  <input type="email" class="form-control bg-light" value="<?= h($u['email'] ?? '') ?>" readonly tabindex="-1">
                  <?php if (!$isMicrosoft): ?><div class="form-hint">Contact an administrator to change your email address.</div><?php endif; ?>
                </div>
                <div class="col-md-6">
                  <label class="form-label d-flex align-items-center gap-1">
                    Username
                    <span class="badge bg-secondary-lt text-secondary ms-1" style="font-size:.68rem;">Read-only</span>
                  </label>
                  <input type="text" class="form-control bg-light" value="<?= h($u['username'] ?? '') ?>" readonly tabindex="-1">
                </div>
              </div>
              <?php if (!$isMicrosoft): ?>
              <div class="mt-3">
                <button class="btn btn-primary" id="save-profile-btn">Save Changes</button>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Change Password (local auth only) -->
          <?php if (!$isMicrosoft): ?>
          <div class="card mb-4">
            <div class="card-header">
              <h3 class="card-title">Change Password</h3>
            </div>
            <div class="card-body">
              <div id="pw-message" class="alert mb-3" style="display:none"></div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Current Password <span class="text-danger">*</span></label>
                  <input type="password" class="form-control" id="current_password" autocomplete="current-password">
                </div>
                <div class="col-12"></div>
                <div class="col-md-6">
                  <label class="form-label">New Password <span class="text-danger">*</span></label>
                  <input type="password" class="form-control" id="new_password" autocomplete="new-password">
                  <div class="form-hint">Minimum 8 characters.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                  <input type="password" class="form-control" id="confirm_password" autocomplete="new-password">
                </div>
              </div>
              <div class="mt-3">
                <button class="btn btn-warning" id="change-pw-btn">Change Password</button>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <!-- Role & Access (read-only) -->
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Role &amp; Access</h3>
              <div class="card-options">
                <span class="badge bg-secondary-lt text-secondary" style="font-size:.72rem;">Read-only — managed by administrators</span>
              </div>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label text-muted">Assigned Role</label>
                  <div class="fw-medium"><?= $role ? h($role) : '<span class="text-muted">No role assigned</span>' ?></div>
                </div>
                <div class="col-md-6">
                  <label class="form-label text-muted">Department</label>
                  <div class="fw-medium"><?= $dept ? h($dept) : '<span class="text-muted">Not assigned</span>' ?></div>
                </div>
                <div class="col-md-6">
                  <label class="form-label text-muted">User Type</label>
                  <div class="fw-medium"><?= h(ucfirst($u['user_type'] ?? '—')) ?></div>
                </div>
                <div class="col-md-6">
                  <label class="form-label text-muted">Account Status</label>
                  <div>
                    <?php $status = $u['status'] ?? 'active'; ?>
                    <span class="badge bg-<?= $status === 'active' ? 'success' : ($status === 'locked' ? 'danger' : 'secondary') ?>-lt
                                          text-<?= $status === 'active' ? 'success' : ($status === 'locked' ? 'danger' : 'secondary') ?>">
                      <?= h(ucfirst($status)) ?>
                    </span>
                  </div>
                </div>
                <?php if (!empty($u['sub_treasury_id'])): ?>
                <div class="col-md-6">
                  <label class="form-label text-muted">Sub-Treasury</label>
                  <div class="fw-medium"><?= h($u['sub_treasury_name'] ?? '—') ?></div>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

<script>
<?php if (!$isMicrosoft): ?>
const PROFILE_UPDATE_URL = "<?= url('api/profile/update.php') ?>";
const CHANGE_PW_URL      = "<?= url('api/profile/change-password.php') ?>";

function showMsg(id, msg, type) {
  type = type || 'danger';
  var el = document.getElementById(id);
  el.className = 'alert alert-' + type;
  el.textContent = msg;
  el.style.display = 'block';
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function hideMsg(id) {
  document.getElementById(id).style.display = 'none';
}

document.getElementById('save-profile-btn').addEventListener('click', async function() {
  hideMsg('profile-message');
  var btn = document.getElementById('save-profile-btn');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    var res = await fetch(PROFILE_UPDATE_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        first_name: document.getElementById('first_name').value,
        last_name:  document.getElementById('last_name').value,
        phone:      document.getElementById('phone').value,
      }),
    });
    var data = await res.json();
    showMsg('profile-message', data.message, data.success ? 'success' : 'danger');
  } catch (e) {
    showMsg('profile-message', 'Network error. Please try again.');
  } finally {
    btn.disabled = false; btn.textContent = 'Save Changes';
  }
});

document.getElementById('change-pw-btn').addEventListener('click', async function() {
  hideMsg('pw-message');
  var btn = document.getElementById('change-pw-btn');
  btn.disabled = true; btn.textContent = 'Updating...';
  try {
    var res = await fetch(CHANGE_PW_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        current_password: document.getElementById('current_password').value,
        new_password:     document.getElementById('new_password').value,
        confirm_password: document.getElementById('confirm_password').value,
      }),
    });
    var data = await res.json();
    showMsg('pw-message', data.message, data.success ? 'success' : 'danger');
    if (data.success) {
      document.getElementById('current_password').value = '';
      document.getElementById('new_password').value     = '';
      document.getElementById('confirm_password').value = '';
    }
  } catch (e) {
    showMsg('pw-message', 'Network error. Please try again.');
  } finally {
    btn.disabled = false; btn.textContent = 'Change Password';
  }
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../includes/layout-tabler-footer.php'; ?>
