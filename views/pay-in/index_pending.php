<?php
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::requireAuth();
require_once __DIR__ . '/../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row align-items-center">
        <div class="col">
          <div class="page-pretitle">Government of Belize — Treasury Department</div>
          <h2 class="page-title">Pay-In (POS Transactions)</h2>
        </div>
        <div class="col-auto ms-auto d-print-none">
          <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">Print / Export</button>
        </div>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">

      <!-- KPI summary cards -->
      <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
          <div class="card card-sm">
            <div class="card-body">
              <div class="row align-items-center">
                <div class="col-auto">
                  <span class="bg-primary text-white avatar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                  </span>
                </div>
                <div class="col">
                  <div class="font-weight-medium">Total Collected</div>
                  <div class="text-muted" id="kpi-total">—</div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="card card-sm">
            <div class="card-body">
              <div class="row align-items-center">
                <div class="col-auto">
                  <span class="bg-info text-white avatar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                  </span>
                </div>
                <div class="col">
                  <div class="font-weight-medium">Transactions</div>
                  <div class="text-muted" id="kpi-count">—</div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="card card-sm">
            <div class="card-body">
              <div class="row align-items-center">
                <div class="col-auto">
                  <span class="bg-success text-white avatar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                  </span>
                </div>
                <div class="col">
                  <div class="font-weight-medium">Completed</div>
                  <div class="text-muted" id="kpi-completed">—</div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="card card-sm">
            <div class="card-body">
              <div class="row align-items-center">
                <div class="col-auto">
                  <span class="bg-danger text-white avatar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                  </span>
                </div>
                <div class="col">
                  <div class="font-weight-medium">Voided / Refunded</div>
                  <div class="text-muted" id="kpi-voided">—</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Table card -->
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div class="d-flex gap-2 align-items-center">
            <input type="text" id="search-input" class="form-control form-control-sm w-auto" placeholder="Search customer, dept, shift...">
            <div id="stats-area" class="d-flex gap-2 ms-2">
              <span class="badge bg-success-lt">Completed: 0</span>
              <span class="badge bg-danger-lt">Voided: 0</span>
              <span class="badge bg-warning-lt">Refunded: 0</span>
            </div>
          </div>
          <div class="d-flex gap-2 align-items-center">
            <select id="filter-status" class="form-select form-select-sm w-auto">
            <option value="">All Statuses</option>
            <option value="completed">Completed</option>
            <option value="voided">Voided</option>
            <option value="refunded">Refunded</option>
          </select>
          <input type="date" id="filter-date-from" class="form-control form-control-sm w-auto" title="From date">
          <input type="date" id="filter-date-to"   class="form-control form-control-sm w-auto" title="To date">
          <button class="btn btn-sm btn-outline-secondary" id="clear-filters-btn">Clear</button>
        </div>
        <div class="card-body p-0">
          <div id="table-message" class="alert m-3" style="display:none"></div>
          <table class="table table-vcenter table-hover card-table">
            <thead>
              <tr>
                <th data-col="id">#</th>
                <th data-col="customer_name">Customer</th>
                <th data-col="dept_name">Department</th>
                <th data-col="activities_summary">Revenue Items</th>
                <th data-col="total_amount">Amount (BZD)</th>
                <th data-col="payment_methods">Payment</th>
                <th data-col="shift_id">Shift</th>
                <th data-col="completed_at">Date</th>
                <th data-col="status">Status</th>
              </tr>
            </thead>
            <tbody id="table-body">
              <tr><td colspan="9" class="text-center py-4 text-muted">Loading...</td></tr>
            </tbody>
          </table>
          <div id="table-pagination"></div>
        </div>
      </div>

    </div>
  </div>

<script>
const LIST_URL = "<?= url('api/cashiering/pos-transactions.php') ?>";

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
  const map = {
    completed: ['bg-success-lt text-success', 'Completed'],
    voided:    ['bg-danger-lt text-danger',   'Voided'],
    refunded:  ['bg-warning-lt text-warning', 'Refunded'],
  };
  const [cls, label] = map[val] || ['bg-secondary-lt text-secondary', val ?? '—'];
  return `<span class="badge ${cls}">${label}</span>`;
}

const METHOD_LABELS = {
  cash:            'Cash',
  check:           'Cheque',
  cheque:          'Cheque',
  credit_card:     'Credit Card',
  debit_card:      'Debit Card',
  online_transfer: 'Online Transfer',
  bank_transfer:   'Bank Transfer',
};

function methodBadge(method) {
  const label = METHOD_LABELS[method] || method;
  return `<span class="badge bg-blue-lt text-blue me-1">${label}</span>`;
}

function fmt(amount) {
  if (amount === null || amount === undefined || amount === '') return '—';
  return parseFloat(amount).toLocaleString('en-BZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dt) {
  if (!dt) return '—';
  return dt.replace('T', ' ').substring(0, 16);
}

function updateKpis(rows) {
  const total     = rows.reduce((s, r) => s + parseFloat(r.total_amount || 0), 0);
  const completed = rows.filter(r => r.status === 'completed').length;
  const voided    = rows.filter(r => r.status === 'voided').length;
  const refunded  = rows.filter(r => r.status === 'refunded').length;

  document.getElementById('kpi-total').textContent     = 'BZD ' + fmt(total);
  document.getElementById('kpi-count').textContent     = rows.length.toLocaleString();
  document.getElementById('kpi-completed').textContent = completed.toLocaleString();
  document.getElementById('kpi-voided').textContent    = (voided + refunded).toLocaleString();

  document.getElementById('stats-area').innerHTML = `
    <span class="badge bg-success-lt">Completed: ${completed}</span>
    <span class="badge bg-danger-lt">Voided: ${voided}</span>
    <span class="badge bg-warning-lt">Refunded: ${refunded}</span>
  `;
}

function renderRow(r) {
  const methods = Array.isArray(r.payment_methods) ? r.payment_methods : [];
  const acts    = Array.isArray(r.activities_summary) ? r.activities_summary : [];

  const methodsHtml = methods.length
    ? methods.map(methodBadge).join('')
    : '<span class="text-muted">—</span>';

  const actsHtml = acts.length
    ? acts.map(a => `<div style="font-size:.82rem;">${a}</div>`).join('')
    : '<span class="text-muted">—</span>';

  return `<tr>
    <td class="text-muted" style="font-size:.8rem;">#${r.id}</td>
    <td>${r.customer_name ?? '—'}</td>
    <td>${r.dept_name ?? '<span class="text-muted">—</span>'}</td>
    <td>${actsHtml}</td>
    <td class="fw-semibold">${fmt(r.total_amount)}</td>
    <td>${methodsHtml}</td>
    <td class="text-muted" style="font-size:.82rem;">${r.shift_id ?? '—'}</td>
    <td style="font-size:.85rem;">${formatDate(r.completed_at)}</td>
    <td>${statusBadge(r.status)}</td>
  </tr>`;
}

async function loadRows() {
  const search   = document.getElementById('search-input').value.trim();
  const status   = document.getElementById('filter-status').value;
  const dateFrom = document.getElementById('filter-date-from').value;
  const dateTo   = document.getElementById('filter-date-to').value;

  const parts = [];
  if (search)   parts.push('search='    + encodeURIComponent(search));
  if (status)   parts.push('status='    + encodeURIComponent(status));
  if (dateFrom) parts.push('date_from=' + encodeURIComponent(dateFrom));
  if (dateTo)   parts.push('date_to='   + encodeURIComponent(dateTo));

  const url = LIST_URL + (parts.length ? '?' + parts.join('&') : '');
  clearMsg('table-message');

  try {
    const res = await apiGet(url);
    const rows = res.data || [];
    updateKpis(rows);
    window.pager.setData(rows);
  } catch (e) {
    showMsg('table-message', e.message);
    updateKpis([]);
    window.pager.setData([]);
  }
}

let searchTimer;
document.getElementById('search-input').addEventListener('input', function () {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(loadRows, 300);
});
document.getElementById('filter-status').addEventListener('change', loadRows);
document.getElementById('filter-date-from').addEventListener('change', loadRows);
document.getElementById('filter-date-to').addEventListener('change', loadRows);
document.getElementById('clear-filters-btn').addEventListener('click', function () {
  document.getElementById('search-input').value     = '';
  document.getElementById('filter-status').value    = '';
  document.getElementById('filter-date-from').value = '';
  document.getElementById('filter-date-to').value   = '';
  loadRows();
});

document.addEventListener('DOMContentLoaded', () => {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 9, renderRow });
  loadRows();
});
</script>
<?php require_once __DIR__ . '/../../includes/layout-tabler-footer.php'; ?>
