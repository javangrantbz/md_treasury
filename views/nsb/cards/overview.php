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
        <div class="page-pretitle">NSB — Analytics</div>
        <h2 class="page-title">Card Operations Overview</h2>
      </div>
      <div class="col-auto ms-auto">
        <div class="btn-list">
          <a href="<?= url('views/nsb/applications/new.php') ?>" class="btn btn-primary d-none d-sm-inline-block">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
            New Application
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    <div id="error-message" class="alert alert-danger" style="display:none"></div>
    
    <div class="row row-cards mb-4">
      <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
          <div class="card-body">
            <div class="row align-items-center">
              <div class="col-auto">
                <span class="bg-primary text-white avatar">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 5m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z" /><path d="M3 10l18 0" /><path d="M7 15l.01 0" /><path d="M11 15l2 0" /></svg>
                </span>
              </div>
              <div class="col">
                <div class="font-weight-medium" id="stat-total-apps">0</div>
                <div class="text-muted small">Total Applications</div>
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
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
                </span>
              </div>
              <div class="col">
                <div class="font-weight-medium" id="stat-issued">0</div>
                <div class="text-muted small">Total Issued</div>
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
                <span class="bg-warning text-white avatar">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 9v2m0 4v.01" /><path d="M5 19h14a2 2 0 0 0 1.84 -2.75l-7.1 -12.25a2 2 0 0 0 -3.5 0l-7.1 12.25a2 2 0 0 0 1.75 2.75" /></svg>
                </span>
              </div>
              <div class="col">
                <div class="font-weight-medium" id="stat-active">0</div>
                <div class="text-muted small">Pending/Processing</div>
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
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 5m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z" /><path d="M16 3l0 4" /><path d="M8 3l0 4" /><path d="M4 11l16 0" /><path d="M11 15l1 0" /><path d="M12 15l0 3" /></svg>
                </span>
              </div>
              <div class="col">
                <div class="font-weight-medium" id="stat-month">0</div>
                <div class="text-muted small">Issued This Month</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-4">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Cards by Type</h3></div>
          <div class="card-body">
            <div id="chart-by-type">
              <div class="d-flex justify-content-between mb-2">
                <span>Debit Cards</span>
                <span class="fw-bold" id="type-debit">0</span>
              </div>
              <div class="progress progress-sm mb-3">
                <div class="progress-bar bg-primary" id="bar-debit" style="width: 0%"></div>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span>ATM Cards</span>
                <span class="fw-bold" id="type-atm">0</span>
              </div>
              <div class="progress progress-sm mb-3">
                <div class="progress-bar bg-azure" id="bar-atm" style="width: 0%"></div>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span>Credit Cards</span>
                <span class="fw-bold" id="type-credit">0</span>
              </div>
              <div class="progress progress-sm mb-3">
                <div class="progress-bar bg-indigo" id="bar-credit" style="width: 0%"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-8">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Recent Activity</h3>
            <div class="card-actions">
              <a href="<?= url('views/nsb/applications/index.php') ?>">View All</a>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table">
              <thead>
                <tr>
                  <th>Customer</th>
                  <th>Branch</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody id="recent-activity-body">
                <tr><td colspan="4" class="text-center py-4 text-muted">Loading activity...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
async function loadOverview() {
  try {
    const res = await apiGet("<?= url('api/nsb/cards/overview.php') ?>");
    const d = res.data;

    document.getElementById('stat-total-apps').textContent = d.total_applications;
    document.getElementById('stat-issued').textContent = d.total_issued;
    document.getElementById('stat-active').textContent = d.active_requests;
    document.getElementById('stat-month').textContent = d.this_month_count;

    // Type Stats
    const total = d.total_applications || 1;
    ['debit', 'atm', 'credit'].forEach(type => {
      const count = d.by_type[type] || 0;
      const pct = (count / total * 100).toFixed(1);
      document.getElementById(`type-${type}`).textContent = count;
      document.getElementById(`bar-${type}`).style.width = pct + '%';
    });

    // Recent Activity
    const tbody = document.getElementById('recent-activity-body');
    if (d.recent_activity.length === 0) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No recent activity</td></tr>';
    } else {
      tbody.innerHTML = d.recent_activity.map(a => {
        const statusColors = {
          'pending': 'warning', 'processing': 'azure', 'approved': 'success',
          'rejected': 'danger', 'printed': 'info', 'delivered': 'primary', 'void': 'secondary'
        };
        const badgeColor = statusColors[a.status] || 'secondary';
        return `<tr>
          <td><div class="font-weight-medium">${a.customer_name}</div></td>
          <td class="text-muted small">${a.branch_name}</td>
          <td><span class="badge bg-${badgeColor}-lt text-capitalize">${a.status}</span></td>
          <td class="text-muted small">${new Date(a.created_at).toLocaleDateString()}</td>
        </tr>`;
      }).join('');
    }

  } catch (e) {
    document.getElementById('error-message').textContent = e.message;
    document.getElementById('error-message').style.display = 'block';
  }
}

document.addEventListener('DOMContentLoaded', loadOverview);
</script>

<?php require_once __DIR__ . '/../../../includes/layout-tabler-footer.php'; ?>
