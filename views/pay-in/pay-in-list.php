<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';
Auth::requireAuth();

$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$dateTo   = isset($_GET['date_to'])   ? trim($_GET['date_to'])   : date('Y-m-d');
$deptFilter = isset($_GET['dept']) ? trim($_GET['dept']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Load pay-ins
$payIns = [];
$fetchError = null;
try {
    $sql = "
        SELECT pi.pay_in_id, pi.department_name, pi.pay_in_date,
               pi.total_cash, pi.total_cheques, pi.total_amount,
               pi.bank_slip_path, pi.status, pi.created_at,
               CONCAT(u.first_name,' ',u.last_name) AS cashier_name
        FROM pay_ins pi
        LEFT JOIN users u ON u.id = pi.cashier_uid
        WHERE pi.pay_in_date >= :df AND pi.pay_in_date <= :dt
    ";
    $params = ['df' => $dateFrom, 'dt' => $dateTo];

    if ($deptFilter !== '') {
        $sql .= " AND pi.department_name LIKE :dept";
        $params['dept'] = '%' . $deptFilter . '%';
    }
    if ($statusFilter !== '') {
        $sql .= " AND pi.status = :status";
        $params['status'] = $statusFilter;
    }

    $sql .= " ORDER BY pi.pay_in_date DESC, pi.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payIns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $fetchError = $e->getMessage();
}

// Grand totals
$grandCash     = 0;
$grandCheques  = 0;
$grandTotal    = 0;
foreach ($payIns as $pi) {
    $grandCash    += (float)$pi['total_cash'];
    $grandCheques += (float)$pi['total_cheques'];
    $grandTotal   += (float)$pi['total_amount'];
}

ob_end_clean();
require_once __DIR__ . '/../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../includes/layout-tabler-sidebar.php';
?>

<div class="page-body">
  <div class="container-xl">

    <!-- Page header -->
    <div class="card mb-4" style="border-left:4px solid var(--tblr-success);">
      <div class="card-body py-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.63rem;letter-spacing:.1em;">Pay-In &middot; Revenue Collection</div>
            <div class="fw-bold" style="font-size:1.05rem;line-height:1.2;">Pay-In History</div>
          </div>
          <div class="d-flex gap-2">
            <a href="<?= url('views/pay-in/index.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Portal</a>
            <a href="<?= url('views/pay-in/pay-in-new.php') ?>" class="btn btn-success btn-sm">+ New Pay-In</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
      <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
          <div class="col-auto">
            <label class="form-label form-label-sm mb-1">From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
          </div>
          <div class="col-auto">
            <label class="form-label form-label-sm mb-1">To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
          </div>
          <div class="col-auto">
            <label class="form-label form-label-sm mb-1">Department</label>
            <input type="text" name="dept" class="form-control form-control-sm" placeholder="Search dept…" value="<?= htmlspecialchars($deptFilter) ?>" style="width:180px;">
          </div>
          <div class="col-auto">
            <label class="form-label form-label-sm mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
              <option value="">All</option>
              <option value="submitted"  <?= $statusFilter === 'submitted'  ? 'selected' : '' ?>>Submitted</option>
              <option value="verified"   <?= $statusFilter === 'verified'   ? 'selected' : '' ?>>Verified</option>
              <option value="rejected"   <?= $statusFilter === 'rejected'   ? 'selected' : '' ?>>Rejected</option>
            </select>
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
          </div>
          <div class="col-auto">
            <a href="<?= url('views/pay-in/pay-in-list.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
          </div>
        </form>
      </div>
    </div>

    <?php if ($fetchError): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($fetchError) ?></div>
    <?php endif; ?>

    <!-- Table -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <?= count($payIns) ?> pay-in<?= count($payIns) !== 1 ? 's' : '' ?>
          <span class="text-muted fw-normal" style="font-size:.82rem;">
            &mdash; <?= htmlspecialchars($dateFrom) ?> to <?= htmlspecialchars($dateTo) ?>
          </span>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-vcenter table-hover card-table">
          <thead>
            <tr>
              <th style="width:140px;">Pay-In ID</th>
              <th>Department</th>
              <th>Date</th>
              <th>Cashier</th>
              <th class="text-end" style="width:110px;">Cash</th>
              <th class="text-end" style="width:110px;">Cheques</th>
              <th class="text-end" style="width:120px;">Total</th>
              <th class="text-center" style="width:80px;">Slip</th>
              <th class="text-center" style="width:90px;">Status</th>
              <th style="width:60px;"></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($payIns)): ?>
            <tr>
              <td colspan="10" class="text-center text-muted py-4">No pay-ins found for this date range.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($payIns as $pi):
              $statusBadge = '';
              if ($pi['status'] === 'submitted') {
                  $statusBadge = '<span class="badge bg-yellow-lt text-yellow" style="font-size:.7rem;">Submitted</span>';
              } elseif ($pi['status'] === 'verified') {
                  $statusBadge = '<span class="badge bg-success-lt text-success" style="font-size:.7rem;">Verified</span>';
              } else {
                  $statusBadge = '<span class="badge bg-danger-lt text-danger" style="font-size:.7rem;">Rejected</span>';
              }
              $cashier = trim($pi['cashier_name']) ?: '—';
              $dept    = $pi['department_name'] ?: '<span class="text-muted fst-italic">Unspecified</span>';
            ?>
            <tr>
              <td><code class="text-muted" style="font-size:.8rem;"><?= htmlspecialchars($pi['pay_in_id']) ?></code></td>
              <td class="fw-medium"><?= $dept !== '<span class="text-muted fst-italic">Unspecified</span>' ? htmlspecialchars($dept) : $dept ?></td>
              <td style="font-size:.84rem;"><?= htmlspecialchars($pi['pay_in_date']) ?></td>
              <td style="font-size:.84rem;"><?= htmlspecialchars($cashier) ?></td>
              <td class="text-end" style="font-size:.84rem;">$<?= number_format((float)$pi['total_cash'], 2) ?></td>
              <td class="text-end" style="font-size:.84rem;">$<?= number_format((float)$pi['total_cheques'], 2) ?></td>
              <td class="text-end fw-bold" style="font-size:.88rem;">BZD $<?= number_format((float)$pi['total_amount'], 2) ?></td>
              <td class="text-center">
                <?php if ($pi['bank_slip_path']): ?>
                <a href="<?= url($pi['bank_slip_path']) ?>" target="_blank" class="btn btn-xs btn-outline-secondary" title="View Slip">
                  <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </a>
                <?php else: ?>
                <span class="text-muted" style="font-size:.75rem;">—</span>
                <?php endif; ?>
              </td>
              <td class="text-center"><?= $statusBadge ?></td>
              <td>
                <a href="<?= url('views/pay-in/pay-in-view.php') ?>?id=<?= urlencode($pi['pay_in_id']) ?>"
                   class="btn btn-sm btn-outline-primary" title="View">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  View
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          <?php if (!empty($payIns)): ?>
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="4" class="text-end">Totals</td>
              <td class="text-end">BZD $<?= number_format($grandCash, 2) ?></td>
              <td class="text-end">BZD $<?= number_format($grandCheques, 2) ?></td>
              <td class="text-end" style="color:#1e4620;">BZD $<?= number_format($grandTotal, 2) ?></td>
              <td colspan="3"></td>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout-tabler-footer.php'; ?>
