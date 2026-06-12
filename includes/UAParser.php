<?php
declare(strict_types=1);

final class UAParser
{
    /**
     * Parses a User Agent string into components.
     * 
     * @param string|null $ua
     * @return array{browser: string, os: string, device: string}
     */
    public static function parse(?string $ua): array
    {
        if ($ua === null || $ua === '') {
            return [
                'browser' => 'Unknown',
                'os'      => 'Unknown',
                'device'  => 'Unknown'
            ];
        }

        $res = [
            'browser' => 'Unknown',
            'os'      => 'Unknown',
            'device'  => 'Desktop'
        ];

        // 1. Detect OS
        $os_patterns = [
            '/windows nt 10/i'      => 'Windows 10/11',
            '/windows nt 6.3/i'     => 'Windows 8.1',
            '/windows nt 6.2/i'     => 'Windows 8',
            '/windows nt 6.1/i'     => 'Windows 7',
            '/macintosh|mac os x/i' => 'Mac OS X',
            '/android/i'            => 'Android',
            '/iphone|ipad|ipod/i'   => 'iOS',
            '/linux/i'              => 'Linux',
        ];
        foreach ($os_patterns as $pattern => $os) {
            if (preg_match($pattern, $ua)) {
                $res['os'] = $os;
                break;
            }
        }

        // 2. Detect Browser
        $browser_patterns = [
            'Edge'    => '/Edge\/([\d\.]+)/i',
            'Edg'     => '/Edg\/([\d\.]+)/i',
            'OPR'     => '/OPR\/([\d\.]+)/i',
            'Opera'   => '/Opera\/([\d\.]+)/i',
            'Chrome'  => '/Chrome\/([\d\.]+)/i',
            'Firefox' => '/Firefox\/([\d\.]+)/i',
            'Safari'  => '/Version\/([\d\.]+).*Safari/i',
            'MSIE'    => '/MSIE\s([\d\.]+)/i',
            'Trident' => '/Trident\/.*rv:([\d\.]+)/i',
        ];

        foreach ($browser_patterns as $name => $pattern) {
            if (preg_match($pattern, $ua, $matches)) {
                $res['browser'] = ($name === 'OPR') ? 'Opera' : (($name === 'Edg') ? 'Edge' : $name);
                // Optionally append version if needed, but keeping it simple for now as requested
                // $res['browser'] .= ' ' . $matches[1];
                break;
            }
        }

        // 3. Detect Device Type
        if (preg_match('/mobile|android|iphone|ipad|ipod|blackberry|iemobile|opera mini/i', $ua)) {
            $res['device'] = 'Mobile';
        }

        return $res;
    }
}
