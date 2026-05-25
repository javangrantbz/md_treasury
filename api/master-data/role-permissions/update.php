<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/Rbac.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.roles.manage');
requirePost();

$body = json_decode(file_get_contents('php://input'), true);
$roleId      = isset($body['role_id'])      ? (int)$body['role_id']      : 0;
$permissionIds = $body['permission_ids'] ?? [];

if ($roleId <= 0) {
    jsonResponse(['success' => false, 'message' => 'role_id is required.'], 422);
}

// Verify role exists
$stmt = $pdo->prepare("SELECT id FROM roles WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $roleId]);
if (!$stmt->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Role not found.'], 404);
}

// Validate all permission IDs exist
if (!empty($permissionIds)) {
    $permissionIds = array_map('intval', $permissionIds);
    $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));
    $check = $pdo->prepare("SELECT COUNT(*) FROM permissions WHERE id IN ($placeholders)");
    $check->execute($permissionIds);
    if ((int)$check->fetchColumn() !== count($permissionIds)) {
        jsonResponse(['success' => false, 'message' => 'One or more permission IDs are invalid.'], 422);
    }
}

$pdo->beginTransaction();
try {
    $pdo->prepare("DELETE FROM role_permissions WHERE role_id = :role_id")->execute(['role_id' => $roleId]);

    if (!empty($permissionIds)) {
        $insert = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)");
        foreach ($permissionIds as $pid) {
            $insert->execute(['role_id' => $roleId, 'permission_id' => $pid]);
        }
    }

    $pdo->commit();
    try { AuditLog::log($pdo, 'update', 'role', $roleId, 'Permissions updated for role #' . $roleId . '.'); } catch (Throwable $e) {}
    jsonResponse(['success' => true, 'message' => 'Permissions updated successfully.']);
} catch (Throwable $e) {
    $pdo->rollBack();
    jsonResponse(['success' => false, 'message' => 'Failed to update permissions.'], 500);
}
