<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
require_once __DIR__ . '/../../../../config/database.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.roles.manage');
require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-body">
    <div class="container-xl">

      <!-- Page identity card -->
      <div class="card mb-3" style="border-left: 4px solid var(--tblr-primary);">
        <div class="card-body py-3">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.68rem;letter-spacing:.1em;">Cashiering &middot; Master Data</div>
              <div class="fw-bold" style="font-size:1.05rem;line-height:1.2;">Role Permissions</div>
            </div>
            <a href="<?= url('views/cashiering/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Cashiering</a>
          </div>
        </div>
      </div>

      <div class="alert alert-info mb-3">
        <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12.01" y2="8"/><polyline points="11 12 12 12 12 16 13 16"/></svg>
        Select a role on the left to view and edit its permissions. Changes are saved immediately when you click <strong>Save Permissions</strong>.
      </div>

      <div class="row">
        <!-- Role list -->
        <div class="col-md-3">
          <div class="card sticky-top" style="top: 4.5rem; z-index: 999;">
            <div class="card-header d-flex align-items-center justify-content-between">
              <h4 class="card-title">Roles</h4>
              <span class="badge bg-secondary-lt" id="role-count-badge">0</span>
            </div>
            <div class="list-group list-group-flush" id="role-list">
              <div class="text-center py-3 text-muted small">Loading...</div>
            </div>
          </div>
        </div>

        <!-- Permissions panel -->
        <div class="col-md-9">
          <div class="card" id="permissions-card" style="display:none">
            <div class="card-header d-flex align-items-center justify-content-between">
              <div>
                <h4 class="card-title mb-0" id="perm-role-name"></h4>
                <div class="text-muted small d-flex gap-2 align-items-center">
                  <span id="perm-role-code"></span>
                  <span class="text-muted">|</span>
                  <span class="text-primary font-weight-medium" id="perm-stats">0 selected out of 0</span>
                </div>
              </div>
              <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" id="btn-select-all">Select All</button>
                <button class="btn btn-sm btn-outline-secondary" id="btn-clear-all">Clear All</button>
                <button class="btn btn-sm btn-primary" id="btn-save">Save Permissions</button>
              </div>
            </div>
            <div class="card-body" id="perm-body"></div>
          </div>

          <div id="perm-placeholder" class="card card-body text-center text-muted py-5">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-lg mb-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><rect x="5" y="11" width="14" height="10" rx="2"/><circle cx="12" cy="16" r="1"/><path d="M8 11v-4a4 4 0 0 1 8 0v4"/></svg>
            <div>Select a role to manage its permissions</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const ROLES_URL   = "<?= url('api/master-data/roles/list.php') ?>";
const SHOW_URL    = "<?= url('api/master-data/role-permissions/show.php') ?>";
const UPDATE_URL  = "<?= url('api/master-data/role-permissions/update.php') ?>";

const MODULE_LABELS = {
  cashiering:   'Cashiering',
  transactions: 'Transactions',
  expenses:     'Expenses',
  master_data:  'Master Data',
  reports:      'Reports',
};

let activeRoleId = null;

async function loadRoles() {
  const res = await fetch(`${ROLES_URL}?status=active`);
  const data = await res.json();
  const list = document.getElementById('role-list');
  const badge = document.getElementById('role-count-badge');
  
  if (!data.success || !data.data?.length) {
    list.innerHTML = '<div class="text-center py-3 text-muted small">No roles found.</div>';
    badge.textContent = '0';
    return;
  }
  
  badge.textContent = data.data.length;
  list.innerHTML = data.data.map(r => `
    <button class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2"
            data-role-id="${r.id}" data-role-name="${r.name}" onclick="selectRole(${r.id})">
      <span style="font-size:.875rem">${r.name}</span>
      <span class="badge bg-blue-lt text-uppercase" style="font-size:10px">${r.code}</span>
    </button>
  `).join('');
}

async function selectRole(roleId) {
  // Highlight selected
  document.querySelectorAll('#role-list button').forEach(b => b.classList.remove('active'));
  const btn = document.querySelector(`#role-list button[data-role-id="${roleId}"]`);
  if (btn) btn.classList.add('active');

  activeRoleId = roleId;

  document.getElementById('perm-placeholder').style.display = 'none';
  document.getElementById('permissions-card').style.display = '';
  document.getElementById('perm-body').innerHTML = '<div class="text-center py-3 text-muted">Loading permissions...</div>';
  hideSaveMessage();

  const res = await fetch(`${SHOW_URL}?role_id=${roleId}`);
  const data = await res.json();
  if (!data.success) {
    document.getElementById('perm-body').innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
    return;
  }

  document.getElementById('perm-role-name').textContent = data.role.name;
  document.getElementById('perm-role-code').textContent = data.role.code;

  renderPermissions(data.permissions);
  updatePermStats();
}

function updatePermStats() {
  const total = document.querySelectorAll('.perm-checkbox').length;
  const selected = document.querySelectorAll('.perm-checkbox:checked').length;
  document.getElementById('perm-stats').textContent = `${selected} selected out of ${total}`;
}

function renderPermissions(grouped) {
  const order = ['cashiering', 'transactions', 'expenses', 'master_data', 'reports'];
  const modules = [...order, ...Object.keys(grouped).filter(m => !order.includes(m))];

  let html = '';
  for (const mod of modules) {
    if (!grouped[mod]) continue;
    const label = MODULE_LABELS[mod] || mod;
    html += `
      <div class="mb-3">
        <div class="d-flex align-items-center mb-1 gap-2">
          <span class="badge bg-azure text-uppercase" style="font-size:11px">${label}</span>
          <hr class="flex-fill m-0">
        </div>
        <div class="row g-1">
          ${grouped[mod].map(p => `
            <div class="col-md-6">
              <label class="d-flex align-items-start gap-2 mb-0 px-2 py-1 rounded border ${p.granted ? 'border-primary bg-blue-lt' : ''}">
                <input class="form-check-input flex-shrink-0 mt-1 perm-checkbox" type="checkbox" value="${p.id}" ${p.granted ? 'checked' : ''}
                       onchange="toggleStyle(this); updatePermStats();">
                <span>
                  <strong>${p.name}</strong>
                  ${p.description ? `<div class="text-muted small">${p.description}</div>` : ''}
                </span>
              </label>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }
  document.getElementById('perm-body').innerHTML = `<div id="save-message" class="alert mb-3" style="display:none"></div>` + html;
}

function toggleStyle(checkbox) {
  const label = checkbox.closest('label');
  label.classList.toggle('border-primary', checkbox.checked);
  label.classList.toggle('bg-blue-lt', checkbox.checked);
}

document.getElementById('btn-select-all').addEventListener('click', () => {
  document.querySelectorAll('.perm-checkbox').forEach(cb => { cb.checked = true; toggleStyle(cb); });
  updatePermStats();
});
document.getElementById('btn-clear-all').addEventListener('click', () => {
  document.querySelectorAll('.perm-checkbox').forEach(cb => { cb.checked = false; toggleStyle(cb); });
  updatePermStats();
});

document.getElementById('btn-save').addEventListener('click', async () => {
  if (!activeRoleId) return;
  const checked = [...document.querySelectorAll('.perm-checkbox:checked')].map(cb => parseInt(cb.value));
  const btn = document.getElementById('btn-save');
  btn.disabled = true;
  btn.textContent = 'Saving...';
  hideSaveMessage();

  try {
    const res = await fetch(UPDATE_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ role_id: activeRoleId, permission_ids: checked }),
    });
    const data = await res.json();
    showSaveMessage(data.success, data.message);
  } catch {
    showSaveMessage(false, 'Network error. Please try again.');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Save Permissions';
  }
});

function showSaveMessage(success, msg) {
  const el = document.getElementById('save-message');
  el.className = `alert ${success ? 'alert-success' : 'alert-danger'}`;
  el.textContent = msg;
  el.style.display = '';
  if (success) setTimeout(() => { el.style.display = 'none'; }, 3000);
}
function hideSaveMessage() {
  const el = document.getElementById('save-message');
  if (el) el.style.display = 'none';
}

loadRoles();
</script>
<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
