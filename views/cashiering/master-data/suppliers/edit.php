<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.suppliers.manage');

$id = (int)($_GET['id'] ?? 0);

require_once __DIR__ . '/../../../../includes/layout-header.php';
require_once __DIR__ . '/../../../../includes/layout-sidebar.php';
?>

<div class="page-body">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Edit Supplier</h2>
            <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/suppliers/index.php') ?>">Back to List</a>
        </div>

        <div id="form-message" class="alert" style="display:none;"></div>

        <?php if ($id <= 0): ?>
            <div class="alert alert-danger">Invalid supplier ID.</div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <form id="supplier-edit-form">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <div class="mb-3">
                            <label class="form-label">Supplier Name</label>
                            <input class="form-control" type="text" name="supplier_name" id="supplier_name" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">TIN</label>
                            <input class="form-control" type="text" name="tax_id" id="tax_id">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Contact Name</label>
                            <input class="form-control" type="text" name="contact_name" id="contact_name">
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
                            <textarea class="form-control" name="notes" id="notes" rows="4"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Active</label>
                            <select class="form-select" name="is_active" id="is_active">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>

                        <button class="btn btn-primary" type="submit" id="update-btn">Update Supplier</button>
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

    const form = document.getElementById('supplier-edit-form');
    const updateBtn = document.getElementById('update-btn');

    try {
        const res = await apiGet("<?= url('api/master-data/suppliers/show.php') ?>?id=" + recordId);
        const r = res.data || {};

        document.getElementById('supplier_name').value = r.supplier_name ?? '';
        document.getElementById('tax_id').value = r.tax_id ?? '';
        document.getElementById('contact_name').value = r.contact_name ?? '';
        document.getElementById('email').value = r.email ?? '';
        document.getElementById('phone').value = r.phone ?? '';
        document.getElementById('address_line_1').value = r.address_line_1 ?? '';
        document.getElementById('address_line_2').value = r.address_line_2 ?? '';
        document.getElementById('district').value = r.district ?? '';
        document.getElementById('country').value = r.country ?? '';
        document.getElementById('notes').value = r.notes ?? '';
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
            await apiPost("<?= url('api/master-data/suppliers/update.php') ?>", payload);
            showMessage('form-message', 'Supplier updated successfully.', 'success');

            setTimeout(() => {
                window.location.href = "<?= url('views/cashiering/master-data/suppliers/index.php') ?>";
            }, 700);
        } catch (error) {
            showMessage('form-message', error.message, 'danger');
            updateBtn.disabled = false;
            updateBtn.textContent = 'Update Supplier';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-footer.php'; ?>