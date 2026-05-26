<?php
$currentUri = strtok($_SERVER['REQUEST_URI'] ?? '', '?');

// Default Module Sections for NSB if on an NSB page and not already set
if (!isset($_pageModuleSections) && strpos($currentUri, '/views/nsb/') !== false) {
    $_pageModuleSections = [
        'title'    => 'NSB Operations',
        'subtitle' => 'National Savings Bank',
        'sections' => [
            'Applications' => [
                ['New Applications',      'views/nsb/applications/new.php'],
                ['Approved Applications', 'views/nsb/applications/approved.php'],
                ['Full Application List', 'views/nsb/applications/index.php'],
            ],
            'Customers' => [
                ['Customer List',    'views/nsb/customers/list.php'],
                ['Customer Profile', 'views/nsb/customers/profile.php'],
                ['Add Customer',     'views/nsb/customers/add.php'],
            ],
            'Ledger' => [
                ['Deposit Ledger',    'views/nsb/ledger/deposits.php'],
                ['Withdrawal Ledger', 'views/nsb/ledger/withdrawals.php'],
                ['Process Transaction', 'views/nsb/ledger/process.php'],
            ],
            'Cards' => [
                ['Card Overview',       'views/nsb/cards/overview.php'],
                ['Process Card Request', 'views/nsb/applications/process-card.php'],
                ['Approve Application', 'views/nsb/cards/approve.php'],
            ],
        ],
    ];
}

require_once __DIR__ . '/Rbac.php';

// Master Data item definitions: key => [label, path, permission]
$_mdItems = [
    'departments'            => ['Departments',            'views/cashiering/master-data/departments/index.php',           'master_data.departments.manage'],
    'sub_treasuries'         => ['Sub-Treasuries',         'views/cashiering/master-data/sub-treasuries/index.php',        'master_data.sub_treasuries.manage'],
    'registers'              => ['Registers',              'views/cashiering/master-data/registers/index.php',             'master_data.registers.manage'],
    'bank_accounts'          => ['Bank Accounts',          'views/cashiering/master-data/bank-accounts/index.php',         'master_data.bank_accounts.manage'],
    'cost_centers'           => ['Cost Centers',           'views/cashiering/master-data/cost-centers/index.php',          'master_data.cost_centers.manage'],
    'cost_center_activities' => ['Cost Center Activities', 'views/cashiering/master-data/cost-center-activities/index.php','master_data.cost_center_activities.manage'],
    'expenditure_types'      => ['Expenditure Types',      'views/cashiering/master-data/expenditure-types/index.php',     'master_data.expenditure_types.manage'],
    'expenses'               => ['Expenses',               'views/cashiering/expenses/index.php',                          'expenses.manage'],
    'customers'              => ['Customers',              'views/cashiering/master-data/customers/index.php',             'master_data.customers.manage'],
    'suppliers'              => ['Suppliers',              'views/cashiering/master-data/suppliers/index.php',             'master_data.suppliers.manage'],
    'users'                  => ['Users',                  'views/cashiering/master-data/users/index.php',                 'master_data.users.manage'],
    'roles'                  => ['Role Permissions',       'views/cashiering/master-data/role-permissions/index.php',      'master_data.roles.manage'],
    'audit_logs'             => ['Audit Log',              'views/cashiering/master-data/audit-logs/index.php',            'master_data.audit_logs.view'],
];
$_mdSectionDefs = [
    'Organisation'   => ['departments', 'sub_treasuries', 'registers'],
    'Finance'        => ['bank_accounts', 'cost_centers', 'cost_center_activities', 'expenditure_types', 'expenses'],
    'Parties'        => ['customers', 'suppliers'],
    'Administration' => ['users', 'roles', 'audit_logs'],
];
// Build only sections/items the current user can access
$_mdVisibleSections = [];
foreach ($_mdSectionDefs as $_sec => $_keys) {
    $_visible = array_values(array_filter($_keys, fn($k) => Rbac::has($pdo, $_mdItems[$k][2])));
    if ($_visible) {
        $_mdVisibleSections[$_sec] = $_visible;
    }
}
$_mdSectionCount  = count($_mdVisibleSections);
$_mdAnyVisible    = $_mdSectionCount > 0;
if ($_mdSectionCount === 1)      { $_mdColClass = 'col-12'; $_mdDropdownWidth = '240px'; }
elseif ($_mdSectionCount === 2) { $_mdColClass = 'col-6';  $_mdDropdownWidth = '380px'; }
elseif ($_mdSectionCount === 3) { $_mdColClass = 'col-4';  $_mdDropdownWidth = '470px'; }
else                            { $_mdColClass = 'col-3';  $_mdDropdownWidth = '600px'; }

function tablerNavLink(string $href, string $label, string $icon, string $currentUri): string {
    $hrefPath = parse_url($href, PHP_URL_PATH);
    $active = false;

    if ($currentUri === $hrefPath) {
        $active = true;
    } else {
        $dir = dirname($hrefPath);
        if ($dir !== '/' && $dir !== '\\' && strpos($currentUri, $dir) === 0) {
            $active = true;
        }
    }

    $activeClass = $active ? ' active' : '';
    return sprintf(
        '<li class="nav-item"><a class="nav-link%s" href="%s">%s<span class="nav-link-title" style="margin-left:.5rem">%s</span></a></li>',
        $activeClass, $href, $icon, $label
    );
}

$iconDashboard = '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1"/><path d="M5 16h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1"/><path d="M15 12h4a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1"/><path d="M15 4h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1"/></svg>';
$iconUsers    = '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 7m-4 0a4 4 0 1 0 8 0a4 4 0 1 0-8 0"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M21 21v-2a4 4 0 0 0-3-3.85"/></svg>';
$iconLogout   = '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 8v-2a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-2"/><path d="M9 12h12l-3-3"/><path d="M18 15l3-3"/></svg>';
$iconPortal   = '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0-18 0"/><path d="M3.6 9h16.8"/><path d="M3.6 15h16.8"/><path d="M11.5 3a17 17 0 0 0 0 18"/><path d="M12.5 3a17 17 0 0 1 0 18"/></svg>';
$iconPayIn    = '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3m0 2a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M3 10v6a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6"/><path d="M7 15h.01"/><path d="M11 15h2"/></svg>';
$iconNsb      = '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21l18 0"/><path d="M3 10l18 0"/><path d="M5 6l7-3l7 3"/><path d="M4 10l0 11"/><path d="M20 10l0 11"/><path d="M8 14l0 3"/><path d="M12 14l0 3"/><path d="M16 14l0 3"/></svg>';
?>
<style>
  /* Ensure active sidebar items are clearly visible with a shade */
  .navbar-vertical .navbar-nav .nav-link.active {
    background: rgba(255, 255, 255, 0.08) !important;
    color: #fff !important;
    border-radius: 4px;
    font-weight: 600;
  }
  .navbar-vertical .navbar-nav .nav-link.active .icon {
    color: var(--tblr-primary) !important;
  }
</style>
<aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu" aria-controls="navbar-menu" aria-expanded="false">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Government identity header -->
        <a href="<?php echo url('views/portal/index.php'); ?>" class="navbar-brand d-flex align-items-center gap-2 py-3 text-decoration-none" style="white-space:normal">
            <img src="<?php echo url('assets/img/coat-of-arms.png'); ?>"
                 alt="Coat of Arms of Belize"
                 style="height:52px;width:auto;flex-shrink:0;filter:drop-shadow(0 1px 2px rgba(0,0,0,.5))">
            <div style="line-height:1.2">
                <div style="font-size:.65rem;font-weight:500;letter-spacing:.08em;text-transform:uppercase;opacity:.7">
                    Government of Belize
                </div>
                <div style="font-size:.95rem;font-weight:700;letter-spacing:.01em;">
                    Treasury Department
                </div>
                <div style="font-size:.7rem;font-weight:400;opacity:.6;letter-spacing:.04em;text-transform:uppercase;">
                    Treasury Revenue System
                </div>
            </div>
        </a>

        <div class="collapse navbar-collapse" id="navbar-menu">
            <ul class="navbar-nav pt-lg-2">
                <?php echo tablerNavLink(url('views/portal/index.php'), 'Portal', $iconPortal, $currentUri); ?>
                <?php echo tablerNavLink(url('views/cashiering/dashboard.php'), 'Cashiering', $iconDashboard, $currentUri); ?>
                <?php echo tablerNavLink(url('views/pay-in/index.php'), 'Pay-In/POS Reporting', $iconPayIn, $currentUri); ?>
                <?php echo tablerNavLink(url('views/nsb/dashboard.php'), 'National Savings Bank', $iconNsb, $currentUri); ?>
            </ul>
            <div class="mt-auto pt-3 border-top">
                <ul class="navbar-nav">
                    <?php echo tablerNavLink(url('api/auth/logout.php'), 'Logout', $iconLogout, $currentUri); ?>
                </ul>
            </div>
        </div>
    </div>
</aside>

<?php
$_topUser       = Auth::user();
$_topFullName   = trim(($_topUser['first_name'] ?? '') . ' ' . ($_topUser['last_name'] ?? ''));
$_topInitials   = strtoupper(substr($_topUser['first_name'] ?? 'U', 0, 1) . substr($_topUser['last_name'] ?? '', 0, 1));
$_topRole       = !empty($_topUser['role_names']) ? $_topUser['role_names'][0] : null;
$_topDept       = $_topUser['department_name'] ?? 'Treasury Department';
$_topLastLogin  = $_topUser['last_login_at'] ?? null;
$_topLastFmt    = $_topLastLogin ? date('d M Y, g:i A', strtotime($_topLastLogin)) : 'First session';
$_topSession    = ($_topUser['user_type'] ?? 'internal') === 'internal' ? 'Internal' : 'External';
?>
<div class="page-wrapper">
  <!-- Top navbar -->
  <header class="navbar navbar-expand-md d-print-none sticky-top" style="border-bottom:2px solid #c8d3e0;box-shadow:0 2px 6px rgba(0,0,0,.08);background:#fff;">
    <div class="container-xl">

      <!-- Left: breadcrumb context placeholder (filled per-page via data attr or empty) -->
      <div class="d-none d-md-flex align-items-center gap-2 text-muted" style="font-size:.8rem;">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        <?php echo date('l, d F Y'); ?>
        <span class="mx-1 text-muted">|</span>
        <span style="display:inline-flex;align-items:center;gap:4px;font-size:.72rem;font-weight:600;letter-spacing:.03em;padding:2px 8px;border-radius:4px;
              background:<?php echo $_topSession === 'Internal' ? 'rgba(32,107,196,.12)' : 'rgba(214,99,14,.12)'; ?>;
              color:<?php echo $_topSession === 'Internal' ? '#114f7a' : '#8f4500'; ?>;
              border:1px solid <?php echo $_topSession === 'Internal' ? 'rgba(32,107,196,.25)' : 'rgba(214,99,14,.25)'; ?>;">
          <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Secure: <?php echo $_topSession; ?> Network
        </span>
      </div>

      <div class="navbar-nav flex-row ms-auto align-items-center gap-1">

        <!-- Module Sections (page-defined) OR Master Data (cashiering, permission-aware) -->
        <?php if (isset($_pageModuleSections)): ?>
        <?php
            $_pmsSections     = $_pageModuleSections['sections'];
            $_pmsSectionCount = count($_pmsSections);
            $_pmsTitle        = $_pageModuleSections['title']    ?? 'Module Sections';
            $_pmsSubtitle     = $_pageModuleSections['subtitle'] ?? '';
            if ($_pmsSectionCount <= 1)      { $_pmsColClass = 'col-12'; $_pmsWidth = '260px'; }
            elseif ($_pmsSectionCount === 2) { $_pmsColClass = 'col-6';  $_pmsWidth = '420px'; }
            elseif ($_pmsSectionCount === 3) { $_pmsColClass = 'col-4';  $_pmsWidth = '540px'; }
            else                             { $_pmsColClass = 'col-3';  $_pmsWidth = '720px'; }
        ?>
        <div class="nav-item dropdown">
          <a href="#" class="nav-link d-flex align-items-center gap-1 px-2" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="<?php echo h($_pmsTitle); ?>" aria-label="<?php echo h($_pmsTitle); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            <span class="d-none d-lg-inline" style="font-size:.82rem;font-weight:500;"><?php echo h($_pmsTitle); ?></span>
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="d-none d-lg-inline text-muted" style="margin-left:2px;"><path d="M6 9l6 6l6-6"/></svg>
          </a>
          <div class="dropdown-menu dropdown-menu-end" style="min-width:<?php echo $_pmsWidth; ?>;padding:0;border-radius:6px;overflow:hidden;">
            <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom bg-light">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon text-primary" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
              <strong style="font-size:.85rem;"><?php echo h($_pmsTitle); ?></strong>
              <?php if ($_pmsSubtitle): ?>
              <span class="text-muted ms-1" style="font-size:.77rem;"><?php echo h($_pmsSubtitle); ?></span>
              <?php endif; ?>
            </div>
            <div class="row g-0 p-2">
              <?php $_pmsIdx = 0; foreach ($_pmsSections as $_pmsHeading => $_pmsItems): $_pmsIdx++; ?>
              <div class="<?php echo $_pmsColClass; ?><?php echo $_pmsIdx < $_pmsSectionCount ? ' border-end' : ''; ?>">
                <div class="px-2 pt-2 pb-1">
                  <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size:.67rem;letter-spacing:.08em;"><?php echo h($_pmsHeading); ?></div>
                  <?php foreach ($_pmsItems as $_pmsItem): $_pmsLbl = $_pmsItem[0]; $_pmsPath = $_pmsItem[1]; ?>
                  <a class="dropdown-item rounded-1 py-1 px-2" style="font-size:.84rem;" href="<?php echo $_pmsPath === '#' ? '#' : url($_pmsPath); ?>"><?php echo h($_pmsLbl); ?></a>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php elseif ($_mdAnyVisible): ?>
        <div class="nav-item dropdown">
          <a href="#" class="nav-link d-flex align-items-center gap-1 px-2" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Master Data" aria-label="Master Data">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/></svg>
            <span class="d-none d-lg-inline" style="font-size:.82rem;font-weight:500;">Master Data</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="d-none d-lg-inline text-muted" style="margin-left:2px;"><path d="M6 9l6 6l6-6"/></svg>
          </a>
          <div class="dropdown-menu dropdown-menu-end" style="min-width:<?php echo $_mdDropdownWidth; ?>;padding:0;border-radius:6px;overflow:hidden;">
            <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom bg-light">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon text-primary" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/></svg>
              <strong style="font-size:.85rem;">Master Data</strong>
              <span class="text-muted ms-1" style="font-size:.77rem;">Reference &amp; configuration records</span>
            </div>
            <div class="row g-0 p-2">
              <?php $_secIdx = 0; foreach ($_mdVisibleSections as $_secHeading => $_secKeys): $_secIdx++; ?>
              <div class="<?php echo $_mdColClass; ?><?php echo $_secIdx < $_mdSectionCount ? ' border-end' : ''; ?>">
                <div class="px-2 pt-2 pb-1">
                  <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size:.67rem;letter-spacing:.08em;"><?php echo $_secHeading; ?></div>
                  <?php foreach ($_secKeys as $_k): $_lbl = $_mdItems[$_k][0]; $_path = $_mdItems[$_k][1]; ?>
                  <a class="dropdown-item rounded-1 py-1 px-2" style="font-size:.84rem;" href="<?php echo url($_path); ?>"><?php echo $_lbl; ?></a>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Notepad -->
        <div class="nav-item dropdown me-2">
          <a href="#" class="nav-link px-2 d-flex align-items-center" data-bs-toggle="dropdown" title="My Notepad" aria-label="Notepad" id="notepad-toggle">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 19c3.333 -2 5 -4 5 -6c0 -3 -1 -3 -2 -3s-2.032 1.085 -2 3c.034 2.048 1.658 2.877 2.5 4c1.5 2 2.5 2.5 4.5 1" /><path d="M5 7h4" /><path d="M5 10h1" /><path d="M10 13l4 -4l4 4" /><path d="M10 17l9 0" /><path d="M10 9l9 0" /><path d="M14 20l5 0" /><path d="M14 5l5 0" /></svg>
            <span class="badge bg-red-lt ms-auto" id="note-count" style="display:none">0</span>
          </a>
          <div class="dropdown-menu dropdown-menu-end" style="width: 320px; max-height: 500px; overflow: hidden;">
            <div class="dropdown-header d-flex justify-content-between align-items-center">
              <span>My Personal Notes</span>
              <button class="btn btn-sm btn-ghost-primary" id="add-note-btn" title="Add new note">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
              </button>
            </div>
            <div id="note-editor" style="display:none" class="p-2 border-bottom bg-light">
              <textarea id="note-textarea" class="form-control mb-2" rows="3" placeholder="Write something..."></textarea>
              <div class="d-flex justify-content-end gap-2">
                <button class="btn btn-sm btn-link link-secondary" id="cancel-note-btn">Cancel</button>
                <button class="btn btn-sm btn-primary" id="save-note-btn">Save Note</button>
              </div>
            </div>
            <div class="list-group list-group-flush overflow-auto flex-fill" id="notes-list" style="max-height: 350px;">
              <div class="p-4 text-center text-muted small">Loading notes...</div>
            </div>
          </div>
        </div>

        <!-- Notifications -->
        <div class="nav-item dropdown">
          <a href="#" class="nav-link px-2 d-flex align-items-center" data-bs-toggle="dropdown" title="Notifications" aria-label="Notifications">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3H4a4 4 0 0 0 2-3v-3a7 7 0 0 1 4-6"/><path d="M9 17v1a3 3 0 0 0 6 0v-1"/></svg>
          </a>
          <div class="dropdown-menu dropdown-menu-end" style="min-width:280px">
            <div class="dropdown-header fw-semibold">Notifications</div>
            <div class="px-3 py-4 text-center text-muted" style="font-size:.85rem;">No new notifications</div>
          </div>
        </div>

        <!-- Help -->
        <div class="nav-item dropdown">
          <a href="#" class="nav-link px-2 d-flex align-items-center" data-bs-toggle="dropdown" title="Help &amp; Support" aria-label="Help">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 17v.01"/><path d="M12 13.5a1.5 1.5 0 0 1 1-1.5a2.6 2.6 0 1 0-3-2.5"/></svg>
          </a>
          <div class="dropdown-menu dropdown-menu-end" style="min-width:220px">
            <div class="dropdown-header fw-semibold">Help &amp; Support</div>
            <a class="dropdown-item" href="<?php echo url('views/help/user-manual.php'); ?>">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              User Manual
            </a>
            <a class="dropdown-item" href="<?php echo url('views/help/it-support.php'); ?>">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              Contact IT Support
            </a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="<?php echo url('views/help/my-tickets.php'); ?>">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/><path d="M9 12h6"/><path d="M9 16h4"/></svg>
              My Support Tickets
            </a>
          </div>
        </div>

        <!-- User dropdown -->
        <div class="nav-item dropdown ms-1">
          <a href="#" class="nav-link d-flex align-items-center gap-2 px-2" data-bs-toggle="dropdown">
            <div class="avatar avatar-sm bg-primary text-white fw-bold" style="font-size:.8rem;width:2rem;height:2rem;line-height:2rem;text-align:center;border-radius:50%;flex-shrink:0;">
              <?php echo h($_topInitials); ?>
            </div>
            <span class="d-none d-lg-inline" style="font-size:.85rem;font-weight:500;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
              <?php echo h($_topFullName); ?>
            </span>
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="d-none d-lg-inline text-muted"><path d="M6 9l6 6l6-6"/></svg>
          </a>
          <div class="dropdown-menu dropdown-menu-end" style="min-width:240px">
            <!-- Identity block -->
            <div class="px-3 py-2 border-bottom">
              <div class="fw-semibold" style="font-size:.9rem;"><?php echo h($_topFullName); ?></div>
              <?php if ($_topRole): ?>
                <div class="text-muted" style="font-size:.78rem;"><?php echo h($_topRole); ?></div>
              <?php endif; ?>
              <div class="text-muted" style="font-size:.75rem;"><?php echo h($_topDept); ?></div>
            </div>
            <!-- Session info -->
            <div class="px-3 py-1 border-bottom" style="font-size:.72rem;color:var(--tblr-secondary);">
              <div>Last login: <?php echo h($_topLastFmt); ?></div>
            </div>
            <!-- Actions -->
            <a class="dropdown-item mt-1" href="<?php echo url('views/profile/index.php'); ?>">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0-18 0"/><path d="M12 10m-3 0a3 3 0 1 0 6 0a3 3 0 1 0-6 0"/><path d="M6.168 18.849a4 4 0 0 1 3.832-2.849h4a4 4 0 0 1 3.834 2.855"/></svg>
              My Profile
            </a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item text-danger" href="<?php echo url('api/auth/logout.php'); ?>">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 8v-2a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-2"/><path d="M9 12h12l-3-3m0 6l3-3"/></svg>
              Sign Out
            </a>
          </div>
        </div>

      </div>
    </div>
  </header>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const listEl = document.getElementById('notes-list');
    const toggleEl = document.getElementById('notepad-toggle');
    const editorEl = document.getElementById('note-editor');
    const textareaEl = document.getElementById('note-textarea');
    const saveBtn = document.getElementById('save-note-btn');
    const addBtn = document.getElementById('add-note-btn');
    const cancelBtn = document.getElementById('cancel-note-btn');
    const countBadge = document.getElementById('note-count');

    let notes = [];
    let editingId = null;

    async function fetchNotes() {
        try {
            const res = await apiGet("<?= abs_url('api/profile/notes/list.php') ?>");
            notes = res.data || [];
            renderNotes();
        } catch (e) {
            console.error('Failed to load notes:', e);
            listEl.innerHTML = '<div class="p-3 text-center text-danger small">Error loading notes</div>';
        }
    }

    function renderNotes() {
        countBadge.textContent = notes.length;
        countBadge.style.display = notes.length > 0 ? 'inline-block' : 'none';

        if (notes.length === 0) {
            listEl.innerHTML = '<div class="p-4 text-center text-muted small">No notes yet. Start writing!</div>';
            return;
        }

        listEl.innerHTML = notes.map(n => `
            <div class="list-group-item" data-note-id="${n.id}">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="text-wrap pe-2" style="font-size: .82rem; white-space: pre-wrap;">${escapeHtml(n.content)}</div>
                    <div class="d-flex gap-1 flex-shrink-0">
                        <button class="btn btn-ghost-secondary btn-icon btn-sm edit-note-btn" title="Edit">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-xs" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 20h4l10.5 -10.5a2.828 2.828 0 1 0 -4 -4l-10.5 10.5v4" /><path d="M13.5 6.5l4 4" /></svg>
                        </button>
                        <button class="btn btn-ghost-danger btn-icon btn-sm delete-note-btn" title="Delete">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-xs" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                        </button>
                    </div>
                </div>
                <div class="text-muted" style="font-size: .65rem; margin-top: 4px;">${new Date(n.created_at).toLocaleString()}</div>
            </div>
        `).join('');

        // Wire buttons
        listEl.querySelectorAll('.edit-note-btn').forEach(btn => {
            btn.onclick = (e) => {
                e.stopPropagation();
                const id = parseInt(e.target.closest('[data-note-id]').dataset.noteId);
                const note = notes.find(n => n.id === id);
                if (note) startEdit(note);
            };
        });

        listEl.querySelectorAll('.delete-note-btn').forEach(btn => {
            btn.onclick = async (e) => {
                e.stopPropagation();
                const id = parseInt(e.target.closest('[data-note-id]').dataset.noteId);
                if (confirm('Delete this note?')) {
                    try {
                        await apiPost("<?= abs_url('api/profile/notes/delete.php') ?>", { id });
                        fetchNotes();
                    } catch (err) { alert(err.message); }
                }
            };
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function startEdit(note = null) {
        editingId = note ? note.id : null;
        textareaEl.value = note ? note.content : '';
        editorEl.style.display = 'block';
        textareaEl.focus();
    }

    function closeEditor() {
        editingId = null;
        textareaEl.value = '';
        editorEl.style.display = 'none';
    }

    addBtn.onclick = (e) => { e.stopPropagation(); startEdit(); };
    cancelBtn.onclick = (e) => { e.stopPropagation(); closeEditor(); };

    saveBtn.onclick = async (e) => {
        e.stopPropagation();
        const content = textareaEl.value.trim();
        if (!content) return;

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
            if (editingId) {
                await apiPost("<?= abs_url('api/profile/notes/update.php') ?>", { id: editingId, content });
            } else {
                await apiPost("<?= abs_url('api/profile/notes/create.php') ?>", { content });
            }
            closeEditor();
            fetchNotes();
        } catch (err) {
            alert(err.message);
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Note';
        }
    };

    // Load notes when dropdown is opened
    toggleEl.addEventListener('show.bs.dropdown', fetchNotes);
    
    // Prevent dropdown from closing when clicking inside
    document.querySelector('#notepad-toggle + .dropdown-menu').addEventListener('click', (e) => {
        e.stopPropagation();
    });

    // Initial fetch to show count
    fetchNotes();
});
</script>
