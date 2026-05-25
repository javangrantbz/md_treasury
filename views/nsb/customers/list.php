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
          <div class="page-pretitle">NSB — Customers</div>
          <h2 class="page-title">Customer List</h2>
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
          <input type="text" id="search-input" class="form-control form-control-sm w-auto" placeholder="Search customers...">
        </div>
        <div class="card-body p-0">
          <div id="table-message" class="alert m-3" style="display:none"></div>
          <table class="table table-vcenter table-hover card-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Type</th>
                <th>Status</th>
                <th class="w-1"></th>
              </tr>
            </thead>
            <tbody id="table-body">
              <tr><td colspan="6" class="text-center py-4 text-muted">Loading...</td></tr>
            </tbody>
          </table>
          <div id="table-pagination"></div>
        </div>
      </div>
    </div>
  </div>

<script>
// For now, pulling from master-data/users as requested
const LIST_URL = "<?= url('api/master-data/users/list.php') ?>";

async function loadRows(search = '') {
  const url = LIST_URL + (search ? `?search=${encodeURIComponent(search)}` : '');
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
    <td>${(r.first_name ?? '') + ' ' + (r.last_name ?? '')}</td>
    <td>${r.username ?? ''}</td>
    <td>${r.email ?? ''}</td>
    <td style="text-transform:capitalize">${r.user_type ?? ''}</td>
    <td><span class="badge bg-${r.status === 'active' ? 'success' : 'secondary'}-lt">${r.status}</span></td>
    <td>
      <a href="<?= url('views/nsb/customers/profile.php') ?>?id=${r.id}" class="btn btn-sm btn-outline-secondary">Profile</a>
    </td>
  </tr>`;
}

function renderTable(rows) {
  window.pager.setData(rows);
}

document.getElementById('search-input').addEventListener('input', function () {
  loadRows(this.value.trim());
});

document.addEventListener('DOMContentLoaded', () => {
  window.pager = new TablePager({ tbodyId: 'table-body', paginationId: 'table-pagination', colCount: 6, renderRow });
  loadRows();
});
</script>
<?php require_once __DIR__ . '/../../../includes/layout-tabler-footer.php'; ?>
