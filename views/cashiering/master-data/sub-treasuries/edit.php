<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.sub_treasuries.manage');

$id = (int)($_GET['id'] ?? 0);

require_once __DIR__ . '/../../../../includes/layout-header.php';
require_once __DIR__ . '/../../../../includes/layout-sidebar.php';
?>

<div class="page-body">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Edit Sub-treasury</h2>
            <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/sub-treasuries/index.php') ?>">Back to List</a>
        </div>

        <div id="form-message" class="alert" style="display:none;"></div>

        <?php if ($id <= 0): ?>
            <div class="alert alert-danger">Invalid sub-treasury ID.</div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <form id="sub-treasury-edit-form">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department_id" id="department_id" required>
                                <option value="">Loading departments...</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Cost Centre Code</label>
                            <input class="form-control" type="text" name="sub_treasury_code" id="sub_treasury_code" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Sub-treasury Name</label>
                            <input class="form-control" type="text" name="sub_treasury_name" id="sub_treasury_name" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">District</label>
                            <input class="form-control" type="text" name="district" id="district">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Address Line</label>
                            <input class="form-control" type="text" name="address_line" id="address_line">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Contact Phone</label>
                            <input class="form-control" type="text" name="contact_phone" id="contact_phone">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Contact Email</label>
                            <input class="form-control" type="email" name="contact_email" id="contact_email">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Active</label>
                            <select class="form-select" name="is_active" id="is_active">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>

                        <button class="btn btn-primary" type="submit" id="update-btn">Update Sub-treasury</button>
                        <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/sub-treasuries/index.php') ?>">Cancel</a>
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
    const form = document.getElementById('sub-treasury-edit-form');
    const updateBtn = document.getElementById('update-btn');
    const departmentSelect = document.getElementById('department_id');

    try {
        const [departmentRes, recordRes] = await Promise.all([
            apiGet("<?= url('api/master-data/departments/list.php') ?>"),
            apiGet("<?= url('api/master-data/sub-treasuries/show.php') ?>?id=" + recordId)
        ]);

        departmentSelect.innerHTML = '<option value="">Select Department</option>';
        (departmentRes.data || []).forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = `${row.code} - ${row.name}`;
            departmentSelect.appendChild(option);
        });

        const r = recordRes.data || {};
        document.getElementById('department_id').value = r.department_id ?? '';
        document.getElementById('sub_treasury_code').value = r.sub_treasury_code ?? '';
        document.getElementById('sub_treasury_name').value = r.sub_treasury_name ?? '';
        document.getElementById('district').value = r.district ?? '';
        document.getElementById('address_line').value = r.address_line ?? '';
        document.getElementById('contact_phone').value = r.contact_phone ?? '';
        document.getElementById('contact_email').value = r.contact_email ?? '';
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
            await apiPost("<?= url('api/master-data/sub-treasuries/update.php') ?>", payload);
            showMessage(messageId, 'Sub-treasury updated successfully.', 'success');

            setTimeout(() => {
                window.location.href = "<?= url('views/cashiering/master-data/sub-treasuries/index.php') ?>";
            }, 700);
        } catch (error) {
            console.error(error);
            showMessage(messageId, error.message, 'danger');
            updateBtn.disabled = false;
            updateBtn.textContent = 'Update Sub-treasury';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-footer.php'; ?>
