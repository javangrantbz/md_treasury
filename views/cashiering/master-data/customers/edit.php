<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.customers.manage');

$id = (int)($_GET['id'] ?? 0);

require_once __DIR__ . '/../../../../includes/layout-header.php';
require_once __DIR__ . '/../../../../includes/layout-sidebar.php';
?>

<div class="page-body">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Edit Customer</h2>
            <div>
                <?php if ($id > 0): ?>
                    <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/customers/view.php') ?>?id=<?= $id ?>">View Details</a>
                <?php endif; ?>
                <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/customers/index.php') ?>">Back to List</a>
            </div>
        </div>

        <div id="form-message" class="alert" style="display:none;"></div>

        <?php if ($id <= 0): ?>
            <div class="alert alert-danger">Invalid customer ID.</div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <form id="customer-edit-form">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <div class="mb-3">
                            <label class="form-label">Customer Type</label>
                            <select class="form-select" name="customer_type" id="customer_type" required>
                                <option value="individual">Individual</option>
                                <option value="organization">Organization</option>
                            </select>
                        </div>

                        <div id="individual-fields">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name</label>
                                    <input class="form-control" type="text" name="first_name" id="first_name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input class="form-control" type="text" name="last_name" id="last_name">
                                </div>
                            </div>
                        </div>

                        <div id="organization-fields" style="display:none;">
                            <div class="mb-3">
                                <label class="form-label">Organization Name</label>
                                <input class="form-control" type="text" name="organization_name" id="organization_name">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">TIN</label>
                            <input class="form-control" type="text" name="tax_id" id="tax_id">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input class="form-control" type="email" name="email" id="email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input class="form-control" type="text" name="phone" id="phone">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Address Line 1</label>
                            <input class="form-control" type="text" name="address_line_1" id="address_line_1">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Address Line 2</label>
                            <input class="form-control" type="text" name="address_line_2" id="address_line_2">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">District</label>
                                <input class="form-control" type="text" name="district" id="district">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Country</label>
                                <input class="form-control" type="text" name="country" id="country">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="notes" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <button class="btn btn-primary" type="submit" id="update-btn">Update Customer</button>
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

    const typeSelect = document.getElementById('customer_type');
    const individualFields = document.getElementById('individual-fields');
    const organizationFields = document.getElementById('organization-fields');

    const applyTypeToggle = () => {
        const isOrg = typeSelect.value === 'organization';
        individualFields.style.display = isOrg ? 'none' : '';
        organizationFields.style.display = isOrg ? '' : 'none';
    };

    typeSelect.addEventListener('change', applyTypeToggle);

    const form = document.getElementById('customer-edit-form');
    const updateBtn = document.getElementById('update-btn');

    try {
        const res = await apiGet("<?= url('api/master-data/customers/show.php') ?>?id=" + recordId);
        const r = res.data || {};

        typeSelect.value = r.customer_type ?? 'individual';
        applyTypeToggle();

        document.getElementById('first_name').value = r.first_name ?? '';
        document.getElementById('last_name').value = r.last_name ?? '';
        document.getElementById('organization_name').value = r.organization_name ?? '';
        document.getElementById('tax_id').value = r.tax_id ?? '';
        document.getElementById('email').value = r.email ?? '';
        document.getElementById('phone').value = r.phone ?? '';
        document.getElementById('address_line_1').value = r.address_line_1 ?? '';
        document.getElementById('address_line_2').value = r.address_line_2 ?? '';
        document.getElementById('district').value = r.district ?? '';
        document.getElementById('country').value = r.country ?? '';
        document.getElementById('notes').value = r.notes ?? '';
        document.getElementById('status').value = r.status ?? 'active';
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
            await apiPost("<?= url('api/master-data/customers/update.php') ?>", payload);
            showMessage('form-message', 'Customer updated successfully.', 'success');

            setTimeout(() => {
                window.location.href = "<?= url('views/cashiering/master-data/customers/index.php') ?>";
            }, 700);
        } catch (error) {
            showMessage('form-message', error.message, 'danger');
            updateBtn.disabled = false;
            updateBtn.textContent = 'Update Customer';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-footer.php'; ?>
