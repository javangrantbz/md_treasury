<?php
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::requireAuth();

require_once __DIR__ . '/../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-body">
    <div class="container-xl">

      <!-- Page identity card -->
      <div class="card mb-4" style="border-left: 4px solid var(--tblr-primary);">
        <div class="card-body py-3">
          <div class="d-flex align-items-center gap-3">
            <img src="<?= url('assets/img/nsb-logo.png') ?>" alt="NSB Logo" style="height:32px;width:auto;flex-shrink:0;">
            <div>
              <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.68rem;letter-spacing:.1em;">Government of Belize &middot; Treasury Department</div>
              <div class="fw-bold" style="font-size:1.05rem;line-height:1.2;">National Savings Bank</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Info alert -->
      <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-4" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" class="icon text-info flex-shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 9h.01M11 12h1v4h1"/></svg>
        <span style="font-size:.85rem;">Live data will populate once the NSB database integration is connected. Figures below are placeholders.</span>
      </div>

      <!-- Combined stats row -->
      <div class="card mb-4">
        <div class="d-flex flex-wrap" style="overflow-x:auto;">

          <!-- Group: Card Applications -->
          <div class="d-flex flex-column flex-shrink-0" style="min-width:0;">
            <div class="px-3 pt-2 pb-1 text-uppercase fw-semibold text-muted" style="font-size:.63rem;letter-spacing:.08em;white-space:nowrap;">Card Applications</div>
            <div class="d-flex">
              <div class="px-3 pb-3 pt-1 border-end">
                <div class="text-muted mb-1" style="font-size:.7rem;white-space:nowrap;">New</div>
                <div class="fw-bold text-azure" style="font-size:1.35rem;line-height:1;">—</div>
              </div>
              <div class="px-3 pb-3 pt-1 border-end">
                <div class="text-muted mb-1" style="font-size:.7rem;white-space:nowrap;">Approved</div>
                <div class="fw-bold text-success" style="font-size:1.35rem;line-height:1;">—</div>
              </div>
              <div class="px-3 pb-3 pt-1 border-end">
                <div class="text-muted mb-1" style="font-size:.7rem;white-space:nowrap;">Shipped</div>
                <div class="fw-bold text-cyan" style="font-size:1.35rem;line-height:1;">—</div>
              </div>
              <div class="px-3 pb-3 pt-1 border-end">
                <div class="text-muted mb-1" style="font-size:.7rem;white-space:nowrap;">Activated</div>
                <div class="fw-bold text-green" style="font-size:1.35rem;line-height:1;">—</div>
              </div>
            </div>
          </div>

          <!-- Divider -->
          <div class="border-start my-3"></div>

          <!-- Group: Accounts & Transactions -->
          <div class="d-flex flex-column flex-shrink-0" style="min-width:0;">
            <div class="px-3 pt-2 pb-1 text-uppercase fw-semibold text-muted" style="font-size:.63rem;letter-spacing:.08em;white-space:nowrap;">Accounts &amp; Transactions</div>
            <div class="d-flex">
              <div class="px-3 pb-3 pt-1 border-end">
                <div class="text-muted mb-1" style="font-size:.7rem;white-space:nowrap;">Active Accounts</div>
                <div class="fw-bold" style="font-size:1.35rem;line-height:1;">—</div>
              </div>
              <div class="px-3 pb-3 pt-1 border-end">
                <div class="text-muted mb-1" style="font-size:.7rem;white-space:nowrap;">Deposits</div>
                <div class="fw-bold text-success" style="font-size:1.35rem;line-height:1;">—</div>
              </div>
              <div class="px-3 pb-3 pt-1 border-end">
                <div class="text-muted mb-1" style="font-size:.7rem;white-space:nowrap;">Withdrawals</div>
                <div class="fw-bold text-danger" style="font-size:1.35rem;line-height:1;">—</div>
              </div>
              <div class="px-3 pb-3 pt-1">
                <div class="text-muted mb-1" style="font-size:.7rem;white-space:nowrap;">Net Balance</div>
                <div class="fw-bold text-primary" style="font-size:1.35rem;line-height:1;">—</div>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Quick access -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Module Sections</h3>
          <div class="card-options">
            <span class="text-muted" style="font-size:.8rem;">National Savings Bank operations</span>
          </div>
        </div>
        <div class="card-body p-2">
          <div class="row g-0">

            <div class="col-12 col-md-6 col-lg-3 border-end-md">
              <div class="px-3 pt-3 pb-1">
                <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size:.72rem;letter-spacing:.08em;">Applications</div>
                <?php foreach ([
                  ['New Applications',      url('views/nsb/applications/new.php')],
                  ['Approved Applications', url('views/nsb/applications/approved.php')],
                  ['Process Card Request',  url('views/nsb/applications/process-card.php')],
                  ['Full Application List', url('views/nsb/applications/index.php')],
                ] as [$label, $path]): ?>
                <a href="<?= $path ?>" class="d-flex align-items-center gap-2 py-2 text-decoration-none text-body border-bottom" style="font-size:.88rem;">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm text-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6l-6 6"/></svg>
                  <?= h($label) ?>
                </a>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="col-12 col-md-6 col-lg-3 border-end-md">
              <div class="px-3 pt-3 pb-1">
                <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size:.72rem;letter-spacing:.08em;">Customers</div>
                <?php foreach ([
                  ['Customer List',    url('views/nsb/customers/list.php')],
                  ['Customer Profile', url('views/nsb/customers/profile.php')],
                  ['Add Customer',      url('views/nsb/customers/add.php')],
                ] as [$label, $path]): ?>
                <a href="<?= $path ?>" class="d-flex align-items-center gap-2 py-2 text-decoration-none text-body border-bottom" style="font-size:.88rem;">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm text-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6l-6 6"/></svg>
                  <?= h($label) ?>
                </a>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="col-12 col-md-6 col-lg-3 border-end-md">
              <div class="px-3 pt-3 pb-1">
                <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size:.72rem;letter-spacing:.08em;">Ledger</div>
                <?php foreach ([
                  ['Deposit Ledger',    url('views/nsb/ledger/deposits.php')],
                  ['Withdrawal Ledger', url('views/nsb/ledger/withdrawals.php')],
                  ['Process Transaction', url('views/nsb/ledger/process.php')],
                ] as [$label, $path]): ?>
                <a href="<?= $path ?>" class="d-flex align-items-center gap-2 py-2 text-decoration-none text-body border-bottom" style="font-size:.88rem;">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm text-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6l-6 6"/></svg>
                  <?= h($label) ?>
                </a>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="col-12 col-md-6 col-lg-3">
              <div class="px-3 pt-3 pb-1">
                <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size:.72rem;letter-spacing:.08em;">Cards</div>
                <?php foreach ([
                  ['Card Overview',       url('views/nsb/cards/overview.php')],
                  ['Approve Application', url('views/nsb/cards/approve.php')],
                ] as [$label, $path]): ?>
                <a href="<?= $path ?>" class="d-flex align-items-center gap-2 py-2 text-decoration-none text-body border-bottom" style="font-size:.88rem;">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm text-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6l-6 6"/></svg>
                  <?= h($label) ?>
                </a>
                <?php endforeach; ?>
              </div>
            </div>

          </div>
        </div>
      </div>

    </div>
  </div>

<?php require_once __DIR__ . '/../../includes/layout-tabler-footer.php'; ?>
