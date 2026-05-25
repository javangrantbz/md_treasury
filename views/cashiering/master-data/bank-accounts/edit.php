<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.bank_accounts.manage');

$id = (int)($_GET['id'] ?? 0);

require_once __DIR__ . '/../../../../includes/layout-header.php';
require_once __DIR__ . '/../../../../includes/layout-sidebar.php';
?>

<div class="page-body">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Edit Bank Account</h2>
            <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/bank-accounts/index.php') ?>">Back to List</a>
        </div>

        <div id="form-message" class="alert" style="display:none;"></div>

        <?php if ($id <= 0): ?>
            <div class="alert alert-danger">Invalid bank account ID.</div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <form id="bank-account-edit-form">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <div class="mb-3">
                            <label class="form-label">Bank Name</label>
                            <input class="form-control" type="text" name="bank_name" id="bank_name" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Account Name</label>
                            <input class="form-control" type="text" name="account_name" id="account_name" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Account Number</label>
                            <input class="form-control" type="text" name="account_number" id="account_number" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Currency</label>
                            <select class="form-select" name="currency_code" id="currency_code" required>
                                <option value="">Select Currency</option>
                                <option value="BZD">BZD</option>
                                <option value="USD">USD</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Item Number</label>
                            <input class="form-control" type="text" name="item_number" id="item_number">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">SOF Number</label>
                            <input class="form-control" type="text" name="sof_number" id="sof_number">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Branch Name</label>
                            <input class="form-control" type="text" name="branch_name" id="branch_name">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Account Type</label>
                            <input class="form-control" type="text" name="account_type" id="account_type">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <button class="btn btn-primary" type="submit" id="update-btn">Update Bank Account</button>
                        <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/bank-accounts/index.php') ?>">Cancel</a>
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
    const form = document.getElementById('bank-account-edit-form');
    const updateBtn = document.getElementById('update-btn');

    try {
        const res = await apiGet("<?= url('api/master-data/bank-accounts/show.php') ?>?id=" + recordId);
        const r = res.data || {};

        document.getElementById('bank_name').value = r.bank_name ?? '';
        document.getElementById('account_name').value = r.account_name ?? '';
        document.getElementById('account_number').value = r.account_number ?? '';
        document.getElementById('currency_code').value = r.currency_code ?? '';
        document.getElementById('item_number').value = r.item_number ?? '';
        document.getElementById('sof_number').value = r.sof_number ?? '';
        document.getElementById('branch_name').value = r.branch_name ?? '';
        document.getElementById('account_type').value = r.account_type ?? '';
        document.getElementById('status').value = r.status ?? 'active';
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
            await apiPost("<?= url('api/master-data/bank-accounts/update.php') ?>", payload);
            showMessage(messageId, 'Bank account updated successfully.', 'success');

            setTimeout(() => {
                window.location.href = "<?= url('views/cashiering/master-data/bank-accounts/index.php') ?>";
            }, 700);
        } catch (error) {
            console.error(error);
            showMessage(messageId, error.message, 'danger');
            updateBtn.disabled = false;
            updateBtn.textContent = 'Update Bank Account';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-footer.php'; ?>