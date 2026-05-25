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
            <h2>Customer Details</h2>
            <div>
                <?php if ($id > 0): ?>
                    <a class="btn btn-primary" href="<?= url('views/cashiering/master-data/customers/edit.php') ?>?id=<?= $id ?>">Edit</a>
                <?php endif; ?>
                <a class="btn btn-secondary" href="<?= url('views/cashiering/master-data/customers/index.php') ?>">Back to List</a>
            </div>
        </div>

        <div id="page-message" class="alert" style="display:none;"></div>

        <?php if ($id <= 0): ?>
            <div class="alert alert-danger">Invalid customer ID.</div>
        <?php else: ?>
            <div class="card" id="detail-card" style="display:none;">
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">Customer Type</dt>
                        <dd class="col-sm-9" id="d-customer_type" style="text-transform:capitalize"></dd>

                        <dt class="col-sm-3">Name</dt>
                        <dd class="col-sm-9" id="d-customer_name"></dd>

                        <dt class="col-sm-3">TIN</dt>
                        <dd class="col-sm-9" id="d-tax_id"></dd>

                        <dt class="col-sm-3">Email</dt>
                        <dd class="col-sm-9" id="d-email"></dd>

                        <dt class="col-sm-3">Phone</dt>
                        <dd class="col-sm-9" id="d-phone"></dd>

                        <dt class="col-sm-3">Address</dt>
                        <dd class="col-sm-9" id="d-address"></dd>

                        <dt class="col-sm-3">District</dt>
                        <dd class="col-sm-9" id="d-district"></dd>

                        <dt class="col-sm-3">Country</dt>
                        <dd class="col-sm-9" id="d-country"></dd>

                        <dt class="col-sm-3">Notes</dt>
                        <dd class="col-sm-9" id="d-notes"></dd>

                        <dt class="col-sm-3">Status</dt>
                        <dd class="col-sm-9" id="d-status"></dd>

                        <dt class="col-sm-3">Created</dt>
                        <dd class="col-sm-9" id="d-created_at"></dd>

                        <dt class="col-sm-3">Last Updated</dt>
                        <dd class="col-sm-9" id="d-updated_at"></dd>
                    </dl>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const recordId = <?= $id ?>;
    if (!recordId) return;

    try {
        const res = await apiGet("<?= url('api/master-data/customers/show.php') ?>?id=" + recordId);
        const r = res.data || {};

        const set = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val ?? '—';
        };

        set('d-customer_type', r.customer_type);
        set('d-customer_name', r.customer_name);
        set('d-tax_id', r.tax_id);
        set('d-email', r.email);
        set('d-phone', r.phone);

        const addr = [r.address_line_1, r.address_line_2].filter(Boolean).join(', ');
        set('d-address', addr || null);
        set('d-district', r.district);
        set('d-country', r.country);
        set('d-notes', r.notes);
        set('d-status', r.status);
        set('d-created_at', r.created_at);
        set('d-updated_at', r.updated_at);

        document.getElementById('detail-card').style.display = '';
    } catch (error) {
        showMessage('page-message', error.message, 'danger');
    }
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-footer.php'; ?>
