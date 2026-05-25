<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/Rbac.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.roles.manage');
requireGet();

$roleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : 0;
if ($roleId <= 0) {
    jsonResponse(['success' => false, 'message' => 'role_id is required.'], 422);
}

// Verify role exists
$stmt = $pdo->prepare("SELECT id, name, code, status FROM roles WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $roleId]);
$role = $stmt->fetch();
if (!$role) {
    jsonResponse(['success' => false, 'message' => 'Role not found.'], 404);
}

// All permissions grouped by module
$allPerms = $pdo->query("SELECT id, code, name, description, module FROM permissions ORDER BY module, id")->fetchAll();

// Permissions currently granted to this role
$stmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = :role_id");
$stmt->execute(['role_id' => $roleId]);
$granted = array_column($stmt->fetchAll(), 'permission_id');
$grantedSet = array_flip($granted);

// Group permissions by module
$grouped = [];
foreach ($allPerms as $p) {
    $grouped[$p['module']][] = [
        'id'          => $p['id'],
        'code'        => $p['code'],
        'name'        => $p['name'],
        'description' => $p['description'],
        'granted'     => isset($grantedSet[$p['id']]),
    ];
}

jsonResponse([
    'success' => true,
    'role'    => $role,
    'permissions' => $grouped,
]);
