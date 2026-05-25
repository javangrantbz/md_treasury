<?php
// Reached only via Rbac::require() — auth and session are already verified.
http_response_code(403);
require_once __DIR__ . '/../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-body d-flex align-items-center" style="min-height:60vh;">
    <div class="container-xl">
      <div class="empty py-5">

        <div class="empty-icon mb-3">
          <svg xmlns="http://www.w3.org/2000/svg" width="72" height="72" viewBox="0 0 24 24"
               fill="none" stroke="var(--tblr-danger)" stroke-width="1.25"
               stroke-linecap="round" stroke-linejoin="round">
            <rect x="5" y="11" width="14" height="10" rx="2"/>
            <circle cx="12" cy="16" r="1" fill="var(--tblr-danger)" stroke="none"/>
            <path d="M8 11V7a4 4 0 0 1 8 0v4"/>
          </svg>
        </div>

        <p class="empty-title">Access Denied</p>
        <p class="empty-subtitle text-muted">
          You don't have permission to view this page.<br>
          Contact your system administrator if you believe this is an error.
        </p>

        <div class="empty-action gap-2 d-flex justify-content-center flex-wrap">
          <a href="<?php echo url('views/portal/index.php'); ?>" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 12l9-9l9 9"/><path d="M9 21V9h6v12"/>
            </svg>
            Go to Portal
          </a>
          <a href="javascript:history.back();" class="btn btn-outline-secondary">Go Back</a>
        </div>

      </div>
    </div>
  </div>

</div>
<?php require_once __DIR__ . '/../../includes/layout-tabler-footer.php'; ?>
