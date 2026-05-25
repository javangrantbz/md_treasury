<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.cost_center_activities.manage');

require_once __DIR__ . '/../../../../includes/layout-header.php';
require_once __DIR__ . '/../../../../includes/layout-sidebar.php';
?>

<div class="page-body">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Add Cost Center Activity</h2>
            <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/cost-center-activities/index.php') ?>">Back to List</a>
        </div>

        <div id="form-message" class="alert" style="display:none;"></div>

        <div class="card">
            <div class="card-body">
                <form id="activity-create-form">
                    <div class="mb-3">
                        <label class="form-label">Cost Center</label>
                        <select class="form-select" name="cost_center_id" id="cost_center_id" required>
                            <option value="">Loading cost centers...</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Activity Code</label>
                        <input class="form-control" type="text" name="activity_code" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Activity Name</label>
                        <input class="form-control" type="text" name="activity_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Revenue Code</label>
                        <input class="form-control" type="text" name="revenue_code">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Default Amount</label>
                        <input class="form-control" type="number" step="0.01" min="0" name="default_amount">
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

                    <button class="btn btn-primary" type="submit" id="save-btn">Save Activity</button>
                    <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/cost-center-activities/index.php') ?>">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const messageId = 'form-message';
    const form = document.getElementById('activity-create-form');
    const saveBtn = document.getElementById('save-btn');
    const costCenterSelect = document.getElementById('cost_center_id');

    try {
        const res = await apiGet("<?= url('api/master-data/cost-centers/list.php') ?>");
        costCenterSelect.innerHTML = '<option value="">Select Cost Center</option>';

        (res.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.code} - ${row.name}`;
            costCenterSelect.appendChild(option);
        });
    } catch (error) {
        console.error(error);
        costCenterSelect.innerHTML = '<option value="">Unable to load cost centers</option>';
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
            await apiPost("<?= url('api/master-data/cost-center-activities/create.php') ?>", payload);
            showMessage(messageId, 'Activity created successfully.', 'success');

            setTimeout(() => {
                window.location.href = "<?= url('views/cashiering/master-data/cost-center-activities/index.php') ?>";
            }, 700);
        } catch (error) {
            console.error(error);
            showMessage(messageId, error.message, 'danger');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Activity';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-footer.php'; ?>