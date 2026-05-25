<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.cost_center_activities.manage');

$id = (int)($_GET['id'] ?? 0);

require_once __DIR__ . '/../../../../includes/layout-header.php';
require_once __DIR__ . '/../../../../includes/layout-sidebar.php';
?>

<div class="page-body">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Edit Cost Center Activity</h2>
            <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/cost-center-activities/index.php') ?>">Back to List</a>
        </div>

        <div id="form-message" class="alert" style="display:none;"></div>

        <?php if ($id <= 0): ?>
            <div class="alert alert-danger">Invalid activity ID.</div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <form id="activity-edit-form">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <div class="mb-3">
                            <label class="form-label">Cost Center</label>
                            <select class="form-select" name="cost_center_id" id="cost_center_id" required>
                                <option value="">Loading cost centers...</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Activity Code</label>
                            <input class="form-control" type="text" name="activity_code" id="activity_code" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Activity Name</label>
                            <input class="form-control" type="text" name="activity_name" id="activity_name" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Revenue Code</label>
                            <input class="form-control" type="text" name="revenue_code" id="revenue_code">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Default Amount</label>
                            <input class="form-control" type="number" step="0.01" min="0" name="default_amount" id="default_amount">
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

                        <button class="btn btn-primary" type="submit" id="update-btn">Update Activity</button>
                        <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/cost-center-activities/index.php') ?>">Cancel</a>
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

    const messageId = 'form-message';
    const form = document.getElementById('activity-edit-form');
    const updateBtn = document.getElementById('update-btn');
    const costCenterSelect = document.getElementById('cost_center_id');

    try {
        const [costCenterRes, recordRes] = await Promise.all([
            apiGet("<?= url('api/master-data/cost-centers/list.php') ?>"),
            apiGet("<?= url('api/master-data/cost-center-activities/show.php') ?>?id=" + recordId)
        ]);

        costCenterSelect.innerHTML = '<option value="">Select Cost Center</option>';
        (costCenterRes.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.code} - ${row.name}`;
            costCenterSelect.appendChild(option);
        });

        const r = recordRes.data || {};
        document.getElementById('cost_center_id').value = r.cost_center_id ?? '';
        document.getElementById('activity_code').value = r.activity_code ?? '';
        document.getElementById('activity_name').value = r.activity_name ?? '';
        document.getElementById('revenue_code').value = r.revenue_code ?? '';
        document.getElementById('default_amount').value = r.default_amount ?? '';
        document.getElementById('description').value = r.description ?? '';
        document.getElementById('is_active').value = String(r.is_active ?? '1');
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
            await apiPost("<?= url('api/master-data/cost-center-activities/update.php') ?>", payload);
            showMessage(messageId, 'Activity updated successfully.', 'success');

            setTimeout(() => {
                window.location.href = "<?= url('views/cashiering/master-data/cost-center-activities/index.php') ?>";
            }, 700);
        } catch (error) {
            console.error(error);
            showMessage(messageId, error.message, 'danger');
            updateBtn.disabled = false;
            updateBtn.textContent = 'Update Activity';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-footer.php'; ?>