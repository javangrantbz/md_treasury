<?php
declare(strict_types=1);

require_once __DIR__ . '/config/env.php';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function parseReferenceTable(string $sql, string $tableName): array
{
    $pattern = sprintf(
        '/INSERT INTO\s+%s\s*\((.*?)\)\s*VALUES\s*(.*?);/si',
        preg_quote($tableName, '/')
    );

    if (!preg_match($pattern, $sql, $matches)) {
        return [];
    }

    $columns = array_map('trim', explode(',', preg_replace('/\s+/', ' ', trim($matches[1]))));
    $valuesBlock = trim($matches[2]);
    $lines = preg_split('/\R+/', $valuesBlock) ?: [];
    $rows = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] !== '(') {
            continue;
        }

        $line = rtrim($line, ',');
        $line = trim($line);
        $line = trim($line, '()');

        $fields = str_getcsv($line, ',', "'");
        if (count($fields) !== count($columns)) {
            continue;
        }

        $row = [];
        foreach ($columns as $index => $column) {
            $value = trim((string) $fields[$index]);
            if (strcasecmp($value, 'NULL') === 0) {
                $value = '';
            }
            $row[$column] = $value;
        }
        $rows[] = $row;
    }

    return $rows;
}

$sqlPath = __DIR__ . '/database/audits/official_verified_belize_master_data_2026-06-01.sql';
$sqlContent = is_file($sqlPath) ? (string) file_get_contents($sqlPath) : '';

$costCenters = [];
$subTreasuries = [];
$activities = [];
$departments = [];
$activityCatalog = [];
$dataSource = 'SQL file';

try {
    $refDsn = 'mysql:host=' . ENV_DB_HOST . ';dbname=md_original_audit;charset=utf8mb4';
    $refPdo = new PDO(
        $refDsn,
        ENV_DB_USER,
        ENV_DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $costCenters = $refPdo->query('SELECT * FROM official_verified_cost_centers ORDER BY CAST(cost_center_code AS UNSIGNED), cost_center_code')->fetchAll();
    $subTreasuries = $refPdo->query('SELECT * FROM official_verified_sub_treasuries ORDER BY CAST(sub_treasury_cost_center_code AS UNSIGNED), sub_treasury_cost_center_code')->fetchAll();
    $activities = $refPdo->query('SELECT * FROM official_verified_activity_items ORDER BY CAST(official_item_code AS UNSIGNED), official_item_code')->fetchAll();
    $departments = $refPdo->query('SELECT * FROM official_cleaned_departments ORDER BY current_department_name')->fetchAll();
    $activityCatalog = $refPdo->query('SELECT * FROM official_cost_center_activity_catalog ORDER BY CAST(cost_center_code AS UNSIGNED), CAST(official_item_code AS UNSIGNED), official_item_code')->fetchAll();
    $dataSource = 'md_original_audit database';
} catch (Throwable $e) {
    $costCenters = $sqlContent !== '' ? parseReferenceTable($sqlContent, 'official_verified_cost_centers') : [];
    $subTreasuries = $sqlContent !== '' ? parseReferenceTable($sqlContent, 'official_verified_sub_treasuries') : [];
    $activities = $sqlContent !== '' ? parseReferenceTable($sqlContent, 'official_verified_activity_items') : [];
    $departments = $sqlContent !== '' ? parseReferenceTable($sqlContent, 'official_cleaned_departments') : [];
    $activityCatalog = $sqlContent !== '' ? parseReferenceTable($sqlContent, 'official_cost_center_activity_catalog') : [];
}

$generatedAt = date('Y-m-d H:i:s');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>New Schema Reference</title>
  <style>
    :root {
      --bg: #f3f0e8;
      --panel: #fffdf8;
      --ink: #1b1d1b;
      --muted: #5e665d;
      --line: #d9d0c1;
      --good-bg: #e9f7ee;
      --good-ink: #166534;
      --bad-bg: #fff0f0;
      --bad-ink: #b42318;
      --accent: #244c3b;
      --accent-soft: #dce8e2;
    }

    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Georgia, "Times New Roman", serif;
      background:
        radial-gradient(circle at top left, rgba(36, 76, 59, .07), transparent 30%),
        linear-gradient(180deg, #f7f4ed 0%, var(--bg) 100%);
      color: var(--ink);
    }

    .page {
      max-width: 1480px;
      margin: 0 auto;
      padding: 28px 20px 40px;
    }

    .hero {
      background: linear-gradient(135deg, #17362a 0%, #2b5a46 65%, #496f5e 100%);
      color: #f8f7f2;
      border-radius: 18px;
      padding: 28px 28px 22px;
      box-shadow: 0 16px 36px rgba(23, 54, 42, .18);
      margin-bottom: 18px;
    }

    .hero h1 {
      margin: 0 0 8px;
      font-size: 2rem;
      line-height: 1.05;
      letter-spacing: -.02em;
    }

    .hero p {
      margin: 0;
      max-width: 980px;
      color: rgba(248, 247, 242, .84);
      font-size: .98rem;
    }

    .meta {
      margin-top: 14px;
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      font-size: .78rem;
    }

    .chip {
      border: 1px solid rgba(255,255,255,.18);
      padding: 6px 10px;
      border-radius: 999px;
      background: rgba(255,255,255,.08);
    }

    .toolbar {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      align-items: center;
      margin: 0 0 18px;
    }

    .search {
      flex: 1 1 280px;
      max-width: 320px;
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 12px 14px;
      font-size: .95rem;
    }

    .legend {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .section-links {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin: 0 0 18px;
    }

    .section-link {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      padding: 10px 14px;
      border-radius: 12px;
      background: var(--panel);
      border: 1px solid var(--line);
      color: var(--accent);
      font-size: .86rem;
      font-weight: 700;
      box-shadow: 0 8px 18px rgba(70, 63, 51, .05);
    }

    .section-link:hover {
      text-decoration: none;
      background: #f7f2e8;
    }

    .legend .tag {
      border-radius: 999px;
      padding: 7px 11px;
      font-size: .8rem;
      font-weight: 700;
    }

    .tag.good { background: var(--good-bg); color: var(--good-ink); }
    .tag.bad { background: var(--bad-bg); color: var(--bad-ink); }
    .tag.neutral { background: var(--accent-soft); color: var(--accent); }

    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
      gap: 12px;
      margin-bottom: 18px;
    }

    .stat {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 16px 18px;
      box-shadow: 0 8px 20px rgba(70, 63, 51, .05);
    }

    .stat .label {
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: .08em;
      font-size: .7rem;
      margin-bottom: 6px;
      font-weight: 700;
    }

    .stat .value {
      font-size: 1.9rem;
      font-weight: 700;
      line-height: 1;
    }

    .section {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 16px;
      overflow: hidden;
      margin-bottom: 18px;
      box-shadow: 0 10px 24px rgba(70, 63, 51, .06);
    }

    .section-header {
      display: flex;
      gap: 10px;
      justify-content: space-between;
      align-items: end;
      flex-wrap: wrap;
      padding: 18px 20px 14px;
      border-bottom: 1px solid var(--line);
      background: linear-gradient(180deg, rgba(36, 76, 59, .05), rgba(36, 76, 59, 0));
    }

    .section-header h2 {
      margin: 0 0 5px;
      font-size: 1.18rem;
    }

    .section-header p {
      margin: 0;
      color: var(--muted);
      font-size: .88rem;
    }

    .table-wrap {
      overflow: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 980px;
    }

    th, td {
      padding: 11px 12px;
      border-bottom: 1px solid #e6ddcf;
      text-align: left;
      vertical-align: top;
      font-size: .88rem;
    }

    th {
      position: sticky;
      top: 0;
      background: #faf6ef;
      z-index: 1;
      color: #493f35;
      font-size: .76rem;
      text-transform: uppercase;
      letter-spacing: .06em;
    }

    tr.good-row { background: var(--good-bg); }
    tr.bad-row { background: var(--bad-bg); }

    .status-pill {
      display: inline-block;
      border-radius: 999px;
      padding: 5px 9px;
      font-size: .72rem;
      font-weight: 700;
      white-space: nowrap;
    }

    .status-pill.good {
      background: rgba(22, 101, 52, .12);
      color: var(--good-ink);
    }

    .status-pill.bad {
      background: rgba(180, 35, 24, .12);
      color: var(--bad-ink);
    }

    .status-pill.neutral {
      background: rgba(36, 76, 59, .1);
      color: var(--accent);
    }

    .mono {
      font-family: Consolas, "Courier New", monospace;
      font-size: .84rem;
    }

    a {
      color: #0d5b93;
      text-decoration: none;
    }

    a:hover { text-decoration: underline; }

    .empty {
      padding: 24px 20px;
      color: var(--bad-ink);
      background: var(--bad-bg);
      font-weight: 700;
    }

    @media (max-width: 720px) {
      .hero h1 { font-size: 1.55rem; }
      .page { padding: 16px 12px 30px; }
      .section-header, .stat { padding-left: 14px; padding-right: 14px; }
    }
  </style>
</head>
<body>
  <div class="page">
    <section class="hero">
      <h1>Verified Belize Schema Reference</h1>
      <p>This page displays the cleaned online-verified reference data pulled from the generated SQL file. Green rows are records confirmed against Belize Ministry of Finance public records. Red rows are records found online but missing from the current app, or records that need further mapping into the live schema.</p>
      <div class="meta">
        <span class="chip">Source file: <span class="mono">database/audits/official_verified_belize_master_data_2026-06-01.sql</span></span>
        <span class="chip">Display source: <span class="mono"><?= h($dataSource) ?></span></span>
        <span class="chip">Generated view: <span class="mono"><?= h($generatedAt) ?></span></span>
      </div>
    </section>

    <div class="toolbar">
      <input id="globalSearch" class="search" type="search" placeholder="Filter all tables by code, name, or note">
      <div class="legend">
        <span class="tag good">Green: current app row exists</span>
        <span class="tag bad">Red: found online but not in current app</span>
        <span class="tag neutral">Neutral: official activity item reference</span>
      </div>
    </div>

    <nav class="section-links">
      <a class="section-link" href="#departments">Departments</a>
      <a class="section-link" href="#cost-centers">Cost Centers</a>
      <a class="section-link" href="#sub-treasuries">Sub-Treasuries</a>
      <a class="section-link" href="#activity-catalog">Activity Catalog</a>
      <a class="section-link" href="#activities">Activity Items</a>
    </nav>

    <section class="stats">
      <div class="stat">
        <div class="label">Verified Cost Centers</div>
        <div class="value"><?= count($costCenters) ?></div>
      </div>
      <div class="stat">
        <div class="label">Verified Sub-Treasuries</div>
        <div class="value"><?= count($subTreasuries) ?></div>
      </div>
      <div class="stat">
        <div class="label">Official Activity Items</div>
        <div class="value"><?= count($activities) ?></div>
      </div>
      <div class="stat">
        <div class="label">Activity Catalog Rows</div>
        <div class="value">
          <?= count($activityCatalog) ?>
        </div>
      </div>
    </section>

    <section class="section" data-section id="departments">
      <div class="section-header">
        <div>
          <h2>Cleaned Departments</h2>
          <p>Proposed department master list based on current app rows, the legacy dump, and current official Belize ministry structures.</p>
        </div>
      </div>
      <?php if ($departments === []): ?>
        <div class="empty">The SQL reference file could not be read or did not contain `official_cleaned_departments` data.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Action</th>
                <th>Current Department Name</th>
                <th>Proposed Department Name</th>
                <th>Basis</th>
                <th>Source</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($departments as $row): ?>
                <?php
                  $action = $row['action_type'];
                  $rowClass = $action === 'remove' ? 'bad-row' : ($action === 'keep' ? 'good-row' : '');
                  $pillClass = $action === 'remove' ? 'bad' : ($action === 'keep' ? 'good' : 'neutral');
                ?>
                <tr class="<?= $rowClass ?>" data-filter-row>
                  <td><span class="status-pill <?= $pillClass ?>"><?= h(ucfirst($action)) ?></span></td>
                  <td><?= h($row['current_department_name']) ?></td>
                  <td><?= h($row['proposed_department_name'] !== '' ? $row['proposed_department_name'] : '—') ?></td>
                  <td><?= h($row['source_basis']) ?></td>
                  <td>
                    <?php if ($row['source_url'] !== ''): ?>
                      <a href="<?= h($row['source_url']) ?>" target="_blank" rel="noreferrer">Government source</a>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                  <td><?= h($row['notes']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="section" data-section id="cost-centers">
      <div class="section-header">
        <div>
          <h2>Verified Cost Centers</h2>
          <p>Official cost centre code and name pairs from MOF records, with current app comparison.</p>
        </div>
      </div>
      <?php if ($costCenters === []): ?>
        <div class="empty">The SQL reference file could not be read or did not contain `official_verified_cost_centers` data.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Status</th>
                <th>Official Code</th>
                <th>Official Name</th>
                <th>Current App Name</th>
                <th>Source</th>
                <th>Note</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($costCenters as $row): ?>
                <?php $isCurrent = $row['current_exists_in_app'] === '1'; ?>
                <tr class="<?= $isCurrent ? 'good-row' : 'bad-row' ?>" data-filter-row>
                  <td>
                    <span class="status-pill <?= $isCurrent ? 'good' : 'bad' ?>">
                      <?= $isCurrent ? 'In App' : 'Online Only' ?>
                    </span>
                  </td>
                  <td class="mono"><?= h($row['cost_center_code']) ?></td>
                  <td><?= h($row['official_name']) ?></td>
                  <td><?= h($row['current_app_name'] !== '' ? $row['current_app_name'] : '—') ?></td>
                  <td><a href="<?= h($row['source_url']) ?>" target="_blank" rel="noreferrer">Government source</a></td>
                  <td><?= h($row['source_note']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="section" data-section id="sub-treasuries">
      <div class="section-header">
        <div>
          <h2>Verified Sub-Treasuries</h2>
          <p>Official sub-treasury cost-centre list separated from the main Treasury in Belize City.</p>
        </div>
      </div>
      <?php if ($subTreasuries === []): ?>
        <div class="empty">The SQL reference file could not be read or did not contain `official_verified_sub_treasuries` data.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Status</th>
                <th>Official Code</th>
                <th>Official Name</th>
                <th>Current App Name</th>
                <th>District</th>
                <th>Source</th>
                <th>Note</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($subTreasuries as $row): ?>
                <?php $isCurrent = $row['current_exists_in_app'] === '1'; ?>
                <tr class="<?= $isCurrent ? 'good-row' : 'bad-row' ?>" data-filter-row>
                  <td>
                    <span class="status-pill <?= $isCurrent ? 'good' : 'bad' ?>">
                      <?= $isCurrent ? 'In App' : 'Online Only' ?>
                    </span>
                  </td>
                  <td class="mono"><?= h($row['sub_treasury_cost_center_code']) ?></td>
                  <td><?= h($row['official_name']) ?></td>
                  <td><?= h($row['current_app_name'] !== '' ? $row['current_app_name'] : '—') ?></td>
                  <td><?= h($row['district']) ?></td>
                  <td><a href="<?= h($row['source_url']) ?>" target="_blank" rel="noreferrer">Government source</a></td>
                  <td><?= h($row['source_note']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="section" data-section id="activity-catalog">
      <div class="section-header">
        <div>
          <h2>Official Cost Center Activity Catalog</h2>
          <p>Verified public item-name records tied to a specific cost centre. Rows marked `Verified Direct` are explicitly shown under that cost centre online. Anything not directly shown should be treated as `Not Verified` until proven.</p>
        </div>
      </div>
      <?php if ($activityCatalog === []): ?>
        <div class="empty">No `official_cost_center_activity_catalog` data was available from the reference source.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Cost Center</th>
                <th>Linked Department</th>
                <th>Linked Sub-Treasury</th>
                <th>Official Item Code</th>
                <th>Official Item Name</th>
                <th>Linkage Scope</th>
                <th>Verification</th>
                <th>Current App Activity Example</th>
                <th>Source</th>
                <th>Note</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($activityCatalog as $row): ?>
                <tr data-filter-row>
                  <td>
                    <div class="mono"><?= h($row['cost_center_code']) ?></div>
                    <div><?= h($row['cost_center_name']) ?></div>
                  </td>
                  <td><?= h($row['linked_department_name'] !== '' ? $row['linked_department_name'] : '—') ?></td>
                  <td><?= h($row['linked_sub_treasury_name'] !== '' ? $row['linked_sub_treasury_name'] : 'Not verified') ?></td>
                  <td class="mono"><?= h($row['official_item_code']) ?></td>
                  <td><?= h($row['official_item_name']) ?></td>
                  <td><span class="status-pill bad">Not Verified</span></td>
                  <td><span class="status-pill neutral"><?= h($row['linkage_scope']) ?></span></td>
                  <td>
                    <?php if ($row['verification_status'] === 'verified_direct_cost_center'): ?>
                      <span class="status-pill good">Verified Direct</span>
                    <?php else: ?>
                      <span class="status-pill bad">Not Verified</span>
                    <?php endif; ?>
                  </td>
                  <td><?= h($row['current_app_activity_example'] !== '' ? $row['current_app_activity_example'] : '—') ?></td>
                  <td><a href="<?= h($row['source_url']) ?>" target="_blank" rel="noreferrer">Government source</a></td>
                  <td><?= h($row['source_note']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="section" data-section id="activities">
      <div class="section-header">
        <div>
          <h2>Official Activity Item Codes</h2>
          <p>Official revenue and licensing item codes related to the current cost-center activities, without treating internal `CCA-*` values as official. These are code/name references only unless the catalog above explicitly verifies the cost-center link.</p>
        </div>
      </div>
      <?php if ($activities === []): ?>
        <div class="empty">The SQL reference file could not be read or did not contain `official_verified_activity_items` data.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Status</th>
                <th>Official Item Code</th>
                <th>Official Item Name</th>
                <th>Cost Center Link</th>
                <th>Current App Activity Example</th>
                <th>Source</th>
                <th>Note</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($activities as $row): ?>
                <tr data-filter-row>
                  <td><span class="status-pill neutral">Reference</span></td>
                  <td class="mono"><?= h($row['official_item_code']) ?></td>
                  <td><?= h($row['official_item_name']) ?></td>
                  <td><?= h($row['current_app_activity_example'] !== '' ? $row['current_app_activity_example'] : '—') ?></td>
                  <td><a href="<?= h($row['source_url']) ?>" target="_blank" rel="noreferrer">Government source</a></td>
                  <td><?= h($row['source_note']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <script>
    (function () {
      var input = document.getElementById('globalSearch');
      var rows = Array.prototype.slice.call(document.querySelectorAll('[data-filter-row]'));

      input.addEventListener('input', function () {
        var term = input.value.trim().toLowerCase();
        rows.forEach(function (row) {
          var text = row.textContent.toLowerCase();
          row.style.display = term === '' || text.indexOf(term) !== -1 ? '' : 'none';
        });
      });
    }());
  </script>
</body>
</html>
