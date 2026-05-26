<?php
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/database.php';
Auth::requireAuth();

// Pre-load recent pay-ins for the pay-in tab (last 30 days)
$recentPayIns = [];
try {
    $s = $pdo->prepare("
        SELECT pi.pay_in_id, pi.department_name, pi.pay_in_date,
               pi.total_cash, pi.total_cheques, pi.total_amount, pi.status,
               CONCAT(u.first_name,' ',u.last_name) AS cashier_name
        FROM pay_ins pi
        LEFT JOIN users u ON u.id = pi.cashier_uid
        WHERE pi.pay_in_date >= :df
        ORDER BY pi.pay_in_date DESC, pi.created_at DESC
        LIMIT 50
    ");
    $s->execute(['df' => date('Y-m-d', strtotime('-30 days'))]);
    $recentPayIns = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* non-fatal */ }

require_once __DIR__ . '/../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-body">
    <div class="container-xl">

      <!-- Page identity card -->
      <div class="card mb-4" style="border-left:4px solid var(--tblr-primary);">
        <div class="card-body py-3">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
              <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.68rem;letter-spacing:.1em;">Government of Belize &middot; Treasury Department</div>
              <div class="fw-bold" style="font-size:1.05rem;line-height:1.2;">Pay-In Portal</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <a href="<?= url('views/pay-in/pos-terminal.php') ?>" class="btn btn-sm" style="background:#1e4620;color:#fff;border-color:#1e4620;">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                Open POS Terminal
              </a>
              <a href="<?= url('views/pay-in/pay-in-new.php') ?>" class="btn btn-sm" style="background:#b45309;color:#fff;border-color:#b45309;">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Pay-In
              </a>
              <button class="btn btn-outline-secondary btn-sm d-print-none" onclick="window.print()">Print / Export</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabbed data card -->
      <div class="card">

        <!-- Tab bar -->
        <div style="border-bottom:1px solid #e5e7eb;">
          <div style="display:flex;align-items:stretch;flex-wrap:wrap;gap:0;">

            <button id="tab-btn-shifts" onclick="switchTab('shifts')"
              style="display:flex;align-items:center;gap:6px;padding:11px 20px;font-size:.82rem;font-weight:700;letter-spacing:.02em;border:none;background:transparent;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-1px;transition:color .15s,border-color .15s;color:#9ca3af;white-space:nowrap;">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
              POS Shifts
            </button>

            <button id="tab-btn-payins" onclick="switchTab('payins')"
              style="display:flex;align-items:center;gap:6px;padding:11px 20px;font-size:.82rem;font-weight:700;letter-spacing:.02em;border:none;background:transparent;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-1px;transition:color .15s,border-color .15s;color:#9ca3af;white-space:nowrap;">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
              Pay-In History
            </button>

            <!-- Date filters — right side -->
            <div style="display:flex;align-items:center;gap:7px;padding:6px 14px;margin-left:auto;flex-wrap:wrap;">
              <label style="font-size:.75rem;color:#9ca3af;white-space:nowrap;">From</label>
              <input type="date" id="filter-date-from" class="form-control form-control-sm" style="width:auto;">
              <label style="font-size:.75rem;color:#9ca3af;white-space:nowrap;">To</label>
              <input type="date" id="filter-date-to" class="form-control form-control-sm" style="width:auto;">
              <button class="btn btn-sm btn-outline-secondary" id="clear-filters-btn">Clear</button>
              <button class="btn btn-sm btn-outline-secondary" id="refresh-btn" title="Refresh">&#8635;</button>
            </div>

          </div>
        </div>

        <!-- POS Shifts pane -->
        <div id="pane-shifts">
          <div id="shifts-message" class="alert m-3" style="display:none;"></div>
          <table class="table table-vcenter table-hover card-table">
            <thead>
              <tr>
                <th>Shift ID</th>
                <th>Cashier</th>
                <th>Shift Start</th>
                <th>Shift End</th>
                <th class="text-end">Transactions</th>
                <th class="text-end">Total (BZD)</th>
              </tr>
            </thead>
            <tbody id="shifts-body">
              <tr><td colspan="6" class="text-center py-4 text-muted">Loading&hellip;</td></tr>
            </tbody>
            <tfoot id="shifts-footer" style="display:none;">
              <tr class="fw-bold" style="background:#f0f7eb;">
                <td colspan="5" class="text-end" style="color:#1e4620;">Grand Total</td>
                <td class="text-end" id="shifts-grand" style="color:#1e4620;">—</td>
              </tr>
            </tfoot>
          </table>
          <div class="px-3 py-2 border-top text-end" style="background:#fafafa;">
            <a href="<?= url('views/pay-in/pos-shift-history.php') ?>" style="font-size:.8rem;color:#1e4620;font-weight:600;">View all shifts &rarr;</a>
          </div>
        </div>

        <!-- Pay-In History pane -->
        <div id="pane-payins" style="display:none;">
          <table class="table table-vcenter table-hover card-table">
            <thead>
              <tr>
                <th style="width:155px;">Pay-In ID</th>
                <th>Department</th>
                <th style="width:110px;">Date</th>
                <th>Cashier</th>
                <th class="text-end" style="width:130px;">Total (BZD)</th>
                <th class="text-center" style="width:95px;">Status</th>
                <th style="width:60px;"></th>
              </tr>
            </thead>
            <tbody id="payins-body">
              <tr><td colspan="7" class="text-center py-4 text-muted">Loading&hellip;</td></tr>
            </tbody>
            <tfoot id="payins-footer" style="display:none;">
              <tr class="fw-bold" style="background:#fef3c7;">
                <td colspan="4" class="text-end" style="color:#92400e;">Grand Total</td>
                <td class="text-end" id="payins-grand" style="color:#92400e;">—</td>
                <td colspan="2"></td>
              </tr>
            </tfoot>
          </table>
          <div class="px-3 py-2 border-top text-end" style="background:#fafafa;">
            <a href="<?= url('views/pay-in/pay-in-list.php') ?>" style="font-size:.8rem;color:#b45309;font-weight:600;">View all pay-ins &rarr;</a>
          </div>
        </div>

      </div><!-- /card -->

    </div>
  </div>

<script>
var SHIFTS_URL      = "<?= url('api/pay-in/shifts.php') ?>";
var PAY_IN_VIEW_URL = "<?= url('views/pay-in/pay-in-view.php') ?>";
var PAY_IN_DATA     = <?= json_encode($recentPayIns, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var activeTab       = 'shifts';

// ---- Tab switching ----
function switchTab(tab) {
    activeTab = tab;
    var paneShifts  = document.getElementById('pane-shifts');
    var panePayins  = document.getElementById('pane-payins');
    var btnShifts   = document.getElementById('tab-btn-shifts');
    var btnPayins   = document.getElementById('tab-btn-payins');

    if (tab === 'shifts') {
        paneShifts.style.display = '';
        panePayins.style.display = 'none';
        btnShifts.style.borderBottomColor = '#1e4620';
        btnShifts.style.color = '#1e4620';
        btnPayins.style.borderBottomColor = 'transparent';
        btnPayins.style.color = '#9ca3af';
        loadShifts();
    } else {
        paneShifts.style.display = 'none';
        panePayins.style.display = '';
        btnPayins.style.borderBottomColor = '#b45309';
        btnPayins.style.color = '#92400e';
        btnShifts.style.borderBottomColor = 'transparent';
        btnShifts.style.color = '#9ca3af';
        renderPayIns();
    }
}

// ---- Formatting helpers ----
function fmt(amount) {
    if (amount === null || amount === undefined || amount === '') return '—';
    return parseFloat(amount).toLocaleString('en-BZ', {minimumFractionDigits:2, maximumFractionDigits:2});
}
function fmtDt(dt) {
    if (!dt) return '—';
    return dt.replace('T', ' ').substring(0, 16);
}

// ---- POS Shifts ----
function loadShifts() {
    var dateFrom = document.getElementById('filter-date-from').value;
    var dateTo   = document.getElementById('filter-date-to').value;
    var parts    = [];
    if (dateFrom) parts.push('date_from=' + encodeURIComponent(dateFrom));
    if (dateTo)   parts.push('date_to='   + encodeURIComponent(dateTo));
    var reqUrl = SHIFTS_URL + (parts.length ? '?' + parts.join('&') : '');

    var tbody   = document.getElementById('shifts-body');
    var tfooter = document.getElementById('shifts-footer');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Loading&hellip;</td></tr>';
    tfooter.style.display = 'none';

    apiGet(reqUrl).then(function(res) {
        var rows = res.data || [];
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No shifts found for this period.</td></tr>';
            return;
        }
        var html = '', grand = 0;
        rows.forEach(function(r) {
            grand += parseFloat(r.total_amount || 0);
            html += '<tr>'
                + '<td><span class="badge bg-blue-lt text-blue" style="font-family:monospace;font-size:.75rem;">' + r.shift_id + '</span></td>'
                + '<td>' + (r.cashier_name || '<span class="text-muted">Unknown</span>') + '</td>'
                + '<td style="font-size:.84rem;">' + fmtDt(r.shift_start) + '</td>'
                + '<td style="font-size:.84rem;">' + fmtDt(r.shift_end) + '</td>'
                + '<td class="text-end">' + parseInt(r.transaction_count || 0).toLocaleString() + '</td>'
                + '<td class="text-end fw-semibold">BZD ' + fmt(r.total_amount) + '</td>'
                + '</tr>';
        });
        tbody.innerHTML = html;
        document.getElementById('shifts-grand').textContent = 'BZD ' + fmt(grand);
        tfooter.style.display = 'table-footer-group';
    }).catch(function(e) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Error loading shifts: ' + e.message + '</td></tr>';
    });
}

// ---- Pay-In History (client-side filter from embedded data) ----
function renderPayIns() {
    var dateFrom = document.getElementById('filter-date-from').value;
    var dateTo   = document.getElementById('filter-date-to').value;
    var tbody    = document.getElementById('payins-body');
    var tfooter  = document.getElementById('payins-footer');

    var rows = PAY_IN_DATA.filter(function(p) {
        if (dateFrom && p.pay_in_date < dateFrom) return false;
        if (dateTo   && p.pay_in_date > dateTo)   return false;
        return true;
    });

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No pay-ins found for this period.</td></tr>';
        tfooter.style.display = 'none';
        return;
    }

    var html = '', grand = 0;
    rows.forEach(function(p) {
        grand += parseFloat(p.total_amount || 0);
        var badge = p.status === 'verified'
            ? '<span class="badge bg-success-lt text-success" style="font-size:.7rem;">Verified</span>'
            : (p.status === 'rejected'
                ? '<span class="badge bg-danger-lt text-danger" style="font-size:.7rem;">Rejected</span>'
                : '<span class="badge bg-yellow-lt text-yellow" style="font-size:.7rem;">Submitted</span>');
        var dept    = p.department_name || '<span class="text-muted fst-italic">Walk-in</span>';
        var cashier = (p.cashier_name && p.cashier_name.trim()) ? p.cashier_name.trim() : '—';
        html += '<tr>'
            + '<td><code class="text-muted" style="font-size:.78rem;">' + p.pay_in_id + '</code></td>'
            + '<td class="fw-medium">' + dept + '</td>'
            + '<td style="font-size:.84rem;">' + p.pay_in_date + '</td>'
            + '<td style="font-size:.84rem;">' + cashier + '</td>'
            + '<td class="text-end fw-semibold">BZD ' + fmt(p.total_amount) + '</td>'
            + '<td class="text-center">' + badge + '</td>'
            + '<td><a href="' + PAY_IN_VIEW_URL + '?id=' + encodeURIComponent(p.pay_in_id) + '" class="btn btn-xs btn-outline-secondary">View</a></td>'
            + '</tr>';
    });
    tbody.innerHTML = html;
    document.getElementById('payins-grand').textContent = 'BZD ' + fmt(grand);
    tfooter.style.display = 'table-footer-group';
}

// ---- Filter controls ----
document.getElementById('filter-date-from').addEventListener('change', function() {
    if (activeTab === 'shifts') loadShifts(); else renderPayIns();
});
document.getElementById('filter-date-to').addEventListener('change', function() {
    if (activeTab === 'shifts') loadShifts(); else renderPayIns();
});
document.getElementById('clear-filters-btn').addEventListener('click', function() {
    document.getElementById('filter-date-from').value = '';
    document.getElementById('filter-date-to').value   = '';
    if (activeTab === 'shifts') loadShifts(); else renderPayIns();
});
document.getElementById('refresh-btn').addEventListener('click', function() {
    if (activeTab === 'shifts') loadShifts(); else renderPayIns();
});

document.addEventListener('DOMContentLoaded', function() { switchTab('shifts'); });
</script>
<?php require_once __DIR__ . '/../../includes/layout-tabler-footer.php'; ?>
