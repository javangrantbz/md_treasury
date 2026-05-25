<?php
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::requireAuth();

$authUser       = Auth::user();
$fullName       = Auth::fullName();
$roleName       = !empty($authUser['role_names']) ? $authUser['role_names'][0] : null;
$departmentName = $authUser['department_name'] ?? 'Treasury Department';
$lastLoginAt    = $authUser['last_login_at'] ?? null;
$lastLoginFmt   = $lastLoginAt ? date('F j, Y \a\t g:i A', strtotime($lastLoginAt)) : 'First session';

require_once __DIR__ . '/../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-body">
    <div class="container-xl">

      <!-- Identity + profile card -->
      <div class="card mb-4" style="border-left: 4px solid var(--tblr-primary);">
        <div class="card-body py-3">
          <div class="row align-items-center g-0">
            <div class="col-12 col-md-auto pe-md-4 d-flex align-items-center gap-3">
              <img src="<?= url('assets/img/nsb-logo.png') ?>" alt="NSB Logo" style="height:36px;width:auto;flex-shrink:0;">
              <div>
                <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.68rem;letter-spacing:.1em;">Government of Belize &middot; Treasury Department</div>
                <div class="fw-bold" style="font-size:1.05rem;line-height:1.2;">National Savings Bank</div>
              </div>
            </div>
            <div class="col-auto d-none d-md-flex px-4">
              <div style="width:1px;height:2.8rem;background:var(--tblr-border-color);"></div>
            </div>
            <div class="col d-flex align-items-center gap-3 mt-3 mt-md-0">
              <div class="avatar avatar-md bg-primary text-white fw-bold position-relative" style="font-size:1rem;flex-shrink:0;">
                <?php echo strtoupper(substr($authUser['first_name'] ?? 'U', 0, 1) . substr($authUser['last_name'] ?? '', 0, 1)); ?>
                <a href="<?php echo url('views/profile/index.php'); ?>" class="badge bg-white text-primary position-absolute bottom-0 end-0 p-1 border shadow-sm" style="transform:translate(25%,25%);" title="Update Profile">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-inline m-0" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                </a>
              </div>
              <div>
                <div class="fw-bold mb-0" style="font-size:.95rem;"><?php echo h($fullName); ?></div>
                <div class="text-muted" style="font-size:.82rem;">
                  <?php if ($roleName): ?><?php echo h($roleName); ?> &mdash; <?php endif; ?><?php echo h($departmentName); ?>
                </div>
                <div style="font-size:.74rem;color:var(--tblr-secondary);">
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1" style="vertical-align:-1px"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                  Last Login: <?php echo h($lastLoginFmt); ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Info alert -->
      <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-4" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" class="icon text-info flex-shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 9h.01M11 12h1v4h1"/></svg>
        <span style="font-size:.85rem;">Live data will populate once the NSB database integration is connected. Figures below are placeholders.</span>
      </div>

      <!-- Card Applications row -->
      <div class="row mb-2">
        <div class="col">
          <div class="text-uppercase fw-semibold text-muted mb-2" style="font-size:.72rem;letter-spacing:.08em;">Card Applications</div>
        </div>
      </div>
      <div class="row row-cards g-3 mb-4 row-cols-2 row-cols-md-3 row-cols-xl-5">

        <div class="col">
          <div class="card">
            <div class="card-body py-3">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <div class="text-muted" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;">Total Applications</div>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--tblr-primary)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              </div>
              <div class="display-6 fw-bold text-muted">—</div>
            </div>
          </div>
        </div>

        <div class="col">
          <div class="card">
            <div class="card-body py-3">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <div class="text-muted" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;">New Applications</div>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--tblr-azure)" stroke-width="2"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
              </div>
              <div class="display-6 fw-bold text-muted">—</div>
            </div>
          </div>
        </div>

        <div class="col">
          <div class="card">
            <div class="card-body py-3">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <div class="text-muted" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;">Approved</div>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--tblr-success)" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
              </div>
              <div class="display-6 fw-bold text-muted">—</div>
            </div>
          </div>
        </div>

        <div class="col">
          <div class="card">
            <div class="card-body py-3">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <div class="text-muted" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;">Shipped</div>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--tblr-cyan)" stroke-width="2"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 5v3h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
              </div>
              <div class="display-6 fw-bold text-muted">—</div>
            </div>
          </div>
        </div>

        <div class="col">
          <div class="card">
            <div class="card-body py-3">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <div class="text-muted" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;">Activated</div>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--tblr-green)" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              </div>
              <div class="display-6 fw-bold text-muted">—</div>
            </div>
          </div>
        </div>

      </div>

      <!-- Accounts & Ledger row -->
      <div class="row mb-2">
        <div class="col">
          <div class="text-uppercase fw-semibold text-muted mb-2" style="font-size:.72rem;letter-spacing:.08em;">Accounts &amp; Transactions</div>
        </div>
      </div>
      <div class="row row-cards g-3 mb-4">

        <div class="col-sm-6 col-lg-3">
          <div class="card">
            <div class="card-body py-3">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <div class="text-muted" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;">Active Accounts</div>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--tblr-primary)" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </div>
              <div class="display-6 fw-bold text-muted">—</div>
            </div>
          </div>
        </div>

        <div class="col-sm-6 col-lg-3">
          <div class="card">
            <div class="card-body py-3">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <div class="text-muted" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;">Total Deposits</div>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--tblr-success)" stroke-width="2"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
              </div>
              <div class="display-6 fw-bold text-muted">—</div>
            </div>
          </div>
        </div>

        <div class="col-sm-6 col-lg-3">
          <div class="card">
            <div class="card-body py-3">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <div class="text-muted" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;">Total Withdrawals</div>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--tblr-danger)" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
              </div>
              <div class="display-6 fw-bold text-muted">—</div>
            </div>
          </div>
        </div>

        <div class="col-sm-6 col-lg-3">
          <div class="card">
            <div class="card-body py-3">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <div class="text-muted" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;">Net Balance</div>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--tblr-primary)" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
              </div>
              <div class="display-6 fw-bold text-muted">—</div>
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
