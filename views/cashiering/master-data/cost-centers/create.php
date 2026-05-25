<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.cost_centers.manage');

require_once __DIR__ . '/../../../../includes/layout-header.php';
require_once __DIR__ . '/../../../../includes/layout-sidebar.php';
?>

<div class="page-body">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Add Cost Center</h2>
            <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/cost-centers/index.php') ?>">Back to List</a>
        </div>

        <div id="form-message" class="alert" style="display:none;"></div>

        <div class="card">
            <div class="card-body">
                <form id="cost-center-create-form">
                    <div class="mb-3">
                        <label class="form-label">Code</label>
                        <input class="form-control" type="text" name="code" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input class="form-control" type="text" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department_id" id="department_id">
                            <option value="">Loading departments...</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Sub-treasury</label>
                        <select class="form-select" name="sub_treasury_id" id="sub_treasury_id">
                            <option value="">Loading sub-treasuries...</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="4"></textarea>
                    </div>

                    <button class="btn btn-primary" type="submit" id="save-btn">Save Cost Center</button>
                    <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/cost-centers/index.php') ?>">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const messageId = 'form-message';
    const form = document.getElementById('cost-center-create-form');
    const saveBtn = document.getElementById('save-btn');
    const departmentSelect = document.getElementById('department_id');
    const subTreasurySelect = document.getElementById('sub_treasury_id');

    try {
        const [departmentRes, subTreasuryRes] = await Promise.all([
            apiGet("<?= url('api/master-data/departments/list.php') ?>"),
            apiGet("<?= url('api/master-data/sub-treasuries/list.php') ?>")
        ]);

        departmentSelect.innerHTML = '<option value="">Select Department</option>';
        (departmentRes.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.code} - ${row.name}`;
            departmentSelect.appendChild(option);
        });

        subTreasurySelect.innerHTML = '<option value="">Select Sub-treasury</option>';
        (subTreasuryRes.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.sub_treasury_code} - ${row.sub_treasury_name}`;
            subTreasurySelect.appendChild(option);
        });
    } catch (error) {
        console.error(error);
        showMessage(messageId, error.message, 'danger');
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearMessage(messageId);
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        try {
            await apiPost("<?= url('api/master-data/cost-centers/create.php') ?>", payload);
            showMessage(messageId, 'Cost center created successfully.', 'success');

            setTimeout(() => {
                window.location.href = "<?= url('views/cashiering/master-data/cost-centers/index.php') ?>";
            }, 700);
        } catch (error) {
            console.error(error);
            showMessage(messageId, error.message, 'danger');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Cost Center';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-footer.php'; ?>