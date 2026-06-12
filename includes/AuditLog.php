<?php
declare(strict_types=1);

require_once __DIR__ . '/UAParser.php';

final class AuditLog
{
    /**
     * Records an audit event.
     * 
     * @param PDO $pdo
     * @param string $action      The action performed (e.g., 'create', 'update', 'delete', 'login')
     * @param string $entityType  The type of entity being acted upon (e.g., 'expense', 'user')
     * @param int|null $entityId  The ID of the entity
     * @param string $description Human readable description of the event
     * @param int|null $userId    The user performing the action (defaults to current session user)
     * @param array|null $oldData Data before the change (for diffing)
     * @param array|null $newData Data after the change (for diffing)
     * @param string $eventType   The category of event (e.g., 'DATA_CHANGE', 'SECURITY', 'SYSTEM')
     */
    public static function log(
        PDO $pdo,
        string $action,
        string $entityType,
        ?int $entityId,
        string $description = '',
        ?int $userId = null,
        ?array $oldData = null,
        ?array $newData = null,
        string $eventType = 'DATA_CHANGE'
    ): void {
        if ($userId === null) {
            $sessionUser = $_SESSION['user'] ?? null;
            $userId = ($sessionUser !== null && isset($sessionUser['id'])) ? (int)$sessionUser['id'] : null;
        }

        // Capture environment metadata
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $uaRaw = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
        
        // Parse user agent for better visibility
        $uaParsed = UAParser::parse($uaRaw);

        // Mask sensitive data in diffs (e.g., passwords)
        $sensitiveKeys = ['password', 'secret', 'mfa_secret', 'auth_key', 'token'];
        $cleanOld = $oldData ? self::maskSensitive($oldData, $sensitiveKeys) : null;
        $cleanNew = $newData ? self::maskSensitive($newData, $sensitiveKeys) : null;

        $pdo->prepare(
            "INSERT INTO audit_logs (
                user_id, event_type, event_action, entity_type, entity_id, 
                old_values, new_values, description, ip_address, user_agent,
                ua_browser, ua_os, ua_device
            ) VALUES (
                :user_id, :event_type, :event_action, :entity_type, :entity_id, 
                :old_values, :new_values, :description, :ip_address, :user_agent,
                :ua_browser, :ua_os, :ua_device
            )"
        )->execute([
            'user_id'      => $userId,
            'event_type'   => $eventType,
            'event_action' => strtoupper($action),
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'old_values'   => $cleanOld ? json_encode($cleanOld) : null,
            'new_values'   => $cleanNew ? json_encode($cleanNew) : null,
            'description'  => $description !== '' ? $description : null,
            'ip_address'   => $ip,
            'user_agent'   => $uaRaw,
            'ua_browser'   => $uaParsed['browser'],
            'ua_os'        => $uaParsed['os'],
            'ua_device'    => $uaParsed['device'],
        ]);
    }

    /**
     * Helper to mask sensitive keys in arrays before logging.
     */
    private static function maskSensitive(array $data, array $keys): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string)$key), $keys)) {
                $data[$key] = '********';
            } elseif (is_array($value)) {
                $data[$key] = self::maskSensitive($value, $keys);
            }
        }
        return $data;
    }
}
