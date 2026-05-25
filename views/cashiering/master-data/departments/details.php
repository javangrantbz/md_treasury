<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.departments.manage');

$id = (int)($_GET['id'] ?? 0);

require_once __DIR__ . '/../../../../includes/layout-header.php';
require_once __DIR__ . '/../../../../includes/layout-sidebar.php';
?>

<div class="page-body">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Department Details</h2>
            <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/departments/index.php') ?>">Back to List</a>
        </div>

        <div id="page-message" class="alert" style="display:none;"></div>

        <?php if ($id <= 0): ?>
            <div class="alert alert-danger">Invalid department ID.</div>
        <?php else: ?>

            <div class="card mb-4">
                <div class="card-body">
                    <h3 id="dept-name">Loading...</h3>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <strong>Status:</strong> <span id="dept-status"></span>
                        </div>
                     </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0">Bank Accounts</h4>
                    </div>

                    <div id="bank-form-message" class="alert" style="display:none;"></div>

                    <form id="assign-bank-form" class="mb-3">
                        <input type="hidden" name="department_id" value="<?= $id ?>">

                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label">Bank Account</label>
                                <select class="form-select" name="bank_account_id" id="bank_account_id" required>
                                    <option value="">Loading bank accounts...</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Default</label>
                                <select class="form-select" name="is_default">
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <button class="btn btn-primary w-100" type="submit" id="assign-bank-btn">Assign</button>
                            </div>
                        </div>
                    </form>

                    <table class="table table-striped table-hover" id="bank-accounts-table">
                        <thead>
                            <tr>
                                <th>Bank</th>
                                <th>Account Name</th>
                                <th>Account Number</th>
                                <th>Currency</th>
                                <th>Default</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="6">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0">Cost Centers</h4>
                    </div>

                    <div id="cc-form-message" class="alert" style="display:none;"></div>

                    <form id="assign-cc-form" class="mb-3">
                        <input type="hidden" name="department_id" value="<?= $id ?>">

                        <div class="row g-3 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label">Cost Center</label>
                                <select class="form-select" name="cost_center_id" id="cost_center_id" required>
                                    <option value="">Loading cost centers...</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <button class="btn btn-primary w-100" type="submit" id="assign-cc-btn">
                                    Assign Cost Center
                                </button>
                            </div>
                        </div>
                    </form>

                    <table class="table table-striped table-hover" id="cost-centers-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Sub-treasury</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="5">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const departmentId = <?= $id ?>;
    if (!departmentId) return;

    const pageMessageId = 'page-message';

    const bankFormMessageId = 'bank-form-message';
    const bankForm = document.getElementById('assign-bank-form');
    const bankSelect = document.getElementById('bank_account_id');
    const assignBankBtn = document.getElementById('assign-bank-btn');

    const ccFormMessageId = 'cc-form-message';
    const ccForm = document.getElementById('assign-cc-form');
    const ccSelect = document.getElementById('cost_center_id');
    const assignCcBtn = document.getElementById('assign-cc-btn');

    async function loadDetails() {
        const res = await apiGet("<?= url('api/master-data/departments/details.php') ?>?id=" + departmentId);
        const payload = res.data || {};
        const department = payload.department || {};
        const bankAccounts = payload.bank_accounts || [];
        const costCenters = payload.cost_centers || [];

        document.getElementById('dept-name').textContent = department.name || '';
        document.getElementById('dept-status').textContent = department.status || '';
        
        const bankTbody = document.querySelector('#bank-accounts-table tbody');
        bankTbody.innerHTML = '';

        if (bankAccounts.length === 0) {
            bankTbody.innerHTML = '<tr><td colspan="6">No bank accounts assigned.</td></tr>';
        } else {
            bankAccounts.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${row.bank_name ?? ''}</td>
                    <td>${row.account_name ?? ''}</td>
                    <td>${row.account_number ?? row.account_masked ?? ''}</td>
                    <td>${row.currency_code ?? ''}</td>
                    <td>${Number(row.is_default) === 1 ? 'Yes' : 'No'}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-bank-id="${row.id}">
                            Remove
                        </button>
                    </td>
                `;
                bankTbody.appendChild(tr);
            });
        }

        bankTbody.querySelectorAll('[data-remove-bank-id]').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Remove this bank account from the department?')) return;

                try {
                    await apiPost("<?= url('api/master-data/departments/remove-bank-account.php') ?>", {
                        id: btn.dataset.removeBankId
                    });
                    await loadDetails();
                } catch (error) {
                    console.error(error);
                    showMessage(pageMessageId, error.message, 'danger');
                }
            });
        });

        const ccTbody = document.querySelector('#cost-centers-table tbody');
        ccTbody.innerHTML = '';

        if (costCenters.length === 0) {
            ccTbody.innerHTML = '<tr><td colspan="5">No cost centers assigned.</td></tr>';
        } else {
            costCenters.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${row.code ?? ''}</td>
                    <td>${row.name ?? ''}</td>
                    <td>${row.sub_treasury_name ?? ''}</td>
                    <td>${row.status ?? ''}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-cc-id="${row.id}">
                            Remove
                        </button>
                    </td>
                `;
                ccTbody.appendChild(tr);
            });
        }

        ccTbody.querySelectorAll('[data-remove-cc-id]').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Remove this cost center from the department?')) return;

                try {
                    await apiPost("<?= url('api/master-data/departments/remove-cost-center.php') ?>", {
                        id: btn.dataset.removeCcId
                    });
                    await loadDetails();
                } catch (error) {
                    console.error(error);
                    showMessage(pageMessageId, error.message, 'danger');
                }
            });
        });
    }

    try {
        const [bankRes, ccRes] = await Promise.all([
			apiGet("<?= url('api/master-data/bank-accounts/list.php') ?>?status=active"),
			apiGet("<?= url('api/master-data/cost-centers/list.php') ?>")
		]);

        bankSelect.innerHTML = '<option value="">Select Bank Account</option>';
        (bankRes.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.bank_name} - ${row.account_name}`;
            bankSelect.appendChild(option);
        });

        ccSelect.innerHTML = '<option value="">Select Cost Center</option>';
        (ccRes.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.code} - ${row.name}`;
            ccSelect.appendChild(option);
        });

        await loadDetails();
    } catch (error) {
        console.error(error);
        showMessage(pageMessageId, error.message, 'danger');
    }

    if (bankForm) {
        bankForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearMessage(bankFormMessageId);

            assignBankBtn.disabled = true;
            assignBankBtn.textContent = 'Assigning...';

            const formData = new FormData(bankForm);
            const payload = Object.fromEntries(formData.entries());

            try {
                await apiPost("<?= url('api/master-data/departments/assign-bank-account.php') ?>", payload);
                showMessage(bankFormMessageId, 'Bank account assigned successfully.', 'success');
                bankForm.reset();
                await loadDetails();
            } catch (error) {
                console.error(error);
                showMessage(bankFormMessageId, error.message, 'danger');
            } finally {
                assignBankBtn.disabled = false;
                assignBankBtn.textContent = 'Assign';
            }
        });
    }

    if (ccForm) {
        ccForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearMessage(ccFormMessageId);

            assignCcBtn.disabled = true;
            assignCcBtn.textContent = 'Assigning...';

            const formData = new FormData(ccForm);
            const payload = Object.fromEntries(formData.entries());

            try {
                await apiPost("<?= url('api/master-data/departments/assign-cost-center.php') ?>", payload);
                showMessage(ccFormMessageId, 'Cost center assigned successfully.', 'success');
                ccForm.reset();
                await loadDetails();
            } catch (error) {
                console.error(error);
                showMessage(ccFormMessageId, error.message, 'danger');
            } finally {
                assignCcBtn.disabled = false;
                assignCcBtn.textContent = 'Assign Cost Center';
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-footer.php'; ?>