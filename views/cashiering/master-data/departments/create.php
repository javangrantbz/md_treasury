<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.departments.manage');

require_once __DIR__ . '/../../../../includes/layout-header.php';
require_once __DIR__ . '/../../../../includes/layout-sidebar.php';
?>

<div class="page-body">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Add Department</h2>
            <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/departments/index.php') ?>">Back to List</a>
        </div>

        <div id="form-message" class="alert" style="display:none;"></div>

        <div class="card">
            <div class="card-body">
                <form id="department-create-form">             

                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input class="form-control" type="text" name="name" required>
                    </div>

					
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <button class="btn btn-primary" type="submit" id="save-btn">Save Department</button>
                    <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/departments/index.php') ?>">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const messageId = 'form-message';
    const form = document.getElementById('department-create-form');
    const saveBtn = document.getElementById('save-btn');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearMessage(messageId);
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        try {
            await apiPost("<?= url('api/master-data/departments/create.php') ?>", payload);
            showMessage(messageId, 'Department created successfully.', 'success');

            setTimeout(() => {
                window.location.href = "<?= url('views/cashiering/master-data/departments/index.php') ?>";
            }, 700);
        } catch (error) {
            showMessage(messageId, error.message, 'danger');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Department';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-footer.php'; ?>