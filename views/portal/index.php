<?php
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/Rbac.php';
Auth::requireAuth();

$authUser       = Auth::user();
$fullName       = Auth::fullName();
$roleName       = !empty($authUser['role_names']) ? $authUser['role_names'][0] : null;
$departmentName = $authUser['department_name'] ?? 'Treasury Department';
$lastLoginAt    = $authUser['last_login_at'] ?? null;
$lastLoginFmt   = $lastLoginAt ? date('F j, Y \a\t g:i A', strtotime($lastLoginAt)) : 'First session';

require_once __DIR__ . '/../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../includes/layout-tabler-sidebar.php';
?>



    <!-- Page header -->
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row align-items-center">
                <div class="col">
                    <div class="page-pretitle">Government of Belize</div>
                    <h2 class="page-title">Treasury Department — Integrated Financial Portal</h2>
                </div>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">

            <!-- Welcome banner -->
            <div class="card mb-4" style="border-left: 4px solid var(--tblr-primary);">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar avatar-lg bg-primary text-white fw-bold position-relative" style="font-size:1.2rem;flex-shrink:0">
                            <?php echo strtoupper(substr($authUser['first_name'] ?? 'U', 0, 1) . substr($authUser['last_name'] ?? '', 0, 1)); ?>
                            <a href="<?php echo url('views/profile/index.php'); ?>" class="badge bg-white text-primary position-absolute bottom-0 end-0 p-1 border shadow-sm" style="transform: translate(25%, 25%);" title="Update Profile">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-inline m-0" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                            </a>
                        </div>
                        <div class="flex-fill">
                            <div class="fw-bold fs-4 mb-0"><?php echo h($fullName); ?></div>
                            <div class="text-muted" style="font-size:.88rem;">
                                <?php if ($roleName): ?>
                                    <?php echo h($roleName); ?> &mdash; <?php echo h($departmentName); ?>
                                <?php else: ?>
                                    <?php echo h($departmentName); ?>
                                <?php endif; ?>
                            </div>
                            <div class="mt-1" style="font-size:.78rem;color:var(--tblr-secondary);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1" style="vertical-align:-1px"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                                Last Login: <?php echo h($lastLoginFmt); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Module heading -->
            <div class="row mb-2 mt-2 align-items-center">
                <div class="col">
                    <h3 class="mb-0">System Modules</h3>
                    <p class="text-muted mb-0" style="font-size:.85rem;">
                        Access is governed by your assigned role and permissions.
                    </p>
                </div>
            </div>

            <!-- Module cards -->
            <div class="row row-cards g-3">

                <!-- Cashiering — active -->
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="<?php echo url('views/cashiering/dashboard.php'); ?>" class="card card-link card-link-pop h-100 text-decoration-none" style="background:rgba(32,107,196,.03);">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <span class="bg-primary text-white rounded p-2 me-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-md" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 21v-16a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16l-3-2l-2 2l-2-2l-2 2l-2-2l-3 2"/>
                                        <path d="M14 8h-2.5a1.5 1.5 0 0 0 0 3h1a1.5 1.5 0 0 1 0 3h-2.5"/>
                                        <path d="M12 17v1"/><path d="M12 6v1"/>
                                    </svg>
                                </span>
                                <div>
                                    <div class="fw-bold fs-4">Cashiering</div>
                                    <span class="badge bg-success-lt text-success">Active</span>
                                </div>
                            </div>
                            <p class="text-muted mb-0">
                                Manage receipts, transactions, expenses, registers, and related master data.
                            </p>
                        </div>
                    </a>
                </div>

                <!-- Pay-In — active -->
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="<?php echo url('views/pay-in/index.php'); ?>" class="card card-link card-link-pop h-100 text-decoration-none" style="background:rgba(32,107,196,.03);">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <span class="bg-primary text-white rounded p-2 me-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-md" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 3m0 2a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                        <path d="M3 10v6a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6"/>
                                        <path d="M7 15h.01"/><path d="M11 15h2"/>
                                    </svg>
                                </span>
                                <div>
                                    <div class="fw-bold fs-4">Pay-In (POS)</div>
                                    <span class="badge bg-warning-lt text-warning">Pending Integration</span>
                                </div>
                            </div>
                            <p class="text-muted mb-0">
                                Point-of-sale receipting system. Revenue collected here flows into Cashiering automatically.
                            </p>
                        </div>
                    </a>
                </div>

                <!-- National Savings Bank — active -->
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="<?php echo url('views/nsb/dashboard.php'); ?>" class="card card-link card-link-pop h-100 text-decoration-none" style="background:rgba(32,107,196,.03);">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <span class="bg-primary text-white rounded p-2 me-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-md" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 21l18 0"/><path d="M3 10l18 0"/>
                                        <path d="M5 6l7-3l7 3"/><path d="M4 10l0 11"/><path d="M20 10l0 11"/>
                                        <path d="M8 14l0 3"/><path d="M12 14l0 3"/><path d="M16 14l0 3"/>
                                    </svg>
                                </span>
                                <div>
                                    <div class="fw-bold fs-4">National Savings Bank</div>
                                    <span class="badge bg-warning-lt text-warning">Pending Integration</span>
                                </div>
                            </div>
                            <p class="text-muted mb-0">
                                Savings account management, deposits, withdrawals, and statements.
                            </p>
                        </div>
                    </a>
                </div>

            </div><!-- /.row -->

            <!-- Footer note -->
            <div class="mt-5 text-center" style="font-size:.78rem;border-top:2px solid var(--tblr-border-color);padding-top:1rem;color:#4a5568;">
                <a href="https://pressoffice.gov.bz" target="_blank" rel="noopener" style="color:inherit;text-decoration:none;">Government of Belize</a>
                &mdash;
                <a href="https://mof.gov.bz" target="_blank" rel="noopener" style="color:inherit;text-decoration:none;">Ministry of Finance</a>
                &bull; Treasury Department
                <br>
                <span style="display:inline-flex;align-items:center;gap:5px;margin-top:.35rem;font-weight:600;color:#2d3748;letter-spacing:.02em;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3l-8 3v7c0 6 8 10 8 10z"/></svg>
                    Authorised users only. All activity is logged and audited.
                </span>
            </div>

        </div>
    </div>



<?php require_once __DIR__ . '/../../includes/layout-tabler-footer.php'; ?>
