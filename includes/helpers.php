<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';


function url(string $path): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function abs_url(string $path): string
{
    $base = BASE_URL;
    if (substr($base, 0, 4) === 'http') {
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/' . ltrim(rtrim($base, '/') . '/' . ltrim($path, '/'), '/');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Sends government-grade security headers.
 */
function sendSecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }

    // Prevent framing (Clickjacking protection)
    header('X-Frame-Options: SAMEORIGIN');

    // Prevent MIME-type sniffing
    header('X-Content-Type-Options: nosniff');

    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // XSS Protection for older browsers
    header('X-XSS-Protection: 1; mode=block');

    // Force HTTPS (HSTS) - 1 year
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

    // Content Security Policy (CSP)
    // Adjust these policies as needed for your specific JS/CSS assets
    $csp = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://fonts.googleapis.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com",
        "img-src 'self' data: blob:",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
        "upgrade-insecure-requests"
    ];
    header('Content-Security-Policy: ' . implode('; ', $csp));
}

// Automatically send headers for all requests
sendSecurityHeaders();

function isApiRequest(): bool
{
    return strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
}

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function post(string $key, ?string $default = null): ?string
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

function get(string $key, ?string $default = null): ?string
{
    return isset($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function wantsJson(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

    return strpos($accept, 'application/json') !== false
        || strtolower($requestedWith) === 'xmlhttprequest';
}

function requestData(): array
{
    if (!empty($_POST)) {
        return $_POST;
    }

    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function apiResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function requirePost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        apiResponse([
            'success' => false,
            'message' => 'Method not allowed.'
        ], 405);
    }
}

function requireGet(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        apiResponse([
            'success' => false,
            'message' => 'Method not allowed.'
        ], 405);
    }
}

function flash(string $key, string $message): void
{
    $_SESSION[$key] = $message;
}

function flashGet(string $key): ?string
{
    if (!isset($_SESSION[$key])) {
        return null;
    }

    $message = $_SESSION[$key];
    unset($_SESSION[$key]);
    return $message;
}