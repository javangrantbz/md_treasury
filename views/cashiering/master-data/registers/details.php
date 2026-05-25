<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.registers.manage');
$id = (int)($_GET['id'] ?? 0);
require_once __DIR__ . '/../../../../includes/layout-header.php';
require_once __DIR__ . '/../../../../includes/layout-sidebar.php';
?>
<div class="page-body">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Register Details</h2>
            <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/registers/index.php') ?>">Back to List</a>
        </div>

        <div id="page-message" class="alert" style="display:none;"></div>

        <?php if ($id <= 0): ?>
            <div class="alert alert-danger">Invalid register ID.</div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h3 id="reg-name">Loading...</h3>
                    <div class="row">
                        <div class="col-md-6 mb-2"><strong>Code:</strong> <span id="reg-code"></span></div>
                        <div class="col-md-6 mb-2"><strong>Department:</strong> <span id="reg-department"></span></div>
                        <div class="col-md-6 mb-2"><strong>Sub-treasury:</strong> <span id="reg-sub"></span></div>
                        <div class="col-md-6 mb-2"><strong>Active:</strong> <span id="reg-active"></span></div>
                        <div class="col-12 mb-2"><strong>Description:</strong> <span id="reg-description"></span></div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h4>Bank Accounts</h4>
                    <div id="bank-form-message" class="alert" style="display:none;"></div>
                    <form id="assign-bank-form" class="mb-3">
                        <input type="hidden" name="register_id" value="<?= $id ?>">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-8">
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
                                <button class="btn btn-primary w-100" type="submit" id="assign-bank-btn">Assign</button>
                            </div>
                        </div>
                    </form>

                    <table class="table table-striped table-hover" id="register-bank-table">
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
                        <tbody><tr><td colspan="6">Loading...</td></tr></tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h4>Assigned Users</h4>
                    <div id="user-form-message" class="alert" style="display:none;"></div>
                    <form id="assign-user-form" class="mb-3">
                        <input type="hidden" name="register_id" value="<?= $id ?>">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-10">
                                <label class="form-label">User</label>
                                <select class="form-select" name="user_id" id="user_id" required>
                                    <option value="">Loading users...</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary w-100" type="submit" id="assign-user-btn">Assign</button>
                            </div>
                        </div>
                    </form>

                    <table class="table table-striped table-hover" id="register-users-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody><tr><td colspan="5">Loading...</td></tr></tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const registerId = <?= $id ?>;
    if (!registerId) return;

    const bankSelect = document.getElementById('bank_account_id');
    const userSelect = document.getElementById('user_id');

    async function loadDetails() {
        const res = await apiGet("<?= url('api/master-data/registers/details.php') ?>?id=" + registerId);
        const payload = res.data || {};
        const r = payload.register || {};
        const bankAccounts = payload.bank_accounts || [];
        const users = payload.users || [];

        document.getElementById('reg-name').textContent = r.register_name || '';
        document.getElementById('reg-code').textContent = r.register_code || '';
        document.getElementById('reg-department').textContent = r.department_name || '';
        document.getElementById('reg-sub').textContent = r.sub_treasury_name || '';
        document.getElementById('reg-active').textContent = Number(r.is_active) === 1 ? 'Yes' : 'No';
        document.getElementById('reg-description').textContent = r.description || '';

        const bankTbody = document.querySelector('#register-bank-table tbody');
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
                    <td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-bank-id="${row.id}">Remove</button></td>
                `;
                bankTbody.appendChild(tr);
            });
        }

        bankTbody.querySelectorAll('[data-remove-bank-id]').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Remove this bank account from the register?')) return;
                await apiPost("<?= url('api/master-data/registers/remove-bank-account.php') ?>", { id: btn.dataset.removeBankId });
                await loadDetails();
            });
        });

        const userTbody = document.querySelector('#register-users-table tbody');
        userTbody.innerHTML = '';
        if (users.length === 0) {
            userTbody.innerHTML = '<tr><td colspan="5">No users assigned.</td></tr>';
        } else {
            users.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${(row.first_name ?? '') + ' ' + (row.last_name ?? '')}</td>
                    <td>${row.username ?? ''}</td>
                    <td>${row.email ?? ''}</td>
                    <td>${row.status ?? ''}</td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-user-id="${row.id}">Remove</button></td>
                `;
                userTbody.appendChild(tr);
            });
        }

        userTbody.querySelectorAll('[data-remove-user-id]').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Remove this user from the register?')) return;
                await apiPost("<?= url('api/master-data/registers/remove-user.php') ?>", { user_id: btn.dataset.removeUserId });
                await loadDetails();
                await loadAssignableUsers(r.department_id ?? '', r.sub_treasury_id ?? '');
            });
        });

        await loadAssignableUsers(r.department_id ?? '', r.sub_treasury_id ?? '');
    }

    async function loadAssignableUsers(departmentId, subTreasuryId) {
		const res = await apiGet("<?= url('api/master-data/users/list.php') ?>?only_unassigned=1&status=active&department_id=" + departmentId + "&sub_treasury_id=" + subTreasuryId);
        userSelect.innerHTML = '<option value="">Select User</option>';
        (res.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.first_name} ${row.last_name} (${row.username ?? row.email ?? ''})`;
            userSelect.appendChild(option);
        });
    }

    try {
		const bankRes = await apiGet("<?= url('api/master-data/bank-accounts/list.php') ?>?status=active");
        bankSelect.innerHTML = '<option value="">Select Bank Account</option>';
        (bankRes.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.bank_name} - ${row.account_name}`;
            bankSelect.appendChild(option);
        });

        await loadDetails();
    } catch (error) {
        showMessage('page-message', error.message, 'danger');
    }

    document.getElementById('assign-bank-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const payload = Object.fromEntries(new FormData(e.target).entries());
            await apiPost("<?= url('api/master-data/registers/assign-bank-account.php') ?>", payload);
            showMessage('bank-form-message', 'Bank account assigned successfully.', 'success');
            e.target.reset();
            await loadDetails();
        } catch (error) {
            showMessage('bank-form-message', error.message, 'danger');
        }
    });

    document.getElementById('assign-user-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const payload = Object.fromEntries(new FormData(e.target).entries());
            await apiPost("<?= url('api/master-data/registers/assign-user.php') ?>", payload);
            showMessage('user-form-message', 'User assigned successfully.', 'success');
            e.target.reset();
            await loadDetails();
        } catch (error) {
            showMessage('user-form-message', error.message, 'danger');
        }
    });
});
</script>
<?php require_once __DIR__ . '/../../../../includes/layout-footer.php'; ?>