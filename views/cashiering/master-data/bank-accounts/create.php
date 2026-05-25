<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.bank_accounts.manage');

require_once __DIR__ . '/../../../../includes/layout-header.php';
require_once __DIR__ . '/../../../../includes/layout-sidebar.php';
?>

<div class="page-body">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Add Bank Account</h2>
            <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/bank-accounts/index.php') ?>">Back to List</a>
        </div>

        <div id="form-message" class="alert" style="display:none;"></div>

        <div class="card">
            <div class="card-body">
                <form id="bank-account-create-form">
                    <div class="mb-3">
                        <label class="form-label">Bank Name</label>
                        <input class="form-control" type="text" name="bank_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Account Name</label>
                        <input class="form-control" type="text" name="account_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Account Number</label>
                        <input class="form-control" type="text" name="account_number" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Currency</label>
                        <select class="form-select" name="currency_code" required>
                            <option value="">Select Currency</option>
                            <option value="BZD">BZD</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Item Number</label>
                        <input class="form-control" type="text" name="item_number">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">SOF Number</label>
                        <input class="form-control" type="text" name="sof_number">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Branch Name</label>
                        <input class="form-control" type="text" name="branch_name">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Account Type</label>
                        <input class="form-control" type="text" name="account_type">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <button class="btn btn-primary" type="submit" id="save-btn">Save Bank Account</button>
                    <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/bank-accounts/index.php') ?>">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const messageId = 'form-message';
    const form = document.getElementById('bank-account-create-form');
    const saveBtn = document.getElementById('save-btn');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearMessage(messageId);

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        try {
            await apiPost("<?= url('api/master-data/bank-accounts/create.php') ?>", payload);
            showMessage(messageId, 'Bank account created successfully.', 'success');

            setTimeout(() => {
                window.location.href = "<?= url('views/cashiering/master-data/bank-accounts/index.php') ?>";
            }, 700);
        } catch (error) {
            console.error(error);
            showMessage(messageId, error.message, 'danger');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Bank Account';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-footer.php'; ?>