<?php
require_once __DIR__ . '/Auth.php';
$authUser = Auth::user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Treasury Revenue System</title>
    <link rel="stylesheet" href="<?php echo url('assets/css/tabler.css'); ?>">
    <script>const BASE_URL = "<?php echo BASE_URL; ?>";</script>
    <style>
      .modal-header { padding: .6rem 1rem; }
      .modal-footer { padding: .5rem 1rem; }
      .modal-body { padding: .75rem 1rem; }
      .modal-body .mb-3 { margin-bottom: .5rem !important; }
      .modal-body .mb-1 { margin-bottom: .25rem !important; }
      .modal-body .form-label { margin-bottom: .2rem; font-size: .8125rem; }
      .modal-body .form-control,
      .modal-body .form-select { padding: .3rem .6rem; font-size: .875rem; }
      .modal-body textarea.form-control { padding: .3rem .6rem; }
      .modal-body .row.g-3 { --bs-gutter-y: .5rem; }
      .modal-body hr.my-3 { margin-top: .75rem !important; margin-bottom: .75rem !important; }
    </style>
</head>
<body class="antialiased">
<div class="bg-dark text-white py-1 px-3 d-flex align-items-center justify-content-between" style="font-size: .65rem; letter-spacing: .05em; text-transform: uppercase; font-weight: 600; position: relative; z-index: 9999;">
    <div class="d-flex align-items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-xs" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3" /></svg>
        Official Government of Belize System
    </div>
    <div class="d-none d-md-block opacity-75">
        Restricted Access &bull; Session Audited
    </div>
</div>
<div class="wrapper">
