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
        <div class="page-pretitle">NSB — Operations</div>
        <h2 class="page-title">Approved Card Applications</h2>
      </div>
      <div class="col-auto ms-auto">
        <a href="<?= url('views/nsb/applications/index.php') ?>" class="btn btn-outline-secondary btn-sm">← All Applications</a>
      </div>
    </div>
  </div>
</div>
<div class="page-body">
  <div class="container-xl">
    <div class="card">
      <div class="card-body p-0">
        <div id="table-message" class="alert m-3" style="display:none"></div>
        <table class="table table-vcenter table-hover card-table">
          <thead>
            <tr>
              <th>Customer</th>
              <th>NIC</th>
              <th>Account</th>
              <th>Card Type</th>
              <th>Branch</th>
              <th>Created</th>
              <th class="w-1"></th>
            </tr>
          </thead>
          <tbody id="table-body">
            <tr><td colspan="7" class="text-center py-4 text-muted">Loading...</td></tr>
          </tbody>
        </table>
        <div id="table-pagination"></div>
      </div>
    </div>
  </div>
</div>

<script>
const LIST_URL = "<?= url('api/nsb/applications/list.php') ?>?status=approved";

async function loadRows() {
  try {
    const res = await apiGet(LIST_URL);
    renderTable(res.data || []);
  } catch (e) {
    document.getElementById('table-message').textContent = e.message;
    document.getElementById('table-message').style.display = 'block';
  }
}

function renderRow(r) {
  return `<tr>
    <td><div class="font-weight-medium">${r.customer_name}</div></td>
    <td class="text-muted">${r.nic}</td>
    <td>${r.account_number}</td>
    <td class="text-capitalize">${r.card_type}</td>
    <td>${r.branch_code} - ${r.branch_name}</td>
    <td class="text-muted">${new Date(r.created_at).toLocaleDateString()}</td>
    <td>
      <a href="<?= url('views/nsb/applications/process-card.php') ?>?id=${r.id}" class="btn btn-sm btn-outline-secondary">Process</a>
    </td>
  </tr>`;
}

function renderTable(rows) {
  window.pager.setData(rows);
}

document.addEventListener('DOMContentLoaded', () => {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 7, renderRow });
  loadRows();
});
</script>
<?php require_once __DIR__ . '/../../../includes/layout-tabler-footer.php'; ?>
