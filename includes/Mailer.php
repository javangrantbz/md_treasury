<?php
declare(strict_types=1);

/**
 * Minimal dependency-free SMTP mailer.
 *
 * Speaks SMTP over a raw socket (no PHPMailer/composer required) with support
 * for implicit SSL (port 465), STARTTLS (port 587) and AUTH LOGIN. Sends a
 * multipart/alternative message (plain text + HTML).
 *
 * Configure via constants in config/env.php:
 *   SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS,
 *   SMTP_SECURE ('ssl' | 'tls' | ''), SMTP_FROM, SMTP_FROM_NAME
 *
 * PHP 7 compatible (no 8.0+ syntax).
 */
class Mailer
{
    /** @var string|null Transcript of the last SMTP conversation (for debugging). */
    public static $lastLog = '';

    /**
     * Send an email. Returns true on success; on failure returns false and sets $error.
     */
    public static function send(string $toEmail, string $toName, string $subject, string $html, string $text, string &$error = null): bool
    {
        self::$lastLog = '';

        $host = defined('SMTP_HOST') ? (string)SMTP_HOST : '';
        if ($host === '') {
            $error = 'SMTP is not configured. Set SMTP_HOST in config/env.php.';
            return false;
        }
        $port   = defined('SMTP_PORT')      ? (int)SMTP_PORT          : 587;
        $user   = defined('SMTP_USER')      ? (string)SMTP_USER       : '';
        $pass   = defined('SMTP_PASS')      ? (string)SMTP_PASS       : '';
        $secure = defined('SMTP_SECURE')    ? strtolower((string)SMTP_SECURE) : 'tls';
        $from   = defined('SMTP_FROM')      ? (string)SMTP_FROM       : $user;
        $fromNm = defined('SMTP_FROM_NAME') ? (string)SMTP_FROM_NAME  : 'Treasury Revenue System';

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid recipient email address.';
            return false;
        }
        if ($from === '') {
            $error = 'SMTP sender (SMTP_FROM) is not configured.';
            return false;
        }

        $timeout = 20;
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]);

        $remote = (($secure === 'ssl') ? 'ssl://' : '') . $host . ':' . $port;
        $errno = 0; $errstr = '';
        $fp = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            $error = 'Could not connect to mail server (' . $errno . ' ' . $errstr . ').';
            return false;
        }
        stream_set_timeout($fp, $timeout);

        $ok = true;
        try {
            if (!self::expect($fp, 220, $error)) { fclose($fp); return false; }

            $ehloHost = self::ehloName();
            self::write($fp, 'EHLO ' . $ehloHost);
            if (!self::expect($fp, 250, $error)) { fclose($fp); return false; }

            if ($secure === 'tls') {
                self::write($fp, 'STARTTLS');
                if (!self::expect($fp, 220, $error)) { fclose($fp); return false; }
                $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
                }
                if (!@stream_socket_enable_crypto($fp, true, $crypto)) {
                    $error = 'Failed to start TLS encryption with the mail server.';
                    fclose($fp);
                    return false;
                }
                // Re-issue EHLO over the now-encrypted channel.
                self::write($fp, 'EHLO ' . $ehloHost);
                if (!self::expect($fp, 250, $error)) { fclose($fp); return false; }
            }

            if ($user !== '') {
                self::write($fp, 'AUTH LOGIN');
                if (!self::expect($fp, 334, $error)) { fclose($fp); return false; }
                self::write($fp, base64_encode($user));
                if (!self::expect($fp, 334, $error)) { fclose($fp); return false; }
                self::write($fp, base64_encode($pass));
                if (!self::expect($fp, 235, $error)) {
                    $error = 'SMTP authentication failed. Check SMTP_USER / SMTP_PASS.';
                    fclose($fp);
                    return false;
                }
            }

            self::write($fp, 'MAIL FROM:<' . $from . '>');
            if (!self::expect($fp, 250, $error)) { fclose($fp); return false; }

            self::write($fp, 'RCPT TO:<' . $toEmail . '>');
            if (!self::expect($fp, [250, 251], $error)) { fclose($fp); return false; }

            self::write($fp, 'DATA');
            if (!self::expect($fp, 354, $error)) { fclose($fp); return false; }

            $message = self::buildMessage($from, $fromNm, $toEmail, $toName, $subject, $html, $text);
            // Dot-stuffing + terminating sequence.
            self::writeRaw($fp, $message . "\r\n.\r\n");
            if (!self::expect($fp, 250, $error)) { fclose($fp); return false; }

            self::write($fp, 'QUIT');
            // Don't fail the send if QUIT's 221 is slow; we're already accepted.
            @fgets($fp, 515);
        } catch (Throwable $e) {
            $error = 'Mail error: ' . $e->getMessage();
            $ok = false;
        }

        fclose($fp);
        return $ok;
    }

    private static function buildMessage(string $from, string $fromNm, string $to, string $toName, string $subject, string $html, string $text): string
    {
        $boundary = 'bnd_' . bin2hex(random_bytes(12));
        $eol = "\r\n";

        $fromHeader = self::formatAddress($from, $fromNm);
        $toHeader   = self::formatAddress($to, $toName);

        $h  = 'Date: ' . date('r') . $eol;
        $h .= 'From: ' . $fromHeader . $eol;
        $h .= 'To: ' . $toHeader . $eol;
        $h .= 'Subject: ' . self::encodeHeader($subject) . $eol;
        $h .= 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . self::ehloName() . '>' . $eol;
        $h .= 'MIME-Version: 1.0' . $eol;
        $h .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . $eol;

        $b  = '--' . $boundary . $eol;
        $b .= 'Content-Type: text/plain; charset=UTF-8' . $eol;
        $b .= 'Content-Transfer-Encoding: 8bit' . $eol . $eol;
        $b .= self::dotStuff(self::normalize($text)) . $eol . $eol;

        $b .= '--' . $boundary . $eol;
        $b .= 'Content-Type: text/html; charset=UTF-8' . $eol;
        $b .= 'Content-Transfer-Encoding: 8bit' . $eol . $eol;
        $b .= self::dotStuff(self::normalize($html)) . $eol . $eol;

        $b .= '--' . $boundary . '--' . $eol;

        return $h . $eol . $b;
    }

    private static function formatAddress(string $email, string $name): string
    {
        $name = trim($name);
        if ($name === '') return '<' . $email . '>';
        return self::encodeHeader($name) . ' <' . $email . '>';
    }

    private static function encodeHeader(string $s): string
    {
        // RFC 2047 encode if non-ASCII present.
        if (preg_match('/[^\x20-\x7E]/', $s)) {
            return '=?UTF-8?B?' . base64_encode($s) . '?=';
        }
        return $s;
    }

    private static function normalize(string $s): string
    {
        // Normalize all line endings to CRLF.
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        return str_replace("\n", "\r\n", $s);
    }

    private static function dotStuff(string $s): string
    {
        // A line starting with '.' must be doubled per RFC 5321.
        return preg_replace('/^\./m', '..', $s);
    }

    private static function ehloName(): string
    {
        $name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
        if ($name === '' && isset($_SERVER['HTTP_HOST'])) $name = $_SERVER['HTTP_HOST'];
        $name = preg_replace('/[^A-Za-z0-9\.\-]/', '', (string)$name);
        return $name !== '' ? $name : 'localhost';
    }

    private static function write($fp, string $line): void
    {
        self::$lastLog .= 'C: ' . $line . "\n";
        fwrite($fp, $line . "\r\n");
    }

    private static function writeRaw($fp, string $data): void
    {
        self::$lastLog .= "C: [message body]\n";
        fwrite($fp, $data);
    }

    /**
     * Read a (possibly multiline) SMTP reply and check the code.
     * @param int|int[] $expected
     */
    private static function expect($fp, $expected, string &$error = null): bool
    {
        $code = self::readResponse($fp, $full);
        $expected = is_array($expected) ? $expected : [$expected];
        if (in_array($code, $expected, true)) {
            return true;
        }
        $error = 'Mail server rejected the request (' . $code . '): ' . trim((string)$full);
        return false;
    }

    private static function readResponse($fp, string &$full = null): int
    {
        $full = '';
        $code = 0;
        while (($line = fgets($fp, 515)) !== false) {
            self::$lastLog .= 'S: ' . rtrim($line) . "\n";
            $full .= $line;
            $code = (int)substr($line, 0, 3);
            // Continuation lines look like "250-..."; the final line is "250 ...".
            if (strlen($line) < 4 || substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return $code;
    }
}
