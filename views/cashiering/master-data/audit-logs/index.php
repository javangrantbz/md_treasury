<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.audit_logs.view');
require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row align-items-center">
        <div class="col">
          <div class="page-pretitle">Cashiering — Master Data</div>
          <h2 class="page-title">Audit Log</h2>
        </div>
        <div class="col-auto ms-auto">
          <a href="<?= url('views/cashiering/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">← Cashiering</a>
        </div>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">
      <div class="card">
        <div class="card-header flex-wrap gap-2">
          <input type="text" id="search-input" class="form-control form-control-sm w-auto" placeholder="Search description...">
          <select id="filter-action" class="form-select form-select-sm w-auto">
            <option value="">All actions</option>
          </select>
          <select id="filter-entity" class="form-select form-select-sm w-auto">
            <option value="">All entities</option>
          </select>
          <input type="date" id="filter-date-from" class="form-control form-control-sm w-auto" title="From date">
          <input type="date" id="filter-date-to"   class="form-control form-control-sm w-auto" title="To date">
          <button class="btn btn-sm btn-primary" id="apply-filters">Filter</button>
          <button class="btn btn-sm btn-outline-secondary" id="reset-filters">Reset</button>
        </div>
        <div class="card-body p-0">
          <div id="table-message" class="alert m-3" style="display:none"></div>
          <table class="table table-vcenter table-hover card-table">
            <thead>
              <tr>
                <th>Timestamp</th>
                <th>User</th>
                <th>Action</th>
                <th>Entity</th>
                <th>Description</th>
              </tr>
            </thead>
            <tbody id="table-body">
              <tr><td colspan="5" class="text-center py-4 text-muted">Loading...</td></tr>
            </tbody>
          </table>
          <div id="table-pagination"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- DETAIL modal -->
<div class="modal modal-blur fade" id="modal-detail" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Audit Log Entry</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <dl class="row" id="detail-body"></dl>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
const LIST_URL = "<?= url('api/master-data/audit-logs/list.php') ?>";

let allRows = [];

const ACTION_BADGE = {
  login:   'bg-blue-lt text-blue',
  logout:  'bg-secondary-lt text-secondary',
  create:  'bg-success-lt text-success',
  update:  'bg-warning-lt text-warning',
  delete:  'bg-danger-lt text-danger',
  approve: 'bg-teal-lt text-teal',
  submit:  'bg-purple-lt text-purple',
  pay:     'bg-green-lt text-green',
  reject:  'bg-red-lt text-red',
};

function actionBadge(action) {
  var cls = ACTION_BADGE[action] || 'bg-secondary-lt text-secondary';
  return '<span class="badge ' + cls + '" style="text-transform:capitalize">' + escHtml(action) + '</span>';
}

function escHtml(str) {
  if (str == null) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function showMsg(id, msg, type) {
  type = type || 'danger';
  var el = document.getElementById(id);
  el.className = 'alert alert-' + type;
  el.textContent = msg;
  el.style.display = 'block';
}

function buildQuery() {
  var params = [];
  var s = document.getElementById('search-input').value.trim();
  var a = document.getElementById('filter-action').value;
  var e = document.getElementById('filter-entity').value;
  var df = document.getElementById('filter-date-from').value;
  var dt = document.getElementById('filter-date-to').value;
  if (s)  params.push('search='      + encodeURIComponent(s));
  if (a)  params.push('action='      + encodeURIComponent(a));
  if (e)  params.push('entity_type=' + encodeURIComponent(e));
  if (df) params.push('date_from='   + encodeURIComponent(df));
  if (dt) params.push('date_to='     + encodeURIComponent(dt));
  return params.length ? '?' + params.join('&') : '';
}

async function loadRows() {
  try {
    var res = await apiGet(LIST_URL + buildQuery());
    allRows = res.data || [];

    // Populate action dropdown once
    var actionSel = document.getElementById('filter-action');
    var current = actionSel.value;
    actionSel.innerHTML = '<option value="">All actions</option>';
    (res.actions || []).forEach(function(a) {
      var opt = document.createElement('option');
      opt.value = a;
      opt.textContent = a.charAt(0).toUpperCase() + a.slice(1);
      if (a === current) opt.selected = true;
      actionSel.appendChild(opt);
    });

    // Populate entity type dropdown once
    var entitySel = document.getElementById('filter-entity');
    var currentE = entitySel.value;
    entitySel.innerHTML = '<option value="">All entities</option>';
    (res.entity_types || []).forEach(function(t) {
      var opt = document.createElement('option');
      opt.value = t;
      opt.textContent = t.charAt(0).toUpperCase() + t.slice(1).replace(/_/g,' ');
      if (t === currentE) opt.selected = true;
      entitySel.appendChild(opt);
    });

    window.pager.setData(allRows);
  } catch (e) {
    showMsg('table-message', e.message);
  }
}

function renderRow(r) {
  var userName = (r.user_name && r.user_name.trim() !== '')
    ? escHtml(r.user_name.trim())
    : (r.username ? escHtml(r.username) : '<span class="text-muted">System</span>');
  var entity = r.entity_type
    ? (escHtml(r.entity_type) + (r.entity_id ? ' #' + escHtml(String(r.entity_id)) : ''))
    : '—';
  var desc = r.description ? escHtml(r.description) : '<span class="text-muted">—</span>';
  var ts = r.created_at ? escHtml(r.created_at) : '—';
  return '<tr style="cursor:pointer" onclick="openDetail(' + r.id + ')">' +
    '<td style="white-space:nowrap;font-size:.85em">' + ts + '</td>' +
    '<td>' + userName + '</td>' +
    '<td>' + actionBadge(r.action) + '</td>' +
    '<td style="white-space:nowrap">' + entity + '</td>' +
    '<td class="text-truncate" style="max-width:320px">' + desc + '</td>' +
    '</tr>';
}

function openDetail(id) {
  var r = allRows.filter(function(x){ return x.id == id; })[0];
  if (!r) return;
  var userName = (r.user_name && r.user_name.trim() !== '')
    ? escHtml(r.user_name.trim()) + (r.username ? ' (' + escHtml(r.username) + ')' : '')
    : (r.username ? escHtml(r.username) : 'System');
  document.getElementById('detail-body').innerHTML =
    '<dt class="col-sm-4">Timestamp</dt><dd class="col-sm-8">' + escHtml(r.created_at) + '</dd>' +
    '<dt class="col-sm-4">User</dt><dd class="col-sm-8">' + userName + '</dd>' +
    '<dt class="col-sm-4">Action</dt><dd class="col-sm-8">' + actionBadge(r.action) + '</dd>' +
    '<dt class="col-sm-4">Entity Type</dt><dd class="col-sm-8">' + escHtml(r.entity_type || '—') + '</dd>' +
    '<dt class="col-sm-4">Entity ID</dt><dd class="col-sm-8">' + (r.entity_id != null ? escHtml(String(r.entity_id)) : '—') + '</dd>' +
    '<dt class="col-sm-4">Description</dt><dd class="col-sm-8">' + escHtml(r.description || '—') + '</dd>' +
    '<dt class="col-sm-4">IP Address</dt><dd class="col-sm-8">' + escHtml(r.ip_address || '—') + '</dd>';
  tabler.Modal.getOrCreateInstance(document.getElementById('modal-detail')).show();
}

document.getElementById('apply-filters').addEventListener('click', function() { loadRows(); });
document.getElementById('reset-filters').addEventListener('click', function() {
  document.getElementById('search-input').value = '';
  document.getElementById('filter-action').value = '';
  document.getElementById('filter-entity').value = '';
  document.getElementById('filter-date-from').value = '';
  document.getElementById('filter-date-to').value = '';
  loadRows();
});
document.getElementById('search-input').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') loadRows();
});

document.addEventListener('DOMContentLoaded', function() {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 5, renderRow: renderRow });
  loadRows();
});
</script>
<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
