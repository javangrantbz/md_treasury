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
          <div class="page-pretitle">NSB — Ledger</div>
          <h2 class="page-title">Deposit Ledger</h2>
        </div>
        <div class="col-auto ms-auto">
          <a href="<?= url('views/nsb/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
        </div>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">
      <div class="card">
        <div class="card-header d-flex gap-2">
          <input type="text" id="search-input" class="form-control form-control-sm w-auto" placeholder="Search deposits...">
        </div>
        <div class="card-body p-0">
          <div id="table-message" class="alert m-3" style="display:none"></div>
          <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
              <thead>
                <tr>
                  <th>Date & Time</th>
                  <th>Transaction ID</th>
                  <th>Account Number</th>
                  <th>Account Holder's Name</th>
                  <th>Deposit Amount</th>
                  <th>Method of Deposit</th>
                  <th>Reference Number</th>
                  <th>Processed By</th>
                  <th>Status</th>
                  <th class="w-1"></th>
                </tr>
              </thead>
              <tbody id="table-body">
                <tr><td colspan="10" class="text-center py-4 text-muted">No deposit records found.</td></tr>
              </tbody>
            </table>
          </div>
          <div id="table-pagination"></div>
        </div>
      </div>
    </div>
  </div>

<script>
const LIST_URL = "<?= url('api/nsb/ledger/list.php') ?>?type=deposit";

async function loadRows(search = '') {
  const url = LIST_URL + (search ? `&search=${encodeURIComponent(search)}` : '');
  try {
    const res = await apiGet(url);
    renderTable(res.data || []);
  } catch (e) {
    document.getElementById('table-message').textContent = e.message;
    document.getElementById('table-message').style.display = 'block';
  }
}

function renderRow(r) {
  return `<tr>
    <td><span class="text-muted" style="font-size:.8rem">${r.created_at ?? '—'}</span></td>
    <td class="fw-semibold">${r.id ?? '—'}</td>
    <td>${r.account_number ?? '—'}</td>
    <td>${r.account_holder ?? '—'}</td>
    <td class="text-success fw-bold">${r.amount ? 'BZD ' + parseFloat(r.amount).toLocaleString(undefined, {minimumFractionDigits: 2}) : '—'}</td>
    <td>${r.method ?? '—'}</td>
    <td class="text-muted" style="font-size:.85rem">${r.reference ?? '—'}</td>
    <td>${r.processed_by ?? '—'}</td>
    <td><span class="badge bg-${r.status === 'completed' ? 'success' : 'secondary'}-lt">${r.status ?? '—'}</span></td>
    <td>
      <button class="btn btn-sm btn-outline-secondary">Details</button>
    </td>
  </tr>`;
}

function renderTable(rows) {
  if (rows.length === 0) {
    document.getElementById('table-body').innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">No deposit records found.</td></tr>';
    return;
  }
  window.pager.setData(rows);
}

document.getElementById('search-input').addEventListener('input', function () {
  loadRows(this.value.trim());
});

document.addEventListener('DOMContentLoaded', () => {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 10, renderRow });
  loadRows();
});
</script>
<?php require_once __DIR__ . '/../../../includes/layout-tabler-footer.php'; ?>
