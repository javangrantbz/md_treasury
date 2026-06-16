<?php
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/helpers.php';
Auth::requireAuth();
require_once __DIR__ . '/../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../includes/layout-tabler-sidebar.php';
?>
<div class="page-header d-print-none">
  <div class="container-xl">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">NSB — Savings</div>
        <h2 class="page-title">Add / Import Accounts</h2>
      </div>
      <div class="col-auto ms-auto">
        <a href="<?= url('views/nsb/accounts/list.php') ?>" class="btn btn-outline-secondary btn-sm">&larr; All Accounts</a>
      </div>
    </div>
  </div>
</div>
<div class="page-body">
  <div class="container-xl">
    <div class="row g-3">

      <!-- Single account -->
      <div class="col-lg-5">
        <div class="card">
          <div class="card-header"><h3 class="card-title">New Account</h3></div>
          <div class="card-body">
            <div id="single-msg" class="alert" style="display:none;"></div>
            <form id="single-form">
              <div class="mb-3">
                <label class="form-label required">Customer Name</label>
                <input type="text" class="form-control" name="customer_name" required placeholder="Full name">
              </div>
              <div class="row g-2">
                <div class="col-7 mb-3">
                  <label class="form-label required">Starting Balance (BZD)</label>
                  <input type="number" step="0.01" min="0" class="form-control" name="starting_balance" required placeholder="0.00">
                </div>
                <div class="col-5 mb-3">
                  <label class="form-label required">Starting Date</label>
                  <input type="date" class="form-control" name="starting_date" required>
                </div>
              </div>
              <div class="row g-2">
                <div class="col-6 mb-3">
                  <label class="form-label">Account # <span class="text-muted">(optional)</span></label>
                  <input type="text" class="form-control" name="account_number" placeholder="Auto if blank">
                </div>
                <div class="col-6 mb-3">
                  <label class="form-label">Branch <span class="text-muted">(optional)</span></label>
                  <select class="form-select" name="branch_id" id="branch-select"><option value="">—</option></select>
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label">Remarks</label>
                <textarea class="form-control" name="remarks" rows="2" placeholder="Optional notes…"></textarea>
              </div>
              <button type="submit" class="btn btn-primary w-100" id="single-submit">Create Account</button>
            </form>
          </div>
        </div>
      </div>

      <!-- Bulk import -->
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Bulk Import (Opening Balances)</h3></div>
          <div class="card-body">
            <p class="text-muted" style="font-size:.85rem;">
              Paste one account per line as <code>Customer Name, Starting Balance, Starting Date</code>.
              An optional 4th column sets the account number (auto-assigned if omitted).
              Dates accept <code>YYYY-MM-DD</code>, <code>DD/MM/YYYY</code>, etc.
            </p>
            <pre class="bg-light p-2 rounded" style="font-size:.78rem;">John Doe, 1500.00, 2019-03-01
Maria Coc, 320.50, 2021-07-15, NSB000123</pre>
            <div id="import-msg" class="alert" style="display:none;"></div>
            <textarea id="import-text" class="form-control" rows="9" placeholder="Paste rows here…" style="font-family:monospace;font-size:.82rem;"></textarea>
            <div class="d-flex justify-content-between align-items-center mt-2">
              <span class="text-muted" style="font-size:.8rem;" id="import-count">0 rows</span>
              <button class="btn btn-success" id="import-btn">Import Accounts</button>
            </div>
            <div id="import-errors" class="mt-3" style="display:none;">
              <div class="text-danger fw-semibold mb-1" style="font-size:.82rem;">Skipped rows</div>
              <ul id="import-errors-list" class="text-danger" style="font-size:.8rem;"></ul>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
const CREATE_URL  = "<?= url('api/nsb/accounts/create.php') ?>";
const IMPORT_URL  = "<?= url('api/nsb/accounts/import.php') ?>";
const BRANCHES_URL= "<?= url('api/master-data/branches/list.php') ?>?status=active";
const ACCT_LIST   = "<?= url('views/nsb/accounts/list.php') ?>";

function showMsg(id, type, text) {
  const el = document.getElementById(id);
  el.className = 'alert alert-' + type;
  el.textContent = text;
  el.style.display = 'block';
}

// Branches dropdown
apiGet(BRANCHES_URL).then(function(res) {
  const sel = document.getElementById('branch-select');
  (res.data || []).forEach(function(b) {
    const o = document.createElement('option');
    o.value = b.id;
    o.textContent = (b.code ? b.code + ' — ' : '') + b.name;
    sel.appendChild(o);
  });
}).catch(function(){ /* non-fatal */ });

// Single create
document.getElementById('single-form').addEventListener('submit', function(e) {
  e.preventDefault();
  const btn = document.getElementById('single-submit');
  const fd = new FormData(this);
  const payload = {
    customer_name:    fd.get('customer_name'),
    starting_balance: fd.get('starting_balance'),
    starting_date:    fd.get('starting_date'),
    account_number:   fd.get('account_number'),
    branch_id:        fd.get('branch_id'),
    remarks:          fd.get('remarks')
  };
  btn.disabled = true; btn.textContent = 'Saving…';
  apiPost(CREATE_URL, payload).then(function(res) {
    showMsg('single-msg', 'success', res.message + ' (' + res.account_number + ')');
    document.getElementById('single-form').reset();
  }).catch(function(err) {
    showMsg('single-msg', 'danger', err.message);
  }).finally(function() {
    btn.disabled = false; btn.textContent = 'Create Account';
  });
});

// Parse pasted CSV-ish rows
function parseRows(text) {
  const rows = [];
  text.split(/\r?\n/).forEach(function(line) {
    line = line.trim();
    if (!line) return;
    const p = line.split(',').map(function(x){ return x.trim(); });
    rows.push({
      customer_name:    p[0] || '',
      starting_balance: p[1] || '0',
      starting_date:    p[2] || '',
      account_number:   p[3] || ''
    });
  });
  return rows;
}

document.getElementById('import-text').addEventListener('input', function() {
  const n = parseRows(this.value).length;
  document.getElementById('import-count').textContent = n + ' row' + (n === 1 ? '' : 's');
});

document.getElementById('import-btn').addEventListener('click', function() {
  const rows = parseRows(document.getElementById('import-text').value);
  if (!rows.length) { showMsg('import-msg', 'warning', 'Nothing to import — paste some rows first.'); return; }
  const btn = this;
  btn.disabled = true; btn.textContent = 'Importing…';
  apiPost(IMPORT_URL, { rows: rows }).then(function(res) {
    showMsg('import-msg', res.skipped ? 'warning' : 'success', res.message);
    const errBox = document.getElementById('import-errors');
    const errList = document.getElementById('import-errors-list');
    errList.innerHTML = '';
    if (res.errors && res.errors.length) {
      res.errors.forEach(function(er) { const li = document.createElement('li'); li.textContent = er; errList.appendChild(li); });
      errBox.style.display = 'block';
    } else {
      errBox.style.display = 'none';
    }
    if (res.imported > 0) {
      document.getElementById('import-text').value = '';
      document.getElementById('import-count').textContent = '0 rows';
      setTimeout(function(){ window.location.href = ACCT_LIST; }, 1500);
    }
  }).catch(function(err) {
    showMsg('import-msg', 'danger', err.message);
  }).finally(function() {
    btn.disabled = false; btn.textContent = 'Import Accounts';
  });
});
</script>
<?php require_once __DIR__ . '/../../../includes/layout-tabler-footer.php'; ?>
