<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.departments.manage');
require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';
?>

<style>
#table-body td { padding-top: .3rem; padding-bottom: .3rem; font-size: .875rem; }

.tag-selector {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: .3rem;
  padding: .35rem .5rem;
  border: 1px solid var(--tblr-border-color);
  border-radius: var(--tblr-border-radius);
  background: #fff;
  cursor: text;
  min-height: 38px;
  position: relative;
}
.tag-selector:focus-within { border-color: var(--tblr-primary); box-shadow: 0 0 0 .2rem rgba(32,107,196,.15); }
.tag-selector .tag {
  display: inline-flex; align-items: center; gap: .25rem;
  background: var(--tblr-primary); color: #fff;
  padding: .1rem .45rem; border-radius: 4px; font-size: .78rem;
}
.tag-selector .tag .tag-remove {
  cursor: pointer; opacity: .75; font-size: .85rem; line-height: 1;
  background: none; border: none; color: #fff; padding: 0;
}
.tag-selector .tag .tag-remove:hover { opacity: 1; }
.tag-selector input {
  border: none; outline: none; flex: 1; min-width: 120px;
  font-size: .875rem; background: transparent; padding: .1rem 0;
}
.tag-dropdown {
  position: absolute; left: 0; right: 0; top: 100%;
  background: #fff; border: 1px solid var(--tblr-border-color);
  border-top: none; border-radius: 0 0 var(--tblr-border-radius) var(--tblr-border-radius);
  max-height: 180px; overflow-y: auto; z-index: 1060;
  box-shadow: 0 4px 12px rgba(0,0,0,.08);
}
.tag-dropdown .tag-opt {
  padding: .4rem .7rem; cursor: pointer; font-size: .85rem;
}
.tag-dropdown .tag-opt:hover { background: var(--tblr-primary); color: #fff; }
.tag-dropdown .tag-opt.disabled { color: var(--tblr-secondary); cursor: default; font-style: italic; }
</style>

  <div class="page-body">
    <div class="container-xl">

      <!-- Page identity card -->
      <div class="card mb-3" style="border-left: 4px solid var(--tblr-primary);">
        <div class="card-body py-3">
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="me-auto">
              <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.68rem;letter-spacing:.1em;">Cashiering &middot; Master Data</div>
              <div class="fw-bold" style="font-size:1.05rem;line-height:1.2;">Departments</div>
            </div>
            <input type="text" id="search-input" class="form-control form-control-sm" style="max-width:200px;" placeholder="Search...">
            <div id="stats-area" class="d-flex gap-2">
              <span class="badge bg-success-lt">Active: 0</span>
              <span class="badge bg-secondary-lt">Inactive: 0</span>
            </div>
            <a href="<?= url('views/cashiering/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Cashiering</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-add">Add Department</button>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-body p-0">
          <div id="table-message" class="alert m-3" style="display:none"></div>
          <table class="table table-vcenter table-hover card-table">
            <thead>
              <tr>
                <th data-col="code">Code</th>
                <th data-col="name">Name</th>
                <th data-col="ministry_name">Ministry</th>
                <th class="text-center" data-col="bank_account_count">Bank Accounts</th>
                <th class="text-center" data-col="cost_center_count">Cost Centers</th>
                <th data-col="status">Status</th>
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

<!-- ══════════════════ ADD MODAL ══════════════════ -->
<div class="modal modal-blur fade" id="modal-add" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Department</h5>
        <label class="form-check d-flex align-items-center gap-1 me-2 mb-0">
          <input class="form-check-input m-0" type="checkbox" id="add-active-check" checked>
          <span class="form-check-label" style="font-size:.8125rem;">Active</span>
        </label>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="add-message" class="alert" style="display:none"></div>
        <div class="mb-3">
          <label class="form-label">Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="add-name" placeholder="Department name">
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Ministry</label>
            <input type="text" class="form-control" id="add-ministry_name" placeholder="Ministry name">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Short Name</label>
            <input type="text" class="form-control" id="add-short_name" placeholder="e.g. MOF">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea class="form-control" id="add-description" rows="2" placeholder="Optional description"></textarea>
        </div>
        <hr class="my-3">

        <div class="mb-3">
          <label class="form-label">Bank Accounts</label>
          <div class="tag-selector" id="add-bank-selector">
            <input type="text" placeholder="Search bank accounts…" id="add-bank-input" autocomplete="off">
            <div class="tag-dropdown" id="add-bank-dropdown" style="display:none"></div>
          </div>
        </div>
        <div class="mb-1">
          <label class="form-label">Cost Centers</label>
          <div class="tag-selector" id="add-cc-selector">
            <input type="text" placeholder="Search cost centers…" id="add-cc-input" autocomplete="off">
            <div class="tag-dropdown" id="add-cc-dropdown" style="display:none"></div>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-link link-secondary me-auto" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="add-save-btn">Save</button>
      </div>
    </div>
  </div>
</div>

<script>
const LIST_URL    = "<?= url('api/master-data/departments/list.php') ?>";
const CREATE_URL  = "<?= url('api/master-data/departments/create.php') ?>";
const BA_LIST_URL = "<?= url('api/master-data/bank-accounts/list.php') ?>";
const CC_LIST_URL = "<?= url('api/master-data/cost-centers/list.php') ?>";
const DEPT_PAGE   = "<?= url('views/cashiering/master-data/departments/details.php') ?>";

let allRows = [];
let allBankAccounts = [];
let allCostCenters  = [];

// ── Tag selector ────────────────────────────────────────────────────────────
function makeTagSelector(selectorId, inputId, dropdownId, getOptions, getSelected, setSelected) {
  const selector = document.getElementById(selectorId);
  const input    = document.getElementById(inputId);
  const dropdown = document.getElementById(dropdownId);

  function renderTags() {
    selector.querySelectorAll('.tag').forEach(t => t.remove());
    getSelected().forEach(item => {
      const tag = document.createElement('span');
      tag.className = 'tag';
      tag.innerHTML = `${escHtml(item.label)}<button class="tag-remove" data-id="${item.id}" title="Remove">&times;</button>`;
      selector.insertBefore(tag, input);
    });
  }

  function renderDropdown(filter = '') {
    const selected = getSelected();
    const selectedIds = selected.map(s => s.id);
    const opts = getOptions().filter(o =>
      !selectedIds.includes(o.id) &&
      (!filter || o.label.toLowerCase().includes(filter.toLowerCase()))
    );
    if (!opts.length) {
      dropdown.innerHTML = `<div class="tag-opt disabled">${filter ? 'No matches' : 'No options available'}</div>`;
    } else {
      dropdown.innerHTML = opts.map(o =>
        `<div class="tag-opt" data-id="${o.id}" data-label="${escHtml(o.label)}">${escHtml(o.label)}</div>`
      ).join('');
      dropdown.querySelectorAll('.tag-opt:not(.disabled)').forEach(el => {
        el.addEventListener('click', () => {
          setSelected([...getSelected(), { id: parseInt(el.dataset.id), label: el.dataset.label }]);
          input.value = '';
          renderTags();
          renderDropdown('');
        });
      });
    }
    dropdown.style.display = '';
  }

  input.addEventListener('focus', () => renderDropdown(input.value));
  input.addEventListener('input', () => renderDropdown(input.value));

  selector.addEventListener('click', e => {
    if (e.target.classList.contains('tag-remove')) {
      const removeId = parseInt(e.target.dataset.id);
      setSelected(getSelected().filter(s => s.id !== removeId));
      renderTags();
      renderDropdown(input.value);
      return;
    }
    input.focus();
  });

  document.addEventListener('click', e => {
    if (!selector.contains(e.target)) dropdown.style.display = 'none';
  });

  return { renderTags, renderDropdown, reset() { setSelected([]); input.value = ''; renderTags(); dropdown.style.display = 'none'; } };
}

// ── State ────────────────────────────────────────────────────────────────────
let addBankSelected = [];
let addCcSelected   = [];

const addBankSel = makeTagSelector(
  'add-bank-selector', 'add-bank-input', 'add-bank-dropdown',
  () => allBankAccounts, () => addBankSelected, v => { addBankSelected = v; }
);
const addCcSel = makeTagSelector(
  'add-cc-selector', 'add-cc-input', 'add-cc-dropdown',
  () => allCostCenters, () => addCcSelected, v => { addCcSelected = v; }
);

// ── Load reference data ───────────────────────────────────────────────────────
async function loadReferenceData() {
  const [baRes, ccRes] = await Promise.all([
    apiGet(BA_LIST_URL + '?status=active'),
    apiGet(CC_LIST_URL),
  ]);
  allBankAccounts = (baRes.data || []).map(b => ({
    id: b.id,
    label: `${b.bank_name} — ${b.account_name} (${b.account_number})`,
  }));
  allCostCenters = (ccRes.data || []).map(c => ({
    id: c.id,
    label: `${c.name} (${c.code})`,
  }));
}

// ── Table ─────────────────────────────────────────────────────────────────────
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
function countBadge(n, color, type) {
  const num = parseInt(n) || 0;
  if (num === 0) {
    return `<span class="text-muted" style="font-size:.78rem;">—</span>`;
  }
  const icons = {
    'bank':        '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px"><path d="M3 21l18 0"/><path d="M3 10l18 0"/><path d="M5 6l7-3l7 3"/><path d="M4 10l0 11"/><path d="M20 10l0 11"/><path d="M8 14l0 3"/><path d="M12 14l0 3"/><path d="M16 14l0 3"/></svg>',
    'cost-center': '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px"><path d="M5 4h4a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1"/><path d="M15 12h4a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1"/><path d="M5 16h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1"/><path d="M15 4h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1"/></svg>',
  };
  return `<span class="badge bg-${color}-lt text-${color}" style="font-size:.75rem;font-weight:600;gap:3px;">
    ${icons[type] ?? ''} ${num}
  </span>`;
}

function statusBadge(val) {
  const ok = (val === 'active' || val === 1 || val === '1' || val === true);
  return ok
    ? '<span class="badge bg-success-lt text-success">Active</span>'
    : '<span class="badge bg-secondary-lt text-secondary">Inactive</span>';
}
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function loadRows(search = '') {
  const url = LIST_URL + (search ? `?search=${encodeURIComponent(search)}` : '');
  try {
    const res = await apiGet(url);
    allRows = res.data || [];
    renderTable(allRows);

    // Update stats
    const active = allRows.filter(r => r.status === 'active').length;
    const inactive = allRows.filter(r => r.status === 'inactive').length;
    document.getElementById('stats-area').innerHTML = `
      <span class="badge bg-success-lt">Active: ${active}</span>
      <span class="badge bg-secondary-lt">Inactive: ${inactive}</span>
    `;
  } catch (e) {
    showMsg('table-message', e.message);
  }
}

function renderRow(r) {
  return `<tr>
    <td class="text-muted" style="font-size:.82rem;font-family:monospace;">${r.code ?? ''}</td>
    <td class="fw-medium">${r.name ?? ''}</td>
    <td class="text-muted" style="font-size:.85rem;">${r.ministry_name ?? '—'}</td>
    <td class="text-center">
      ${countBadge(r.bank_account_count, 'primary', 'bank')}
    </td>
    <td class="text-center">
      ${countBadge(r.cost_center_count, 'teal', 'cost-center')}
    </td>
    <td>${statusBadge(r.status)}</td>
    <td>
      <a href="${DEPT_PAGE}?id=${r.id}" class="btn btn-sm btn-outline-secondary">Open →</a>
    </td>
  </tr>`;
}

function renderTable(rows) {
  window.pager.setData(rows);
}

// ── Add ───────────────────────────────────────────────────────────────────────
document.getElementById('add-save-btn').addEventListener('click', async () => {
  clearMsg('add-message');
  const btn = document.getElementById('add-save-btn');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    await apiPost(CREATE_URL, {
      name:            document.getElementById('add-name').value,
      ministry_name:   document.getElementById('add-ministry_name').value,
      short_name:      document.getElementById('add-short_name').value,
      description:     document.getElementById('add-description').value,
      status:          document.getElementById('add-active-check').checked ? 'active' : 'inactive',
      bank_account_ids: addBankSelected.map(s => s.id),
      cost_center_ids:  addCcSelected.map(s => s.id),
    });
    tabler.Modal.getInstance(document.getElementById('modal-add'))?.hide();
    loadRows();
  } catch (e) {
    showMsg('add-message', e.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Save';
  }
});

// ── Reset add modal on close ──────────────────────────────────────────────────
document.getElementById('modal-add').addEventListener('hidden.bs.modal', () => {
  clearMsg('add-message');
  document.getElementById('add-active-check').checked = true;
  document.getElementById('modal-add').querySelectorAll('input,select,textarea').forEach(el => {
    if (el.type === 'checkbox' || el.id === 'add-bank-input' || el.id === 'add-cc-input') return;
    el.value = el.tagName === 'SELECT' ? (el.options[0]?.value ?? '') : '';
  });
  addBankSel.reset();
  addCcSel.reset();
});

document.getElementById('search-input').addEventListener('input', function () {
  loadRows(this.value.trim());
});

document.addEventListener('DOMContentLoaded', async () => {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 7, renderRow });
  await loadReferenceData();
  loadRows();
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
