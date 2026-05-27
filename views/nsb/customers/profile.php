<?php
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../config/database.php';
Auth::requireAuth();

$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . url('views/nsb/customers/list.php'));
    exit;
}

// ── User + all joins ──────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT
        u.id, u.uuid, u.username, u.email, u.phone,
        u.first_name, u.last_name,
        u.user_type, u.auth_source, u.mfa_enabled,
        u.status, u.failed_login_count, u.locked_until,
        u.last_login_at, u.email_verified_at, u.created_at,
        u.department_id, u.branch_id, u.sub_treasury_id,
        rl.name AS role_name,
        rl.code AS role_code,
        b.name         AS branch_name,
        b.code         AS branch_code,
        b.district     AS branch_district,
        b.address_line AS branch_address,
        d.name         AS dept_name,
        d.code         AS dept_code,
        d.ministry_name,
        st.sub_treasury_name,
        st.sub_treasury_code,
        st.district      AS st_district,
        st.address_line  AS st_address,
        st.contact_phone AS st_phone,
        st.contact_email AS st_email,
        CONCAT(cb.first_name, ' ', cb.last_name) AS created_by_name
    FROM users u
    LEFT JOIN user_roles ur   ON ur.user_id = u.id
    LEFT JOIN roles rl        ON rl.id      = ur.role_id
    LEFT JOIN branches b      ON b.id       = u.branch_id
    LEFT JOIN departments d   ON d.id       = u.department_id
    LEFT JOIN sub_treasuries st ON st.id    = u.sub_treasury_id
    LEFT JOIN users cb        ON cb.id      = u.created_by
    WHERE u.id = :id AND u.deleted_at IS NULL
    LIMIT 1
");
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    require_once __DIR__ . '/../../../includes/layout-tabler-header.php';
    require_once __DIR__ . '/../../../includes/layout-tabler-sidebar.php';
    echo '<div class="page-body"><div class="container-xl"><div class="alert alert-danger mt-4">Customer #' . $userId . ' not found.</div></div></div>';
    require_once __DIR__ . '/../../../includes/layout-tabler-footer.php';
    exit;
}

// ── NSB applications submitted by this user ──────────────────────────────
$stmtApps = $pdo->prepare("
    SELECT a.id, a.customer_name, a.nic, a.account_number,
           a.card_type, a.status, a.created_at,
           b.name AS branch_name, b.code AS branch_code
    FROM nsb_applications a
    LEFT JOIN branches b ON b.id = a.branch_id
    WHERE a.created_by = :uid
    ORDER BY a.created_at DESC
    LIMIT 25
");
$stmtApps->execute(['uid' => $userId]);
$applications = $stmtApps->fetchAll(PDO::FETCH_ASSOC);

// ── Audit logs ────────────────────────────────────────────────────────────
$stmtLogs = $pdo->prepare("
    SELECT id, event_type, event_action, entity_type,
           status, ip_address, description, created_at
    FROM audit_logs
    WHERE user_id = :uid
    ORDER BY created_at DESC
    LIMIT 10
");
$stmtLogs->execute(['uid' => $userId]);
$auditLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

// ── Helpers ───────────────────────────────────────────────────────────────
function profileDate($dt, $fmt = 'M j, Y') {
    if (!$dt) return '—';
    return date($fmt, strtotime($dt));
}
function profileDateTime($dt) {
    if (!$dt) return '—';
    return date('M j, Y g:i A', strtotime($dt));
}
function profileVal($v, $fallback = '—') {
    return (isset($v) && $v !== '' && $v !== null) ? h($v) : $fallback;
}

$initials = strtoupper(
    substr($user['first_name'] ?? '', 0, 1) . substr($user['last_name'] ?? '', 0, 1)
);
$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

$statusColors = [
    'active'   => ['badge' => 'bg-success-lt text-success',    'dot' => '#2fb344', 'ring' => '#2fb344'],
    'inactive' => ['badge' => 'bg-secondary-lt text-secondary', 'dot' => '#adb5bd', 'ring' => '#adb5bd'],
    'locked'   => ['badge' => 'bg-danger-lt text-danger',       'dot' => '#d63939', 'ring' => '#d63939'],
    'pending'  => ['badge' => 'bg-warning-lt text-warning',     'dot' => '#f59f00', 'ring' => '#f59f00'],
];
$sc = isset($statusColors[$user['status']]) ? $statusColors[$user['status']] : $statusColors['inactive'];

$appStatusCls = [
    'pending'    => 'bg-yellow-lt text-yellow',
    'processing' => 'bg-blue-lt text-blue',
    'approved'   => 'bg-success-lt text-success',
    'rejected'   => 'bg-danger-lt text-danger',
    'printed'    => 'bg-azure-lt text-azure',
    'delivered'  => 'bg-teal-lt text-teal',
    'void'       => 'bg-secondary-lt text-secondary',
];
$logStatusCls = [
    'success' => 'bg-success-lt text-success',
    'failure' => 'bg-danger-lt text-danger',
    'warning' => 'bg-warning-lt text-warning',
    'info'    => 'bg-blue-lt text-blue',
];
$cardTypeCls = [
    'debit'  => 'bg-primary-lt text-primary',
    'atm'    => 'bg-azure-lt text-azure',
    'credit' => 'bg-warning-lt text-warning',
];

require_once __DIR__ . '/../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../includes/layout-tabler-sidebar.php';
?>

<div class="page-body">
  <div class="container-xl">

    <!-- ── HEADER CARD ─────────────────────────────────────────────── -->
    <div class="card mb-4" style="border-left:4px solid var(--tblr-primary);">
      <div class="card-body py-3 px-4">
        <div style="display:flex;align-items:center;flex-wrap:nowrap;gap:0;overflow-x:auto;">

          <!-- Avatar -->
          <div style="flex-shrink:0;padding-right:1rem;">
            <div style="width:52px;height:52px;border-radius:50%;background:var(--tblr-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:800;letter-spacing:.02em;border:3px solid <?= $sc['ring'] ?>;">
              <?= h($initials) ?>
            </div>
          </div>

          <!-- Name + meta -->
          <div style="flex-shrink:0;padding-right:.75rem;">
            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
              <span style="font-size:1.15rem;font-weight:800;line-height:1.2;white-space:nowrap;"><?= h($fullName) ?></span>
              <span class="badge <?= $sc['badge'] ?>" style="font-size:.7rem;text-transform:capitalize;"><?= h($user['status']) ?></span>
              <?php if ($user['role_name']): ?>
              <span class="badge bg-primary-lt text-primary" style="font-size:.7rem;"><?= h($user['role_name']) ?></span>
              <?php endif; ?>
            </div>
            <div style="font-size:.8rem;color:#6b7280;margin-top:3px;white-space:nowrap;">
              <?php if ($user['username']): ?>@<?= h($user['username']) ?>&ensp;&middot;&ensp;<?php endif; ?>
              <?= $user['email'] ? h($user['email']) : '<span class="text-muted">No email</span>' ?>
            </div>
          </div>

          <!-- Divider -->
          <div style="width:1px;background:#e2e8f0;align-self:stretch;flex-shrink:0;margin:0 1rem;"></div>

          <!-- KPIs -->
          <div style="display:flex;align-items:center;flex-wrap:nowrap;gap:0;flex:1;min-width:0;">

            <div style="padding:0 14px;flex-shrink:0;">
              <div style="font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#9ca3af;margin-bottom:2px;white-space:nowrap;">User Type</div>
              <div style="font-size:.88rem;font-weight:700;text-transform:capitalize;white-space:nowrap;"><?= h($user['user_type'] ?? '—') ?></div>
            </div>

            <div style="width:1px;background:#e2e8f0;align-self:stretch;flex-shrink:0;"></div>

            <div style="padding:0 14px;flex-shrink:0;">
              <div style="font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#9ca3af;margin-bottom:2px;white-space:nowrap;">Auth</div>
              <div style="font-size:.88rem;font-weight:700;text-transform:capitalize;white-space:nowrap;"><?= h($user['auth_source'] ?? '—') ?></div>
            </div>

            <div style="width:1px;background:#e2e8f0;align-self:stretch;flex-shrink:0;"></div>

            <div style="padding:0 14px;flex-shrink:0;">
              <div style="font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#9ca3af;margin-bottom:2px;white-space:nowrap;">Member Since</div>
              <div style="font-size:.88rem;font-weight:700;white-space:nowrap;"><?= profileDate($user['created_at'], 'M j, Y') ?></div>
            </div>

            <div style="width:1px;background:#e2e8f0;align-self:stretch;flex-shrink:0;"></div>

            <div style="padding:0 14px;flex-shrink:0;">
              <div style="font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#9ca3af;margin-bottom:2px;white-space:nowrap;">Last Login</div>
              <div style="font-size:.88rem;font-weight:700;white-space:nowrap;"><?= profileDate($user['last_login_at'], 'M j, Y') ?></div>
            </div>

            <div style="width:1px;background:#e2e8f0;align-self:stretch;flex-shrink:0;"></div>

            <div style="padding:0 14px;flex-shrink:0;">
              <div style="font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#9ca3af;margin-bottom:2px;white-space:nowrap;">NSB Applications</div>
              <div style="font-size:1.05rem;font-weight:900;line-height:1;color:var(--tblr-primary);"><?= count($applications) ?></div>
            </div>

          </div>

          <!-- Back button -->
          <div style="flex-shrink:0;margin-left:auto;padding-left:1rem;">
            <a href="<?= url('views/nsb/customers/list.php') ?>" class="btn btn-outline-secondary btn-sm" style="white-space:nowrap;">&#8592; Customer List</a>
          </div>

        </div>
      </div>
    </div>

    <!-- ── INFO ROW ─────────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">

      <!-- Personal Info -->
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header" style="background:#f8fafc;">
            <div class="card-title" style="font-size:.82rem;">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1 text-primary" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              Personal Information
            </div>
          </div>
          <div class="card-body p-0">
            <?php
            $personalRows = [
                ['Full Name',   $fullName],
                ['Email',       $user['email']   ?? null],
                ['Phone',       $user['phone']   ?? null],
                ['Username',    $user['username'] ? '@' . $user['username'] : null],
                ['MFA',         $user['mfa_enabled'] ? 'Enabled' : 'Disabled'],
                ['UUID',        $user['uuid'] ?? null],
            ];
            foreach ($personalRows as $i => $row):
                $border = $i < count($personalRows) - 1 ? 'border-bottom' : '';
            ?>
            <div class="d-flex px-3 py-2 <?= $border ?>">
              <span style="font-size:.78rem;color:#9ca3af;width:96px;flex-shrink:0;"><?= h($row[0]) ?></span>
              <span style="font-size:.82rem;font-weight:500;word-break:break-all;"><?= ($row[1] !== null && $row[1] !== '') ? h($row[1]) : '<span class="text-muted">—</span>' ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Organisation -->
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header" style="background:#f8fafc;">
            <div class="card-title" style="font-size:.82rem;">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1 text-primary" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
              Organisation
            </div>
          </div>
          <div class="card-body p-0">

            <!-- Department -->
            <div class="px-3 py-2 border-bottom">
              <div style="font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;margin-bottom:4px;">Department</div>
              <?php if ($user['dept_name']): ?>
                <div style="font-size:.85rem;font-weight:600;"><?= h($user['dept_name']) ?></div>
                <?php if ($user['dept_code']): ?>
                <div style="font-size:.75rem;color:#6b7280;">Code: <?= h($user['dept_code']) ?></div>
                <?php endif; ?>
                <?php if ($user['ministry_name']): ?>
                <div style="font-size:.75rem;color:#6b7280;"><?= h($user['ministry_name']) ?></div>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-muted" style="font-size:.82rem;">Not assigned</span>
              <?php endif; ?>
            </div>

            <!-- Branch -->
            <div class="px-3 py-2 border-bottom">
              <div style="font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;margin-bottom:4px;">Branch</div>
              <?php if ($user['branch_name']): ?>
                <div style="font-size:.85rem;font-weight:600;"><?= h($user['branch_name']) ?></div>
                <div style="font-size:.75rem;color:#6b7280;">
                  <?= $user['branch_code'] ? h($user['branch_code']) : '' ?>
                  <?= ($user['branch_code'] && $user['branch_district']) ? ' &middot; ' : '' ?>
                  <?= $user['branch_district'] ? h($user['branch_district']) : '' ?>
                </div>
                <?php if ($user['branch_address']): ?>
                <div style="font-size:.75rem;color:#9ca3af;"><?= h($user['branch_address']) ?></div>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-muted" style="font-size:.82rem;">Not assigned</span>
              <?php endif; ?>
            </div>

            <!-- Sub-Treasury -->
            <div class="px-3 py-2">
              <div style="font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;margin-bottom:4px;">Sub-Treasury</div>
              <?php if ($user['sub_treasury_name']): ?>
                <div style="font-size:.85rem;font-weight:600;"><?= h($user['sub_treasury_name']) ?></div>
                <div style="font-size:.75rem;color:#6b7280;">
                  <?= $user['sub_treasury_code'] ? h($user['sub_treasury_code']) : '' ?>
                  <?= ($user['sub_treasury_code'] && $user['st_district']) ? ' &middot; ' : '' ?>
                  <?= $user['st_district'] ? h($user['st_district']) : '' ?>
                </div>
                <?php if ($user['st_address']): ?>
                <div style="font-size:.75rem;color:#9ca3af;"><?= h($user['st_address']) ?></div>
                <?php endif; ?>
                <?php if ($user['st_phone'] || $user['st_email']): ?>
                <div style="font-size:.75rem;color:#9ca3af;">
                  <?= $user['st_phone'] ? h($user['st_phone']) : '' ?>
                  <?= ($user['st_phone'] && $user['st_email']) ? ' &middot; ' : '' ?>
                  <?= $user['st_email'] ? h($user['st_email']) : '' ?>
                </div>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-muted" style="font-size:.82rem;">Not assigned</span>
              <?php endif; ?>
            </div>

          </div>
        </div>
      </div>

      <!-- Account & Security -->
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header" style="background:#f8fafc;">
            <div class="card-title" style="font-size:.82rem;">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1 text-primary" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              Account &amp; Security
            </div>
          </div>
          <div class="card-body p-0">

            <div class="px-3 py-2 border-bottom">
              <div style="font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;margin-bottom:4px;">Account Status</div>
              <span class="badge <?= $sc['badge'] ?>" style="text-transform:capitalize;"><?= h($user['status']) ?></span>
              <?php if ($user['status'] === 'locked' && $user['locked_until']): ?>
              <div style="font-size:.75rem;color:#d63939;margin-top:3px;">Locked until <?= profileDateTime($user['locked_until']) ?></div>
              <?php endif; ?>
            </div>

            <div class="px-3 py-2 border-bottom">
              <div style="font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;margin-bottom:4px;">Role</div>
              <?php if ($user['role_name']): ?>
                <span style="font-size:.85rem;font-weight:600;"><?= h($user['role_name']) ?></span>
                <?php if ($user['role_code']): ?>
                <span style="font-size:.73rem;color:#9ca3af;margin-left:4px;">(<?= h($user['role_code']) ?>)</span>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-muted" style="font-size:.82rem;">No role assigned</span>
              <?php endif; ?>
            </div>

            <?php
            $secRows = [
                ['Failed Logins',    $user['failed_login_count'] > 0
                                        ? '<span style="color:#d63939;font-weight:700;">' . (int)$user['failed_login_count'] . '</span>'
                                        : '0'],
                ['Email Verified',   $user['email_verified_at']
                                        ? '<span style="color:#2fb344;">Yes &mdash; ' . profileDate($user['email_verified_at']) . '</span>'
                                        : '<span style="color:#9ca3af;">Not verified</span>'],
                ['MFA',              $user['mfa_enabled']
                                        ? '<span style="color:#2fb344;">Enabled</span>'
                                        : '<span style="color:#9ca3af;">Disabled</span>'],
                ['Last Login',       profileDateTime($user['last_login_at'])],
                ['Created',          profileDate($user['created_at'])
                                     . ($user['created_by_name'] ? ' &mdash; by ' . h(trim($user['created_by_name'])) : '')],
            ];
            foreach ($secRows as $i => $row):
                $border = $i < count($secRows) - 1 ? 'border-bottom' : '';
            ?>
            <div class="d-flex px-3 py-2 <?= $border ?>">
              <span style="font-size:.78rem;color:#9ca3af;width:96px;flex-shrink:0;"><?= h($row[0]) ?></span>
              <span style="font-size:.82rem;"><?= $row[1] ?></span>
            </div>
            <?php endforeach; ?>

          </div>
        </div>
      </div>

    </div><!-- /info row -->

    <!-- ── NSB APPLICATIONS ─────────────────────────────────────────── -->
    <div class="card mb-4">
      <div class="card-header" style="background:#f8fafc;">
        <div class="card-title" style="font-size:.9rem;">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1 text-primary" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
          NSB Applications Submitted
        </div>
        <div class="card-options">
          <span class="text-muted" style="font-size:.78rem;"><?= count($applications) ?> record<?= count($applications) !== 1 ? 's' : '' ?></span>
        </div>
      </div>
      <div class="card-body p-0">
        <?php if (empty($applications)): ?>
        <div class="text-center text-muted py-4" style="font-size:.88rem;">No applications submitted by this user.</div>
        <?php else: ?>
        <table class="table table-vcenter table-hover card-table mb-0">
          <thead>
            <tr>
              <th style="font-size:.78rem;">#</th>
              <th style="font-size:.78rem;">Customer Name</th>
              <th style="font-size:.78rem;">NIC</th>
              <th style="font-size:.78rem;">Account No.</th>
              <th style="font-size:.78rem;">Card Type</th>
              <th style="font-size:.78rem;">Branch</th>
              <th style="font-size:.78rem;">Status</th>
              <th style="font-size:.78rem;">Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($applications as $app): ?>
            <?php
                $aCls = isset($appStatusCls[$app['status']]) ? $appStatusCls[$app['status']] : 'bg-secondary-lt text-secondary';
                $cCls = isset($cardTypeCls[$app['card_type']]) ? $cardTypeCls[$app['card_type']] : 'bg-secondary-lt text-secondary';
            ?>
            <tr>
              <td class="text-muted" style="font-size:.78rem;">#<?= (int)$app['id'] ?></td>
              <td style="font-size:.85rem;font-weight:500;"><?= h($app['customer_name'] ?? '') ?></td>
              <td style="font-size:.82rem;"><?= h($app['nic'] ?? '') ?></td>
              <td style="font-size:.82rem;font-family:monospace;"><?= h($app['account_number'] ?? '') ?></td>
              <td><span class="badge <?= $cCls ?>" style="font-size:.68rem;text-transform:capitalize;"><?= h($app['card_type'] ?? '') ?></span></td>
              <td style="font-size:.8rem;"><?= $app['branch_code'] ? h($app['branch_code']) : '<span class="text-muted">—</span>' ?></td>
              <td><span class="badge <?= $aCls ?>" style="font-size:.68rem;text-transform:capitalize;"><?= h($app['status'] ?? '') ?></span></td>
              <td style="font-size:.8rem;color:#6b7280;"><?= substr($app['created_at'] ?? '', 0, 10) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php if (count($applications) >= 25): ?>
        <div class="px-3 py-2 text-muted border-top" style="font-size:.78rem;">Showing latest 25. <a href="<?= url('views/nsb/applications/index.php') ?>">View all applications &rarr;</a></div>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── AUDIT LOG ─────────────────────────────────────────────────── -->
    <div class="card mb-4">
      <div class="card-header" style="background:#f8fafc;">
        <div class="card-title" style="font-size:.9rem;">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1 text-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          Recent Activity
        </div>
        <div class="card-options">
          <span class="text-muted" style="font-size:.78rem;">Last <?= count($auditLogs) ?> events</span>
        </div>
      </div>
      <div class="card-body p-0">
        <?php if (empty($auditLogs)): ?>
        <div class="text-center text-muted py-4" style="font-size:.88rem;">No activity recorded for this user.</div>
        <?php else: ?>
        <table class="table table-vcenter card-table mb-0">
          <thead>
            <tr>
              <th style="font-size:.78rem;">Event</th>
              <th style="font-size:.78rem;">Action</th>
              <th style="font-size:.78rem;">Entity</th>
              <th style="font-size:.78rem;">Status</th>
              <th style="font-size:.78rem;">IP Address</th>
              <th style="font-size:.78rem;">Description</th>
              <th style="font-size:.78rem;">Date / Time</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($auditLogs as $log): ?>
            <?php $lCls = isset($logStatusCls[$log['status']]) ? $logStatusCls[$log['status']] : 'bg-secondary-lt text-secondary'; ?>
            <tr>
              <td style="font-size:.82rem;"><?= h($log['event_type'] ?? '—') ?></td>
              <td style="font-size:.82rem;"><?= h($log['event_action'] ?? '—') ?></td>
              <td style="font-size:.8rem;color:#6b7280;"><?= $log['entity_type'] ? h($log['entity_type']) : '<span class="text-muted">—</span>' ?></td>
              <td><span class="badge <?= $lCls ?>" style="font-size:.68rem;text-transform:capitalize;"><?= h($log['status'] ?? '') ?></span></td>
              <td style="font-size:.8rem;font-family:monospace;"><?= $log['ip_address'] ? h($log['ip_address']) : '<span class="text-muted">—</span>' ?></td>
              <td style="font-size:.8rem;color:#6b7280;max-width:260px;" class="text-truncate"><?= $log['description'] ? h($log['description']) : '<span class="text-muted">—</span>' ?></td>
              <td style="font-size:.8rem;color:#6b7280;white-space:nowrap;"><?= profileDateTime($log['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../../../includes/layout-tabler-footer.php'; ?>
