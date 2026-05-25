<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.customers.manage');

require_once __DIR__ . '/../../../../includes/layout-header.php';
require_once __DIR__ . '/../../../../includes/layout-sidebar.php';
?>

<div class="page-body">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Add Customer</h2>
            <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/customers/index.php') ?>">Back to List</a>
        </div>

        <div id="form-message" class="alert" style="display:none;"></div>

        <div class="card">
            <div class="card-body">
                <form id="customer-create-form">

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
                        <input class="form-control" type="text" name="tax_id">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input class="form-control" type="email" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input class="form-control" type="text" name="phone">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Address Line 1</label>
                        <input class="form-control" type="text" name="address_line_1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Address Line 2</label>
                        <input class="form-control" type="text" name="address_line_2">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">District</label>
                            <input class="form-control" type="text" name="district">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Country</label>
                            <input class="form-control" type="text" name="country">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <button class="btn btn-primary" type="submit" id="save-btn">Save Customer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const typeSelect = document.getElementById('customer_type');
    const individualFields = document.getElementById('individual-fields');
    const organizationFields = document.getElementById('organization-fields');

    typeSelect.addEventListener('change', () => {
        const isOrg = typeSelect.value === 'organization';
        individualFields.style.display = isOrg ? 'none' : '';
        organizationFields.style.display = isOrg ? '' : 'none';
    });

    const form = document.getElementById('customer-create-form');
    const saveBtn = document.getElementById('save-btn');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearMessage('form-message');

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
            const payload = Object.fromEntries(new FormData(form).entries());
            await apiPost("<?= url('api/master-data/customers/create.php') ?>", payload);
            showMessage('form-message', 'Customer created successfully.', 'success');

            setTimeout(() => {
                window.location.href = "<?= url('views/cashiering/master-data/customers/index.php') ?>";
            }, 700);
        } catch (error) {
            showMessage('form-message', error.message, 'danger');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Customer';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-footer.php'; ?>
