<?php
require_once __DIR__ . '/../../../../includes/Auth.php';
require_once __DIR__ . '/../../../../includes/Rbac.php';
require_once __DIR__ . '/../../../../includes/helpers.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.customers.manage');

$id = (int)($_GET['id'] ?? 0);

require_once __DIR__ . '/../../../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-body">
    <div class="container-xl">

      <?php if ($id <= 0): ?>
        <div class="alert alert-danger">Invalid customer ID.</div>
      <?php else: ?>

        <!-- Page identity card -->
        <div class="card mb-3" style="border-left: 4px solid var(--tblr-primary);">
          <div class="card-body py-3">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size:.68rem;letter-spacing:.1em;">Cashiering &middot; Master Data &middot; Customers</div>
                <div class="fw-bold" id="page-subtitle" style="font-size:1.05rem;line-height:1.2;">Loading...</div>
              </div>
              <a href="<?= url('views/cashiering/master-data/customers/index.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Back to Customers</a>
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
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Type</dt>
              <dd class="col-sm-9 mb-2" id="v-customer_type">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">Name</dt>
              <dd class="col-sm-9 mb-2" id="v-customer_name">—</dd>
              <dt class="col-sm-3 text-muted fw-normal" style="font-size:.85rem;">TIN</dt>
              <dd class="col-sm-9 mb-2" id="v-tax_id">—</dd>
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
              <dd class="col-sm-9 mb-0" id="v-status">—</dd>
            </dl>

            <!-- Edit mode -->
            <div id="edit-mode" style="display:none;">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Customer Type</label>
                  <select class="form-select" id="edit-customer_type">
                    <option value="individual">Individual</option>
                    <option value="organization">Organization</option>
                  </select>
                </div>

                <!-- Individual fields -->
                <div class="col-md-4" id="field-first_name">
                  <label class="form-label">First Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="edit-first_name" placeholder="First name">
                </div>
                <div class="col-md-4" id="field-last_name">
                  <label class="form-label">Last Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="edit-last_name" placeholder="Last name">
                </div>

                <!-- Organization field -->
                <div class="col-md-8" id="field-organization_name" style="display:none;">
                  <label class="form-label">Organization Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="edit-organization_name" placeholder="Organization name">
                </div>

                <div class="col-md-4">
                  <label class="form-label">TIN</label>
                  <input type="text" class="form-control" id="edit-tax_id" placeholder="Tax ID / TIN">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" id="edit-email" placeholder="Email address">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Phone</label>
                  <input type="text" class="form-control" id="edit-phone" placeholder="Phone number">
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
                <div class="col-md-4">
                  <label class="form-label">Status</label>
                  <select class="form-select" id="edit-status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </select>
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
var SHOW_URL   = "<?= url('api/master-data/customers/show.php') ?>";
var UPDATE_URL = "<?= url('api/master-data/customers/update.php') ?>";

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
function statusBadge(val) {
  return val === 'active'
    ? '<span class="badge bg-success-lt text-success">Active</span>'
    : '<span class="badge bg-secondary-lt text-secondary">Inactive</span>';
}

var recordData = null;

function applyTypeToggle(type) {
  var isOrg = type === 'organization';
  document.getElementById('field-first_name').style.display      = isOrg ? 'none' : '';
  document.getElementById('field-last_name').style.display       = isOrg ? 'none' : '';
  document.getElementById('field-organization_name').style.display = isOrg ? '' : 'none';
}

document.getElementById('edit-customer_type').addEventListener('change', function() {
  applyTypeToggle(this.value);
});

async function loadRecord() {
  try {
    var res = await apiGet(SHOW_URL + '?id=' + RECORD_ID);
    var r = res.data;
    recordData = r;
    var name = r.customer_name || (r.first_name ? r.first_name + ' ' + r.last_name : r.organization_name) || 'Customer';
    document.getElementById('record-heading').textContent = name;
    document.getElementById('page-subtitle').textContent  = name;
    document.getElementById('v-customer_type').textContent = r.customer_type === 'organization' ? 'Organization' : 'Individual';
    document.getElementById('v-customer_name').textContent = name;
    document.getElementById('v-tax_id').textContent        = r.tax_id || '—';
    document.getElementById('v-email').textContent         = r.email || '—';
    document.getElementById('v-phone').textContent         = r.phone || '—';
    var addr = [r.address_line_1, r.address_line_2].filter(Boolean).join(', ') || '—';
    document.getElementById('v-address').textContent       = addr;
    document.getElementById('v-district').textContent      = r.district || '—';
    document.getElementById('v-country').textContent       = r.country || '—';
    document.getElementById('v-notes').textContent         = r.notes || '—';
    document.getElementById('v-status').innerHTML          = statusBadge(r.status);
  } catch (e) {
    showMsg('page-message', e.message);
  }
}

var editToggleBtn = document.getElementById('edit-toggle-btn');
var viewMode      = document.getElementById('view-mode');
var editMode      = document.getElementById('edit-mode');

function enterEditMode() {
  if (recordData) {
    var type = recordData.customer_type || 'individual';
    document.getElementById('edit-customer_type').value      = type;
    document.getElementById('edit-first_name').value         = recordData.first_name || '';
    document.getElementById('edit-last_name').value          = recordData.last_name || '';
    document.getElementById('edit-organization_name').value  = recordData.organization_name || '';
    document.getElementById('edit-tax_id').value             = recordData.tax_id || '';
    document.getElementById('edit-email').value              = recordData.email || '';
    document.getElementById('edit-phone').value              = recordData.phone || '';
    document.getElementById('edit-address_line_1').value     = recordData.address_line_1 || '';
    document.getElementById('edit-address_line_2').value     = recordData.address_line_2 || '';
    document.getElementById('edit-district').value           = recordData.district || '';
    document.getElementById('edit-country').value            = recordData.country || '';
    document.getElementById('edit-status').value             = recordData.status || 'active';
    document.getElementById('edit-notes').value              = recordData.notes || '';
    applyTypeToggle(type);
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
      id:                RECORD_ID,
      customer_type:     document.getElementById('edit-customer_type').value,
      first_name:        document.getElementById('edit-first_name').value,
      last_name:         document.getElementById('edit-last_name').value,
      organization_name: document.getElementById('edit-organization_name').value,
      tax_id:            document.getElementById('edit-tax_id').value,
      email:             document.getElementById('edit-email').value,
      phone:             document.getElementById('edit-phone').value,
      address_line_1:    document.getElementById('edit-address_line_1').value,
      address_line_2:    document.getElementById('edit-address_line_2').value,
      district:          document.getElementById('edit-district').value,
      country:           document.getElementById('edit-country').value,
      status:            document.getElementById('edit-status').value,
      notes:             document.getElementById('edit-notes').value,
    });
    exitEditMode();
    await loadRecord();
    showMsg('page-message', 'Customer updated successfully.', 'success');
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
