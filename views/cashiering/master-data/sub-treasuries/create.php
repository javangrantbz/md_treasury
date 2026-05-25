<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.sub_treasuries.manage');

require_once __DIR__ . '/../../../../includes/layout-header.php';
require_once __DIR__ . '/../../../../includes/layout-sidebar.php';
?>

<div class="page-body">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Add Sub-treasury</h2>
            <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/sub-treasuries/index.php') ?>">Back to List</a>
        </div>

        <div id="form-message" class="alert" style="display:none;"></div>

        <div class="card">
            <div class="card-body">
                <form id="sub-treasury-create-form">
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department_id" id="department_id" required>
                            <option value="">Loading departments...</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Sub-treasury Name</label>
                        <input class="form-control" type="text" name="sub_treasury_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">District</label>
                        <input class="form-control" type="text" name="district">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Address Line</label>
                        <input class="form-control" type="text" name="address_line">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Contact Phone</label>
                        <input class="form-control" type="text" name="contact_phone">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Contact Email</label>
                        <input class="form-control" type="email" name="contact_email">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Active</label>
                        <select class="form-select" name="is_active">
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>

                    <button class="btn btn-primary" type="submit" id="save-btn">Save Sub-treasury</button>
                    <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/sub-treasuries/index.php') ?>">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const messageId = 'form-message';
    const form = document.getElementById('sub-treasury-create-form');
    const saveBtn = document.getElementById('save-btn');
    const departmentSelect = document.getElementById('department_id');

    try {
        const res = await apiGet("<?= url('api/master-data/departments/list.php') ?>");
        departmentSelect.innerHTML = '<option value="">Select Department</option>';

        (res.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.code} - ${row.name}`;
            departmentSelect.appendChild(option);
        });
    } catch (error) {
        console.error(error);
        departmentSelect.innerHTML = '<option value="">Unable to load departments</option>';
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
            await apiPost("<?= url('api/master-data/sub-treasuries/create.php') ?>", payload);
            showMessage(messageId, 'Sub-treasury created successfully.', 'success');

            setTimeout(() => {
                window.location.href = "<?= url('views/cashiering/master-data/sub-treasuries/index.php') ?>";
            }, 700);
        } catch (error) {
            console.error(error);
            showMessage(messageId, error.message, 'danger');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Sub-treasury';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-footer.php'; ?>