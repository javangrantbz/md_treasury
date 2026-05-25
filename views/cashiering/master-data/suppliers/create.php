<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.suppliers.manage');

require_once __DIR__ . '/../../../../includes/layout-header.php';
require_once __DIR__ . '/../../../../includes/layout-sidebar.php';
?>

<div class="page-body">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Add Supplier</h2>
            <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/suppliers/index.php') ?>">Back to List</a>
        </div>

        <div id="form-message" class="alert" style="display:none;"></div>

        <div class="card">
            <div class="card-body">
                <form id="supplier-create-form">
                    <div class="mb-3">
                        <label class="form-label">Supplier Name</label>
                        <input class="form-control" type="text" name="supplier_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">TIN</label>
                        <input class="form-control" type="text" name="tax_id">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Contact Name</label>
                        <input class="form-control" type="text" name="contact_name">
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
                        <textarea class="form-control" name="notes" rows="4"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Active</label>
                        <select class="form-select" name="is_active">
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>

                    <button class="btn btn-primary" type="submit" id="save-btn">Save Supplier</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('supplier-create-form');
    const saveBtn = document.getElementById('save-btn');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearMessage('form-message');

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
            const payload = Object.fromEntries(new FormData(form).entries());
            await apiPost("<?= url('api/master-data/suppliers/create.php') ?>", payload);
            showMessage('form-message', 'Supplier created successfully.', 'success');

            setTimeout(() => {
                window.location.href = "<?= url('views/cashiering/master-data/suppliers/index.php') ?>";
            }, 700);
        } catch (error) {
            showMessage('form-message', error.message, 'danger');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Supplier';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-footer.php'; ?>