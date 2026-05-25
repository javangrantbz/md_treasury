<?php
declare(strict_types=1);

final class Totp
{
    private const DIGITS   = 6;
    private const PERIOD   = 30;
    private const ALGO     = 'sha1';
    private const SKEW     = 1; // allow ±1 window for clock drift

    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!ctype_digit($code) || strlen($code) !== self::DIGITS) {
            return false;
        }

        $counter = (int) floor(time() / self::PERIOD);
        $key     = self::base32Decode($secret);

        for ($i = -self::SKEW; $i <= self::SKEW; $i++) {
            if (hash_equals(self::hotp($key, $counter + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function otpauthUri(string $secret, string $accountLabel, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer . ':' . $accountLabel),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD
        );
    }

    // -------------------------------------------------------------------------
    // HOTP core (RFC 4226)
    // -------------------------------------------------------------------------

    private static function hotp(string $key, int $counter): string
    {
        $msg  = pack('J', $counter); // 8-byte big-endian
        $hash = hash_hmac(self::ALGO, $msg, $key, true);

        $offset = ord($hash[19]) & 0x0f;
        $code   = (
            (ord($hash[$offset])     & 0x7f) << 24 |
            (ord($hash[$offset + 1]) & 0xff) << 16 |
            (ord($hash[$offset + 2]) & 0xff) << 8  |
            (ord($hash[$offset + 3]) & 0xff)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    // -------------------------------------------------------------------------
    // Base32 (RFC 4648) — no external libraries needed
    // -------------------------------------------------------------------------

    public static function base32Encode(string $data): string
    {
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split(str_pad($binary, (int) ceil(strlen($binary) / 5) * 5, '0'), 5) as $chunk) {
            $output .= self::BASE32_CHARS[bindec($chunk)];
        }

        return str_pad($output, (int) ceil(strlen($output) / 8) * 8, '=');
    }

    private static function base32Decode(string $data): string
    {
        $data   = strtoupper(preg_replace('/=+$/', '', $data));
        $binary = '';

        foreach (str_split($data) as $char) {
            $pos = strpos(self::BASE32_CHARS, $char);
            if ($pos === false) {
                continue;
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split(substr($binary, 0, (int) floor(strlen($binary) / 8) * 8), 8) as $byte) {
            $output .= chr(bindec($byte));
        }

        return $output;
    }
}
