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
            <h2>Edit Department</h2>
            <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/departments/index.php') ?>">Back to List</a>
        </div>

        <div id="form-message" class="alert" style="display:none;"></div>

        <?php if ($id <= 0): ?>
            <div class="alert alert-danger">Invalid department ID.</div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <form id="department-edit-form">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <div class="mb-3">
                            <label class="form-label">Code</label>
                            <input class="form-control" type="text" name="code" id="code" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input class="form-control" type="text" name="name" id="name" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Ministry Name</label>
                            <input class="form-control" type="text" name="ministry_name" id="ministry_name">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Branch</label>
                            <select class="form-select" name="branch_id" id="branch_id">
                                <option value="">Loading branches...</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Short Name</label>
                            <input class="form-control" type="text" name="short_name" id="short_name">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="description" rows="4"></textarea>
                        </div>

                        <button class="btn btn-primary" type="submit" id="update-btn">Update Department</button>
                        <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/departments/index.php') ?>">Cancel</a>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const departmentId = <?= $id ?>;
    if (!departmentId) return;

    const messageId = 'form-message';
    const form = document.getElementById('department-edit-form');
    const updateBtn = document.getElementById('update-btn');
    const branchSelect = document.getElementById('branch_id');

    try {
        const [branchRes, departmentRes] = await Promise.all([
            apiGet("<?= url('api/master-data/branches/list.php') ?>"),
            apiGet("<?= url('api/master-data/departments/show.php') ?>?id=" + departmentId)
        ]);

        branchSelect.innerHTML = '<option value="">Select Branch</option>';
        (branchRes.data || []).forEach(branch => {
            const option = document.createElement('option');
            option.value = branch.id;
            option.textContent = `${branch.code} - ${branch.name}`;
            branchSelect.appendChild(option);
        });

        const d = departmentRes.data || {};
        document.getElementById('code').value = d.code ?? '';
        document.getElementById('name').value = d.name ?? '';
        document.getElementById('ministry_name').value = d.ministry_name ?? '';
        document.getElementById('branch_id').value = d.branch_id ?? '';
        document.getElementById('status').value = d.status ?? 'active';
        document.getElementById('short_name').value = d.short_name ?? '';
        document.getElementById('description').value = d.description ?? '';
    } catch (error) {
        console.error(error);
        showMessage(messageId, error.message, 'danger');
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearMessage(messageId);
        updateBtn.disabled = true;
        updateBtn.textContent = 'Updating...';

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        try {
            await apiPost("<?= url('api/master-data/departments/update.php') ?>", payload);
            showMessage(messageId, 'Department updated successfully.', 'success');

            setTimeout(() => {
                window.location.href = "<?= url('views/cashiering/master-data/departments/index.php') ?>";
            }, 700);
        } catch (error) {
            console.error(error);
            showMessage(messageId, error.message, 'danger');
            updateBtn.disabled = false;
            updateBtn.textContent = 'Update Department';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-footer.php'; ?>