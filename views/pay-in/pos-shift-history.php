<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';
Auth::requireAuth();

// Date filter
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$dateTo   = isset($_GET['date_to'])   ? trim($_GET['date_to'])   : date('Y-m-d');

$params = ['date_from' => $dateFrom, 'date_to' => $dateTo];

try {
    $stmt = $pdo->prepare("
        SELECT
            ps.shift_id,
            ps.started_at,
            ps.ended_at,
            ps.starting_cash,
            ps.status,
            CONCAT(u.first_name,' ',u.last_name) AS cashier_name,
            d.short_name AS dept_short,
            d.name       AS dept_name,
            r.register_name,
            r.register_code,
            COUNT(DISTINCT pt.id)               AS tx_count,
            COALESCE(SUM(pt.total_amount), 0)   AS total_amount,
            COALESCE(SUM(CASE WHEN ci.payment_method = 'cash' THEN ci.amount ELSE 0 END), 0) AS cash_collected
        FROM pos_shifts ps
        LEFT JOIN users u ON u.id = ps.uid
        LEFT JOIN departments d ON d.id = ps.department_id
        LEFT JOIN registers r ON r.id = ps.register_id
        LEFT JOIN pos_transactions pt ON pt.shift_id = ps.shift_id
        LEFT JOIN pos_cart_items ci ON ci.shift_id = ps.shift_id AND ci.status = 'completed'
        WHERE DATE(ps.started_at) >= :date_from
          AND DATE(ps.started_at) <= :date_to
        GROUP BY ps.shift_id, ps.started_at, ps.ended_at, ps.starting_cash, ps.status,
                 u.first_name, u.last_name, d.short_name, d.name, r.register_name, r.register_code
        ORDER BY ps.started_at DESC
    ");
    $stmt->execute($params);
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $shifts = [];
    $fetchError = $e->getMessage();
}

function hFmtDT($dt) {
    if (!$dt) return '—';
    $ts = strtotime($dt);
    return $ts ? date('d M Y  h:i A', $ts) : '—';
}
function hDuration($start, $end) {
    if (!$start || !$end) return '<span class="text-muted">Open</span>';
    $diff = strtotime($end) - strtotime($start);
    if ($diff <= 0) return '—';
    $h = floor($diff / 3600);
    $m = floor(($diff % 3600) / 60);
    return $h.'h '.$m.'m';
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
            <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.63rem;letter-spacing:.1em;">Pay-In &middot; POS Terminal</div>
            <div class="fw-bold" style="font-size:1.05rem;line-height:1.2;">Shift History</div>
          </div>
          <div class="d-flex gap-2">
            <a href="<?= url('views/pay-in/index.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Back</a>
            <a href="<?= url('views/pay-in/pos-shift-start.php') ?>" class="btn btn-success btn-sm">+ New Shift</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Date filter -->
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
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
          </div>
          <div class="col-auto">
            <a href="<?= url('views/pay-in/pos-shift-history.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
          </div>
        </form>
      </div>
    </div>

    <?php if (!empty($fetchError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($fetchError) ?></div>
    <?php endif; ?>

    <!-- Shifts table -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <?= count($shifts) ?> shift<?= count($shifts) !== 1 ? 's' : '' ?>
          <span class="text-muted fw-normal" style="font-size:.82rem;">
            &mdash; <?= htmlspecialchars($dateFrom) ?> to <?= htmlspecialchars($dateTo) ?>
          </span>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-vcenter table-hover card-table">
          <thead>
            <tr>
              <th style="width:120px;">Shift ID</th>
              <th>Cashier</th>
              <th>Branch / Terminal</th>
              <th>Opened</th>
              <th>Closed</th>
              <th style="width:80px;">Duration</th>
              <th class="text-center" style="width:80px;">Txn</th>
              <th class="text-end" style="width:120px;">Revenue</th>
              <th class="text-center" style="width:80px;">Status</th>
              <th style="width:60px;"></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($shifts)): ?>
            <tr>
              <td colspan="10" class="text-center text-muted py-4">No shifts found for this date range.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($shifts as $sh): ?>
            <?php
              $isOpen   = (int)$sh['status'] === 1;
              $branch   = $sh['dept_short'] ?: ($sh['dept_name'] ?: '—');
              $terminal = $sh['register_name'] ?: ($sh['register_code'] ?: '—');
              $cashier  = trim($sh['cashier_name']) ?: '—';
            ?>
            <tr>
              <td><code class="text-muted" style="font-size:.8rem;"><?= htmlspecialchars($sh['shift_id']) ?></code></td>
              <td class="fw-medium"><?= htmlspecialchars($cashier) ?></td>
              <td>
                <div class="fw-medium" style="font-size:.85rem;"><?= htmlspecialchars($branch) ?></div>
                <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($terminal) ?></div>
              </td>
              <td style="font-size:.83rem;"><?= hFmtDT($sh['started_at']) ?></td>
              <td style="font-size:.83rem;"><?= $sh['ended_at'] ? hFmtDT($sh['ended_at']) : '<span class="text-success fw-semibold" style="font-size:.78rem;">Active</span>' ?></td>
              <td style="font-size:.82rem;"><?= hDuration($sh['started_at'], $sh['ended_at']) ?></td>
              <td class="text-center fw-semibold"><?= (int)$sh['tx_count'] ?></td>
              <td class="text-end fw-bold" style="font-size:.88rem;">BZD $<?= number_format((float)$sh['total_amount'], 2) ?></td>
              <td class="text-center">
                <?php if ($isOpen): ?>
                <span class="badge bg-success-lt text-success" style="font-size:.7rem;">Open</span>
                <?php else: ?>
                <span class="badge bg-secondary-lt text-secondary" style="font-size:.7rem;">Closed</span>
                <?php endif; ?>
              </td>
              <td>
                <a href="<?= url('views/pay-in/pos-shift-report.php') ?>?shift_id=<?= urlencode($sh['shift_id']) ?>"
                   class="btn btn-sm btn-outline-primary" title="View Report">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                  Report
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout-tabler-footer.php'; ?>
