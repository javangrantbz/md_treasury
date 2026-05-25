<?php
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::requireAuth();

$authUser  = Auth::user();
$fullName  = Auth::fullName();
$email     = $authUser['email'] ?? '';
$submitted = false;
$error     = null;

require_once __DIR__ . '/../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row align-items-center">
        <div class="col">
          <div class="page-pretitle">Help &amp; Support</div>
          <h2 class="page-title">Contact IT Support</h2>
        </div>
        <div class="col-auto">
          <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">← Back</a>
        </div>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">
      <div class="row g-4 justify-content-center">

        <!-- Contact info -->
        <div class="col-lg-4">

          <div class="card mb-3" style="border-top:3px solid var(--tblr-primary);">
            <div class="card-body">
              <h4 class="card-title mb-3">IT Helpdesk</h4>

              <div class="d-flex gap-3 mb-3">
                <div class="text-primary pt-1">
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.84 12a19.79 19.79 0 0 1-3-8.59A2 2 0 0 1 3.88 1.5h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                </div>
                <div>
                  <div class="text-muted" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;">Phone</div>
                  <div class="fw-medium">+501 822-0000</div>
                  <div class="text-muted" style="font-size:.8rem;">Mon–Fri, 8:00 AM – 5:00 PM</div>
                </div>
              </div>

              <div class="d-flex gap-3 mb-3">
                <div class="text-primary pt-1">
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </div>
                <div>
                  <div class="text-muted" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;">Email</div>
                  <a href="mailto:itsupport@mof.gov.bz" class="fw-medium">itsupport@mof.gov.bz</a>
                </div>
              </div>

              <div class="d-flex gap-3">
                <div class="text-primary pt-1">
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                </div>
                <div>
                  <div class="text-muted" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;">Location</div>
                  <div class="fw-medium">Treasury Building</div>
                  <div class="text-muted" style="font-size:.8rem;">Belmopan, Cayo District</div>
                </div>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-body py-3">
              <h4 class="card-title mb-2" style="font-size:.9rem;">Common Issues</h4>
              <ul class="list-unstyled mb-0" style="font-size:.85rem;">
                <?php foreach ([
                  ['label' => 'Account locked or cannot sign in',  'category' => 'Account / Login Issue',      'subject' => 'Account locked or cannot sign in'],
                  ['label' => 'Password reset request',            'category' => 'Password Reset',             'subject' => 'Password reset request'],
                  ['label' => 'Role or permission issue',          'category' => 'Role / Permission Issue',    'subject' => 'Role or permission issue'],
                  ['label' => 'System error or unexpected behaviour','category'=> 'System Error',              'subject' => 'System error or unexpected behaviour'],
                  ['label' => 'Request for new user account',      'category' => 'New User Account Request',  'subject' => 'Request for new user account'],
                ] as $item): ?>
                  <li class="d-flex gap-2 py-1 border-bottom">
                    <svg xmlns="http://www.w3.org/2000/svg" class="text-primary flex-shrink-0 mt-1" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6l-6 6"/></svg>
                    <a href="#"
                       class="quick-issue text-decoration-none text-reset"
                       data-category="<?= h($item['category']) ?>"
                       data-subject="<?= h($item['subject']) ?>"
                       style="cursor:pointer;">
                      <?= h($item['label']) ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>

        </div>

        <!-- Support request form -->
        <div class="col-lg-7">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Submit a Support Request</h3>
            </div>
            <div class="card-body">
              <div id="form-message" class="alert" style="display:none"></div>

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Your Name</label>
                  <input type="text" class="form-control bg-light" value="<?= h($fullName) ?>" readonly>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Your Email</label>
                  <input type="text" class="form-control bg-light" value="<?= h($email) ?>" readonly>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Issue Category <span class="text-danger">*</span></label>
                  <select class="form-select" id="category">
                    <option value="">— Select category —</option>
                    <option>Account / Login Issue</option>
                    <option>Password Reset</option>
                    <option>Role / Permission Issue</option>
                    <option>System Error</option>
                    <option>New User Account Request</option>
                    <option>Other</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Priority</label>
                  <select class="form-select" id="priority">
                    <option value="normal">Normal</option>
                    <option value="high">High — affecting my work</option>
                    <option value="urgent">Urgent — system down</option>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label">Subject <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="subject" placeholder="Brief description of the issue">
                </div>
                <div class="col-12">
                  <label class="form-label">Description <span class="text-danger">*</span></label>
                  <textarea class="form-control" id="description" rows="5"
                            placeholder="Please describe the issue in detail — include any error messages, steps to reproduce, and when it started."></textarea>
                </div>
              </div>

              <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary" id="submit-btn">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1" style="vertical-align:-2px"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                  Submit Request
                </button>
                <button class="btn btn-outline-secondary" id="reset-btn" type="button">Clear</button>
              </div>

            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

<script>
const SUBMIT_URL = '<?= url('api/support/submit.php') ?>';

document.getElementById('submit-btn').addEventListener('click', async () => {
  const category    = document.getElementById('category').value;
  const priority    = document.getElementById('priority').value;
  const subject     = document.getElementById('subject').value.trim();
  const description = document.getElementById('description').value.trim();
  const msgEl       = document.getElementById('form-message');
  const btn         = document.getElementById('submit-btn');

  msgEl.style.display = 'none';

  if (!category || !subject || !description) {
    msgEl.className   = 'alert alert-danger';
    msgEl.textContent = 'Please fill in all required fields before submitting.';
    msgEl.style.display = 'block';
    msgEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }

  btn.disabled = true;
  btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status"></span> Submitting…`;

  try {
    const res  = await fetch(SUBMIT_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ category, priority, subject, description }),
    });
    const json = await res.json();

    if (json.success) {
      msgEl.className   = 'alert alert-success';
      msgEl.innerHTML   = `<strong>Request submitted.</strong> ${json.message}`;
      msgEl.style.display = 'block';
      // Reset form
      document.getElementById('category').value    = '';
      document.getElementById('priority').value    = 'normal';
      document.getElementById('subject').value     = '';
      document.getElementById('description').value = '';
    } else {
      msgEl.className   = 'alert alert-danger';
      msgEl.textContent = json.message || 'Submission failed. Please try again.';
      msgEl.style.display = 'block';
    }
  } catch (err) {
    msgEl.className   = 'alert alert-danger';
    msgEl.textContent = 'A network error occurred. Please try again.';
    msgEl.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1" style="vertical-align:-2px"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Submit Request`;
    msgEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
});

document.querySelectorAll('.quick-issue').forEach(link => {
  link.addEventListener('click', e => {
    e.preventDefault();
    const categoryEl = document.getElementById('category');
    const subjectEl  = document.getElementById('subject');
    categoryEl.value = link.dataset.category;
    subjectEl.value  = link.dataset.subject;
    subjectEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    document.getElementById('description').focus();
    document.getElementById('form-message').style.display = 'none';
  });
});

document.getElementById('reset-btn').addEventListener('click', () => {
  document.getElementById('category').value    = '';
  document.getElementById('priority').value    = 'normal';
  document.getElementById('subject').value     = '';
  document.getElementById('description').value = '';
  const msgEl = document.getElementById('form-message');
  msgEl.style.display = 'none';
});
</script>

<?php require_once __DIR__ . '/../../includes/layout-tabler-footer.php'; ?>
