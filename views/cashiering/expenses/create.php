<?php
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/Rbac.php';
require_once __DIR__ . '/../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'expenses.create');

require_once __DIR__ . '/../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row align-items-center">
        <div class="col">
          <div class="page-pretitle">Government of Belize — Treasury Department</div>
          <h2 class="page-title">Add Expense Entry</h2>
        </div>
        <div class="col-auto ms-auto">
          <a href="<?= url('views/cashiering/expenses/index.php') ?>" class="btn btn-outline-secondary btn-sm">← Back to List</a>
        </div>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">

      <div id="form-message" class="alert mb-3" style="display:none;"></div>

      <div class="card">
        <form id="expense-create-form">
          <div class="card-body">
            <div class="row row-cards">
              <div class="col-md-6">
                <label class="form-label">Supplier <span class="text-danger">*</span></label>
                <select name="supplier_id" id="supplier_id" class="form-select" required>
                  <option value="">Loading suppliers...</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Expenditure Type <span class="text-danger">*</span></label>
                <select name="expenditure_type_id" id="expenditure_type_id" class="form-select" required>
                  <option value="">Loading expenditure types...</option>
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label">Branch</label>
                <select name="branch_id" id="branch_id" class="form-select">
                  <option value="">Loading branches...</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Department <span class="text-danger">*</span></label>
                <select name="department_id" id="department_id" class="form-select" required>
                  <option value="">Select branch first</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Sub-Treasury</label>
                <select name="sub_treasury_id" id="sub_treasury_id" class="form-select">
                  <option value="">Select department first</option>
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label">Register</label>
                <select name="register_id" id="register_id" class="form-select">
                  <option value="">Select department first</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Invoice Number</label>
                <input type="text" name="invoice_number" class="form-control" placeholder="Optional">
              </div>
              <div class="col-md-4">
                <label class="form-label">Invoice Date</label>
                <input type="date" name="invoice_date" class="form-control">
              </div>

              <div class="col-md-4">
                <label class="form-label">Total Amount <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">BZD</span>
                  <input type="number" step="0.01" min="0" name="total_amount" class="form-control" required>
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Total GST</label>
                <input type="number" step="0.01" min="0" name="total_gst" class="form-control" value="0.00">
              </div>
              <div class="col-md-4">
                <label class="form-label">Currency</label>
                <select name="currency_code" class="form-select">
                  <option value="BZD" selected>BZD</option>
                  <option value="USD">USD</option>
                </select>
              </div>

              <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="3" class="form-control" placeholder="Optional notes..."></textarea>
              </div>
            </div>
          </div>
          <div class="card-footer d-flex gap-2">
            <button type="submit" id="save-draft-btn" class="btn btn-outline-primary">Save Draft</button>
            <button type="button" id="save-submit-btn" class="btn btn-primary">Save &amp; Submit</button>
            <a href="<?= url('views/cashiering/expenses/index.php') ?>" class="btn btn-link link-secondary ms-auto">Cancel</a>
          </div>
        </form>
      </div>

      <!-- Footer note -->
      <div class="mt-4 text-center text-muted" style="font-size:.78rem; border-top:1px solid var(--tblr-border-color); padding-top:1rem;">
        Government of Belize &mdash; Ministry of Finance &bull; Treasury Department &bull;
        Authorised users only. All activity is logged and audited.
      </div>

    </div>
  </div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const form = document.getElementById('expense-create-form');
    const supplierSelect = document.getElementById('supplier_id');
    const expenditureTypeSelect = document.getElementById('expenditure_type_id');
    const branchSelect = document.getElementById('branch_id');
    const departmentSelect = document.getElementById('department_id');
    const subTreasurySelect = document.getElementById('sub_treasury_id');
    const registerSelect = document.getElementById('register_id');
    const saveDraftBtn = document.getElementById('save-draft-btn');
    const saveSubmitBtn = document.getElementById('save-submit-btn');

    async function loadSuppliers() {
        const res = await apiGet("<?= url('api/master-data/suppliers/list.php') ?>?is_active=1");
        supplierSelect.innerHTML = '<option value="">Select Supplier</option>';
        (res.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = row.supplier_name;
            supplierSelect.appendChild(option);
        });
    }

    async function loadExpenditureTypes() {
        const res = await apiGet("<?= url('api/master-data/expenditure-types/list.php') ?>?is_active=1");
        expenditureTypeSelect.innerHTML = '<option value="">Select Expenditure Type</option>';
        (res.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.expenditure_code} - ${row.expenditure_name}`;
            expenditureTypeSelect.appendChild(option);
        });
    }

    async function loadBranches() {
        const res = await apiGet("<?= url('api/master-data/branches/list.php') ?>");
        branchSelect.innerHTML = '<option value="">Select Branch</option>';
        (res.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.code} - ${row.name}`;
            branchSelect.appendChild(option);
        });
    }

    async function loadDepartments(branchId) {
        departmentSelect.innerHTML = '<option value="">Loading...</option>';
        const res = await apiGet("<?= url('api/master-data/departments/list.php') ?>?branch_id=" + branchId);
        departmentSelect.innerHTML = '<option value="">Select Department</option>';
        (res.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.code} - ${row.name}`;
            departmentSelect.appendChild(option);
        });
    }

    async function loadSubTreasuries(departmentId) {
        subTreasurySelect.innerHTML = '<option value="">Loading...</option>';
        const res = await apiGet("<?= url('api/master-data/sub-treasuries/list.php') ?>?is_active=1&department_id=" + departmentId);
        subTreasurySelect.innerHTML = '<option value="">Select Sub-Treasury</option>';
        (res.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.sub_treasury_code} - ${row.sub_treasury_name}`;
            subTreasurySelect.appendChild(option);
        });
    }

    async function loadRegisters(departmentId, subTreasuryId = '') {
        let url = "<?= url('api/master-data/registers/list.php') ?>?is_active=1&department_id=" + departmentId;
        if (subTreasuryId) url += "&sub_treasury_id=" + subTreasuryId;

        registerSelect.innerHTML = '<option value="">Loading...</option>';
        const res = await apiGet(url);
        registerSelect.innerHTML = '<option value="">Select Register</option>';
        (res.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.register_code} - ${row.register_name}`;
            registerSelect.appendChild(option);
        });
    }

    try {
        await Promise.all([
            loadSuppliers(),
            loadExpenditureTypes(),
            loadBranches()
        ]);
    } catch (error) {
        showMessage('form-message', error.message, 'danger');
    }

    branchSelect.addEventListener('change', async () => {
        const branchId = branchSelect.value;
        departmentSelect.innerHTML = '<option value="">Select branch first</option>';
        subTreasurySelect.innerHTML = '<option value="">Select department first</option>';
        registerSelect.innerHTML = '<option value="">Select department first</option>';
        if (!branchId) return;

        try {
            await loadDepartments(branchId);
        } catch (error) {
            showMessage('form-message', error.message, 'danger');
        }
    });

    departmentSelect.addEventListener('change', async () => {
        const departmentId = departmentSelect.value;
        subTreasurySelect.innerHTML = '<option value="">Select department first</option>';
        registerSelect.innerHTML = '<option value="">Select department first</option>';
        if (!departmentId) return;

        try {
            await loadSubTreasuries(departmentId);
            await loadRegisters(departmentId);
        } catch (error) {
            showMessage('form-message', error.message, 'danger');
        }
    });

    subTreasurySelect.addEventListener('change', async () => {
        const departmentId = departmentSelect.value;
        const subTreasuryId = subTreasurySelect.value;
        registerSelect.innerHTML = '<option value="">Loading...</option>';
        if (!departmentId) return;

        try {
            await loadRegisters(departmentId, subTreasuryId);
        } catch (error) {
            showMessage('form-message', error.message, 'danger');
        }
    });

    async function submitExpense(status) {
        clearMessage('form-message');
        const btn = status === 'draft' ? saveDraftBtn : saveSubmitBtn;
        const originalText = btn.textContent;
        
        saveDraftBtn.disabled = true;
        saveSubmitBtn.disabled = true;
        btn.textContent = 'Saving...';

        const payload = Object.fromEntries(new FormData(form).entries());
        payload.status = status;

        try {
            await apiPost("<?= url('api/expenses/create.php') ?>", payload);
            showMessage('form-message', 'Expense entry saved successfully.', 'success');
            setTimeout(() => {
                window.location.href = "<?= url('views/cashiering/expenses/index.php') ?>";
            }, 700);
        } catch (error) {
            showMessage('form-message', error.message, 'danger');
            saveDraftBtn.disabled = false;
            saveSubmitBtn.disabled = false;
            btn.textContent = originalText;
        }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        await submitExpense('draft');
    });

    saveSubmitBtn.addEventListener('click', async () => {
        if (form.reportValidity()) {
          await submitExpense('submitted');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../../includes/layout-tabler-footer.php'; ?>