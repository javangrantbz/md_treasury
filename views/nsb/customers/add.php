<?php
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/helpers.php';
Auth::requireAuth();
require_once __DIR__ . '/../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../includes/layout-tabler-sidebar.php';
?>
<div class="page-header d-print-none">
  <div class="container-xl">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">NSB — Customers</div>
        <h2 class="page-title">Add Customer</h2>
      </div>
      <div class="col-auto ms-auto">
        <a href="<?= url('views/nsb/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
      </div>
    </div>
  </div>
</div>
<div class="page-body">
  <div class="container-xl">
    <div class="card">
      <div class="card-body">
        <p class="text-muted">This page is currently under development.</p>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../../includes/layout-tabler-footer.php'; ?>
