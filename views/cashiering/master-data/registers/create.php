<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.registers.manage');
require_once __DIR__ . '/../../../../includes/layout-header.php';
require_once __DIR__ . '/../../../../includes/layout-sidebar.php';
?>
<div class="page-body">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Add Register</h2>
            <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/registers/index.php') ?>">Back to List</a>
        </div>

        <div id="form-message" class="alert" style="display:none;"></div>

        <div class="card">
            <div class="card-body">
                <form id="register-create-form">
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department_id" id="department_id" required>
                            <option value="">Loading departments...</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Sub-treasury</label>
                        <select class="form-select" name="sub_treasury_id" id="sub_treasury_id" required>
                            <option value="">Select department first</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Register Code</label>
                        <input class="form-control" type="text" name="register_code" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Register Name</label>
                        <input class="form-control" type="text" name="register_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="4"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Active</label>
                        <select class="form-select" name="is_active">
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>

                    <button class="btn btn-primary" type="submit" id="save-btn">Save Register</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const form = document.getElementById('register-create-form');
    const deptSelect = document.getElementById('department_id');
    const stSelect = document.getElementById('sub_treasury_id');
    const saveBtn = document.getElementById('save-btn');

    async function loadSubTreasuries(departmentId, selectedId = '') {
        stSelect.innerHTML = '<option value="">Loading...</option>';
        try {
			const res = await apiGet("<?= url('api/master-data/sub-treasuries/list.php') ?>?is_active=1&department_id=" + departmentId);
            const rows = res.data || [];
            stSelect.innerHTML = '<option value="">Select Sub-treasury</option>';
            rows.forEach(row => {
                const option = document.createElement('option');
                option.value = row.id;
                option.textContent = `${row.sub_treasury_code} - ${row.sub_treasury_name}`;
                if (String(row.id) === String(selectedId)) option.selected = true;
                stSelect.appendChild(option);
            });
        } catch (error) {
            stSelect.innerHTML = '<option value="">Unable to load sub-treasuries</option>';
            showMessage('form-message', error.message, 'danger');
        }
    }

    try {
        const deptRes = await apiGet("<?= url('api/master-data/departments/list.php') ?>");
        deptSelect.innerHTML = '<option value="">Select Department</option>';
        (deptRes.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.code} - ${row.name}`;
            deptSelect.appendChild(option);
        });
    } catch (error) {
        showMessage('form-message', error.message, 'danger');
    }

    deptSelect.addEventListener('change', () => {
        const departmentId = deptSelect.value;
        if (!departmentId) {
            stSelect.innerHTML = '<option value="">Select department first</option>';
            return;
        }
        loadSubTreasuries(departmentId);
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearMessage('form-message');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
            const payload = Object.fromEntries(new FormData(form).entries());
            await apiPost("<?= url('api/master-data/registers/create.php') ?>", payload);
            showMessage('form-message', 'Register created successfully.', 'success');
            setTimeout(() => {
                window.location.href = "<?= url('views/cashiering/master-data/registers/index.php') ?>";
            }, 700);
        } catch (error) {
            showMessage('form-message', error.message, 'danger');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Register';
        }
    });
});
</script>
<?php require_once __DIR__ . '/../../../../includes/layout-footer.php'; ?>