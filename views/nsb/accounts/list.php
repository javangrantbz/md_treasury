<?php
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/helpers.php';
Auth::requireAuth();
require_once __DIR__ . '/../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../includes/layout-tabler-sidebar.php';
?>
<div class="page-header d-print-none">
  <div class="container-xl">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">NSB — Savings</div>
        <h2 class="page-title">Savings Accounts</h2>
      </div>
      <div class="col-auto ms-auto">
        <a href="<?= url('views/nsb/accounts/add.php') ?>" class="btn btn-primary">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
          Add / Import Accounts
        </a>
      </div>
    </div>
  </div>
</div>
<div class="page-body">
  <div class="container-xl">
    <div class="card">
      <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs" id="status-tabs">
          <li class="nav-item"><a href="#" class="nav-link active" data-status="">All</a></li>
          <li class="nav-item"><a href="#" class="nav-link" data-status="active">Active</a></li>
          <li class="nav-item"><a href="#" class="nav-link" data-status="dormant">Dormant</a></li>
          <li class="nav-item"><a href="#" class="nav-link" data-status="closed">Closed</a></li>
        </ul>
        <div class="card-options">
          <input type="text" id="search-input" class="form-control form-control-sm" placeholder="Search name or account #..." style="width:230px;">
        </div>
      </div>
      <div class="card-body p-0">
        <div id="table-message" class="alert m-3" style="display:none"></div>
        <div class="table-responsive">
          <table class="table table-vcenter table-hover card-table">
            <thead>
              <tr>
                <th>Account #</th>
                <th>Customer</th>
                <th>Branch</th>
                <th class="text-end">Starting Balance</th>
                <th class="text-end">Current Balance</th>
                <th>Opened</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="table-body">
              <tr><td colspan="7" class="text-center py-4 text-muted">Loading...</td></tr>
            </tbody>
            <tfoot id="table-footer" style="display:none;">
              <tr class="fw-bold" style="background:#f6f8f1;">
                <td colspan="4" class="text-end">Total Current Balance</td>
                <td class="text-end" id="balance-grand">—</td>
                <td colspan="2"></td>
              </tr>
            </tfoot>
          </table>
        </div>
        <div id="table-pagination"></div>
      </div>
    </div>
  </div>
</div>

<script>
const LIST_URL = "<?= url('api/nsb/accounts/list.php') ?>";
let currentStatus = '';
let searchTimer;

function fmt(n) {
  if (n === null || n === undefined || n === '') return '—';
  return parseFloat(n).toLocaleString('en-BZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function esc(s) { const d = document.createElement('div'); d.textContent = (s == null ? '' : s); return d.innerHTML; }

function renderRow(r) {
  const colors = { active: 'success', dormant: 'warning', closed: 'secondary' };
  const badge = colors[r.status] || 'secondary';
  const branch = r.branch_name ? esc(r.branch_name) : '<span class="text-muted">—</span>';
  return `<tr>
    <td><span class="text-muted" style="font-family:monospace;">${esc(r.account_number) || '—'}</span></td>
    <td class="fw-medium">${esc(r.customer_name)}</td>
    <td class="small">${branch}</td>
    <td class="text-end">BZD ${fmt(r.starting_balance)}</td>
    <td class="text-end fw-semibold">BZD ${fmt(r.current_balance)}</td>
    <td class="text-muted small">${r.starting_date || '—'}</td>
    <td><span class="badge bg-${badge}-lt text-capitalize">${esc(r.status)}</span></td>
  </tr>`;
}

function loadRows() {
  const params = [];
  if (currentStatus) params.push('status=' + encodeURIComponent(currentStatus));
  const term = document.getElementById('search-input').value.trim();
  if (term) params.push('search=' + encodeURIComponent(term));
  const url = LIST_URL + (params.length ? '?' + params.join('&') : '');

  apiGet(url).then(function(res) {
    const rows = res.data || [];
    window.pager.setData(rows);
    let grand = 0;
    rows.forEach(function(r) { grand += parseFloat(r.current_balance || 0); });
    document.getElementById('balance-grand').textContent = 'BZD ' + fmt(grand);
    document.getElementById('table-footer').style.display = rows.length ? 'table-footer-group' : 'none';
  }).catch(function(e) {
    const m = document.getElementById('table-message');
    m.className = 'alert alert-danger m-3'; m.textContent = e.message; m.style.display = 'block';
  });
}

document.querySelectorAll('#status-tabs .nav-link').forEach(function(link) {
  link.addEventListener('click', function(e) {
    e.preventDefault();
    document.querySelectorAll('#status-tabs .nav-link').forEach(function(l){ l.classList.remove('active'); });
    this.classList.add('active');
    currentStatus = this.dataset.status;
    loadRows();
  });
});
document.getElementById('search-input').addEventListener('input', function() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(loadRows, 250);
});

document.addEventListener('DOMContentLoaded', function() {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 7, renderRow });
  loadRows();
});
</script>
<?php require_once __DIR__ . '/../../../includes/layout-tabler-footer.php'; ?>
