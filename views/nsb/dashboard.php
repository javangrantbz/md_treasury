<?php
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::requireAuth();

require_once __DIR__ . '/../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-body">
    <div class="container-xl">

      <!-- Identity + KPIs -->
      <div class="card mb-4" style="border-left:4px solid var(--tblr-primary);">
        <div class="card-body py-3">
          <div style="display:flex;align-items:center;flex-wrap:wrap;gap:0;">

            <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;padding-right:.5rem;">
              <img src="<?= url('assets/img/nsb-logo.png') ?>" alt="NSB Logo" style="height:32px;width:auto;flex-shrink:0;">
              <div>
                <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.68rem;letter-spacing:.1em;white-space:nowrap;">Government of Belize &middot; Treasury Department</div>
                <div class="fw-bold" style="font-size:1.05rem;line-height:1.2;white-space:nowrap;">National Savings Bank</div>
              </div>
            </div>

            <div style="width:1px;background:#e2e8f0;align-self:stretch;flex-shrink:0;margin:0 .75rem;"></div>

            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:0;flex:1;min-width:0;">
              <div style="padding:0 16px;flex-shrink:0;">
                <div style="font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#9ca3af;margin-bottom:3px;white-space:nowrap;">Total Accounts</div>
                <div style="font-size:1.05rem;font-weight:900;line-height:1;color:var(--tblr-primary);" id="kpi-total">—</div>
              </div>
              <div style="width:1px;background:#e2e8f0;align-self:stretch;flex-shrink:0;"></div>
              <div style="padding:0 16px;flex-shrink:0;">
                <div style="font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#9ca3af;margin-bottom:3px;white-space:nowrap;">Active</div>
                <div style="font-size:1.05rem;font-weight:900;line-height:1;color:var(--tblr-success);" id="kpi-active">—</div>
              </div>
              <div style="width:1px;background:#e2e8f0;align-self:stretch;flex-shrink:0;"></div>
              <div style="padding:0 16px;flex-shrink:0;">
                <div style="font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#9ca3af;margin-bottom:3px;white-space:nowrap;">Total Balance</div>
                <div style="font-size:1.05rem;font-weight:900;line-height:1;color:var(--tblr-body-color);" id="kpi-balance">—</div>
              </div>
              <div style="width:1px;background:#e2e8f0;align-self:stretch;flex-shrink:0;"></div>
              <div style="padding:0 16px;flex-shrink:0;">
                <div style="font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#9ca3af;margin-bottom:3px;white-space:nowrap;">New This Month</div>
                <div style="font-size:1.05rem;font-weight:900;line-height:1;color:var(--tblr-azure);" id="kpi-month">—</div>
              </div>
            </div>

            <div class="ms-auto flex-shrink-0 ps-3">
              <a href="<?= url('views/nsb/accounts/add.php') ?>" class="btn btn-primary btn-sm">+ Add / Import Accounts</a>
            </div>

          </div>
        </div>
      </div>

      <div class="row g-3">

        <!-- Recent accounts -->
        <div class="col-lg-8">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Recent Accounts</h3>
              <div class="card-options"><a href="<?= url('views/nsb/accounts/list.php') ?>" style="font-size:.82rem;">View all &rarr;</a></div>
            </div>
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead>
                  <tr><th>Account #</th><th>Customer</th><th class="text-end">Balance</th><th>Opened</th><th>Status</th></tr>
                </thead>
                <tbody id="recent-body">
                  <tr><td colspan="5" class="text-center py-4 text-muted">Loading…</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Quick access -->
        <div class="col-lg-4">
          <div class="card">
            <div class="card-header"><h3 class="card-title">Module Sections</h3></div>
            <div class="card-body p-2">

              <div class="px-2 pt-1 pb-2">
                <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.7rem;letter-spacing:.08em;">Savings Accounts</div>
                <?php foreach ([
                  ['All Accounts',          url('views/nsb/accounts/list.php')],
                  ['Add / Import Accounts', url('views/nsb/accounts/add.php')],
                ] as [$label, $path]): ?>
                <a href="<?= $path ?>" class="d-flex align-items-center gap-2 py-2 text-decoration-none text-body border-bottom" style="font-size:.88rem;">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm text-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6l-6 6"/></svg>
                  <?= h($label) ?>
                </a>
                <?php endforeach; ?>
              </div>

              <div class="px-2 pt-2 pb-2">
                <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.7rem;letter-spacing:.08em;">Customers</div>
                <?php foreach ([
                  ['Customer List', url('views/nsb/customers/list.php')],
                  ['Add Customer',  url('views/nsb/customers/add.php')],
                ] as [$label, $path]): ?>
                <a href="<?= $path ?>" class="d-flex align-items-center gap-2 py-2 text-decoration-none text-body border-bottom" style="font-size:.88rem;">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm text-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6l-6 6"/></svg>
                  <?= h($label) ?>
                </a>
                <?php endforeach; ?>
              </div>

              <div class="px-2 pt-2 pb-1">
                <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.7rem;letter-spacing:.08em;">Card Applications <span class="badge bg-secondary-lt ms-1">Later</span></div>
                <?php foreach ([
                  ['New Application',       url('views/nsb/applications/new.php')],
                  ['Full Application List', url('views/nsb/applications/index.php')],
                ] as [$label, $path]): ?>
                <a href="<?= $path ?>" class="d-flex align-items-center gap-2 py-2 text-decoration-none text-body border-bottom" style="font-size:.88rem;">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm text-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6l-6 6"/></svg>
                  <?= h($label) ?>
                </a>
                <?php endforeach; ?>
              </div>

            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

<script>
const OVERVIEW_URL = "<?= url('api/nsb/accounts/overview.php') ?>";
const ADD_URL = "<?= url('views/nsb/accounts/add.php') ?>";

function fmtMoney(n) { return 'BZD ' + (parseFloat(n || 0)).toLocaleString('en-BZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function esc(s) { const d = document.createElement('div'); d.textContent = (s == null ? '' : s); return d.innerHTML; }

document.addEventListener('DOMContentLoaded', function() {
  apiGet(OVERVIEW_URL).then(function(res) {
    const d = res.data || {};
    document.getElementById('kpi-total').textContent   = (d.total_accounts || 0).toLocaleString();
    document.getElementById('kpi-active').textContent  = (d.active_accounts || 0).toLocaleString();
    document.getElementById('kpi-balance').textContent = fmtMoney(d.total_balance);
    document.getElementById('kpi-month').textContent   = (d.new_this_month || 0).toLocaleString();

    const tb = document.getElementById('recent-body');
    const rows = d.recent_accounts || [];
    if (!rows.length) {
      tb.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No accounts yet. <a href="' + ADD_URL + '">Add or import accounts</a> to get started.</td></tr>';
      return;
    }
    const colors = { active: 'success', dormant: 'warning', closed: 'secondary' };
    tb.innerHTML = rows.map(function(r) {
      const badge = colors[r.status] || 'secondary';
      return '<tr>'
        + '<td style="font-family:monospace;" class="text-muted">' + (esc(r.account_number) || '—') + '</td>'
        + '<td class="fw-medium">' + esc(r.customer_name) + '</td>'
        + '<td class="text-end fw-semibold">' + fmtMoney(r.current_balance) + '</td>'
        + '<td class="text-muted small">' + (r.starting_date || '—') + '</td>'
        + '<td><span class="badge bg-' + badge + '-lt text-capitalize">' + esc(r.status) + '</span></td>'
        + '</tr>';
    }).join('');
  }).catch(function(e) {
    document.getElementById('recent-body').innerHTML =
      '<tr><td colspan="5" class="text-center py-4 text-danger">' + esc(e.message) + '</td></tr>';
    ['kpi-total','kpi-active','kpi-balance','kpi-month'].forEach(function(id){ document.getElementById(id).textContent = 'Err'; });
  });
});
</script>

<?php require_once __DIR__ . '/../../includes/layout-tabler-footer.php'; ?>
