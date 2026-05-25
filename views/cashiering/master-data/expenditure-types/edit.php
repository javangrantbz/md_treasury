<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.expenditure_types.manage');

$id = (int)($_GET['id'] ?? 0);

require_once __DIR__ . '/../../../../includes/layout-header.php';
require_once __DIR__ . '/../../../../includes/layout-sidebar.php';
?>

<div class="page-body">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Edit Expenditure Type</h2>
            <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/expenditure-types/index.php') ?>">Back to List</a>
        </div>

        <div id="form-message" class="alert" style="display:none;"></div>

        <?php if ($id <= 0): ?>
            <div class="alert alert-danger">Invalid expenditure type ID.</div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <form id="expenditure-type-edit-form">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <div class="mb-3">
                            <label class="form-label">Expenditure Code</label>
                            <input class="form-control" type="text" name="expenditure_code" id="expenditure_code" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Expenditure Name</label>
                            <input class="form-control" type="text" name="expenditure_name" id="expenditure_name" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="description" rows="4"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Active</label>
                            <select class="form-select" name="is_active" id="is_active">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>

                        <button class="btn btn-primary" type="submit" id="update-btn">Update Expenditure Type</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const recordId = <?= $id ?>;
    if (!recordId) return;

    const form = document.getElementById('expenditure-type-edit-form');
    const updateBtn = document.getElementById('update-btn');

    try {
        const res = await apiGet("<?= url('api/master-data/expenditure-types/show.php') ?>?id=" + recordId);
        const r = res.data || {};

        document.getElementById('expenditure_code').value = r.expenditure_code ?? '';
        document.getElementById('expenditure_name').value = r.expenditure_name ?? '';
        document.getElementById('description').value = r.description ?? '';
        document.getElementById('is_active').value = String(r.is_active ?? '1');
    } catch (error) {
        showMessage('form-message', error.message, 'danger');
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearMessage('form-message');

        updateBtn.disabled = true;
        updateBtn.textContent = 'Updating...';

        try {
            const payload = Object.fromEntries(new FormData(form).entries());
            await apiPost("<?= url('api/master-data/expenditure-types/update.php') ?>", payload);
            showMessage('form-message', 'Expenditure type updated successfully.', 'success');

            setTimeout(() => {
                window.location.href = "<?= url('views/cashiering/master-data/expenditure-types/index.php') ?>";
            }, 700);
        } catch (error) {
            showMessage('form-message', error.message, 'danger');
            updateBtn.disabled = false;
            updateBtn.textContent = 'Update Expenditure Type';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-footer.php'; ?>