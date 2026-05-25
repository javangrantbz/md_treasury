<?php
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/Rbac.php';
require_once __DIR__ . '/../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'expenses.update');

$id = (int)($_GET['id'] ?? 0);

require_once __DIR__ . '/../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row align-items-center">
        <div class="col">
          <div class="page-pretitle">Government of Belize — Treasury Department</div>
          <h2 class="page-title">Edit Expense Entry</h2>
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

      <?php if ($id <= 0): ?>
        <div class="alert alert-danger">Invalid expense entry ID.</div>
      <?php else: ?>
        <div class="card">
          <form id="expense-edit-form">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="card-body">
              <div class="row row-cards">
                <div class="col-md-6">
                  <label class="form-label">Supplier <span class="text-danger">*</span></label>
                  <select class="form-select" name="supplier_id" id="supplier_id" required>
                    <option value="">Loading suppliers...</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Expenditure Type <span class="text-danger">*</span></label>
                  <select class="form-select" name="expenditure_type_id" id="expenditure_type_id" required>
                    <option value="">Loading expenditure types...</option>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label">Branch</label>
                  <select class="form-select" name="branch_id" id="branch_id">
                    <option value="">Loading branches...</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Department <span class="text-danger">*</span></label>
                  <select class="form-select" name="department_id" id="department_id" required>
                    <option value="">Loading departments...</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Sub-treasury</label>
                  <select class="form-select" name="sub_treasury_id" id="sub_treasury_id">
                    <option value="">Loading sub-treasuries...</option>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label">Register</label>
                  <select class="form-select" name="register_id" id="register_id">
                    <option value="">Loading registers...</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Invoice Number</label>
                  <input class="form-control" type="text" name="invoice_number" id="invoice_number" placeholder="Optional">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Invoice Date</label>
                  <input class="form-control" type="date" name="invoice_date" id="invoice_date">
                </div>

                <div class="col-md-4">
                  <label class="form-label">Total Amount <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text">BZD</span>
                    <input class="form-control" type="number" step="0.01" min="0" name="total_amount" id="total_amount" required>
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Total GST</label>
                  <input class="form-control" type="number" step="0.01" min="0" name="total_gst" id="total_gst">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Currency</label>
                  <select class="form-select" name="currency_code" id="currency_code">
                    <option value="BZD">BZD</option>
                    <option value="USD">USD</option>
                  </select>
                </div>

                <div class="col-12">
                  <label class="form-label">Notes</label>
                  <textarea class="form-control" name="notes" id="notes" rows="4" placeholder="Optional notes..."></textarea>
                </div>
              </div>
            </div>
            <div class="card-footer d-flex gap-2">
              <button class="btn btn-outline-primary" type="submit" id="update-btn">Save Changes</button>
              <button class="btn btn-primary" type="button" id="update-submit-btn">Save &amp; Submit</button>
              <a href="<?= url('views/cashiering/expenses/index.php') ?>" class="btn btn-link link-secondary ms-auto">Cancel</a>
            </div>
          </form>
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

    const form = document.getElementById('expense-edit-form');
    const branchSelect = document.getElementById('branch_id');
    const departmentSelect = document.getElementById('department_id');
    const subTreasurySelect = document.getElementById('sub_treasury_id');
    const registerSelect = document.getElementById('register_id');
    const supplierSelect = document.getElementById('supplier_id');
    const expenditureTypeSelect = document.getElementById('expenditure_type_id');
    const updateBtn = document.getElementById('update-btn');
    const updateSubmitBtn = document.getElementById('update-submit-btn');

    async function loadBranches(selectedId = '') {
        const res = await apiGet("<?= url('api/master-data/branches/list.php') ?>");
        branchSelect.innerHTML = '<option value="">Select Branch</option>';
        (res.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.code} - ${row.name}`;
            if (String(row.id) === String(selectedId)) option.selected = true;
            branchSelect.appendChild(option);
        });
    }

    async function loadSuppliers(selectedId = '') {
        const res = await apiGet("<?= url('api/master-data/suppliers/list.php') ?>?is_active=1");
        supplierSelect.innerHTML = '<option value="">Select Supplier</option>';
        (res.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = row.supplier_name;
            if (String(row.id) === String(selectedId)) option.selected = true;
            supplierSelect.appendChild(option);
        });
    }

    async function loadExpenditureTypes(selectedId = '') {
        const res = await apiGet("<?= url('api/master-data/expenditure-types/list.php') ?>?is_active=1");
        expenditureTypeSelect.innerHTML = '<option value="">Select Expenditure Type</option>';
        (res.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.expenditure_code} - ${row.expenditure_name}`;
            if (String(row.id) === String(selectedId)) option.selected = true;
            expenditureTypeSelect.appendChild(option);
        });
    }

    async function loadDepartments(branchId, selectedId = '') {
        departmentSelect.innerHTML = '<option value="">Loading...</option>';
        const res = await apiGet("<?= url('api/master-data/departments/list.php') ?>?branch_id=" + branchId);
        departmentSelect.innerHTML = '<option value="">Select Department</option>';
        (res.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.code} - ${row.name}`;
            if (String(row.id) === String(selectedId)) option.selected = true;
            departmentSelect.appendChild(option);
        });
    }

    async function loadSubTreasuries(departmentId, selectedId = '') {
        subTreasurySelect.innerHTML = '<option value="">Loading...</option>';
        const res = await apiGet("<?= url('api/master-data/sub-treasuries/list.php') ?>?is_active=1&department_id=" + departmentId);
        subTreasurySelect.innerHTML = '<option value="">Select Sub-treasury</option>';
        (res.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.sub_treasury_code} - ${row.sub_treasury_name}`;
            if (String(row.id) === String(selectedId)) option.selected = true;
            subTreasurySelect.appendChild(option);
        });
    }

    async function loadRegisters(departmentId, subTreasuryId = '', selectedId = '') {
        let url = "<?= url('api/master-data/registers/list.php') ?>?is_active=1&department_id=" + departmentId;
        if (subTreasuryId) url += "&sub_treasury_id=" + subTreasuryId;

        registerSelect.innerHTML = '<option value="">Loading...</option>';
        const res = await apiGet(url);
        registerSelect.innerHTML = '<option value="">Select Register</option>';
        (res.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.register_code} - ${row.register_name}`;
            if (String(row.id) === String(selectedId)) option.selected = true;
            registerSelect.appendChild(option);
        });
    }

    try {
        const showRes = await apiGet("<?= url('api/expenses/show.php') ?>?id=" + recordId);
        const entry = showRes.data.entry || {};

        await Promise.all([
            loadBranches(entry.branch_id ?? ''),
            loadSuppliers(entry.supplier_id ?? ''),
            loadExpenditureTypes(entry.expenditure_type_id ?? '')
        ]);

        if (entry.branch_id) {
            await loadDepartments(entry.branch_id, entry.department_id ?? '');
        }
        if (entry.department_id) {
            await loadSubTreasuries(entry.department_id, entry.sub_treasury_id ?? '');
            await loadRegisters(entry.department_id, entry.sub_treasury_id ?? '', entry.register_id ?? '');
        }

        document.getElementById('invoice_number').value = entry.invoice_number ?? '';
        document.getElementById('invoice_date').value = entry.invoice_date ?? '';
        document.getElementById('total_amount').value = entry.total_amount ?? '';
        document.getElementById('total_gst').value = entry.total_gst ?? '';
        document.getElementById('currency_code').value = entry.currency_code ?? 'BZD';
        document.getElementById('notes').value = entry.notes ?? '';
    } catch (error) {
        showMessage('form-message', error.message, 'danger');
    }

    branchSelect.addEventListener('change', async () => {
        const branchId = branchSelect.value;
        departmentSelect.innerHTML = '<option value="">Select branch first</option>';
        subTreasurySelect.innerHTML = '<option value="">Select department first</option>';
        registerSelect.innerHTML = '<option value="">Select department first</option>';
        if (!branchId) return;
        await loadDepartments(branchId);
    });

    departmentSelect.addEventListener('change', async () => {
        const departmentId = departmentSelect.value;
        subTreasurySelect.innerHTML = '<option value="">Select department first</option>';
        registerSelect.innerHTML = '<option value="">Select department first</option>';
        if (!departmentId) return;
        await loadSubTreasuries(departmentId);
        await loadRegisters(departmentId);
    });

    subTreasurySelect.addEventListener('change', async () => {
        const departmentId = departmentSelect.value;
        const subTreasuryId = subTreasurySelect.value;
        registerSelect.innerHTML = '<option value="">Loading...</option>';
        if (!departmentId) return;
        await loadRegisters(departmentId, subTreasuryId);
    });

    async function submitUpdate(submitNow) {
        clearMessage('form-message');
        const btn = submitNow ? updateSubmitBtn : updateBtn;
        const originalText = btn.textContent;

        updateBtn.disabled = true;
        updateSubmitBtn.disabled = true;
        btn.textContent = 'Saving...';

        const payload = Object.fromEntries(new FormData(form).entries());
        payload.submit_now = submitNow ? 1 : 0;

        try {
            await apiPost("<?= url('api/expenses/update.php') ?>", payload);
            showMessage('form-message', 'Expense entry updated successfully.', 'success');
            setTimeout(() => {
                window.location.href = "<?= url('views/cashiering/expenses/index.php') ?>";
            }, 700);
        } catch (error) {
            showMessage('form-message', error.message, 'danger');
            updateBtn.disabled = false;
            updateSubmitBtn.disabled = false;
            btn.textContent = originalText;
        }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        await submitUpdate(false);
    });

    updateSubmitBtn.addEventListener('click', async () => {
        if (form.reportValidity()) {
          await submitUpdate(true);
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../../includes/layout-tabler-footer.php'; ?>