<?php
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/Rbac.php';
Auth::requireAuth();

require_once __DIR__ . '/../../config/database.php';

$authUser = Auth::user();
$fullName = Auth::fullName();

// Pre-load pay-ins (last 60 days), split by origin for the reporting tabs.
$dailyPayIns = [];   // cashier daily sales pay-ins (generated from POS cash sales)
$deptPayIns  = [];   // department pay-ins (department brought money / deposit slips)
try {
    $s = $pdo->prepare("
        SELECT pi.pay_in_id, pi.department_name, pi.pay_in_date,
               pi.total_cash, pi.total_cheques, pi.total_amount, pi.status, pi.source,
               pi.created_at, CONCAT(u.first_name,' ',u.last_name) AS cashier_name
        FROM pay_ins pi
        LEFT JOIN users u ON u.id = pi.cashier_uid
        WHERE pi.deleted_at IS NULL AND pi.pay_in_date >= :df
        ORDER BY pi.pay_in_date DESC, pi.created_at DESC
        LIMIT 300
    ");
    $s->execute(['df' => date('Y-m-d', strtotime('-60 days'))]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['source'] === 'pos_sales') { $dailyPayIns[] = $row; }
        else                                { $deptPayIns[]  = $row; }
    }
} catch (Throwable $e) { /* non-fatal */ }

require_once __DIR__ . '/../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-body">
    <div class="container-xl">

      <!-- Page identity card with inline KPIs -->
      <div class="card mb-4" style="border-left:4px solid var(--tblr-primary);">
        <div class="card-body py-3">
          <div class="d-flex align-items-center flex-wrap gap-2">

            <!-- Title -->
            <div style="flex-shrink:0;">
              <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.68rem;letter-spacing:.1em;">Government of Belize &middot; Treasury Department</div>
              <div class="fw-bold" style="font-size:1.05rem;line-height:1.2;">Cashiering Module</div>
            </div>

            <!-- Vertical divider -->
            <div style="width:1px;background:#e2e8f0;align-self:stretch;flex-shrink:0;margin:0 .5rem;"></div>

            <!-- KPI stats -->
            <div class="d-flex align-items-center flex-wrap gap-0" style="flex:1;min-width:0;">

              <div style="padding:0 18px;flex-shrink:0;">
                <div style="font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#9ca3af;margin-bottom:3px;">Receipts Today</div>
                <div style="font-size:1.05rem;font-weight:900;line-height:1;color:var(--tblr-primary);" id="kpi-receipts">—</div>
              </div>

              <div style="width:1px;background:#e2e8f0;align-self:stretch;flex-shrink:0;"></div>

              <div style="padding:0 18px;flex-shrink:0;">
                <div style="font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#9ca3af;margin-bottom:3px;">Revenue Today</div>
                <div style="font-size:1.05rem;font-weight:900;line-height:1;color:var(--tblr-success);" id="kpi-revenue">—</div>
              </div>

              <div style="width:1px;background:#e2e8f0;align-self:stretch;flex-shrink:0;"></div>

              <div style="padding:0 18px;flex-shrink:0;">
                <div style="font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#9ca3af;margin-bottom:3px;">Completed</div>
                <div style="font-size:1.05rem;font-weight:900;line-height:1;color:var(--tblr-success);" id="kpi-completed">—</div>
              </div>

              <div style="width:1px;background:#e2e8f0;align-self:stretch;flex-shrink:0;"></div>

              <div style="padding:0 18px;flex-shrink:0;">
                <div style="font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#9ca3af;margin-bottom:3px;">Voids / Refunds</div>
                <div style="font-size:1.05rem;font-weight:900;line-height:1;color:var(--tblr-danger);" id="kpi-voided">—</div>
              </div>

            </div>

            <!-- Operational actions -->
            <div class="d-flex gap-2 flex-wrap flex-shrink-0">
              <a href="<?= url('views/pay-in/pos-terminal.php') ?>" class="btn btn-sm" style="background:#1e4620;color:#fff;border-color:#1e4620;">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                Open POS Terminal
              </a>
              <a href="<?= url('views/pay-in/pay-in-new.php') ?>" class="btn btn-sm" style="background:#b45309;color:#fff;border-color:#b45309;">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Pay-In
              </a>
            </div>

          </div>
        </div>
      </div>

      <!-- Reporting card: Transactions / Daily Pay-Ins / Department Pay-Ins -->
      <div class="card">

        <!-- Tab bar -->
        <div style="border-bottom:1px solid #e5e7eb;">
          <div style="display:flex;align-items:stretch;flex-wrap:wrap;gap:0;">

            <button id="tab-btn-tx" onclick="switchTab('tx')" class="cash-tab"
              style="display:flex;align-items:center;gap:6px;padding:11px 18px;font-size:.82rem;font-weight:700;letter-spacing:.02em;border:none;background:transparent;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-1px;transition:color .15s,border-color .15s;color:#9ca3af;white-space:nowrap;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
              Transactions
            </button>

            <button id="tab-btn-daily" onclick="switchTab('daily')" class="cash-tab"
              style="display:flex;align-items:center;gap:6px;padding:11px 18px;font-size:.82rem;font-weight:700;letter-spacing:.02em;border:none;background:transparent;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-1px;transition:color .15s,border-color .15s;color:#9ca3af;white-space:nowrap;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
              Daily Pay-Ins
            </button>

            <button id="tab-btn-dept" onclick="switchTab('dept')" class="cash-tab"
              style="display:flex;align-items:center;gap:6px;padding:11px 18px;font-size:.82rem;font-weight:700;letter-spacing:.02em;border:none;background:transparent;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-1px;transition:color .15s,border-color .15s;color:#9ca3af;white-space:nowrap;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9v.01"/><path d="M9 12v.01"/><path d="M9 15v.01"/></svg>
              Department Pay-Ins
            </button>

            <!-- Filters — right side -->
            <div style="display:flex;align-items:center;gap:7px;padding:6px 14px;margin-left:auto;flex-wrap:wrap;">
              <div class="dropdown" id="payment-filter-wrap">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="payment-filter-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">Payment: All</button>
                <div class="dropdown-menu p-2" style="min-width:190px;">
                  <?php foreach ([
                    ['cash','Cash'], ['check','Cheque'], ['pos_terminal','POS / Card'],
                    ['bank_deposit','Bank Deposit'], ['online_transfer','Online Transfer'], ['e_invoicing','E-Invoice'],
                  ] as [$pmVal, $pmLbl]): ?>
                  <label class="dropdown-item d-flex align-items-center gap-2 px-2 py-1 mb-0" style="cursor:pointer;font-size:.85rem;">
                    <input type="checkbox" class="form-check-input m-0 pay-method-cb" value="<?= $pmVal ?>"><?= $pmLbl ?>
                  </label>
                  <?php endforeach; ?>
                  <div class="dropdown-divider my-1"></div>
                  <button type="button" class="btn btn-sm btn-link text-decoration-none w-100 text-start px-2 py-1" id="payment-filter-clear">Clear payment filter</button>
                </div>
              </div>
              <input type="text" id="dash-search" class="form-control form-control-sm" style="width:auto;" placeholder="Search...">
              <label style="font-size:.75rem;color:#9ca3af;white-space:nowrap;">From</label>
              <input type="date" id="filter-date-from" class="form-control form-control-sm" style="width:auto;">
              <label style="font-size:.75rem;color:#9ca3af;white-space:nowrap;">To</label>
              <input type="date" id="filter-date-to" class="form-control form-control-sm" style="width:auto;">
              <button class="btn btn-sm btn-outline-secondary" id="clear-filters-btn">Clear</button>
            </div>

          </div>
        </div>

        <div id="dash-message" class="alert m-3" style="display:none"></div>

        <!-- Transactions pane -->
        <div id="pane-tx">
          <div class="d-flex gap-2 align-items-center px-3 py-2 border-bottom" id="stats-area" style="background:#fafafa;">
            <span class="badge bg-primary-lt">Total: 0</span>
            <span class="badge bg-success-lt">Cash: 0</span>
            <span class="badge bg-azure-lt">Cheque: 0</span>
          </div>
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

        <!-- Daily Pay-Ins pane (cashier daily sales) -->
        <div id="pane-daily" style="display:none;">
          <table class="table table-vcenter table-hover card-table">
            <thead>
              <tr>
                <th style="width:160px;">Pay-In ID</th>
                <th style="width:110px;">Date</th>
                <th>Cashier</th>
                <th class="text-end" style="width:130px;">Cash (BZD)</th>
                <th class="text-end" style="width:130px;">Total (BZD)</th>
                <th class="text-center" style="width:100px;">Status</th>
                <th style="width:60px;"></th>
              </tr>
            </thead>
            <tbody id="daily-tbody">
              <tr><td colspan="7" class="text-center py-4 text-muted">Loading...</td></tr>
            </tbody>
            <tfoot id="daily-footer" style="display:none;">
              <tr class="fw-bold" style="background:#f0f7eb;">
                <td colspan="4" class="text-end" style="color:#1e4620;">Grand Total</td>
                <td class="text-end" id="daily-grand" style="color:#1e4620;">—</td>
                <td colspan="2"></td>
              </tr>
            </tfoot>
          </table>
          <div class="px-3 py-2 text-end border-top" style="background:#fafafa;">
            <a href="<?= url('views/pay-in/pay-in-list.php') ?>" style="font-size:.8rem;color:#1e4620;font-weight:600;">View all pay-ins &rarr;</a>
          </div>
        </div>

        <!-- Department Pay-Ins pane -->
        <div id="pane-dept" style="display:none;">
          <table class="table table-vcenter table-hover card-table">
            <thead>
              <tr>
                <th style="width:160px;">Pay-In ID</th>
                <th style="width:110px;">Date</th>
                <th>Department</th>
                <th>Received By</th>
                <th class="text-end" style="width:120px;">Cash (BZD)</th>
                <th class="text-end" style="width:120px;">Cheques (BZD)</th>
                <th class="text-end" style="width:130px;">Total (BZD)</th>
                <th class="text-center" style="width:100px;">Status</th>
                <th style="width:60px;"></th>
              </tr>
            </thead>
            <tbody id="dept-tbody">
              <tr><td colspan="9" class="text-center py-4 text-muted">Loading...</td></tr>
            </tbody>
            <tfoot id="dept-footer" style="display:none;">
              <tr class="fw-bold" style="background:#fef3c7;">
                <td colspan="6" class="text-end" style="color:#92400e;">Grand Total</td>
                <td class="text-end" id="dept-grand" style="color:#92400e;">—</td>
                <td colspan="2"></td>
              </tr>
            </tfoot>
          </table>
          <div class="px-3 py-2 text-end border-top" style="background:#fafafa;">
            <a href="<?= url('views/pay-in/pay-in-list.php') ?>" style="font-size:.8rem;color:#92400e;font-weight:600;">View all pay-ins &rarr;</a>
          </div>
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
const PAY_IN_VIEW_URL = "<?= url('views/pay-in/pay-in-view.php') ?>";
const DAILY_PAYINS = <?= json_encode($dailyPayIns, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const DEPT_PAYINS  = <?= json_encode($deptPayIns,  JSON_HEX_TAG | JSON_HEX_AMP) ?>;
let activeTab = 'tx';

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
  const df   = document.getElementById('filter-date-from').value;
  const dt   = document.getElementById('filter-date-to').value;

  let rows = allRows;

  if (df || dt) {
    rows = rows.filter(r => {
      const d = (r.completed_at ?? r.created_at ?? '').substring(0, 10);
      if (!d) return false;
      if (df && d < df) return false;
      if (dt && d > dt) return false;
      return true;
    });
  }

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

  // Payment-type filter (multi-select). Keep rows that include ANY selected method.
  const selMethods = selectedPayMethods();
  if (selMethods.length) {
    rows = rows.filter(r => {
      const methods = Array.isArray(r.payment_methods) ? r.payment_methods : [];
      return selMethods.some(sel =>
        methods.includes(sel) ||
        (sel === 'check' && methods.includes('cheque')) ||
        (sel === 'pos_terminal' && (methods.includes('credit_card') || methods.includes('debit_card')))
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

// ---- Pay-in tabs (daily / department) ----
function payInStatusBadge(s) {
  if (s === 'verified') return '<span class="badge bg-success-lt text-success" style="font-size:.7rem;">Verified</span>';
  if (s === 'rejected') return '<span class="badge bg-danger-lt text-danger" style="font-size:.7rem;">Rejected</span>';
  return '<span class="badge bg-yellow-lt text-yellow" style="font-size:.7rem;">Submitted</span>';
}

function filterPayIns(data) {
  const term = document.getElementById('dash-search').value.trim().toLowerCase();
  const df   = document.getElementById('filter-date-from').value;
  const dt   = document.getElementById('filter-date-to').value;
  return data.filter(p => {
    if (df && p.pay_in_date < df) return false;
    if (dt && p.pay_in_date > dt) return false;
    if (term) {
      const hay = (p.pay_in_id + ' ' + (p.department_name || '') + ' ' + (p.cashier_name || '')).toLowerCase();
      if (!hay.includes(term)) return false;
    }
    return true;
  });
}

function renderDailyPayIns() {
  const rows = filterPayIns(DAILY_PAYINS);
  const tbody = document.getElementById('daily-tbody');
  const tfoot = document.getElementById('daily-footer');
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No daily pay-ins found.</td></tr>';
    tfoot.style.display = 'none';
    return;
  }
  let grand = 0;
  tbody.innerHTML = rows.map(p => {
    grand += parseFloat(p.total_amount || 0);
    const cashier = (p.cashier_name && p.cashier_name.trim()) ? p.cashier_name.trim() : '—';
    return '<tr>'
      + '<td><code class="text-muted" style="font-size:.78rem;">' + p.pay_in_id + '</code></td>'
      + '<td style="font-size:.84rem;">' + p.pay_in_date + '</td>'
      + '<td class="fw-medium">' + cashier + '</td>'
      + '<td class="text-end">' + fmt(p.total_cash) + '</td>'
      + '<td class="text-end fw-semibold">' + fmt(p.total_amount) + '</td>'
      + '<td class="text-center">' + payInStatusBadge(p.status) + '</td>'
      + '<td><a href="' + PAY_IN_VIEW_URL + '?id=' + encodeURIComponent(p.pay_in_id) + '" class="btn btn-xs btn-outline-secondary">View</a></td>'
      + '</tr>';
  }).join('');
  document.getElementById('daily-grand').textContent = 'BZD ' + fmt(grand);
  tfoot.style.display = 'table-footer-group';
}

function renderDeptPayIns() {
  const rows = filterPayIns(DEPT_PAYINS);
  const tbody = document.getElementById('dept-tbody');
  const tfoot = document.getElementById('dept-footer');
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No department pay-ins found.</td></tr>';
    tfoot.style.display = 'none';
    return;
  }
  let grand = 0;
  tbody.innerHTML = rows.map(p => {
    grand += parseFloat(p.total_amount || 0);
    const dept    = p.department_name || '<span class="text-muted fst-italic">Walk-in</span>';
    const cashier = (p.cashier_name && p.cashier_name.trim()) ? p.cashier_name.trim() : '—';
    return '<tr>'
      + '<td><code class="text-muted" style="font-size:.78rem;">' + p.pay_in_id + '</code></td>'
      + '<td style="font-size:.84rem;">' + p.pay_in_date + '</td>'
      + '<td class="fw-medium">' + dept + '</td>'
      + '<td style="font-size:.84rem;">' + cashier + '</td>'
      + '<td class="text-end">' + fmt(p.total_cash) + '</td>'
      + '<td class="text-end">' + fmt(p.total_cheques) + '</td>'
      + '<td class="text-end fw-semibold">' + fmt(p.total_amount) + '</td>'
      + '<td class="text-center">' + payInStatusBadge(p.status) + '</td>'
      + '<td><a href="' + PAY_IN_VIEW_URL + '?id=' + encodeURIComponent(p.pay_in_id) + '" class="btn btn-xs btn-outline-secondary">View</a></td>'
      + '</tr>';
  }).join('');
  document.getElementById('dept-grand').textContent = 'BZD ' + fmt(grand);
  tfoot.style.display = 'table-footer-group';
}

// ---- Tab switching + filter dispatch ----
const TAB_COLORS = { tx: '#206bc4', daily: '#1e4620', dept: '#92400e' };

function switchTab(tab) {
  activeTab = tab;
  ['tx', 'daily', 'dept'].forEach(t => {
    document.getElementById('pane-' + t).style.display = (t === tab) ? '' : 'none';
    const btn = document.getElementById('tab-btn-' + t);
    const on  = (t === tab);
    btn.style.color = on ? TAB_COLORS[t] : '#9ca3af';
    btn.style.borderBottomColor = on ? TAB_COLORS[t] : 'transparent';
  });
  // Payment-type filter only applies to the Transactions tab.
  const pf = document.getElementById('payment-filter-wrap');
  if (pf) pf.style.display = (tab === 'tx') ? '' : 'none';
  applyFilters();
}

function selectedPayMethods() {
  return Array.prototype.slice.call(document.querySelectorAll('.pay-method-cb:checked')).map(cb => cb.value);
}

function updatePayLabel() {
  const n = selectedPayMethods().length;
  const btn = document.getElementById('payment-filter-btn');
  if (btn) btn.textContent = n ? ('Payment: ' + n + ' selected') : 'Payment: All';
}

function applyFilters() {
  if (activeTab === 'tx')        applySearchAndSort();
  else if (activeTab === 'daily') renderDailyPayIns();
  else                            renderDeptPayIns();
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

  // Search + date filters apply to the active tab
  let searchTimer;
  document.getElementById('dash-search').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 250);
  });
  document.getElementById('filter-date-from').addEventListener('change', applyFilters);
  document.getElementById('filter-date-to').addEventListener('change', applyFilters);
  document.getElementById('clear-filters-btn').addEventListener('click', function () {
    document.getElementById('dash-search').value     = '';
    document.getElementById('filter-date-from').value = '';
    document.getElementById('filter-date-to').value   = '';
    document.querySelectorAll('.pay-method-cb').forEach(cb => { cb.checked = false; });
    updatePayLabel();
    applyFilters();
  });

  // Payment-type multi-select
  document.querySelectorAll('.pay-method-cb').forEach(cb => {
    cb.addEventListener('change', function () { updatePayLabel(); applyFilters(); });
  });
  const payClear = document.getElementById('payment-filter-clear');
  if (payClear) payClear.addEventListener('click', function () {
    document.querySelectorAll('.pay-method-cb').forEach(cb => { cb.checked = false; });
    updatePayLabel();
    applyFilters();
  });

  // Render the pay-in tabs immediately (preloaded server-side)
  renderDailyPayIns();
  renderDeptPayIns();

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
    } else {
      updateSortIcons();
      applySearchAndSort();
    }

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

  // Activate default tab
  switchTab('tx');
});
</script>

<?php require_once __DIR__ . '/../../includes/layout-tabler-footer.php'; ?>
