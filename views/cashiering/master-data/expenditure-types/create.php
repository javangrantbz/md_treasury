<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.expenditure_types.manage');

require_once __DIR__ . '/../../../../includes/layout-header.php';
require_once __DIR__ . '/../../../../includes/layout-sidebar.php';
?>

<div class="page-body">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Add Expenditure Type</h2>
            <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/expenditure-types/index.php') ?>">Back to List</a>
        </div>

        <div id="form-message" class="alert" style="display:none;"></div>

        <div class="card">
            <div class="card-body">
                <form id="expenditure-type-create-form">
                    <div class="mb-3">
                        <label class="form-label">Expenditure Code</label>
                        <input class="form-control" type="text" name="expenditure_code" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Expenditure Name</label>
                        <input class="form-control" type="text" name="expenditure_name" required>
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

                    <button class="btn btn-primary" type="submit" id="save-btn">Save Expenditure Type</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('expenditure-type-create-form');
    const saveBtn = document.getElementById('save-btn');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearMessage('form-message');

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
            const payload = Object.fromEntries(new FormData(form).entries());
            await apiPost("<?= url('api/master-data/expenditure-types/create.php') ?>", payload);
            showMessage('form-message', 'Expenditure type created successfully.', 'success');

            setTimeout(() => {
                window.location.href = "<?= url('views/cashiering/master-data/expenditure-types/index.php') ?>";
            }, 700);
        } catch (error) {
            showMessage('form-message', error.message, 'danger');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Expenditure Type';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-footer.php'; ?>