<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.suppliers.manage');

$id = (int)($_GET['id'] ?? 0);

require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-body">
    <div class="container-xl">

      <?php if ($id <= 0): ?>
        <div class="alert alert-danger">Invalid supplier ID.</div>
      <?php else: ?>

        <!-- Page identity card -->
        <div class="card mb-3" style="border-left: 4px solid var(--tblr-primary);">
          <div class="card-body py-3">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.68rem;letter-spacing:.1em;">Cashiering &middot; Master Data &middot; Suppliers</div>
                <div class="fw-bold" id="page-subtitle" style="font-size:1.05rem;line-height:1.2;">Loading...</div>
              </div>
              <a href="<?= url('views/cashiering/master-data/suppliers/index.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Back to Suppliers</a>
            </div>
          </div>
        </div>

        <div id="page-message" class="alert mb-3" style="display:none;"></div>

        <div class="card">
          <div class="card-header">
            <h3 class="card-title" id="record-heading">Loading...</h3>
            <div class="card-options">
              <button class="btn btn-sm btn-primary" id="edit-toggle-btn">Edit</button>
            </div>
          </div>
          <div class="card-body">
            <div id="edit-message" class="alert mb-3" style="display:none;"></div>

            <!-- View mode -->
            <dl class="row mb-0" id="view-mode">
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Supplier Name</dt>
              <dd class="col-sm-9 mb-2" id="v-supplier_name">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">TIN</dt>
              <dd class="col-sm-9 mb-2" id="v-tax_id">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Contact</dt>
              <dd class="col-sm-9 mb-2" id="v-contact_name">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Email</dt>
              <dd class="col-sm-9 mb-2" id="v-email">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Phone</dt>
              <dd class="col-sm-9 mb-2" id="v-phone">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Address</dt>
              <dd class="col-sm-9 mb-2" id="v-address">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">District</dt>
              <dd class="col-sm-9 mb-2" id="v-district">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Country</dt>
              <dd class="col-sm-9 mb-2" id="v-country">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Notes</dt>
              <dd class="col-sm-9 mb-2" id="v-notes">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Status</dt>
              <dd class="col-sm-9 mb-0" id="v-is_active">—</dd>
            </dl>

            <!-- Edit mode -->
            <div id="edit-mode" style="display:none;">
              <div class="row g-3">
                <div class="col-md-8">
                  <label class="form-label">Supplier Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="edit-supplier_name" placeholder="Supplier name">
                </div>
                <div class="col-md-4">
                  <label class="form-label">TIN</label>
                  <input type="text" class="form-control" id="edit-tax_id" placeholder="Tax ID / TIN">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Contact Name</label>
                  <input type="text" class="form-control" id="edit-contact_name" placeholder="Contact person">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" id="edit-email" placeholder="Email address">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Phone</label>
                  <input type="text" class="form-control" id="edit-phone" placeholder="Phone number">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Status</label>
                  <select class="form-select" id="edit-is_active">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label">Address Line 1</label>
                  <input type="text" class="form-control" id="edit-address_line_1" placeholder="Street address">
                </div>
                <div class="col-12">
                  <label class="form-label">Address Line 2</label>
                  <input type="text" class="form-control" id="edit-address_line_2" placeholder="Apt, suite, etc.">
                </div>
                <div class="col-md-6">
                  <label class="form-label">District</label>
                  <input type="text" class="form-control" id="edit-district" placeholder="District">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Country</label>
                  <input type="text" class="form-control" id="edit-country" placeholder="Country">
                </div>
                <div class="col-12">
                  <label class="form-label">Notes</label>
                  <textarea class="form-control" id="edit-notes" rows="3" placeholder="Optional notes"></textarea>
                </div>
              </div>
              <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary" id="save-btn">Save Changes</button>
                <button class="btn btn-outline-secondary" id="cancel-btn">Cancel</button>
              </div>
            </div>
          </div>
        </div>

      <?php endif; ?>
    </div>
  </div>

<script>
var RECORD_ID  = <?= $id ?>;
var SHOW_URL   = "<?= url('api/master-data/suppliers/show.php') ?>";
var UPDATE_URL = "<?= url('api/master-data/suppliers/update.php') ?>";

function showMsg(id, msg, type) {
  type = type || 'danger';
  var el = document.getElementById(id);
  el.className = 'alert alert-' + type;
  el.textContent = msg;
  el.style.display = 'block';
}
function clearMsg(id) {
  var el = document.getElementById(id);
  el.textContent = '';
  el.style.display = 'none';
}
function isActiveBadge(val) {
  return Number(val) === 1
    ? '<span class="badge bg-success-lt text-success">Active</span>'
    : '<span class="badge bg-secondary-lt text-secondary">Inactive</span>';
}

var recordData = null;

async function loadRecord() {
  try {
    var res = await apiGet(SHOW_URL + '?id=' + RECORD_ID);
    var r = res.data;
    recordData = r;
    var addr = [r.address_line_1, r.address_line_2].filter(Boolean).join(', ') || '—';
    document.getElementById('record-heading').textContent    = r.supplier_name || 'Supplier';
    document.getElementById('page-subtitle').textContent     = r.supplier_name || 'Supplier Details';
    document.getElementById('v-supplier_name').textContent   = r.supplier_name || '—';
    document.getElementById('v-tax_id').textContent          = r.tax_id || '—';
    document.getElementById('v-contact_name').textContent    = r.contact_name || '—';
    document.getElementById('v-email').textContent           = r.email || '—';
    document.getElementById('v-phone').textContent           = r.phone || '—';
    document.getElementById('v-address').textContent         = addr;
    document.getElementById('v-district').textContent        = r.district || '—';
    document.getElementById('v-country').textContent         = r.country || '—';
    document.getElementById('v-notes').textContent           = r.notes || '—';
    document.getElementById('v-is_active').innerHTML         = isActiveBadge(r.is_active);
  } catch (e) {
    showMsg('page-message', e.message);
  }
}

var editToggleBtn = document.getElementById('edit-toggle-btn');
var viewMode      = document.getElementById('view-mode');
var editMode      = document.getElementById('edit-mode');

function enterEditMode() {
  if (recordData) {
    document.getElementById('edit-supplier_name').value  = recordData.supplier_name || '';
    document.getElementById('edit-tax_id').value         = recordData.tax_id || '';
    document.getElementById('edit-contact_name').value   = recordData.contact_name || '';
    document.getElementById('edit-email').value          = recordData.email || '';
    document.getElementById('edit-phone').value          = recordData.phone || '';
    document.getElementById('edit-address_line_1').value = recordData.address_line_1 || '';
    document.getElementById('edit-address_line_2').value = recordData.address_line_2 || '';
    document.getElementById('edit-district').value       = recordData.district || '';
    document.getElementById('edit-country').value        = recordData.country || '';
    document.getElementById('edit-notes').value          = recordData.notes || '';
    document.getElementById('edit-is_active').value      = String(recordData.is_active) === '1' ? '1' : '0';
  }
  viewMode.style.display = 'none';
  editMode.style.display = '';
  editToggleBtn.textContent = 'Cancel';
  editToggleBtn.className = 'btn btn-sm btn-outline-secondary';
}

function exitEditMode() {
  editMode.style.display = 'none';
  viewMode.style.display = '';
  editToggleBtn.textContent = 'Edit';
  editToggleBtn.className = 'btn btn-sm btn-primary';
  clearMsg('edit-message');
}

editToggleBtn.addEventListener('click', function() {
  editMode.style.display !== 'none' ? exitEditMode() : enterEditMode();
});
document.getElementById('cancel-btn').addEventListener('click', exitEditMode);

document.getElementById('save-btn').addEventListener('click', async function() {
  clearMsg('edit-message');
  var btn = document.getElementById('save-btn');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    await apiPost(UPDATE_URL, {
      id:             RECORD_ID,
      supplier_name:  document.getElementById('edit-supplier_name').value,
      tax_id:         document.getElementById('edit-tax_id').value,
      contact_name:   document.getElementById('edit-contact_name').value,
      email:          document.getElementById('edit-email').value,
      phone:          document.getElementById('edit-phone').value,
      address_line_1: document.getElementById('edit-address_line_1').value,
      address_line_2: document.getElementById('edit-address_line_2').value,
      district:       document.getElementById('edit-district').value,
      country:        document.getElementById('edit-country').value,
      notes:          document.getElementById('edit-notes').value,
      is_active:      document.getElementById('edit-is_active').value,
    });
    exitEditMode();
    await loadRecord();
    showMsg('page-message', 'Supplier updated successfully.', 'success');
    setTimeout(function() { clearMsg('page-message'); }, 3000);
  } catch (e) {
    showMsg('edit-message', e.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Save Changes';
  }
});

document.addEventListener('DOMContentLoaded', function() {
  if (!RECORD_ID) return;
  loadRecord();
});
</script>

<?php require_once __DIR__ . '/../../../../includes/layout-tabler-footer.php'; ?>
