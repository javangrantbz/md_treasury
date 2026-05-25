<?php
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/Rbac.php';
require_once __DIR__ . '/../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'expenses.view');
require_once __DIR__ . '/../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row align-items-center">
        <div class="col">
          <div class="page-pretitle">Government of Belize — Treasury Department</div>
          <h2 class="page-title">Expense Entries</h2>
        </div>
        <div class="col-auto ms-auto d-flex gap-2">
          <a href="<?= url('views/cashiering/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">← Cashiering</a>
          <a href="<?= url('views/cashiering/expenses/create.php') ?>" class="btn btn-primary">Add Expense Entry</a>
        </div>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">
      <div class="card">
        <div class="card-header d-flex gap-2">
          <input type="text" id="search-input" class="form-control form-control-sm w-auto" placeholder="Search...">
          <select id="filter-status" class="form-select form-select-sm w-auto">
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="submitted">Submitted</option>
            <option value="approved">Approved</option>
            <option value="paid">Paid</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
        <div class="card-body p-0">
          <div id="table-message" class="alert m-3" style="display:none"></div>
          <table class="table table-vcenter table-hover card-table">
            <thead>
              <tr>
                <th data-col="expense_number">Expense #</th>
                <th data-col="supplier_name">Supplier</th>
                <th data-col="expenditure_name">Type</th>
                <th data-col="invoice_number">Invoice #</th>
                <th data-col="invoice_date">Invoice Date</th>
                <th data-col="total_amount">Amount</th>
                <th data-col="status">Status</th>
                <th class="w-1"></th>
              </tr>
            </thead>
            <tbody id="table-body">
              <tr><td colspan="8" class="text-center py-4 text-muted">Loading...</td></tr>
            </tbody>
          </table>
          <div id="table-pagination"></div>
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
const LIST_URL = "<?= url('api/expenses/list.php') ?>";
const DETAILS_URL = "<?= url('views/cashiering/expenses/details.php') ?>";
const EDIT_URL    = "<?= url('views/cashiering/expenses/edit.php') ?>";

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
    draft:     ['bg-secondary-lt text-secondary', 'Draft'],
    submitted: ['bg-info-lt text-info',            'Submitted'],
    approved:  ['bg-success-lt text-success',      'Approved'],
    paid:      ['bg-success text-white',            'Paid'],
    rejected:  ['bg-danger-lt text-danger',         'Rejected'],
  };
  const [cls, label] = map[val] || ['bg-secondary-lt text-secondary', val ?? '—'];
  return `<span class="badge ${cls}">${label}</span>`;
}

function formatAmount(currency, amount) {
  if (amount === null || amount === undefined || amount === '') return '—';
  return `${currency ?? ''} ${parseFloat(amount).toFixed(2)}`;
}

async function loadRows() {
  const search = document.getElementById('search-input').value.trim();
  const status = document.getElementById('filter-status').value;
  let url = LIST_URL;
  const params = [];
  if (search) params.push('search=' + encodeURIComponent(search));
  if (status) params.push('status=' + encodeURIComponent(status));
  if (params.length) url += '?' + params.join('&');

  clearMsg('table-message');
  try {
    const res = await apiGet(url);
    window.pager.setData(res.data || []);
  } catch (e) {
    showMsg('table-message', e.message);
    window.pager.setData([]);
  }
}

function renderRow(r) {
  return `<tr>
    <td><a href="${DETAILS_URL}?id=${r.id}" class="text-reset">${r.expense_number ?? '—'}</a></td>
    <td>${r.supplier_name ?? '—'}</td>
    <td>${r.expenditure_name ?? '—'}</td>
    <td>${r.invoice_number ?? '—'}</td>
    <td>${r.invoice_date ?? '—'}</td>
    <td>${formatAmount(r.currency_code, r.total_amount)}</td>
    <td>${statusBadge(r.status)}</td>
    <td>
      <div class="d-flex gap-1 justify-content-end">
        <a class="btn btn-sm btn-outline-secondary" href="${DETAILS_URL}?id=${r.id}">Details</a>
        <a class="btn btn-sm btn-outline-primary" href="${EDIT_URL}?id=${r.id}">Edit</a>
      </div>
    </td>
  </tr>`;
}

let searchTimer;
document.getElementById('search-input').addEventListener('input', function () {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(loadRows, 300);
});
document.getElementById('filter-status').addEventListener('change', loadRows);

document.addEventListener('DOMContentLoaded', () => {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 8, renderRow });
  loadRows();
});
</script>
<?php require_once __DIR__ . '/../../../includes/layout-tabler-footer.php'; ?>
