<?php
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::requireAuth();
require_once __DIR__ . '/../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row align-items-center">
        <div class="col">
          <div class="page-pretitle">Government of Belize — Treasury Department</div>
          <h2 class="page-title">Pay-In (Point of Sale)</h2>
        </div>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">

      <div class="row justify-content-center">
        <div class="col-lg-7">
          <div class="card" style="border-left:4px solid var(--tblr-warning);">
            <div class="card-body py-5 text-center">

              <div class="mb-4">
                <span class="bg-warning-lt rounded p-3 d-inline-block">
                  <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--tblr-warning)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 3m0 2a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <path d="M3 10v6a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6"/>
                    <path d="M7 15h.01"/><path d="M11 15h2"/>
                  </svg>
                </span>
              </div>

              <h2 class="mb-1">Pay-In Module</h2>
              <p class="text-muted mb-4" style="font-size:.95rem;">
                Point-of-sale receipting system — pending integration
              </p>

              <div class="alert alert-warning text-start mb-4">
                <div class="d-flex gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon flex-shrink-0" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                  <div>
                    <strong>Integration Pending</strong><br>
                    <span style="font-size:.88rem;">
                      The Pay-In POS module is managed by a separate system and will be integrated into Treasury Revenue System in a future release. Revenue collected through Pay-In flows directly into the Cashiering module.
                    </span>
                  </div>
                </div>
              </div>

              <div class="row g-3 text-start mb-4">
                <div class="col-md-6">
                  <div class="d-flex gap-2 align-items-start">
                    <svg xmlns="http://www.w3.org/2000/svg" class="text-primary flex-shrink-0 mt-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    <div>
                      <div class="fw-semibold" style="font-size:.88rem;">Point-of-Sale Receipting</div>
                      <div class="text-muted" style="font-size:.8rem;">Collect revenue at cashier desks across departments</div>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="d-flex gap-2 align-items-start">
                    <svg xmlns="http://www.w3.org/2000/svg" class="text-primary flex-shrink-0 mt-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    <div>
                      <div class="fw-semibold" style="font-size:.88rem;">Revenue Stream Tracking</div>
                      <div class="text-muted" style="font-size:.8rem;">Track revenue by type, department, and cashier</div>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="d-flex gap-2 align-items-start">
                    <svg xmlns="http://www.w3.org/2000/svg" class="text-primary flex-shrink-0 mt-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    <div>
                      <div class="fw-semibold" style="font-size:.88rem;">Receipt Generation</div>
                      <div class="text-muted" style="font-size:.8rem;">Print and issue official government receipts</div>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="d-flex gap-2 align-items-start">
                    <svg xmlns="http://www.w3.org/2000/svg" class="text-primary flex-shrink-0 mt-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    <div>
                      <div class="fw-semibold" style="font-size:.88rem;">Cashiering Integration</div>
                      <div class="text-muted" style="font-size:.8rem;">Transactions post automatically to the cashiering ledger</div>
                    </div>
                  </div>
                </div>
              </div>

              <a href="<?= url('views/portal/index.php') ?>" class="btn btn-outline-secondary">← Back to Portal</a>

            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

<?php require_once __DIR__ . '/../../includes/layout-tabler-footer.php'; ?>
