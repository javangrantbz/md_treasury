<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Auth.php';

final class Rbac
{
    public static function permissions(PDO $pdo, int $userId): array
    {
        $sql = "SELECT DISTINCT p.code
                FROM user_roles ur
                INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
                INNER JOIN permissions p ON p.id = rp.permission_id
                WHERE ur.user_id = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return array_column($stmt->fetchAll(), 'code');
    }

    public static function has(PDO $pdo, string $permissionCode): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        static $cache = [];
        $userId = (int) $user['id'];

        if (!isset($cache[$userId])) {
            $cache[$userId] = self::permissions($pdo, $userId);
        }

        return in_array($permissionCode, $cache[$userId], true);
    }

    public static function require(PDO $pdo, string $permissionCode): void
    {
        if (!self::has($pdo, $permissionCode)) {
            if (isApiRequest()) {
                jsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
            }
            require __DIR__ . '/../views/errors/403.php';
            exit;
        }
    }
}
