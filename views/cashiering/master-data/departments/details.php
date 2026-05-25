<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.departments.manage');

$id = (int)($_GET['id'] ?? 0);

require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-body">
    <div class="container-xl">

      <?php if ($id <= 0): ?>
        <div class="alert alert-danger">Invalid department ID.</div>
      <?php else: ?>

        <!-- Page identity card -->
        <div class="card mb-3" style="border-left: 4px solid var(--tblr-primary);">
          <div class="card-body py-3">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.68rem;letter-spacing:.1em;">Cashiering &middot; Master Data &middot; Departments</div>
                <div class="fw-bold" id="page-subtitle" style="font-size:1.05rem;line-height:1.2;">Loading...</div>
              </div>
              <a href="<?= url('views/cashiering/master-data/departments/index.php') ?>" class="btn btn-outline-secondary btn-sm">← Back to Departments</a>
            </div>
          </div>
        </div>

        <div id="page-message" class="alert mb-3" style="display:none;"></div>

        <!-- Department info -->
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title" id="dept-heading">Loading...</h3>
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
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Ministry</dt>
              <dd class="col-sm-9 mb-2" id="v-ministry">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Short Name</dt>
              <dd class="col-sm-9 mb-2" id="v-short-name">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Description</dt>
              <dd class="col-sm-9 mb-2" id="v-description">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Status</dt>
              <dd class="col-sm-9 mb-0" id="v-status">—</dd>
            </dl>

            <!-- Edit mode -->
            <div id="edit-mode" style="display:none;">
              <div class="row g-3">
                <div class="col-md-8">
                  <label class="form-label">Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="edit-name">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Short Name</label>
                  <input type="text" class="form-control" id="edit-short-name" placeholder="e.g. MOF">
                </div>
                <div class="col-md-8">
                  <label class="form-label">Ministry</label>
                  <input type="text" class="form-control" id="edit-ministry">
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
                  <textarea class="form-control" id="edit-description" rows="2"></textarea>
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
              <div class="col-md-6">
                <label class="form-label form-label-sm">Bank Account</label>
                <select class="form-select form-select-sm" id="bank-select">
                  <option value="">Loading...</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label form-label-sm">Default</label>
                <select class="form-select form-select-sm" id="bank-default">
                  <option value="0">No</option>
                  <option value="1">Yes</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label form-label-sm">Status</label>
                <select class="form-select form-select-sm" id="bank-status">
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
              <div class="col-md-2">
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
                  <th class="text-center">Default</th>
                  <th class="w-1"></th>
                </tr>
              </thead>
              <tbody id="bank-tbody">
                <tr><td colspan="6" class="text-muted text-center py-3">Loading...</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Cost Centers -->
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title">Cost Centers</h3>
          </div>
          <div class="card-body">
            <div id="cc-message" class="alert mb-3" style="display:none;"></div>
            <div class="row g-2 align-items-end mb-3">
              <div class="col-md-8">
                <label class="form-label form-label-sm">Cost Center</label>
                <select class="form-select form-select-sm" id="cc-select">
                  <option value="">Loading...</option>
                </select>
              </div>
              <div class="col-md-4">
                <button class="btn btn-primary btn-sm w-100" id="assign-cc-btn">Assign</button>
              </div>
            </div>
            <table class="table table-sm table-vcenter table-hover card-table" style="font-size:.85rem;">
              <thead>
                <tr>
                  <th>Code</th>
                  <th>Name</th>
                  <th>Sub-Treasury</th>
                  <th>Status</th>
                  <th class="w-1"></th>
                </tr>
              </thead>
              <tbody id="cc-tbody">
                <tr><td colspan="5" class="text-muted text-center py-3">Loading...</td></tr>
              </tbody>
            </table>
          </div>
        </div>

      <?php endif; ?>
    </div>
  </div>

<script>
const DEPT_ID         = <?= $id ?>;
const DETAILS_URL     = "<?= url('api/master-data/departments/details.php') ?>";
const UPDATE_URL      = "<?= url('api/master-data/departments/update.php') ?>";
const BA_LIST_URL     = "<?= url('api/master-data/bank-accounts/list.php') ?>";
const CC_LIST_URL     = "<?= url('api/master-data/cost-centers/list.php') ?>";
const ASSIGN_BANK_URL = "<?= url('api/master-data/departments/assign-bank-account.php') ?>";
const REMOVE_BANK_URL = "<?= url('api/master-data/departments/remove-bank-account.php') ?>";
const ASSIGN_CC_URL   = "<?= url('api/master-data/departments/assign-cost-center.php') ?>";
const REMOVE_CC_URL   = "<?= url('api/master-data/departments/remove-cost-center.php') ?>";

function showMsg(id, msg, type = 'danger') {
  const el = document.getElementById(id);
  el.className = `alert alert-${type}`;
  el.textContent = msg;
  el.style.display = 'block';
}
function clearMsg(id) {
  const el = document.getElementById(id);
  el.textContent = '';
  el.style.display = 'none';
}
function statusBadge(val) {
  return val === 'active'
    ? '<span class="badge bg-success-lt text-success">Active</span>'
    : '<span class="badge bg-secondary-lt text-secondary">Inactive</span>';
}

let deptData = null;

async function loadDetails() {
  const res = await apiGet(DETAILS_URL + '?id=' + DEPT_ID);
  const d   = res.data;
  deptData  = d.department;
  const banks = d.bank_accounts || [];
  const ccs   = d.cost_centers  || [];

  document.getElementById('dept-heading').textContent  = deptData.name ?? 'Department';
  document.getElementById('page-subtitle').textContent = deptData.name ?? 'Department Details';

  document.getElementById('v-code').textContent        = deptData.code          ?? '—';
  document.getElementById('v-name').textContent        = deptData.name          ?? '—';
  document.getElementById('v-ministry').textContent    = deptData.ministry_name ?? '—';
  document.getElementById('v-short-name').textContent  = deptData.short_name    ?? '—';
  document.getElementById('v-description').textContent = deptData.description   ?? '—';
  document.getElementById('v-status').innerHTML        = statusBadge(deptData.status);

  // Bank accounts
  const bankTbody = document.getElementById('bank-tbody');
  if (!banks.length) {
    bankTbody.innerHTML = '<tr><td colspan="6" class="text-muted text-center py-3">No bank accounts assigned.</td></tr>';
  } else {
    bankTbody.innerHTML = banks.map(b => `<tr>
      <td>${b.bank_name ?? '—'}</td>
      <td>${b.account_name ?? '—'}</td>
      <td style="font-family:monospace;">${b.account_number ?? b.account_masked ?? '—'}</td>
      <td>${b.currency_code ?? '—'}</td>
      <td class="text-center">${Number(b.is_default) === 1 ? '<span class="badge bg-primary-lt text-primary">Default</span>' : '—'}</td>
      <td>
        <button class="btn btn-sm btn-ghost-danger" data-remove-bank="${b.id}" title="Remove">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        </button>
      </td>
    </tr>`).join('');
    bankTbody.querySelectorAll('[data-remove-bank]').forEach(btn => {
      btn.addEventListener('click', async () => {
        if (!confirm('Remove this bank account from the department?')) return;
        try {
          await apiPost(REMOVE_BANK_URL, { id: btn.dataset.removeBank });
          await loadDetails();
        } catch (e) { showMsg('bank-message', e.message); }
      });
    });
  }

  // Cost centers
  const ccTbody = document.getElementById('cc-tbody');
  if (!ccs.length) {
    ccTbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center py-3">No cost centers assigned.</td></tr>';
  } else {
    ccTbody.innerHTML = ccs.map(c => `<tr>
      <td style="font-family:monospace;">${c.code ?? '—'}</td>
      <td>${c.name ?? '—'}</td>
      <td>${c.sub_treasury_name ?? '—'}</td>
      <td>${statusBadge(c.status)}</td>
      <td>
        <button class="btn btn-sm btn-ghost-danger" data-remove-cc="${c.id}" title="Remove">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        </button>
      </td>
    </tr>`).join('');
    ccTbody.querySelectorAll('[data-remove-cc]').forEach(btn => {
      btn.addEventListener('click', async () => {
        if (!confirm('Remove this cost center from the department?')) return;
        try {
          await apiPost(REMOVE_CC_URL, { id: btn.dataset.removeCc });
          await loadDetails();
        } catch (e) { showMsg('cc-message', e.message); }
      });
    });
  }
}

// View / Edit toggle
const editToggleBtn = document.getElementById('edit-toggle-btn');
const viewMode      = document.getElementById('view-mode');
const editMode      = document.getElementById('edit-mode');

function enterEditMode() {
  if (deptData) {
    document.getElementById('edit-name').value        = deptData.name          ?? '';
    document.getElementById('edit-short-name').value  = deptData.short_name    ?? '';
    document.getElementById('edit-ministry').value    = deptData.ministry_name ?? '';
    document.getElementById('edit-status').value      = deptData.status        ?? 'active';
    document.getElementById('edit-description').value = deptData.description   ?? '';
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

editToggleBtn.addEventListener('click', () => {
  editMode.style.display !== 'none' ? exitEditMode() : enterEditMode();
});

document.getElementById('cancel-btn').addEventListener('click', exitEditMode);

document.getElementById('save-btn').addEventListener('click', async () => {
  clearMsg('edit-message');
  const btn = document.getElementById('save-btn');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    await apiPost(UPDATE_URL, {
      id:            DEPT_ID,
      code:          deptData?.code ?? '',
      name:          document.getElementById('edit-name').value,
      short_name:    document.getElementById('edit-short-name').value,
      ministry_name: document.getElementById('edit-ministry').value,
      status:        document.getElementById('edit-status').value,
      description:   document.getElementById('edit-description').value,
    });
    exitEditMode();
    await loadDetails();
    showMsg('page-message', 'Department updated successfully.', 'success');
    setTimeout(() => clearMsg('page-message'), 3000);
  } catch (e) {
    showMsg('edit-message', e.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Save Changes';
  }
});

// Assign bank account
document.getElementById('assign-bank-btn').addEventListener('click', async () => {
  const bankId = document.getElementById('bank-select').value;
  if (!bankId) { showMsg('bank-message', 'Please select a bank account.', 'warning'); return; }
  clearMsg('bank-message');
  const btn = document.getElementById('assign-bank-btn');
  btn.disabled = true; btn.textContent = 'Assigning...';
  try {
    await apiPost(ASSIGN_BANK_URL, {
      department_id:   DEPT_ID,
      bank_account_id: bankId,
      is_default:      document.getElementById('bank-default').value,
      status:          document.getElementById('bank-status').value,
    });
    showMsg('bank-message', 'Bank account assigned.', 'success');
    await loadDetails();
    setTimeout(() => clearMsg('bank-message'), 2500);
  } catch (e) {
    showMsg('bank-message', e.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Assign';
  }
});

// Assign cost center
document.getElementById('assign-cc-btn').addEventListener('click', async () => {
  const ccId = document.getElementById('cc-select').value;
  if (!ccId) { showMsg('cc-message', 'Please select a cost center.', 'warning'); return; }
  clearMsg('cc-message');
  const btn = document.getElementById('assign-cc-btn');
  btn.disabled = true; btn.textContent = 'Assigning...';
  try {
    await apiPost(ASSIGN_CC_URL, { department_id: DEPT_ID, cost_center_id: ccId });
    showMsg('cc-message', 'Cost center assigned.', 'success');
    await loadDetails();
    setTimeout(() => clearMsg('cc-message'), 2500);
  } catch (e) {
    showMsg('cc-message', e.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Assign';
  }
});

document.addEventListener('DOMContentLoaded', async () => {
  if (!DEPT_ID) return;
  try {
    const [baRes, ccRes] = await Promise.all([
      apiGet(BA_LIST_URL + '?status=active'),
      apiGet(CC_LIST_URL),
    ]);

    const bankSelect = document.getElementById('bank-select');
    bankSelect.innerHTML = '<option value="">Select bank account...</option>';
    (baRes.data || []).forEach(b => {
      const o = document.createElement('option');
      o.value = b.id;
      o.textContent = `${b.bank_name} — ${b.account_name} (${b.account_number})`;
      bankSelect.appendChild(o);
    });

    const ccSelect = document.getElementById('cc-select');
    ccSelect.innerHTML = '<option value="">Select cost center...</option>';
    (ccRes.data || []).forEach(c => {
      const o = document.createElement('option');
      o.value = c.id;
      o.textContent = `${c.code} — ${c.name}`;
      ccSelect.appendChild(o);
    });

    await loadDetails();
  } catch (e) {
    showMsg('page-message', e.message);
  }
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
