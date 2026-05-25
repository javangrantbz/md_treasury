<?php
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/helpers.php';

Auth::requireAuth();

$id = (int)($_GET['id'] ?? 0);

require_once __DIR__ . '/../../../includes/layout-header.php';
require_once __DIR__ . '/../../../includes/layout-sidebar.php';
?>

<main class="content">
    <div class="page-head d-flex justify-content-between align-items-center">
        <h1>Transaction Details</h1>
        <a class="btn btn-secondary" href="<?= url('views/cashiering/transactions/index.php') ?>">
            Back
        </a>
    </div>

    <div id="page-message" class="alert" style="display:none;"></div>

    <?php if ($id <= 0): ?>
        <div class="alert alert-danger">Invalid transaction ID.</div>
    <?php else: ?>

        <div class="card mb-4">
            <div class="card-body">
                <h3 id="receipt-number">Loading...</h3>

                <div class="row">
                    <div class="col-md-6 mb-2">
                        <strong>Department:</strong> <span id="department-name"></span>
                    </div>

                    <div class="col-md-6 mb-2">
                        <strong>Sub-Treasury:</strong> <span id="sub-treasury-name"></span>
                    </div>

                    <div class="col-md-6 mb-2">
                        <strong>Register:</strong> <span id="register-name"></span>
                    </div>

                    <div class="col-md-6 mb-2">
                        <strong>Cost Center:</strong> <span id="cost-center-name"></span>
                    </div>

                    <div class="col-md-6 mb-2">
                        <strong>Customer:</strong> <span id="customer-name"></span>
                    </div>

                    <div class="col-md-6 mb-2">
                        <strong>Date:</strong> <span id="transaction-date"></span>
                    </div>

                    <div class="col-md-6 mb-2">
                        <strong>Status:</strong> <span id="status"></span>
                    </div>

                    <div class="col-12 mb-2">
                        <strong>Narration:</strong> <span id="narration"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h4 class="mb-3">Transaction Items</h4>

                <div id="items-message" class="alert" style="display:none;"></div>

                <form id="item-form" class="row g-3 mb-3">
                    <input type="hidden" name="transaction_id" value="<?= $id ?>">
                    <input type="hidden" name="id" id="item_id">

                    <div class="col-md-4">
                        <label class="form-label">Cost Center Activity</label>
                        <select class="form-select" name="cost_center_activity_id" id="cost_center_activity_id">
                            <option value="">Optional</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Description</label>
                        <input class="form-control" type="text" name="description" id="description" required>
                    </div>

                    <div class="col-md-1">
                        <label class="form-label">Qty</label>
                        <input class="form-control" type="number" step="0.01" min="0.01" name="quantity" id="quantity" value="1.00" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Unit Amount</label>
                        <input class="form-control" type="number" step="0.01" min="0" name="unit_amount" id="unit_amount" value="0.00" required>
                    </div>

                    <div class="col-md-1">
                        <label class="form-label">Currency</label>
                        <select class="form-select" name="currency_code" id="currency_code">
                            <option value="BZD">BZD</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Sort</label>
                        <input class="form-control" type="number" min="1" name="sort_order" id="sort_order" value="1">
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary" type="submit" id="item-save-btn">Add Item</button>
                        <button class="btn btn-secondary" type="button" id="item-cancel-btn" style="display:none;">Cancel Edit</button>
                    </div>
                </form>

                <table class="table table-striped table-hover" id="items-table">
                    <thead>
                        <tr>
                            <th>Activity</th>
                            <th>Description</th>
                            <th>Qty</th>
                            <th>Unit Amount</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="6">Loading...</td></tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">Grand Total</th>
                            <th id="items-grand-total">0.00</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h4>Status History</h4>

                <table class="table table-striped" id="status-history-table">
                    <thead>
                        <tr>
                            <th>Previous</th>
                            <th>New</th>
                            <th>Changed By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="4">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const recordId = <?= $id ?>;
    if (!recordId) return;

    const itemForm = document.getElementById('item-form');
    const itemSaveBtn = document.getElementById('item-save-btn');
    const itemCancelBtn = document.getElementById('item-cancel-btn');

    const itemIdField = document.getElementById('item_id');
    const activityField = document.getElementById('cost_center_activity_id');
    const descriptionField = document.getElementById('description');
    const quantityField = document.getElementById('quantity');
    const unitAmountField = document.getElementById('unit_amount');
    const currencyField = document.getElementById('currency_code');
    const sortOrderField = document.getElementById('sort_order');

    let transactionData = null;

    function resetItemForm() {
        itemForm.reset();
        itemIdField.value = '';
        quantityField.value = '1.00';
        unitAmountField.value = '0.00';
        sortOrderField.value = '1';
        currencyField.value = 'BZD';
        itemSaveBtn.textContent = 'Add Item';
        itemCancelBtn.style.display = 'none';
    }

    async function loadActivities(costCenterId) {
        activityField.innerHTML = '<option value="">Optional</option>';

        if (!costCenterId) return;

        try {
            const res = await apiGet("<?= url('api/master-data/cost-center-activities/list.php') ?>?cost_center_id=" + costCenterId);
            (res.data || []).forEach(row => {
                const option = document.createElement('option');
                option.value = row.id;
                option.textContent = `${row.activity_code ?? ''} - ${row.activity_name ?? ''}`;
                activityField.appendChild(option);
            });
        } catch (error) {
            showMessage('items-message', error.message, 'danger');
        }
    }

    async function loadTransactionDetails() {
        const res = await apiGet("<?= url('api/transactions/show.php') ?>?id=" + recordId);

        const tx = res.data.transaction || {};
        const history = res.data.status_history || [];
        transactionData = tx;

        document.getElementById('receipt-number').textContent = tx.receipt_number ?? '';
        document.getElementById('department-name').textContent = tx.department_name ?? '';
        document.getElementById('sub-treasury-name').textContent = tx.sub_treasury_name ?? '';
        document.getElementById('register-name').textContent = tx.register_name ?? '';
        document.getElementById('cost-center-name').textContent = tx.cost_center_name ?? '';
        document.getElementById('customer-name').textContent = tx.customer_name ?? '';
        document.getElementById('transaction-date').textContent = tx.transaction_date ?? '';
        document.getElementById('status').textContent = tx.status ?? '';
        document.getElementById('narration').textContent = tx.narration ?? '';

        await loadActivities(tx.cost_center_id ?? '');

        const tbody = document.querySelector('#status-history-table tbody');
        tbody.innerHTML = '';

        if (history.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4">No history found.</td></tr>';
        } else {
            history.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${row.previous_status ?? ''}</td>
                    <td>${row.new_status ?? ''}</td>
                    <td>${row.changed_by_name ?? ''}</td>
                    <td>${row.created_at ?? ''}</td>
                `;
                tbody.appendChild(tr);
            });
        }
    }

    async function loadItems() {
        const res = await apiGet("<?= url('api/transactions/item-list.php') ?>?transaction_id=" + recordId);
        const items = res.data.items || [];
        const total = res.data.total || '0.00';

        const tbody = document.querySelector('#items-table tbody');
        tbody.innerHTML = '';

        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6">No items added yet.</td></tr>';
        } else {
            items.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${row.activity_name ? ((row.activity_code ?? '') + ' - ' + row.activity_name) : ''}</td>
                    <td>${row.description ?? ''}</td>
                    <td>${row.quantity ?? ''}</td>
                    <td>${row.currency_code ?? ''} ${row.unit_amount ?? ''}</td>
                    <td>${row.currency_code ?? ''} ${row.line_total ?? ''}</td>
                    <td class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-edit-id="${row.id}">
                            Edit
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-delete-id="${row.id}">
                            Delete
                        </button>
                    </td>
                `;
                tr.dataset.item = JSON.stringify(row);
                tbody.appendChild(tr);
            });
        }

        document.getElementById('items-grand-total').textContent = total;

        tbody.querySelectorAll('[data-edit-id]').forEach(btn => {
            btn.addEventListener('click', () => {
                const row = btn.closest('tr');
                const item = JSON.parse(row.dataset.item);

                itemIdField.value = item.id ?? '';
                activityField.value = item.cost_center_activity_id ?? '';
                descriptionField.value = item.description ?? '';
                quantityField.value = item.quantity ?? '1.00';
                unitAmountField.value = item.unit_amount ?? '0.00';
                currencyField.value = item.currency_code ?? 'BZD';
                sortOrderField.value = item.sort_order ?? '1';

                itemSaveBtn.textContent = 'Update Item';
                itemCancelBtn.style.display = 'inline-block';
                window.scrollTo({ top: itemForm.offsetTop - 100, behavior: 'smooth' });
            });
        });

        tbody.querySelectorAll('[data-delete-id]').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Delete this item?')) return;

                try {
                    await apiPost("<?= url('api/transactions/item-delete.php') ?>", {
                        id: btn.dataset.deleteId
                    });
                    showMessage('items-message', 'Item deleted successfully.', 'success');
                    await loadItems();
                    resetItemForm();
                } catch (error) {
                    showMessage('items-message', error.message, 'danger');
                }
            });
        });
    }

    itemForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearMessage('items-message');

        const payload = Object.fromEntries(new FormData(itemForm).entries());

        try {
            if (itemIdField.value) {
                await apiPost("<?= url('api/transactions/item-update.php') ?>", payload);
                showMessage('items-message', 'Item updated successfully.', 'success');
            } else {
                await apiPost("<?= url('api/transactions/item-create.php') ?>", payload);
                showMessage('items-message', 'Item added successfully.', 'success');
            }

            await loadItems();
            resetItemForm();
        } catch (error) {
            showMessage('items-message', error.message, 'danger');
        }
    });

    itemCancelBtn.addEventListener('click', () => {
        resetItemForm();
    });

    try {
        await loadTransactionDetails();
        await loadItems();
        resetItemForm();
    } catch (error) {
        showMessage('page-message', error.message, 'danger');
    }
});
</script>

<?php require_once __DIR__ . '/../../../includes/layout-footer.php'; ?>