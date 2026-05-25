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
          <h2 class="page-title">My Support Tickets</h2>
        </div>
        <div class="col-auto d-flex gap-2">
          <a href="<?= url('views/help/it-support.php') ?>" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1" style="vertical-align:-1px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Request
          </a>
          <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">← Back</a>
        </div>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">

      <!-- Summary stats -->
      <div class="row g-2 mb-3" id="summary-row">
        <?php foreach ([
          ['key' => 'total',       'label' => 'Total',       'color' => 'primary'],
          ['key' => 'open',        'label' => 'Open',        'color' => 'azure'],
          ['key' => 'in_progress', 'label' => 'In Progress', 'color' => 'warning'],
          ['key' => 'resolved',    'label' => 'Resolved',    'color' => 'success'],
          ['key' => 'closed',      'label' => 'Closed',      'color' => 'secondary'],
        ] as $s): ?>
        <div class="col-6 col-md-4 col-lg">
          <div class="card summary-card"
               style="<?= $s['key'] !== 'total' ? 'cursor:pointer;' : '' ?>"
               data-filter="<?= $s['key'] === 'total' ? '' : $s['key'] ?>">
            <div class="card-body" style="padding:.6rem .85rem;">
              <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:var(--tblr-secondary);font-weight:600;margin-bottom:.15rem;"><?= $s['label'] ?></div>
              <div class="text-<?= $s['color'] ?> summary-count fw-bold" data-key="<?= $s['key'] ?>" style="font-size:1.4rem;line-height:1.2;">—</div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Filter bar -->
      <div class="card mb-3">
        <div class="card-body py-2">
          <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="text-muted" style="font-size:.85rem;">Filter by status:</span>
            <button class="btn btn-sm btn-primary filter-btn active" data-status="">All</button>
            <button class="btn btn-sm btn-outline-azure filter-btn"     data-status="open">Open</button>
            <button class="btn btn-sm btn-outline-warning filter-btn"   data-status="in_progress">In Progress</button>
            <button class="btn btn-sm btn-outline-success filter-btn"   data-status="resolved">Resolved</button>
            <button class="btn btn-sm btn-outline-secondary filter-btn" data-status="closed">Closed</button>
            <span class="ms-auto text-muted" id="ticket-count" style="font-size:.82rem;"></span>
          </div>
        </div>
      </div>

      <!-- Tickets table -->
      <div class="card">
        <div class="card-body p-0">
          <div id="loading-row" class="text-center py-5 text-muted">
            <div class="spinner-border spinner-border-sm me-2" role="status"></div> Loading tickets…
          </div>
          <div id="empty-row" class="text-center py-5 text-muted" style="display:none;">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mb-2 d-block mx-auto text-muted"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            No tickets found.
          </div>
          <div class="table-responsive" id="table-wrap" style="display:none;">
            <table class="table table-vcenter card-table table-hover">
              <thead>
                <tr>
                  <th style="width:60px">#</th>
                  <th>Subject</th>
                  <th>Category</th>
                  <th>Priority</th>
                  <th>Status</th>
                  <th>Submitted</th>
                  <th>Resolved</th>
                  <th style="width:40px"></th>
                </tr>
              </thead>
              <tbody id="ticket-tbody"></tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- Detail modal -->
  <div class="modal modal-blur fade" id="ticket-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modal-subject">Ticket Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <div class="text-muted mb-1" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;">Ticket #</div>
              <div id="modal-id" class="fw-semibold"></div>
            </div>
            <div class="col-sm-6">
              <div class="text-muted mb-1" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;">Status</div>
              <div id="modal-status"></div>
            </div>
            <div class="col-sm-6">
              <div class="text-muted mb-1" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;">Category</div>
              <div id="modal-category"></div>
            </div>
            <div class="col-sm-6">
              <div class="text-muted mb-1" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;">Priority</div>
              <div id="modal-priority"></div>
            </div>
            <div class="col-sm-6">
              <div class="text-muted mb-1" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;">Submitted</div>
              <div id="modal-created"></div>
            </div>
            <div class="col-sm-6">
              <div class="text-muted mb-1" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;">Resolved</div>
              <div id="modal-resolved"></div>
            </div>
          </div>

          <div class="mb-3">
            <div class="text-muted mb-1" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;">Description</div>
            <div id="modal-description" class="p-3 rounded" style="background:var(--tblr-bg-surface-secondary);white-space:pre-wrap;font-size:.88rem;"></div>
          </div>

          <div id="resolution-section" style="display:none;">
            <div class="text-muted mb-1" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;">Resolution Notes</div>
            <div id="modal-resolution" class="p-3 rounded border-start border-success border-3" style="background:#f0fdf4;white-space:pre-wrap;font-size:.88rem;"></div>
            <div class="text-muted mt-1" style="font-size:.78rem;">Resolved by <span id="modal-resolved-by"></span></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

<script>
const API_URL = '<?= url('api/support/my-tickets.php') ?>';

const statusBadge = s => ({
  open:        '<span class="badge bg-azure-lt text-azure">Open</span>',
  in_progress: '<span class="badge bg-warning-lt text-warning">In Progress</span>',
  resolved:    '<span class="badge bg-success-lt text-success">Resolved</span>',
  closed:      '<span class="badge bg-secondary-lt text-secondary">Closed</span>',
}[s] ?? `<span class="badge bg-secondary-lt">${s}</span>`);

const priorityBadge = p => ({
  normal: '<span class="badge bg-secondary-lt text-secondary">Normal</span>',
  high:   '<span class="badge bg-warning-lt text-warning">High</span>',
  urgent: '<span class="badge bg-danger-lt text-danger">Urgent</span>',
}[p] ?? `<span class="badge">${p}</span>`);

const fmtDate = d => d ? new Date(d).toLocaleDateString('en-BZ', { year:'numeric', month:'short', day:'numeric' }) : '—';
const fmtDateTime = d => d ? new Date(d).toLocaleString('en-BZ', { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' }) : '—';

let allTickets = [];
let activeFilter = '';

async function load(status = '') {
  document.getElementById('loading-row').style.display = '';
  document.getElementById('table-wrap').style.display  = 'none';
  document.getElementById('empty-row').style.display   = 'none';

  const url = API_URL + (status ? `?status=${encodeURIComponent(status)}` : '');
  const res  = await fetch(url);
  const json = await res.json();

  if (!json.success) return;

  // Update summary counts (always from full summary)
  const s = json.summary;
  document.querySelectorAll('.summary-count').forEach(el => {
    el.textContent = s[el.dataset.key] ?? 0;
  });

  allTickets = json.tickets;
  renderTable(allTickets);
}

function renderTable(tickets) {
  document.getElementById('loading-row').style.display = 'none';

  if (tickets.length === 0) {
    document.getElementById('empty-row').style.display  = '';
    document.getElementById('table-wrap').style.display = 'none';
    document.getElementById('ticket-count').textContent = 'No tickets';
    return;
  }

  document.getElementById('table-wrap').style.display = '';
  document.getElementById('empty-row').style.display  = 'none';
  document.getElementById('ticket-count').textContent = `${tickets.length} ticket${tickets.length !== 1 ? 's' : ''}`;

  document.getElementById('ticket-tbody').innerHTML = tickets.map(t => `
    <tr style="cursor:pointer;" onclick="openDetail(${t.id})">
      <td class="text-muted" style="font-size:.82rem;">#${t.id}</td>
      <td class="fw-medium">${escHtml(t.subject)}</td>
      <td class="text-muted" style="font-size:.85rem;">${escHtml(t.category)}</td>
      <td>${priorityBadge(t.priority)}</td>
      <td>${statusBadge(t.status)}</td>
      <td class="text-muted" style="font-size:.85rem;">${fmtDate(t.created_at)}</td>
      <td class="text-muted" style="font-size:.85rem;">${fmtDate(t.resolved_at)}</td>
      <td>
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-muted"><path d="M9 6l6 6l-6 6"/></svg>
      </td>
    </tr>
  `).join('');
}

function openDetail(id) {
  const t = allTickets.find(x => x.id == id);
  if (!t) return;

  document.getElementById('modal-subject').textContent    = t.subject;
  document.getElementById('modal-id').textContent         = `#${t.id}`;
  document.getElementById('modal-status').innerHTML       = statusBadge(t.status);
  document.getElementById('modal-category').textContent   = t.category;
  document.getElementById('modal-priority').innerHTML     = priorityBadge(t.priority);
  document.getElementById('modal-created').textContent    = fmtDateTime(t.created_at);
  document.getElementById('modal-resolved').textContent   = t.resolved_at ? fmtDateTime(t.resolved_at) : '—';
  document.getElementById('modal-description').textContent = t.description;

  const resSection = document.getElementById('resolution-section');
  if (t.resolution_notes) {
    document.getElementById('modal-resolution').textContent  = t.resolution_notes;
    document.getElementById('modal-resolved-by').textContent = t.resolved_by_name || 'IT Support';
    resSection.style.display = '';
  } else {
    resSection.style.display = 'none';
  }

  tabler.Modal.getOrCreateInstance(document.getElementById('ticket-modal')).show();
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Filter buttons
document.querySelectorAll('.filter-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.filter-btn').forEach(b => {
      b.className = b.className.replace(/\bbtn-(?!outline|sm)\w+/g, '').replace('btn-outline-', 'btn-outline-');
    });
    activeFilter = btn.dataset.status;
    // Toggle active style
    document.querySelectorAll('.filter-btn').forEach(b => {
      const isActive = b.dataset.status === activeFilter;
      const color = { '': 'primary', open: 'azure', in_progress: 'warning', resolved: 'success', closed: 'secondary' }[b.dataset.status];
      b.className = `btn btn-sm ${isActive ? `btn-${color}` : `btn-outline-${color}`} filter-btn`;
    });
    load(activeFilter);
  });
});

// Summary card click filters
document.querySelectorAll('.summary-card[data-filter]').forEach(card => {
  const f = card.dataset.filter;
  if (f === '') return;
  card.addEventListener('click', () => {
    document.querySelector(`.filter-btn[data-status="${f}"]`)?.click();
  });
});

load();
</script>

<?php require_once __DIR__ . '/../../includes/layout-tabler-footer.php'; ?>
