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
        <a href="<?= url('views/nsb/applications/index.php') ?>" class="btn btn-outline-secondary btn-sm">← Back to List</a>
      </div>
    </div>
  </div>
</div>
<div class="page-body">
  <div class="container-xl">
    <div id="form-message" class="alert" style="display:none;"></div>
    <div class="card">
      <div class="card-body">
        <form id="application-form">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label required">Customer Name</label>
              <input type="text" class="form-control" name="customer_name" required placeholder="Full Name as per NIC">
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
                <option value="">Loading...</option>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Remarks</label>
            <textarea class="form-control" name="remarks" rows="3" placeholder="Any additional notes..."></textarea>
          </div>

          <div class="form-footer">
            <button type="submit" class="btn btn-primary" id="submit-btn">Submit Application</button>
            <button type="reset" class="btn btn-link">Reset</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
  const form = document.getElementById('application-form');
  const branchSelect = document.getElementById('branch_id');
  const submitBtn = document.getElementById('submit-btn');

  async function loadBranches() {
    try {
      const res = await apiGet("<?= url('api/master-data/branches/list.php') ?>?status=active");
      branchSelect.innerHTML = '<option value="">Select Branch</option>';
      (res.data || []).forEach(b => {
        const opt = document.createElement('option');
        opt.value = b.id;
        opt.textContent = `${b.code} - ${b.name}`;
        branchSelect.appendChild(opt);
      });
    } catch (e) {
      console.error('Failed to load branches:', e);
      branchSelect.innerHTML = '<option value="">Error loading branches</option>';
    }
  }

  await loadBranches();

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearMessage('form-message');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';

    try {
      const formData = new FormData(form);
      const payload = Object.fromEntries(formData.entries());
      const res = await apiPost("<?= url('api/nsb/applications/create.php') ?>", payload);
      
      showMessage('form-message', res.message, 'success');
      form.reset();
      setTimeout(() => {
        window.location.href = "<?= url('views/nsb/applications/index.php') ?>";
      }, 1500);
    } catch (e) {
      showMessage('form-message', e.message, 'danger');
      submitBtn.disabled = false;
      submitBtn.textContent = 'Submit Application';
    }
  });
});
</script>
<?php require_once __DIR__ . '/../../../includes/layout-tabler-footer.php'; ?>
