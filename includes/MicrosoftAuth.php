<?php
declare(strict_types=1);

final class MicrosoftAuth
{
    /**
     * Generate the Microsoft Login URL.
     */
    public static function getLoginUrl(string $redirectUri): string
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['microsoft_oauth_state'] = $state;

        $params = [
            'client_id'     => ENV_MICROSOFT_CLIENT_ID,
            'response_type' => 'code',
            'redirect_uri'  => $redirectUri,
            'response_mode' => 'query',
            'scope'         => 'openid profile email',
            'state'         => $state,
            'prompt'        => 'select_account'
        ];

        return "https://login.microsoftonline.com/" . ENV_MICROSOFT_TENANT_ID . "/oauth2/v2.0/authorize?" . http_build_query($params);
    }

    /**
     * Handle the callback from Microsoft.
     */
    public static function handleCallback(PDO $pdo, string $code, string $state, string $redirectUri): array
    {
        // 1. Verify State
        if (empty($state) || $state !== ($_SESSION['microsoft_oauth_state'] ?? '')) {
            return ['success' => false, 'message' => 'Invalid session state.'];
        }
        unset($_SESSION['microsoft_oauth_state']);

        // 2. Exchange Code for Token
        $tokenUrl = "https://login.microsoftonline.com/" . ENV_MICROSOFT_TENANT_ID . "/oauth2/v2.0/token";
        $postData = [
            'client_id'     => ENV_MICROSOFT_CLIENT_ID,
            'client_secret' => ENV_MICROSOFT_CLIENT_SECRET,
            'code'          => $code,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return ['success' => false, 'message' => 'Failed to retrieve token from Microsoft.'];
        }

        $tokenData = json_decode($response, true);
        $idToken = $tokenData['id_token'] ?? null;

        if (!$idToken) {
            return ['success' => false, 'message' => 'ID Token missing from Microsoft response.'];
        }

        // 3. Decode and Validate User
        $payload = self::decodeIdToken($idToken);
        $email = $payload['email'] ?? $payload['preferred_username'] ?? null;

        if (!$email) {
            return ['success' => false, 'message' => 'Could not determine user email from Microsoft.'];
        }

        // 4. Match with Local User
        $stmt = $pdo->prepare("SELECT id, auth_source, status FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'message' => "User account ($email) not found in the system."];
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Your account is currently inactive.'];
        }

        // Restriction: Only allow microsoft auth for users configured with it
        if ($user['auth_source'] !== 'microsoft') {
            return ['success' => false, 'message' => 'This account is not configured to use Microsoft login.'];
        }

        // 5. Sync profile fields from Microsoft token into DB
        // Store claim keys in session temporarily for debugging
        $_SESSION['_debug_ms_claims'] = array_keys($payload);

        $givenName  = trim($payload['given_name']  ?? '');
        $familyName = trim($payload['family_name'] ?? '');

        // Fallback: split the always-present 'name' claim on last space
        if ($givenName === '' && $familyName === '' && !empty($payload['name'])) {
            $nameParts  = explode(' ', trim($payload['name']), 2);
            $givenName  = $nameParts[0];
            $familyName = isset($nameParts[1]) ? $nameParts[1] : '';
        }

        if ($givenName !== '' || $familyName !== '') {
            $syncFields = [];
            $syncParams = ['id' => (int)$user['id']];
            if ($givenName !== '') {
                $syncFields[] = 'first_name = :first_name';
                $syncParams['first_name'] = $givenName;
            }
            if ($familyName !== '') {
                $syncFields[] = 'last_name = :last_name';
                $syncParams['last_name'] = $familyName;
            }
            $pdo->prepare("UPDATE users SET " . implode(', ', $syncFields) . " WHERE id = :id")
                ->execute($syncParams);
        }

        // 6. Complete Login
        require_once __DIR__ . '/Auth.php';
        Auth::completeLogin($pdo, (int)$user['id']);

        return ['success' => true, 'redirect' => url('views/portal/index.php')];
    }

    /**
     * Decodes the JWT ID Token (In production, you should also verify the signature).
     */
    private static function decodeIdToken(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) < 2) return [];
        $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
        return json_decode($payload, true) ?? [];
    }
}
