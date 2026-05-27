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
        <h2 class="page-title">New Card Application</h2>
      </div>
      <div class="col-auto ms-auto">
        <a href="<?= url('views/nsb/applications/index.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Back to List</a>
      </div>
    </div>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    <div id="form-message" class="alert mb-3" style="display:none;"></div>

    <div class="row g-4 align-items-start">

      <!-- Form — 3/4 -->
      <div class="col-lg-9">
        <div class="card">
          <div class="card-header">
            <div class="card-title">Application Details</div>
          </div>
          <div class="card-body">
            <form id="application-form">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label required">Customer Name</label>
                  <input type="text" class="form-control" name="customer_name" required placeholder="Full name as per NIC">
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label required">NIC Number</label>
                  <input type="text" class="form-control" name="nic" required placeholder="e.g. 199012345678 or 901234567V">
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label required">Account Number</label>
                  <input type="text" class="form-control" name="account_number" required placeholder="12-digit account number">
                </div>
                <div class="col-md-3 mb-3">
                  <label class="form-label required">Card Type</label>
                  <select class="form-select" name="card_type" required>
                    <option value="debit">Debit Card</option>
                    <option value="atm">ATM Card</option>
                    <option value="credit">Credit Card</option>
                  </select>
                </div>
                <div class="col-md-3 mb-3">
                  <label class="form-label required">Branch</label>
                  <select class="form-select" name="branch_id" id="branch_id" required>
                    <option value="">Loading…</option>
                  </select>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Remarks</label>
                <textarea class="form-control" name="remarks" rows="3" placeholder="Any additional notes…"></textarea>
              </div>

              <div class="form-footer">
                <button type="submit" class="btn btn-primary" id="submit-btn">Submit Application</button>
                <button type="reset" class="btn btn-link">Reset</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Sidebar — 1/4 -->
      <div class="col-lg-3">

        <!-- Application Guidelines -->
        <div class="card mb-3">
          <div class="card-header" style="background:#f8fafc;">
            <div class="card-title" style="font-size:.82rem;">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1 text-primary" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 9h.01M11 12h1v4h1"/></svg>
              Application Guidelines
            </div>
          </div>
          <div class="card-body p-0">

            <div class="px-3 py-2 border-bottom">
              <div class="text-uppercase fw-bold text-muted mb-2" style="font-size:.63rem;letter-spacing:.08em;">Required Documents</div>
              <?php foreach ([
                'Valid National Identity Card (NIC)',
                'Account must be active and in good standing',
                'Customer must be physically present',
              ] as $item): ?>
              <div class="d-flex align-items-start gap-2 mb-1" style="font-size:.8rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--tblr-success)" stroke-width="3" style="flex-shrink:0;margin-top:3px;"><polyline points="20 6 9 17 4 12"/></svg>
                <span><?= $item ?></span>
              </div>
              <?php endforeach; ?>
            </div>

            <div class="px-3 py-2 border-bottom">
              <div class="text-uppercase fw-bold text-muted mb-2" style="font-size:.63rem;letter-spacing:.08em;">Card Types</div>
              <div class="mb-2">
                <div class="d-flex align-items-center gap-1 mb-1">
                  <span class="badge bg-primary-lt text-primary" style="font-size:.65rem;">Debit</span>
                </div>
                <div style="font-size:.78rem;color:#6b7280;">Linked to savings or current account. Standard purchases &amp; withdrawals.</div>
              </div>
              <div class="mb-2">
                <div class="d-flex align-items-center gap-1 mb-1">
                  <span class="badge bg-azure-lt text-azure" style="font-size:.65rem;">ATM</span>
                </div>
                <div style="font-size:.78rem;color:#6b7280;">Cash withdrawals only. No point-of-sale transactions.</div>
              </div>
              <div>
                <div class="d-flex align-items-center gap-1 mb-1">
                  <span class="badge bg-warning-lt text-warning" style="font-size:.65rem;">Credit</span>
                </div>
                <div style="font-size:.78rem;color:#6b7280;">Subject to credit assessment. Additional income verification required.</div>
              </div>
            </div>

            <div class="px-3 py-2">
              <div class="text-uppercase fw-bold text-muted mb-2" style="font-size:.63rem;letter-spacing:.08em;">Processing Times</div>
              <div class="d-flex justify-content-between mb-1" style="font-size:.78rem;">
                <span class="text-muted">Review</span>
                <span class="fw-semibold">2–3 business days</span>
              </div>
              <div class="d-flex justify-content-between mb-1" style="font-size:.78rem;">
                <span class="text-muted">Card delivery</span>
                <span class="fw-semibold">7–10 business days</span>
              </div>
              <div class="d-flex justify-content-between" style="font-size:.78rem;">
                <span class="text-muted">Notification</span>
                <span class="fw-semibold">Phone / Email</span>
              </div>
            </div>

          </div>
        </div>

        <!-- Recent Applications -->
        <div class="card">
          <div class="card-header" style="background:#f8fafc;">
            <div class="card-title" style="font-size:.82rem;">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1 text-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
              Recent Submissions
            </div>
          </div>
          <div id="recent-apps-body" class="card-body p-0">
            <div class="text-center text-muted py-3" style="font-size:.8rem;">Loading…</div>
          </div>
          <div class="card-footer py-2 text-end" style="background:#f8fafc;">
            <a href="<?= url('views/nsb/applications/index.php') ?>" style="font-size:.78rem;color:var(--tblr-primary);font-weight:600;">View all &rarr;</a>
          </div>
        </div>

      </div><!-- /sidebar -->

    </div><!-- /row -->
  </div>
</div>

<script>
var BRANCHES_URL = "<?= url('api/master-data/branches/list.php') ?>?status=active";
var APPS_URL     = "<?= url('api/nsb/applications/list.php') ?>";
var SUBMIT_URL   = "<?= url('api/nsb/applications/create.php') ?>";
var LIST_URL     = "<?= url('views/nsb/applications/index.php') ?>";

var CARD_TYPE_LABELS = {debit: 'Debit', atm: 'ATM', credit: 'Credit'};
var STATUS_STYLES = {
    pending:    'bg-yellow-lt text-yellow',
    approved:   'bg-success-lt text-success',
    rejected:   'bg-danger-lt text-danger',
    processing: 'bg-blue-lt text-blue'
};

// ---- Branches ----
document.addEventListener('DOMContentLoaded', function() {
    var branchSelect = document.getElementById('branch_id');
    apiGet(BRANCHES_URL).then(function(res) {
        branchSelect.innerHTML = '<option value="">Select Branch</option>';
        (res.data || []).forEach(function(b) {
            var opt = document.createElement('option');
            opt.value = b.id;
            opt.textContent = b.code + ' - ' + b.name;
            branchSelect.appendChild(opt);
        });
    }).catch(function() {
        branchSelect.innerHTML = '<option value="">Error loading branches</option>';
    });

    loadRecentApps();
});

// ---- Recent Applications ----
function loadRecentApps() {
    var container = document.getElementById('recent-apps-body');
    apiGet(APPS_URL).then(function(res) {
        var rows = (res.data || []).slice(0, 5);
        if (!rows.length) {
            container.innerHTML = '<div class="text-center text-muted py-3" style="font-size:.8rem;">No applications yet.</div>';
            return;
        }
        var html = '';
        rows.forEach(function(a, i) {
            var statusCls = STATUS_STYLES[a.status] || 'bg-secondary-lt text-secondary';
            var label     = CARD_TYPE_LABELS[a.card_type] || a.card_type;
            var date      = (a.created_at || '').substring(0, 10);
            var border    = i < rows.length - 1 ? 'border-bottom' : '';
            html += '<div class="px-3 py-2 ' + border + '">'
                + '<div class="d-flex align-items-center justify-content-between mb-1">'
                + '<span class="fw-semibold text-truncate" style="font-size:.8rem;max-width:130px;" title="' + escAttr(a.customer_name) + '">' + escHtml(a.customer_name) + '</span>'
                + '<span class="badge ' + statusCls + '" style="font-size:.65rem;">' + cap(a.status) + '</span>'
                + '</div>'
                + '<div class="d-flex align-items-center justify-content-between">'
                + '<span style="font-size:.73rem;color:#9ca3af;">' + label + ' &middot; ' + escHtml(a.branch_code || '') + '</span>'
                + '<span style="font-size:.72rem;color:#9ca3af;">' + date + '</span>'
                + '</div>'
                + '</div>';
        });
        container.innerHTML = html;
    }).catch(function() {
        container.innerHTML = '<div class="text-center text-muted py-3" style="font-size:.8rem;">Unable to load.</div>';
    });
}

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function escAttr(s) {
    if (!s) return '';
    return String(s).replace(/"/g,'&quot;');
}
function cap(s) {
    if (!s) return '';
    return s.charAt(0).toUpperCase() + s.slice(1);
}

// ---- Form submit ----
document.getElementById('application-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('submit-btn');
    clearMessage('form-message');
    btn.disabled = true;
    btn.textContent = 'Submitting…';

    var formData = new FormData(this);
    var payload  = {};
    formData.forEach(function(v, k) { payload[k] = v; });

    apiPost(SUBMIT_URL, payload).then(function(res) {
        showMessage('form-message', res.message, 'success');
        document.getElementById('application-form').reset();
        loadRecentApps();
        setTimeout(function() { window.location.href = LIST_URL; }, 1500);
    }).catch(function(err) {
        showMessage('form-message', err.message, 'danger');
        btn.disabled = false;
        btn.textContent = 'Submit Application';
    });
});
</script>
<?php require_once __DIR__ . '/../../../includes/layout-tabler-footer.php'; ?>
