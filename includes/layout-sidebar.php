<?php
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
?>
<aside class="sidebar">
    <div class="brand">Treasury Revenue System</div>
    <nav>
		<a href="<?php echo url('views/portal/index.php'); ?>">Portal</a>
		<a href="<?php echo url('views/cashiering/dashboard.php'); ?>">Cashiering</a>
		<a href="<?php echo url('api/auth/logout.php'); ?>">Logout</a>
    </nav>
	
</aside>
