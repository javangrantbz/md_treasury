<?php
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/helpers.php';
Auth::requireAuth();
require_once __DIR__ . '/../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../includes/layout-tabler-sidebar.php';
?>

<div class="page-body">
  <div class="container-xl">

    <!-- Header card: title | stats | search -->
    <div class="card mb-3" style="border-left:4px solid var(--tblr-primary);">
      <div class="card-body py-3">
        <div style="display:flex;align-items:center;flex-wrap:nowrap;overflow-x:auto;gap:0;">

          <!-- Title -->
          <div style="flex-shrink:0;padding-right:.5rem;">
            <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.68rem;letter-spacing:.1em;white-space:nowrap;">NSB &mdash; Customers</div>
            <div class="fw-bold" style="font-size:1.05rem;line-height:1.2;white-space:nowrap;">Customer List</div>
          </div>

          <!-- Divider -->
          <div style="width:1px;background:#e2e8f0;align-self:stretch;flex-shrink:0;margin:0 .75rem;"></div>

          <!-- Stats -->
          <div style="padding:0 12px;flex-shrink:0;">
            <div style="font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#9ca3af;margin-bottom:3px;white-space:nowrap;">Total</div>
            <div style="font-size:1.05rem;font-weight:900;line-height:1;color:var(--tblr-body-color);" id="stat-total">&mdash;</div>
          </div>
          <div style="width:1px;background:#e2e8f0;align-self:stretch;flex-shrink:0;"></div>
          <div style="padding:0 12px;flex-shrink:0;">
            <div style="font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#9ca3af;margin-bottom:3px;white-space:nowrap;">Active</div>
            <div style="font-size:1.05rem;font-weight:900;line-height:1;color:var(--tblr-success);" id="stat-active">&mdash;</div>
          </div>
          <div style="width:1px;background:#e2e8f0;align-self:stretch;flex-shrink:0;"></div>
          <div style="padding:0 12px;flex-shrink:0;">
            <div style="font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#9ca3af;margin-bottom:3px;white-space:nowrap;">Inactive</div>
            <div style="font-size:1.05rem;font-weight:900;line-height:1;color:var(--tblr-secondary);" id="stat-inactive">&mdash;</div>
          </div>

          <!-- Divider -->
          <div style="width:1px;background:#e2e8f0;align-self:stretch;flex-shrink:0;margin:0 .75rem;"></div>

          <!-- Search + back -->
          <div style="display:flex;align-items:center;gap:.5rem;flex:1;min-width:0;justify-content:flex-end;">
            <input type="text" id="search-input" class="form-control form-control-sm" placeholder="Search customers..." style="width:200px;flex-shrink:0;">
            <a href="<?= url('views/nsb/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm" style="white-space:nowrap;">&#8592; Dashboard</a>
          </div>

        </div>
      </div>
    </div>

    <!-- Table card -->
    <div class="card">
      <div class="card-body p-0">
        <div id="table-message" class="alert m-3" style="display:none"></div>
        <table class="table table-vcenter table-hover card-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Username</th>
              <th>Email</th>
              <th>Type</th>
              <th>Status</th>
              <th class="w-1"></th>
            </tr>
          </thead>
          <tbody id="table-body">
            <tr><td colspan="6" class="text-center py-4 text-muted">Loading...</td></tr>
          </tbody>
        </table>
        <div id="table-pagination"></div>
      </div>
    </div>

  </div>
</div>

<script>
var LIST_URL = "<?= url('api/master-data/users/list.php') ?>";
var PROFILE_URL = "<?= url('views/nsb/customers/profile.php') ?>";

var allRows = [];

function renderRow(r) {
    var name = ((r.first_name || '') + ' ' + (r.last_name || '')).trim();
    var statusCls = r.status === 'active' ? 'bg-success-lt text-success' : 'bg-secondary-lt text-secondary';
    return '<tr>' +
        '<td>' + escHtml(name) + '</td>' +
        '<td>' + escHtml(r.username || '') + '</td>' +
        '<td>' + escHtml(r.email || '') + '</td>' +
        '<td style="text-transform:capitalize">' + escHtml(r.user_type || '') + '</td>' +
        '<td><span class="badge ' + statusCls + '">' + escHtml(r.status || '') + '</span></td>' +
        '<td><a href="' + PROFILE_URL + '?id=' + encodeURIComponent(r.id) + '" class="btn btn-sm btn-outline-secondary">Profile</a></td>' +
        '</tr>';
}

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function updateStats(rows) {
    var active = rows.filter(function(r) { return r.status === 'active'; }).length;
    document.getElementById('stat-total').textContent   = rows.length.toLocaleString();
    document.getElementById('stat-active').textContent  = active.toLocaleString();
    document.getElementById('stat-inactive').textContent = (rows.length - active).toLocaleString();
}

function applySearch() {
    var term = document.getElementById('search-input').value.trim().toLowerCase();
    var rows = term ? allRows.filter(function(r) {
        var name = ((r.first_name || '') + ' ' + (r.last_name || '')).toLowerCase();
        return name.indexOf(term) !== -1 ||
            (r.username  || '').toLowerCase().indexOf(term) !== -1 ||
            (r.email     || '').toLowerCase().indexOf(term) !== -1 ||
            (r.user_type || '').toLowerCase().indexOf(term) !== -1;
    }) : allRows;
    window.pager.setData(rows);
}

var searchTimer;
document.getElementById('search-input').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applySearch, 250);
});

document.addEventListener('DOMContentLoaded', function() {
    window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 6, renderRow: renderRow });

    apiGet(LIST_URL).then(function(res) {
        allRows = res.data || [];
        updateStats(allRows);
        applySearch();
    }).catch(function(e) {
        var el = document.getElementById('table-message');
        el.textContent = e.message;
        el.className = 'alert alert-danger m-3';
        el.style.display = 'block';
        document.getElementById('table-body').innerHTML =
            '<tr><td colspan="6" class="text-center py-4 text-danger">Failed to load customers.</td></tr>';
    });
});
</script>
<?php require_once __DIR__ . '/../../../includes/layout-tabler-footer.php'; ?>
