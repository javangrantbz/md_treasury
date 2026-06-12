<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.cost_centers.manage');

require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';

$officialSources = [
    '12017' => 'MOF estimates',
    '12128' => 'MOF estimates',
    '14118' => 'MOF estimates',
    '18017' => 'MOF estimates',
    '18041' => 'MOF estimates',
    '18071' => 'MOF estimates',
    '18152' => 'MOF estimates',
    '18163' => 'MOF estimates',
    '18178' => 'MOF estimates',
    '18184' => 'MOF estimates',
    '18195' => 'MOF estimates',
    '18206' => 'MOF estimates',
    '18211' => 'MOF estimates',
    '18453' => 'MOF estimates',
    '18528' => 'MOF estimates',
    '30261' => 'MOF estimates',
    '33162' => 'MOF estimates',
];

$legacyAvailable = true;
$legacyError = null;
$legacyMap = [];

try {
    $legacyPdo = new PDO(
        'mysql:host=' . ENV_DB_HOST . ';dbname=md_original_audit;charset=utf8mb4',
        ENV_DB_USER,
        ENV_DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $legacyRows = $legacyPdo->query("
        SELECT
            costCenterNumber AS code,
            COUNT(*) AS legacy_count,
            COUNT(DISTINCT costCenterName) AS legacy_name_count,
            GROUP_CONCAT(DISTINCT costCenterName ORDER BY costCenterName SEPARATOR ' | ') AS legacy_names
        FROM op_cost_centers
        GROUP BY costCenterNumber
    ")->fetchAll();

    foreach ($legacyRows as $row) {
        $legacyMap[(string) $row['code']] = $row;
    }
} catch (Throwable $e) {
    $legacyAvailable = false;
    $legacyError = $e->getMessage();
}

$costCenters = $pdo->query("
    SELECT
        cc.id,
        cc.code,
        cc.name,
        cc.department_id,
        cc.sub_treasury_id,
        cc.status,
        d.name AS department_name,
        st.sub_treasury_name
    FROM cost_centers cc
    LEFT JOIN departments d ON d.id = cc.department_id
    LEFT JOIN sub_treasuries st ON st.id = cc.sub_treasury_id
    ORDER BY
        CASE WHEN cc.code REGEXP '^[0-9]+$' THEN CAST(cc.code AS UNSIGNED) ELSE 999999 END,
        cc.code,
        cc.name
")->fetchAll();

$departments = $pdo->query("
    SELECT
        d.id,
        d.name,
        d.status,
        COUNT(cc.id) AS mapped_cost_centers
    FROM departments d
    LEFT JOIN cost_centers cc ON cc.department_id = d.id
    GROUP BY d.id, d.name, d.status
    ORDER BY d.name
")->fetchAll();

$subTreasuries = $pdo->query("
    SELECT id, sub_treasury_code, sub_treasury_name, district
    FROM sub_treasuries
    ORDER BY CAST(sub_treasury_code AS UNSIGNED), sub_treasury_name
")->fetchAll();

$activityHotspots = $pdo->query("
    SELECT
        cc.code,
        cc.name,
        COUNT(*) AS activity_rows,
        COUNT(DISTINCT cca.activity_name) AS distinct_activity_names,
        GROUP_CONCAT(DISTINCT cca.activity_name ORDER BY cca.activity_name SEPARATOR ' | ') AS sample_activity_names
    FROM cost_center_activities cca
    INNER JOIN cost_centers cc ON cc.id = cca.cost_center_id
    GROUP BY cc.id, cc.code, cc.name
    HAVING activity_rows >= 8
    ORDER BY activity_rows DESC, cc.code
    LIMIT 18
")->fetchAll();

function containsLegacyName(string $currentName, string $legacyNames): bool
{
    $names = array_map('trim', explode('|', $legacyNames));
    foreach ($names as $name) {
        if ($name === $currentName) {
            return true;
        }
    }
    return false;
}

$stats = [
    'verified' => 0,
    'suspect' => 0,
    'review' => 0,
    'unmappedDepartments' => 0,
    'legacyConflicts' => 0,
];

$auditRows = [];

foreach ($costCenters as $row) {
    $code = (string) $row['code'];
    $name = (string) $row['name'];
    $legacy = $legacyMap[$code] ?? null;
    $reasons = [];
    $statusClass = 'review';
    $statusLabel = 'Review';
    $onlineSource = $officialSources[$code] ?? '';

    if ($row['department_id'] === null) {
        $stats['unmappedDepartments']++;
        $reasons[] = 'No department mapped in current table.';
    }

    if (!preg_match('/^[0-9]{5}$/', $code)) {
        $statusClass = 'suspect';
        $statusLabel = 'Suspect';
        $reasons[] = 'Non-standard cost-centre code format.';
    }

    if (stripos($name, 'test') !== false || $code === '132323') {
        $statusClass = 'suspect';
        $statusLabel = 'Suspect';
        $reasons[] = 'Local test row.';
    }

    if ($legacy === null) {
        $statusClass = 'suspect';
        $statusLabel = 'Suspect';
        $reasons[] = 'Missing from md_original dump.';
    } else {
        if ((int) $legacy['legacy_name_count'] > 1) {
            $statusClass = 'suspect';
            $statusLabel = 'Suspect';
            $stats['legacyConflicts']++;
            $reasons[] = 'Legacy dump maps this code to multiple names.';
        }
        if (!containsLegacyName($name, (string) $legacy['legacy_names'])) {
            $statusClass = 'suspect';
            $statusLabel = 'Suspect';
            $reasons[] = 'Current name does not match legacy names for this code.';
        }
    }

    if ($statusClass !== 'suspect' && $onlineSource !== '') {
        $statusClass = 'verified';
        $statusLabel = 'Verified';
        $reasons[] = 'Matched representative MOF budget records online.';
    } elseif ($statusClass !== 'suspect' && $legacy !== null) {
        $reasons[] = 'Single legacy mapping and official 5-digit code pattern.';
    }

    if ($statusClass === 'verified') {
        $stats['verified']++;
    } elseif ($statusClass === 'suspect') {
        $stats['suspect']++;
    } else {
        $stats['review']++;
    }

    $auditRows[] = [
        'code' => $code,
        'name' => $name,
        'department_name' => $row['department_name'] ?? '',
        'sub_treasury_name' => $row['sub_treasury_name'] ?? '',
        'legacy_count' => $legacy['legacy_count'] ?? 0,
        'legacy_name_count' => $legacy['legacy_name_count'] ?? 0,
        'legacy_names' => $legacy['legacy_names'] ?? '',
        'status_class' => $statusClass,
        'status_label' => $statusLabel,
        'online_source' => $onlineSource,
        'reasons' => implode(' ', array_unique($reasons)),
    ];
}
?>

<style>
  .audit-card {
    border-left: 4px solid var(--tblr-primary);
  }
  .audit-table td,
  .audit-table th {
    vertical-align: top;
    font-size: .82rem;
  }
  .audit-row-verified {
    background: #edf9f0;
  }
  .audit-row-suspect {
    background: #fff0f0;
  }
  .legend-chip {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .25rem .55rem;
    border-radius: 999px;
    font-size: .74rem;
    font-weight: 600;
  }
  .legend-good {
    background: #edf9f0;
    color: #166534;
  }
  .legend-bad {
    background: #fff0f0;
    color: #b42318;
  }
  .legend-mid {
    background: #f5f7fa;
    color: #475467;
  }
  .mono-wrap {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    word-break: break-word;
  }
</style>

<div class="page-body">
  <div class="container-xl">
    <div class="card mb-3 audit-card">
      <div class="card-body py-3">
        <div class="d-flex align-items-center gap-3 flex-wrap">
          <div class="me-auto">
            <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.68rem;letter-spacing:.1em;">Cashiering · Master Data · Audit</div>
            <div class="fw-bold" style="font-size:1.05rem;line-height:1.2;">Cost Center Mapping Audit</div>
            <div class="text-muted mt-1" style="font-size:.82rem;">Current `cost_centers` compared to `md_original.sql` and representative Belize MOF records.</div>
          </div>
          <input type="text" id="audit-search" class="form-control form-control-sm" style="max-width:220px;" placeholder="Filter rows...">
          <a href="<?= url('views/cashiering/master-data/cost-centers/index.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Cost Centers</a>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <div class="card">
          <div class="card-body">
            <div class="text-uppercase text-muted fw-semibold" style="font-size:.68rem;letter-spacing:.08em;">Green Rows</div>
            <div class="fs-2 fw-bold text-success"><?= $stats['verified'] ?></div>
            <div class="text-muted" style="font-size:.8rem;">Verified online and not conflicting with legacy.</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card">
          <div class="card-body">
            <div class="text-uppercase text-muted fw-semibold" style="font-size:.68rem;letter-spacing:.08em;">Red Rows</div>
            <div class="fs-2 fw-bold text-danger"><?= $stats['suspect'] ?></div>
            <div class="text-muted" style="font-size:.8rem;">Duplicates, test data, or wrong mapping signals.</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card">
          <div class="card-body">
            <div class="text-uppercase text-muted fw-semibold" style="font-size:.68rem;letter-spacing:.08em;">Need Review</div>
            <div class="fs-2 fw-bold"><?= $stats['review'] ?></div>
            <div class="text-muted" style="font-size:.8rem;">Looks structurally okay but not yet explicitly verified online.</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card">
          <div class="card-body">
            <div class="text-uppercase text-muted fw-semibold" style="font-size:.68rem;letter-spacing:.08em;">Unmapped Departments</div>
            <div class="fs-2 fw-bold text-warning"><?= $stats['unmappedDepartments'] ?></div>
            <div class="text-muted" style="font-size:.8rem;">Current cost centers missing `department_id`.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body d-flex gap-2 flex-wrap">
        <span class="legend-chip legend-good">Green: good pattern and online-backed examples</span>
        <span class="legend-chip legend-bad">Red: duplicate or suspicious mapping</span>
        <span class="legend-chip legend-mid">Neutral: likely reusable but still needs explicit review</span>
      </div>
    </div>

    <?php if (!$legacyAvailable): ?>
      <div class="alert alert-warning mb-3">
        Legacy audit database `md_original_audit` could not be opened. The comparison still shows current data, but legacy duplicate checks are unavailable.
        <?php if ($legacyError): ?>
          <div class="small mt-1 mono-wrap"><?= h($legacyError) ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="card mb-4">
      <div class="card-header">
        <h3 class="card-title">Cost Center Comparison</h3>
      </div>
      <div class="table-responsive">
        <table class="table table-vcenter card-table audit-table" id="audit-table">
          <thead>
            <tr>
              <th>Status</th>
              <th>Current Code</th>
              <th>Current Name</th>
              <th>Department</th>
              <th>Sub-Treasury</th>
              <th>md_original Rows</th>
              <th>md_original Names</th>
              <th>Online Check</th>
              <th>Audit Note</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($auditRows as $row): ?>
              <tr class="<?= $row['status_class'] === 'verified' ? 'audit-row-verified' : ($row['status_class'] === 'suspect' ? 'audit-row-suspect' : '') ?>">
                <td>
                  <?php if ($row['status_class'] === 'verified'): ?>
                    <span class="badge bg-success-lt text-success"><?= h($row['status_label']) ?></span>
                  <?php elseif ($row['status_class'] === 'suspect'): ?>
                    <span class="badge bg-danger-lt text-danger"><?= h($row['status_label']) ?></span>
                  <?php else: ?>
                    <span class="badge bg-secondary-lt text-secondary"><?= h($row['status_label']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="mono-wrap"><?= h($row['code']) ?></td>
                <td><?= h($row['name']) ?></td>
                <td><?= h($row['department_name'] ?: '—') ?></td>
                <td><?= h($row['sub_treasury_name'] ?: '—') ?></td>
                <td>
                  <div class="mono-wrap"><?= h((string) $row['legacy_count']) ?></div>
                  <?php if ((int) $row['legacy_name_count'] > 1): ?>
                    <div class="text-danger small"><?= h((string) $row['legacy_name_count']) ?> different names</div>
                  <?php endif; ?>
                </td>
                <td><?= h($row['legacy_names'] ?: '—') ?></td>
                <td>
                  <?php if ($row['online_source'] !== ''): ?>
                    <span class="badge bg-success-lt text-success"><?= h($row['online_source']) ?></span>
                  <?php else: ?>
                    <span class="text-muted">Not explicitly checked</span>
                  <?php endif; ?>
                </td>
                <td><?= h($row['reasons']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header">
            <h3 class="card-title">Departments</h3>
          </div>
          <div class="card-body">
            <div class="text-muted mb-3" style="font-size:.82rem;">
              `md_original.sql` does not contain a normalized departments master table for direct comparison. This section highlights current department rows that look safe versus test or inactive records.
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-vcenter audit-table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Mapped Cost Centers</th>
                    <th>Review</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($departments as $dept): ?>
                    <?php
                      $isTest = stripos((string) $dept['name'], 'test') !== false;
                      $rowClass = ($isTest || $dept['status'] !== 'active') ? 'audit-row-suspect' : '';
                      $review = $isTest ? 'Test department.' : ($dept['status'] !== 'active' ? 'Inactive.' : 'Current app department.');
                    ?>
                    <tr class="<?= $rowClass ?>">
                      <td><?= h((string) $dept['name']) ?></td>
                      <td><?= h((string) $dept['status']) ?></td>
                      <td><?= h((string) $dept['mapped_cost_centers']) ?></td>
                      <td><?= h($review) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header">
            <h3 class="card-title">Sub-Treasuries</h3>
          </div>
          <div class="card-body">
            <div class="text-muted mb-3" style="font-size:.82rem;">
              These are the cleaned official sub-treasury records. Belize City is intentionally absent because it is the main Treasury, not a sub-treasury.
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-vcenter audit-table">
                <thead>
                  <tr>
                    <th>Cost Centre Code</th>
                    <th>Name</th>
                    <th>District</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($subTreasuries as $st): ?>
                    <tr class="audit-row-verified">
                      <td class="mono-wrap"><?= h((string) $st['sub_treasury_code']) ?></td>
                      <td><?= h((string) $st['sub_treasury_name']) ?></td>
                      <td><?= h((string) $st['district']) ?></td>
                      <td><span class="badge bg-success-lt text-success">Official</span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mt-3 mb-4">
      <div class="card-header">
        <h3 class="card-title">Cost Center Activities Hotspots</h3>
      </div>
      <div class="card-body">
        <div class="text-muted mb-3" style="font-size:.82rem;">
          This table shows why `cost_center_activities` should be rebuilt rather than trusted. Many cost centers have lots of rows but only one or two repeated activity names.
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter audit-table">
            <thead>
              <tr>
                <th>Cost Center</th>
                <th>Rows</th>
                <th>Distinct Activity Names</th>
                <th>Sample Activity Names</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($activityHotspots as $spot): ?>
                <?php $spotClass = ((int) $spot['activity_rows'] > 20 || (int) $spot['distinct_activity_names'] <= 2) ? 'audit-row-suspect' : ''; ?>
                <tr class="<?= $spotClass ?>">
                  <td><span class="mono-wrap"><?= h((string) $spot['code']) ?></span> <?= h((string) $spot['name']) ?></td>
                  <td><?= h((string) $spot['activity_rows']) ?></td>
                  <td><?= h((string) $spot['distinct_activity_names']) ?></td>
                  <td><?= h((string) $spot['sample_activity_names']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var input = document.getElementById('audit-search');
  var rows = Array.from(document.querySelectorAll('#audit-table tbody tr'));

  input.addEventListener('input', function () {
    var term = input.value.trim().toLowerCase();
    rows.forEach(function (row) {
      var text = row.textContent.toLowerCase();
      row.style.display = text.indexOf(term) !== -1 ? '' : 'none';
    });
  });
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
