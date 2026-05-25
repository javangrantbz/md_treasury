<?php
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/Rbac.php';
Auth::requireAuth();

require_once __DIR__ . '/../../config/database.php';

$authUser = Auth::user();
$fullName = Auth::fullName();

require_once __DIR__ . '/../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row align-items-center">
        <div class="col">
          <div class="page-pretitle">Government of Belize — Treasury Department</div>
          <h2 class="page-title">Cashiering Module</h2>
        </div>
        <div class="col-auto">
          <a href="https://pos.treasuryconnect.biz/" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
            Treasury POS
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">

      <!-- KPI cards — live from POS -->
      <div class="row row-cards g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
          <div class="card card-sm">
            <div class="card-body">
              <div class="row align-items-center">
                <div class="col-auto">
                  <span class="bg-primary text-white avatar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                  </span>
                </div>
                <div class="col">
                  <div class="text-muted" style="font-size:.78rem; text-transform:uppercase; letter-spacing:.05em;">Receipts Today</div>
                  <div class="h3 mb-0" id="kpi-receipts">—</div>
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
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                  </span>
                </div>
                <div class="col">
                  <div class="text-muted" style="font-size:.78rem; text-transform:uppercase; letter-spacing:.05em;">Revenue Today</div>
                  <div class="h3 mb-0" id="kpi-revenue">—</div>
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
                  <span class="bg-success-lt avatar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--tblr-success)" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                  </span>
                </div>
                <div class="col">
                  <div class="text-muted" style="font-size:.78rem; text-transform:uppercase; letter-spacing:.05em;">Completed Today</div>
                  <div class="h3 mb-0" id="kpi-completed">—</div>
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
                  <span class="bg-danger-lt avatar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--tblr-danger)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                  </span>
                </div>
                <div class="col">
                  <div class="text-muted" style="font-size:.78rem; text-transform:uppercase; letter-spacing:.05em;">Voids / Refunds</div>
                  <div class="h3 mb-0" id="kpi-voided">—</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent POS transactions -->
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div class="d-flex gap-2 align-items-center">
            <h3 class="card-title me-2">Pay-In Transactions</h3>
            <input type="text" id="dash-search" class="form-control form-control-sm w-auto" placeholder="Search...">
            <div id="stats-area" class="d-flex gap-2 ms-2">
              <span class="badge bg-primary-lt">Total: 0</span>
              <span class="badge bg-success-lt">Cash: 0</span>
              <span class="badge bg-azure-lt">Cheque: 0</span>
            </div>
          </div>
        </div>
        <div class="card-body p-0">
          <div id="dash-message" class="alert m-3" style="display:none"></div>
          <table class="table table-vcenter table-hover card-table">
            <thead>
              <tr>
                <th data-col="id" class="sortable" style="cursor:pointer;white-space:nowrap;"># <span class="sort-icon"></span></th>
                <th data-col="customer_name" class="sortable" style="cursor:pointer;white-space:nowrap;">Customer <span class="sort-icon"></span></th>
                <th data-col="dept_name" class="sortable" style="cursor:pointer;white-space:nowrap;">Department <span class="sort-icon"></span></th>
                <th data-col="activities_summary" class="sortable" style="cursor:pointer;white-space:nowrap;">Revenue Items <span class="sort-icon"></span></th>
                <th data-col="total_amount" class="sortable" style="cursor:pointer;white-space:nowrap;">Amount (BZD) <span class="sort-icon"></span></th>
                <th data-col="payment_methods" class="sortable" style="cursor:pointer;white-space:nowrap;">Payment <span class="sort-icon"></span></th>
                <th data-col="completed_at" class="sortable" style="cursor:pointer;white-space:nowrap;">Date <span class="sort-icon"></span></th>
                <th data-col="status" class="sortable" style="cursor:pointer;white-space:nowrap;">Status <span class="sort-icon"></span></th>
              </tr>
            </thead>
            <tbody id="dash-tbody">
              <tr><td colspan="8" class="text-center py-4 text-muted">Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Footer note -->
      <div class="mt-4 text-center text-muted" style="font-size:.78rem; border-top:1px solid var(--tblr-border-color); padding-top:1rem;">
        Government of Belize &mdash; Ministry of Finance &bull; Treasury Department &bull;
        Authorised users only. All activity is logged and audited.
      </div>

    </div>
  </div>

<script>
const POS_URL = "<?= url('api/cashiering/pos-transactions.php') ?>";
const TODAY   = new Date().toISOString().substring(0, 10);

function fmt(amount) {
  if (amount === null || amount === undefined || amount === '') return '—';
  return parseFloat(amount).toLocaleString('en-BZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

function isToday(dtStr) {
  return dtStr && dtStr.substring(0, 10) === TODAY;
}

function renderDashRow(r) {
  const methods = Array.isArray(r.payment_methods) ? r.payment_methods : [];
  const acts    = Array.isArray(r.activities_summary) ? r.activities_summary : [];
  const methodsHtml = methods.map(m => `<span class="badge bg-blue-lt text-blue me-1">${METHOD_LABELS[m] || m}</span>`).join('') || '—';
  const actsHtml    = acts.map(a => `<div style="font-size:.82rem;">${a}</div>`).join('') || '<span class="text-muted">—</span>';

  return `<tr>
    <td class="text-muted" style="font-size:.8rem;">#${r.id}</td>
    <td>${r.customer_name ?? '—'}</td>
    <td>${r.dept_name ?? '<span class="text-muted">—</span>'}</td>
    <td>${actsHtml}</td>
    <td class="fw-semibold">${fmt(r.total_amount)}</td>
    <td>${methodsHtml}</td>
    <td style="font-size:.85rem;">${(r.completed_at ?? '').substring(0, 16).replace('T', ' ')}</td>
    <td>${statusBadge(r.status)}</td>
  </tr>`;
}

// Return a sortable scalar value for a given column key
function sortVal(r, col) {
  switch (col) {
    case 'id':           return parseInt(r.id) || 0;
    case 'total_amount': return parseFloat(r.total_amount) || 0;
    case 'completed_at': return r.completed_at ?? '';
    case 'activities_summary':
      return Array.isArray(r.activities_summary) ? (r.activities_summary[0] ?? '') : '';
    case 'payment_methods':
      return Array.isArray(r.payment_methods) ? (r.payment_methods[0] ?? '') : '';
    default:
      return (r[col] ?? '').toString().toLowerCase();
  }
}

let allRows  = [];
let sortCol  = 'completed_at';
let sortDir  = 'desc';

function applySearchAndSort() {
  const term = document.getElementById('dash-search').value.trim().toLowerCase();

  let rows = allRows;

  if (term) {
    rows = rows.filter(r => {
      const acts    = Array.isArray(r.activities_summary) ? r.activities_summary.join(' ') : '';
      const methods = Array.isArray(r.payment_methods)    ? r.payment_methods.join(' ')    : '';
      return (
        (r.customer_name ?? '').toLowerCase().includes(term) ||
        (r.dept_name     ?? '').toLowerCase().includes(term) ||
        (r.status        ?? '').toLowerCase().includes(term) ||
        acts.toLowerCase().includes(term) ||
        methods.toLowerCase().includes(term)
      );
    });
  }

  // Update stats based on filtered rows
  const cashCount = rows.filter(r => (r.payment_methods || []).includes('cash')).length;
  const chequeCount = rows.filter(r => 
    (r.payment_methods || []).includes('cheque') || (r.payment_methods || []).includes('check')
  ).length;

  document.getElementById('stats-area').innerHTML = `
    <span class="badge bg-primary-lt">Total: ${rows.length}</span>
    <span class="badge bg-success-lt">Cash: ${cashCount}</span>
    <span class="badge bg-azure-lt">Cheque: ${chequeCount}</span>
  `;

  rows = [...rows].sort((a, b) => {
    const va = sortVal(a, sortCol);
    const vb = sortVal(b, sortCol);
    const cmp = typeof va === 'number'
      ? va - vb
      : va.toString().localeCompare(vb.toString());
    return sortDir === 'asc' ? cmp : -cmp;
  });

  const tbody = document.getElementById('dash-tbody');
  tbody.innerHTML = rows.length
    ? rows.map(renderDashRow).join('')
    : '<tr><td colspan="8" class="text-center py-4 text-muted">No results.</td></tr>';
}

function updateSortIcons() {
  document.querySelectorAll('th.sortable').forEach(th => {
    const icon = th.querySelector('.sort-icon');
    if (th.dataset.col === sortCol) {
      icon.textContent = sortDir === 'asc' ? ' ▲' : ' ▼';
    } else {
      icon.textContent = '';
    }
  });
}

document.addEventListener('DOMContentLoaded', async () => {
  // Column sort clicks
  document.querySelectorAll('th.sortable').forEach(th => {
    th.addEventListener('click', () => {
      const col = th.dataset.col;
      if (sortCol === col) {
        sortDir = sortDir === 'asc' ? 'desc' : 'asc';
      } else {
        sortCol = col;
        sortDir = 'asc';
      }
      updateSortIcons();
      applySearchAndSort();
    });
  });

  // Search
  let searchTimer;
  document.getElementById('dash-search').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applySearchAndSort, 250);
  });

  try {
    const res  = await apiGet(POS_URL);
    allRows = res.data || [];

    // KPI cards — today only
    const todayRows = allRows.filter(r => isToday(r.completed_at));
    const revenue   = todayRows.reduce((s, r) => s + parseFloat(r.total_amount || 0), 0);
    const completed = todayRows.filter(r => r.status === 'completed').length;
    const voided    = todayRows.filter(r => r.status === 'voided' || r.status === 'refunded').length;

    document.getElementById('kpi-receipts').textContent  = todayRows.length.toLocaleString();
    document.getElementById('kpi-revenue').textContent   = 'BZD ' + fmt(revenue);
    document.getElementById('kpi-completed').textContent = completed.toLocaleString();
    document.getElementById('kpi-voided').textContent    = voided.toLocaleString();

    if (allRows.length === 0) {
      document.getElementById('dash-tbody').innerHTML =
        '<tr><td colspan="8" class="text-center py-4 text-muted">No transactions found.</td></tr>';
      return;
    }

    updateSortIcons();
    applySearchAndSort();

  } catch (e) {
    const el = document.getElementById('dash-message');
    el.className = 'alert alert-danger m-3';
    el.textContent = e.message;
    el.style.display = 'block';
    document.getElementById('dash-tbody').innerHTML =
      '<tr><td colspan="8" class="text-center py-4 text-danger">Failed to load transactions.</td></tr>';
    ['kpi-receipts','kpi-revenue','kpi-completed','kpi-voided'].forEach(id => {
      document.getElementById(id).textContent = 'Err';
    });
  }
});
</script>

<?php require_once __DIR__ . '/../../includes/layout-tabler-footer.php'; ?>
