<?php
require_once __DIR__ . '/Auth.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Treasury Revenue System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="<?php echo url('assets/css/app.css'); ?>" rel="stylesheet">
	<script>
    const BASE_URL = "<?php echo BASE_URL; ?>";
</script>
</head>
<body>
<div style="background: #1a1a1a; color: #fff; padding: 4px 12px; display: flex; align-items: center; justify-content: space-between; font-size: 10px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; position: relative; z-index: 9999;">
    <div style="display: flex; align-items: center; gap: 6px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Official Government of Belize System
    </div>
    <div style="opacity: 0.7;">Restricted Access &bull; Session Audited</div>
</div>
<div class="app-shell">

