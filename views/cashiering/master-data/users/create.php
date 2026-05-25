<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.users.manage');
require_once __DIR__ . '/../../../../includes/layout-header.php';
require_once __DIR__ . '/../../../../includes/layout-sidebar.php';
?>

<div class="page-body">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Add User</h2>
            <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/users/index.php') ?>">Back to List</a>
        </div>

        <div id="form-message" class="alert" style="display:none;"></div>

        <div class="card">
            <div class="card-body">
                <form id="user-create-form">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name</label>
                            <input class="form-control" type="text" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name</label>
                            <input class="form-control" type="text" name="last_name" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username</label>
                            <input class="form-control" type="text" name="username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input class="form-control" type="email" name="email" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input class="form-control" type="text" name="phone">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">User Type</label>
                            <select class="form-select" name="user_type">
                                <option value="internal">Internal</option>
                                <option value="external">External</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Auth Source</label>
                            <select class="form-select" name="auth_source">
								<option value="local">Local</option>
								<option value="microsoft">Microsoft SSO</option>
							</select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password</label>
                            <input class="form-control" type="password" name="password" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input class="form-control" type="password" name="confirm_password" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">MFA Enabled</label>
                            <select class="form-select" name="mfa_enabled">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="locked">Locked</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role_id" id="role_id">
                                <option value="">Loading roles...</option>
                            </select>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Branch</label>
                            <select class="form-select" name="branch_id" id="branch_id">
                                <option value="">Loading branches...</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department_id" id="department_id">
                                <option value="">Select branch first</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Sub-treasury</label>
                            <select class="form-select" name="sub_treasury_id" id="sub_treasury_id">
                                <option value="">Select department first</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Register</label>
                            <select class="form-select" name="register_id" id="register_id">
                                <option value="">Select department first</option>
                            </select>
                        </div>
                    </div>

                    <button class="btn btn-primary" type="submit" id="save-btn">Save User</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const form = document.getElementById('user-create-form');
    const saveBtn = document.getElementById('save-btn');

    const branchSelect = document.getElementById('branch_id');
    const departmentSelect = document.getElementById('department_id');
    const subTreasurySelect = document.getElementById('sub_treasury_id');
    const registerSelect = document.getElementById('register_id');
    const roleSelect = document.getElementById('role_id');

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

    async function loadRoles() {
        const res = await apiGet("<?= url('api/master-data/roles/list.php') ?>?status=active");		
        roleSelect.innerHTML = '<option value="">Select Role</option>';
        (res.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.name}`;
            roleSelect.appendChild(option);
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
        subTreasurySelect.innerHTML = '<option value="">Select Sub-treasury</option>';
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
        await Promise.all([loadBranches(), loadRoles()]);
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

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearMessage('form-message');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
            const payload = Object.fromEntries(new FormData(form).entries());
            await apiPost("<?= url('api/master-data/users/create.php') ?>", payload);
            showMessage('form-message', 'User created successfully.', 'success');
            setTimeout(() => {
                window.location.href = "<?= url('views/cashiering/master-data/users/index.php') ?>";
            }, 700);
        } catch (error) {
            showMessage('form-message', error.message, 'danger');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save User';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-footer.php'; ?>