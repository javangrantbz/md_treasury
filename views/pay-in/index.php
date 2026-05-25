<?php
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::requireAuth();

$authUser       = Auth::user();
$fullName       = Auth::fullName();
$roleName       = !empty($authUser['role_names']) ? $authUser['role_names'][0] : null;
$departmentName = $authUser['department_name'] ?? 'Treasury Department';
$lastLoginAt    = $authUser['last_login_at'] ?? null;
$lastLoginFmt   = $lastLoginAt ? date('F j, Y \a\t g:i A', strtotime($lastLoginAt)) : 'First session';

require_once __DIR__ . '/../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-body">
    <div class="container-xl">

      <!-- Identity + profile card -->
      <div class="card mb-4" style="border-left: 4px solid var(--tblr-primary);">
        <div class="card-body py-3">
          <div class="row align-items-center g-0">
            <div class="col-12 col-md-auto pe-md-4">
              <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.68rem;letter-spacing:.1em;">Government of Belize &middot; Treasury Department</div>
              <div class="fw-bold" style="font-size:1.05rem;line-height:1.2;">Pay-In Shift Summary</div>
            </div>
            <div class="col-auto d-none d-md-flex px-4">
              <div style="width:1px;height:2.8rem;background:var(--tblr-border-color);"></div>
            </div>
            <div class="col d-flex align-items-center gap-3 mt-3 mt-md-0">
              <div class="avatar avatar-md bg-primary text-white fw-bold position-relative" style="font-size:1rem;flex-shrink:0;">
                <?php echo strtoupper(substr($authUser['first_name'] ?? 'U', 0, 1) . substr($authUser['last_name'] ?? '', 0, 1)); ?>
                <a href="<?php echo url('views/profile/index.php'); ?>" class="badge bg-white text-primary position-absolute bottom-0 end-0 p-1 border shadow-sm" style="transform:translate(25%,25%);" title="Update Profile">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-inline m-0" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                </a>
              </div>
              <div>
                <div class="fw-bold mb-0" style="font-size:.95rem;"><?php echo h($fullName); ?></div>
                <div class="text-muted" style="font-size:.82rem;">
                  <?php if ($roleName): ?><?php echo h($roleName); ?> &mdash; <?php endif; ?><?php echo h($departmentName); ?>
                </div>
                <div style="font-size:.74rem;color:var(--tblr-secondary);">
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1" style="vertical-align:-1px"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                  Last Login: <?php echo h($lastLoginFmt); ?>
                </div>
              </div>
              <div class="ms-auto d-print-none">
                <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">Print / Export</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header flex-wrap gap-2">
          <div class="d-flex align-items-center gap-2">
            <label class="form-label mb-0">From:</label>
            <input type="date" id="filter-date-from" class="form-control form-control-sm w-auto">
          </div>
          <div class="d-flex align-items-center gap-2">
            <label class="form-label mb-0">To:</label>
            <input type="date" id="filter-date-to"   class="form-control form-control-sm w-auto">
          </div>
          <button class="btn btn-sm btn-outline-secondary" id="clear-filters-btn">Clear</button>
          <div class="ms-auto">
            <button class="btn btn-sm btn-primary" onclick="loadShifts()">Refresh</button>
          </div>
        </div>
        <div class="card-body p-0">
          <div id="table-message" class="alert m-3" style="display:none"></div>
          <table class="table table-vcenter table-hover card-table">
            <thead>
              <tr>
                <th>Shift ID</th>
                <th>Cashier Name</th>
                <th>Shift Start</th>
                <th>Shift End</th>
                <th class="text-end">Transactions</th>
                <th class="text-end">Total Amount (BZD)</th>
              </tr>
            </thead>
            <tbody id="table-body">
              <tr><td colspan="6" class="text-center py-4 text-muted">Loading...</td></tr>
            </tbody>
            <tfoot id="table-footer" style="display:none">
              <tr class="bg-light fw-bold">
                <td colspan="5" class="text-end">Grand Total:</td>
                <td class="text-end" id="grand-total">—</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

    </div>
  </div>

<script>
const SHIFTS_URL = "<?= url('api/pay-in/shifts.php') ?>";

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

function fmt(amount) {
  if (amount === null || amount === undefined || amount === '') return '—';
  return parseFloat(amount).toLocaleString('en-BZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dt) {
  if (!dt) return '—';
  return dt.replace('T', ' ').substring(0, 16);
}

async function loadShifts() {
  const dateFrom = document.getElementById('filter-date-from').value;
  const dateTo   = document.getElementById('filter-date-to').value;

  const parts = [];
  if (dateFrom) parts.push('date_from=' + encodeURIComponent(dateFrom));
  if (dateTo)   parts.push('date_to='   + encodeURIComponent(dateTo));

  const url = SHIFTS_URL + (parts.length ? '?' + parts.join('&') : '');
  const tbody = document.getElementById('table-body');
  const tfooter = document.getElementById('table-footer');
  const grandTotalEl = document.getElementById('grand-total');

  tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Loading...</td></tr>';
  tfooter.style.display = 'none';
  clearMsg('table-message');

  try {
    const res = await apiGet(url);
    const rows = res.data || [];

    if (rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No shifts found for the selected period.</td></tr>';
      return;
    }

    let html = '';
    let grandTotal = 0;

    rows.forEach(r => {
      grandTotal += parseFloat(r.total_amount || 0);
      html += `<tr>
        <td><span class="badge bg-blue-lt text-blue">${r.shift_id}</span></td>
        <td>${r.cashier_name ?? '<span class="text-muted">Unknown</span>'}</td>
        <td style="font-size:.85rem;">${formatDate(r.shift_start)}</td>
        <td style="font-size:.85rem;">${formatDate(r.shift_end)}</td>
        <td class="text-end">${parseInt(r.transaction_count).toLocaleString()}</td>
        <td class="text-end fw-semibold">${fmt(r.total_amount)}</td>
      </tr>`;
    });

    tbody.innerHTML = html;
    grandTotalEl.textContent = 'BZD ' + fmt(grandTotal);
    tfooter.style.display = 'table-footer-group';

  } catch (e) {
    showMsg('table-message', e.message);
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Error loading data.</td></tr>';
  }
}

document.getElementById('filter-date-from').addEventListener('change', loadShifts);
document.getElementById('filter-date-to').addEventListener('change', loadShifts);
document.getElementById('clear-filters-btn').addEventListener('click', function () {
  document.getElementById('filter-date-from').value = '';
  document.getElementById('filter-date-to').value   = '';
  loadShifts();
});

document.addEventListener('DOMContentLoaded', loadShifts);
</script>
<?php require_once __DIR__ . '/../../includes/layout-tabler-footer.php'; ?>
