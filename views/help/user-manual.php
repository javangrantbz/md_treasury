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
          <div class="page-pretitle">Help &amp; Support</div>
          <h2 class="page-title">User Manual</h2>
        </div>
        <div class="col-auto">
          <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">← Back</a>
        </div>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">
      <div class="row g-4">

        <!-- Sidebar TOC -->
        <div class="col-lg-3">
          <div class="card sticky-top" style="top:1rem;">
            <div class="card-header py-2">
              <h4 class="card-title" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;color:var(--tblr-secondary);">Contents</h4>
            </div>
            <div class="list-group list-group-flush" style="font-size:.85rem;">
              <a href="#getting-started"   class="list-group-item list-group-item-action py-2">Getting Started</a>
              <a href="#portal"            class="list-group-item list-group-item-action py-2">Portal</a>
              <a href="#cashiering"        class="list-group-item list-group-item-action py-2">Cashiering Module</a>
              <a href="#master-data"       class="list-group-item list-group-item-action py-2">Master Data</a>
              <a href="#nsb"               class="list-group-item list-group-item-action py-2">National Savings Bank</a>
              <a href="#roles"             class="list-group-item list-group-item-action py-2">Roles &amp; Permissions</a>
              <a href="#profile"           class="list-group-item list-group-item-action py-2">Your Profile</a>
              <a href="#security"          class="list-group-item list-group-item-action py-2">Security &amp; Sessions</a>
            </div>
          </div>
        </div>

        <!-- Content -->
        <div class="col-lg-9">

          <!-- Getting Started -->
          <div class="card mb-4" id="getting-started">
            <div class="card-header" style="border-left:3px solid var(--tblr-primary);">
              <h3 class="card-title">Getting Started</h3>
            </div>
            <div class="card-body">
              <p>Treasury Revenue System is the Government of Belize's integrated financial management platform. It is accessible to authorised personnel only and all activity is monitored and audited.</p>
              <h4 class="mt-3 mb-2" style="font-size:1rem;">Signing In</h4>
              <ol class="mb-0">
                <li class="mb-1">Navigate to the Treasury Revenue System login page.</li>
                <li class="mb-1">Enter your <strong>government email address</strong> and <strong>password</strong>.</li>
                <li class="mb-1">If Multi-Factor Authentication (MFA) is enabled on your account, enter the verification code from your authenticator app.</li>
                <li>You will be redirected to the Portal upon successful authentication.</li>
              </ol>
              <div class="alert alert-info mt-3 mb-0 py-2" style="font-size:.85rem;">
                If you cannot sign in, contact the IT Helpdesk. Do not share your credentials with anyone.
              </div>
            </div>
          </div>

          <!-- Portal -->
          <div class="card mb-4" id="portal">
            <div class="card-header" style="border-left:3px solid var(--tblr-primary);">
              <h3 class="card-title">Portal</h3>
            </div>
            <div class="card-body">
              <p>The Portal is your central landing page after login. It provides access to all system modules based on your assigned role.</p>
              <ul class="mb-0">
                <li class="mb-1"><strong>Cashiering</strong> — Active. Click to enter the cashiering module.</li>
                <li class="mb-1"><strong>Pay-In/POS</strong> — Pending integration. Will be available in a future release.</li>
                <li><strong>National Savings Bank</strong> — Pending integration.</li>
              </ul>
            </div>
          </div>

          <!-- Cashiering -->
          <div class="card mb-4" id="cashiering">
            <div class="card-header" style="border-left:3px solid var(--tblr-primary);">
              <h3 class="card-title">Cashiering Module</h3>
            </div>
            <div class="card-body">
              <p>The Cashiering module handles receipts, transactions, expenses, and registers. The dashboard provides an overview of daily activity once the Pay-In module is integrated.</p>
              <p class="mb-0">From the dashboard you can navigate to all <strong>Master Data</strong> sections required to configure and operate the cashiering function.</p>
            </div>
          </div>

          <!-- Master Data -->
          <div class="card mb-4" id="master-data">
            <div class="card-header" style="border-left:3px solid var(--tblr-primary);">
              <h3 class="card-title">Master Data</h3>
            </div>
            <div class="card-body">
              <p>Master Data contains reference and configuration records used across the system. Each section supports adding, viewing, and editing records via in-page modals — no separate pages are required.</p>
              <div class="table-responsive">
                <table class="table table-sm table-vcenter">
                  <thead><tr><th>Section</th><th>Description</th></tr></thead>
                  <tbody>
                    <tr><td>Departments</td><td>Government departments and associated ministries</td></tr>
                    <tr><td>Sub-Treasuries</td><td>Sub-treasury offices across districts</td></tr>
                    <tr><td>Registers</td><td>Cash registers assigned to cashiers</td></tr>
                    <tr><td>Bank Accounts</td><td>Linked government bank accounts</td></tr>
                    <tr><td>Cost Centers</td><td>Budget cost center codes</td></tr>
                    <tr><td>Expenditure Types</td><td>Categories of government expenditure</td></tr>
                    <tr><td>Customers</td><td>Members of the public or entities transacting with Treasury</td></tr>
                    <tr><td>Suppliers</td><td>Vendors and service providers</td></tr>
                    <tr><td>Users</td><td>System user accounts</td></tr>
                    <tr><td>Role Permissions</td><td>Permission sets assigned per role</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- NSB -->
          <div class="card mb-4" id="nsb">
            <div class="card-header" style="border-left:3px solid var(--tblr-primary);">
              <h3 class="card-title">National Savings Bank</h3>
            </div>
            <div class="card-body">
              <p>The NSB module manages savings account operations including card applications, customer accounts, deposits, and withdrawals. Live data will be connected once the NSB database integration is complete.</p>
              <ul class="mb-0">
                <li class="mb-1"><strong>Applications</strong> — Track new, approved, shipped, and activated card applications.</li>
                <li class="mb-1"><strong>Customers</strong> — View and manage NSB account holders.</li>
                <li class="mb-1"><strong>Ledger</strong> — Deposit and withdrawal transaction records.</li>
                <li><strong>Cards</strong> — Card management and approval workflows.</li>
              </ul>
            </div>
          </div>

          <!-- Roles -->
          <div class="card mb-4" id="roles">
            <div class="card-header" style="border-left:3px solid var(--tblr-primary);">
              <h3 class="card-title">Roles &amp; Permissions</h3>
            </div>
            <div class="card-body">
              <p>Access to system features is governed by Role-Based Access Control (RBAC). Each user is assigned one role which determines what they can see and do within the system.</p>
              <div class="table-responsive">
                <table class="table table-sm table-vcenter">
                  <thead><tr><th>Role</th><th>Typical Access</th></tr></thead>
                  <tbody>
                    <tr><td>Super Admin</td><td>Full system access including all configuration</td></tr>
                    <tr><td>System Admin</td><td>Full access excluding certain super-admin functions</td></tr>
                    <tr><td>Treasury Finance Officer</td><td>Full cashiering and financial operations</td></tr>
                    <tr><td>Treasury Recon.</td><td>View and reporting access</td></tr>
                    <tr><td>Treasury Pay-In Clerk</td><td>Transaction creation and submission</td></tr>
                    <tr><td>Department Officer in Charge</td><td>Department-level approval and oversight</td></tr>
                    <tr><td>Department Cashier</td><td>Transaction entry and submission</td></tr>
                  </tbody>
                </table>
              </div>
              <p class="mb-0 mt-3 text-muted" style="font-size:.85rem;">Role permissions can be managed under <strong>Cashiering → Master Data → Role Permissions</strong> by authorised administrators.</p>
            </div>
          </div>

          <!-- Profile -->
          <div class="card mb-4" id="profile">
            <div class="card-header" style="border-left:3px solid var(--tblr-primary);">
              <h3 class="card-title">Your Profile</h3>
            </div>
            <div class="card-body">
              <p>Access your profile from the user dropdown in the top-right corner. From your profile you can:</p>
              <ul class="mb-0">
                <li class="mb-1">Update your <strong>first name</strong>, <strong>last name</strong>, and <strong>phone number</strong>.</li>
                <li class="mb-1">Change your <strong>password</strong> — your current password is required to set a new one.</li>
                <li>View your assigned <strong>role</strong>, <strong>department</strong>, and <strong>account status</strong> (read-only).</li>
              </ul>
            </div>
          </div>

          <!-- Security -->
          <div class="card mb-4" id="security">
            <div class="card-header" style="border-left:3px solid var(--tblr-primary);">
              <h3 class="card-title">Security &amp; Sessions</h3>
            </div>
            <div class="card-body">
              <ul class="mb-0">
                <li class="mb-1">Always <strong>sign out</strong> when leaving your workstation.</li>
                <li class="mb-1">Do not share your login credentials with anyone.</li>
                <li class="mb-1">Your last login time is displayed on the portal and in your profile — report any unrecognised access to IT immediately.</li>
                <li class="mb-1">Accounts are locked after repeated failed login attempts. Contact IT to unlock.</li>
                <li>All actions within the system are logged and subject to audit.</li>
              </ul>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

<?php require_once __DIR__ . '/../../includes/layout-tabler-footer.php'; ?>
