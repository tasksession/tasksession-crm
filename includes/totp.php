<?php
/**
 * RFC 6238 TOTP (HMAC-SHA1, 6 digits, 30s) for Google Authenticator / Authy.
 * No Composer dependency.
 */

if (!function_exists('totp_base32_alphabet')) {
    function totp_base32_alphabet()
    {
        return 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    }
}

if (!function_exists('totp_random_secret')) {
    function totp_random_secret($bytes = 20)
    {
        $raw = random_bytes(max(10, (int) $bytes));
        return totp_base32_encode($raw);
    }
}

if (!function_exists('totp_base32_encode')) {
    function totp_base32_encode($data)
    {
        $alphabet = totp_base32_alphabet();
        $binary = '';
        foreach (str_split((string) $data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $binary = str_pad($binary, (int) (ceil(strlen($binary) / 5) * 5), '0', STR_PAD_RIGHT);
        $out = '';
        foreach (str_split($binary, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                break;
            }
            $out .= $alphabet[bindec($chunk)];
        }
        return $out;
    }
}

if (!function_exists('totp_base32_decode')) {
    function totp_base32_decode($secret)
    {
        $alphabet = totp_base32_alphabet();
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', (string) $secret));
        if ($secret === '') {
            return '';
        }
        $binary = '';
        $len = strlen($secret);
        for ($i = 0; $i < $len; $i++) {
            $pos = strpos($alphabet, $secret[$i]);
            if ($pos === false) {
                return '';
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($binary, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }
        return $bytes;
    }
}

if (!function_exists('totp_time_slice')) {
    function totp_time_slice($timestamp = null, $period = 30)
    {
        $timestamp = $timestamp === null ? time() : (int) $timestamp;
        $period = max(1, (int) $period);
        return (int) floor($timestamp / $period);
    }
}

if (!function_exists('totp_code_at')) {
    function totp_code_at($secret, $timeSlice, $digits = 6)
    {
        $key = totp_base32_decode($secret);
        if ($key === '') {
            return '';
        }
        $timeSlice = (int) $timeSlice;
        $binCounter = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = unpack('N', substr($hash, $offset, 4));
        $value = ($truncated[1] & 0x7FFFFFFF) % (10 ** $digits);
        return str_pad((string) $value, $digits, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('totp_verify')) {
    /**
     * @return array{ok:bool,slice:?int}
     */
    function totp_verify($secret, $code, $window = 2, $timestamp = null)
    {
        $code = preg_replace('/\s+/', '', (string) $code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return array('ok' => false, 'slice' => null);
        }
        $center = totp_time_slice($timestamp);
        $window = max(0, (int) $window);
        for ($i = -$window; $i <= $window; $i++) {
            $slice = $center + $i;
            $expected = totp_code_at($secret, $slice);
            if ($expected !== '' && hash_equals($expected, $code)) {
                return array('ok' => true, 'slice' => $slice);
            }
        }
        return array('ok' => false, 'slice' => null);
    }
}

if (!function_exists('totp_secret_display')) {
    function totp_secret_display($secret)
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', (string) $secret));
        return trim(chunk_split($secret, 4, ' '));
    }
}

if (!function_exists('totp_otpauth_uri')) {
    function totp_otpauth_uri($secret, $account, $issuer)
    {
        $issuer = trim((string) $issuer);
        if ($issuer === '') {
            $issuer = 'TaskSession';
        }
        $account = trim((string) $account);
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', (string) $secret));
        $label = rawurlencode($issuer . ':' . $account);
        return 'otpauth://totp/' . $label
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }
}

if (!function_exists('totp_qr_svg')) {
    function totp_qr_svg($payload)
    {
        $qrFile = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'TCPDF' . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'barcodes' . DIRECTORY_SEPARATOR . 'qrcode.php';
        if (!is_file($qrFile)) {
            return '';
        }
        require_once $qrFile;
        if (!class_exists('QRcode', false)) {
            return '';
        }
        try {
            $qr = new QRcode((string) $payload, 'M');
            $arr = $qr->getBarcodeArray();
        } catch (Throwable $e) {
            return '';
        }
        $rows = (int) ($arr['num_rows'] ?? 0);
        $cols = (int) ($arr['num_cols'] ?? 0);
        $bcode = $arr['bcode'] ?? array();
        if ($rows < 1 || $cols < 1 || !is_array($bcode)) {
            return '';
        }
        $mod = 4;
        $quiet = 4 * $mod;
        $w = ($cols * $mod) + ($quiet * 2);
        $h = ($rows * $mod) + ($quiet * 2);
        $rects = '';
        for ($r = 0; $r < $rows; $r++) {
            $line = $bcode[$r] ?? array();
            for ($c = 0; $c < $cols; $c++) {
                if (!empty($line[$c])) {
                    $rects .= '<rect x="' . ($quiet + $c * $mod) . '" y="' . ($quiet + $r * $mod) . '" width="' . $mod . '" height="' . $mod . '" fill="#000"/>';
                }
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" width="168" height="168" shape-rendering="crispEdges" role="img" aria-label="Authenticator QR code" style="display:block"><rect width="100%" height="100%" fill="#fff"/>' . $rects . '</svg>';
    }
}

if (!function_exists('totp_qr_frame')) {
    function totp_qr_frame($svg)
    {
        $svg = (string) $svg;
        if ($svg === '') {
            return '';
        }
        return '<div class="d-inline-block p-3" style="background: var(--card-body-color, #fff); border: 1px solid var(--border-color); border-radius: var(--card-border-radius, 12px); box-shadow: var(--box-shadow, 0 3px 13px -5px rgb(0 0 0 / 0.08)); line-height: 0;">' . $svg . '</div>';
    }
}

if (!function_exists('totp_recovery_codes_plain')) {
    /**
     * @return string[]
     */
    function totp_recovery_codes_plain($count = 8)
    {
        $out = array();
        $n = max(4, min(12, (int) $count));
        for ($i = 0; $i < $n; $i++) {
            $out[] = strtoupper(bin2hex(random_bytes(5)));
        }
        return $out;
    }
}

if (!function_exists('totp_hash_recovery_code')) {
    function totp_hash_recovery_code($code)
    {
        $code = strtoupper(preg_replace('/\s+/', '', (string) $code));
        return password_hash($code, PASSWORD_BCRYPT);
    }
}

if (!function_exists('totp_normalize_recovery_code')) {
    function totp_normalize_recovery_code($code)
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $code));
    }
}
