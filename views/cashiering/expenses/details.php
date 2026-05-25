<?php
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/Rbac.php';
require_once __DIR__ . '/../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'expenses.view');

$id = (int)($_GET['id'] ?? 0);

require_once __DIR__ . '/../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row align-items-center">
        <div class="col">
          <div class="page-pretitle">Government of Belize — Treasury Department</div>
          <h2 class="page-title">Expense Entry Details</h2>
        </div>
        <div class="col-auto ms-auto">
          <a href="<?= url('views/cashiering/expenses/index.php') ?>" class="btn btn-outline-secondary btn-sm">← Back to List</a>
        </div>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">

      <div id="page-message" class="alert mb-3" style="display:none;"></div>

      <?php if ($id <= 0): ?>
        <div class="alert alert-danger">Invalid expense entry ID.</div>
      <?php else: ?>
        <div class="card mb-4">
          <div class="card-header">
            <h3 class="card-title" id="expense-number">Loading...</h3>
          </div>
          <div class="card-body">
            <div class="row row-cards">
              <div class="col-md-6">
                <div class="form-label text-muted mb-1">Supplier</div>
                <div class="fw-medium" id="supplier-name">—</div>
              </div>
              <div class="col-md-6">
                <div class="form-label text-muted mb-1">Expenditure Type</div>
                <div class="fw-medium" id="expenditure-type">—</div>
              </div>
              <div class="col-md-6">
                <div class="form-label text-muted mb-1">Department</div>
                <div class="fw-medium" id="department-name">—</div>
              </div>
              <div class="col-md-6">
                <div class="form-label text-muted mb-1">Sub-treasury</div>
                <div class="fw-medium" id="sub-treasury-name">—</div>
              </div>
              <div class="col-md-6">
                <div class="form-label text-muted mb-1">Register</div>
                <div class="fw-medium" id="register-name">—</div>
              </div>
              <div class="col-md-6">
                <div class="form-label text-muted mb-1">Branch</div>
                <div class="fw-medium" id="branch-name">—</div>
              </div>
              <div class="col-md-6">
                <div class="form-label text-muted mb-1">Invoice Number</div>
                <div class="fw-medium" id="invoice-number">—</div>
              </div>
              <div class="col-md-6">
                <div class="form-label text-muted mb-1">Invoice Date</div>
                <div class="fw-medium" id="invoice-date">—</div>
              </div>
              <div class="col-md-6">
                <div class="form-label text-muted mb-1">Total Amount</div>
                <div class="fw-medium fs-3 text-primary" id="total-amount">—</div>
              </div>
              <div class="col-md-6">
                <div class="form-label text-muted mb-1">Total GST</div>
                <div class="fw-medium" id="total-gst">—</div>
              </div>
              <div class="col-md-6">
                <div class="form-label text-muted mb-1">Status</div>
                <div id="status">—</div>
              </div>
              <div class="col-md-6">
                <div class="form-label text-muted mb-1">Created At</div>
                <div class="fw-medium" id="created-at">—</div>
              </div>
              <div class="col-12">
                <div class="form-label text-muted mb-1">Notes</div>
                <div class="p-2 bg-light rounded" id="notes">—</div>
              </div>
            </div>

            <div class="mt-4 d-flex gap-2">
              <button class="btn btn-primary" type="button" id="submit-btn" style="display:none;">Submit Entry</button>
              <a class="btn btn-outline-primary" id="edit-link" href="#">Edit Entry</a>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Status History</h3>
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table" id="status-history-table">
              <thead>
                <tr>
                  <th>Previous</th>
                  <th>New Status</th>
                  <th>Changed By</th>
                  <th>Reason</th>
                  <th>Timestamp</th>
                </tr>
              </thead>
              <tbody>
                <tr><td colspan="5" class="text-center py-4 text-muted">Loading history...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <!-- Footer note -->
      <div class="mt-4 text-center text-muted" style="font-size:.78rem; border-top:1px solid var(--tblr-border-color); padding-top:1rem;">
        Government of Belize &mdash; Ministry of Finance &bull; Treasury Department &bull;
        Authorised users only. All activity is logged and audited.
      </div>

    </div>
  </div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const recordId = <?= $id ?>;
    if (!recordId) return;

    const submitBtn = document.getElementById('submit-btn');
    const editLink = document.getElementById('edit-link');

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

    async function loadDetails() {
        const res = await apiGet("<?= url('api/expenses/show.php') ?>?id=" + recordId);
        const entry = res.data.entry || {};
        const history = res.data.status_history || [];

        document.getElementById('expense-number').textContent = entry.expense_number ?? 'Expense Details';
        document.getElementById('supplier-name').textContent = entry.supplier_name ?? '—';
        document.getElementById('expenditure-type').textContent = entry.expenditure_name ?? '—';
        document.getElementById('department-name').textContent = entry.department_name ?? '—';
        document.getElementById('sub-treasury-name').textContent = entry.sub_treasury_name ?? '—';
        document.getElementById('register-name').textContent = entry.register_name ?? '—';
        document.getElementById('branch-name').textContent = entry.branch_name ?? '—';
        document.getElementById('invoice-number').textContent = entry.invoice_number ?? '—';
        document.getElementById('invoice-date').textContent = entry.invoice_date ?? '—';
        document.getElementById('total-amount').textContent = `${entry.currency_code ?? ''} ${parseFloat(entry.total_amount ?? 0).toFixed(2)}`;
        document.getElementById('total-gst').textContent = `${entry.currency_code ?? ''} ${parseFloat(entry.total_gst ?? 0).toFixed(2)}`;
        document.getElementById('status').innerHTML = statusBadge(entry.status);
        document.getElementById('created-at').textContent = entry.created_at ?? '—';
        document.getElementById('notes').textContent = entry.notes || '—';

        editLink.href = "<?= url('views/cashiering/expenses/edit.php') ?>?id=" + recordId;

        if (entry.status === 'draft') {
            submitBtn.style.display = 'inline-block';
        } else {
            submitBtn.style.display = 'none';
        }

        const tbody = document.querySelector('#status-history-table tbody');
        tbody.innerHTML = '';

        if (history.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No status history found.</td></tr>';
            return;
        }

        history.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${statusBadge(row.previous_status)}</td>
                <td>${statusBadge(row.new_status)}</td>
                <td>
                  <div class="fw-medium">${(row.first_name ?? '') + ' ' + (row.last_name ?? '')}</div>
                  <div class="text-muted small">${row.username ?? ''}</div>
                </td>
                <td class="text-muted">${row.change_reason ?? '—'}</td>
                <td class="text-nowrap">${row.created_at ?? ''}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    submitBtn?.addEventListener('click', async () => {
        if (!confirm('Are you sure you want to submit this expense entry?')) return;
        try {
            await apiPost("<?= url('api/expenses/submit.php') ?>", {
                id: recordId,
                reason: 'Submitted from details page.'
            });
            showMessage('page-message', 'Expense entry submitted successfully.', 'success');
            await loadDetails();
        } catch (error) {
            showMessage('page-message', error.message, 'danger');
        }
    });

    try {
        await loadDetails();
    } catch (error) {
        showMessage('page-message', error.message, 'danger');
    }
});
</script>

<?php require_once __DIR__ . '/../../../includes/layout-tabler-footer.php'; ?>