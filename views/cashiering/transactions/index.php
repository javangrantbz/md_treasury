<?php
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/helpers.php';

Auth::requireAuth();

require_once __DIR__ . '/../../../includes/layout-header.php';
require_once __DIR__ . '/../../../includes/layout-sidebar.php';
?>

<main class="content">
    <div class="page-head d-flex justify-content-between align-items-center">
        <h1>Transactions</h1>
    </div>

    <div id="page-message" class="alert" style="display:none;"></div>

    <div class="card">
        <div class="card-body">
            <table class="table table-striped table-hover" id="transactions-table">
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th>Department</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="6">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const tbody = document.querySelector('#transactions-table tbody');

    try {
        const res = await apiGet("<?= url('api/transactions/list.php') ?>");
        const rows = res.data || [];

        tbody.innerHTML = '';

        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6">No transactions found.</td></tr>';
            return;
        }

        rows.forEach(row => {
            const tr = document.createElement('tr');

            tr.innerHTML = `
                <td>${row.receipt_number ?? ''}</td>
                <td>${row.department_name ?? ''}</td>
                <td>${row.customer_name ?? ''}</td>
                <td>${row.transaction_date ?? ''}</td>
                <td>${row.status ?? ''}</td>
                <td>
                    <a class="btn btn-sm btn-outline-primary"
                       href="<?= url('views/cashiering/transactions/details.php') ?>?id=${row.id}">
                       View
                    </a>
                </td>
            `;

            tbody.appendChild(tr);
        });

    } catch (error) {
        showMessage('page-message', error.message, 'danger');
        tbody.innerHTML = '<tr><td colspan="6">Failed to load transactions.</td></tr>';
    }
});
</script>

<?php require_once __DIR__ . '/../../../includes/layout-footer.php'; ?>