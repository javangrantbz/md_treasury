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
        <h2 class="page-title">Card Applications</h2>
      </div>
      <div class="col-auto ms-auto">
        <a href="<?= url('views/nsb/applications/new.php') ?>" class="btn btn-primary d-none d-sm-inline-block">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
          New Application
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
          <li class="nav-item">
            <a href="#tab-all" class="nav-link active" data-status="">All Applications</a>
          </li>
          <li class="nav-item">
            <a href="#tab-pending" class="nav-link" data-status="pending">Pending</a>
          </li>
          <li class="nav-item">
            <a href="#tab-processing" class="nav-link" data-status="processing">In Process</a>
          </li>
          <li class="nav-item">
            <a href="#tab-approved" class="nav-link" data-status="approved">Approved</a>
          </li>
          <li class="nav-item">
            <a href="#tab-void" class="nav-link" data-status="void">Void</a>
          </li>
        </ul>
      </div>
      <div class="card-body p-0">
        <div id="table-message" class="alert m-3" style="display:none"></div>
        <div class="table-responsive">
          <table class="table table-vcenter table-hover card-table">
            <thead>
              <tr>
                <th>Customer</th>
                <th>NIC / Account</th>
                <th>Card Type</th>
                <th>Branch</th>
                <th>Status</th>
                <th>Created</th>
                <th class="w-1"></th>
              </tr>
            </thead>
            <tbody id="table-body">
              <tr><td colspan="7" class="text-center py-4 text-muted">Loading...</td></tr>
            </tbody>
          </table>
        </div>
        <div id="table-pagination"></div>
      </div>
    </div>
  </div>
</div>

<script>
const LIST_URL = "<?= url('api/nsb/applications/list.php') ?>";
let currentStatus = '';

async function loadRows() {
  const url = LIST_URL + (currentStatus ? `?status=${currentStatus}` : '');
  try {
    const res = await apiGet(url);
    renderTable(res.data || []);
  } catch (e) {
    document.getElementById('table-message').textContent = e.message;
    document.getElementById('table-message').style.display = 'block';
  }
}

function renderRow(r) {
  const statusColors = {
    'pending': 'warning',
    'processing': 'azure',
    'approved': 'success',
    'rejected': 'danger',
    'printed': 'info',
    'delivered': 'primary',
    'void': 'secondary'
  };
  const badgeColor = statusColors[r.status] || 'secondary';
  const statusLabel = r.status === 'processing' ? 'In Process' : r.status;

  return `<tr>
    <td>
      <div class="font-weight-medium">${r.customer_name}</div>
    </td>
    <td>
      <div class="text-muted small">NIC: ${r.nic}</div>
      <div class="small">Acc: ${r.account_number}</div>
    </td>
    <td class="text-capitalize">${r.card_type}</td>
    <td>
      <div class="small">${r.branch_code}</div>
      <div class="text-muted small">${r.branch_name}</div>
    </td>
    <td>
      <span class="badge bg-${badgeColor}-lt text-capitalize">${statusLabel}</span>
    </td>
    <td class="text-muted small">
      ${new Date(r.created_at).toLocaleDateString()}<br>
      ${new Date(r.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
    </td>
    <td>
      <div class="btn-list flex-nowrap">
        <a href="<?= url('views/nsb/applications/process-card.php') ?>?id=${r.id}" class="btn btn-sm btn-white">
          Manage
        </a>
      </div>
    </td>
  </tr>`;
}

function renderTable(rows) {
  window.pager.setData(rows);
}

document.querySelectorAll('#status-tabs .nav-link').forEach(link => {
  link.addEventListener('click', function(e) {
    e.preventDefault();
    document.querySelectorAll('#status-tabs .nav-link').forEach(l => l.classList.remove('active'));
    this.classList.add('active');
    currentStatus = this.dataset.status;
    loadRows();
  });
});

document.addEventListener('DOMContentLoaded', () => {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 7, renderRow });
  loadRows();
});
</script>
<?php require_once __DIR__ . '/../../../includes/layout-tabler-footer.php'; ?>
