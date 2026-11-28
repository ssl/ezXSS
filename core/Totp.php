<?php
class Totp
{
    /**
     * RFC 4648 base32 alphabet (no padding used).
     */
    const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Default time step in seconds.
     */
    const STEP = 30;

    /**
     * Default number of digits.
     */
    const DIGITS = 6;

    /**
     * Generate a new random base32 secret.
     *
     * @param int $bytes
     */
    public static function generateSecret($bytes = 20)
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /**
     * Build an otpauth:// provisioning URI for authenticator apps / QR codes.
     *
     * @param string $secret
     * @param string $label
     * @param string $issuer
     * @return string
     */
    public static function otpauthUri($secret, $label, $issuer = 'ezXSS')
    {
        $label = $issuer . ':' . $label;
        $query = http_build_query(array(
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => self::DIGITS,
            'period'    => self::STEP,
        ));
        return 'otpauth://totp/' . rawurlencode($label) . '?' . $query;
    }

    /**
     * Current TOTP code for a base32 secret.
     *
     * @param string $secret
     * @param int $time
     */
    public static function codeAt($secret, $time = null)
    {
        $key = self::base32Decode($secret);
        return self::totpAt($key, $time);
    }

    /**
     * Verify a submitted code against a base32 secret with +/-$window tolerance.
     *
     * @param string $secret
     * @param string $code
     * @param int $window
     * @param int $time
     * @return bool
     */
    public static function verify($secret, $code, $window = 1, $time = null)
    {
        $code = preg_replace('/\s+/', '', (string) $code);
        if (!preg_match('/^\d{' . self::DIGITS . '}$/', $code)) {
            return false;
        }
        $secret = strtoupper(preg_replace('/\s+/', '', (string) $secret));
        if ($secret === '' || preg_match('/[^A-Z2-7]/', $secret)) {
            return false;
        }

        $key = self::base32Decode($secret);
        if ($key === '') {
            return false;
        }
        $time = ($time === null) ? time() : $time;

        for ($i = -$window; $i <= $window; $i++) {
            $candidate = self::totpAt($key, $time + ($i * self::STEP));

            if (hash_equals($candidate, $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verify a code and then burn it: a code that passes is recorded so the same
     * code cannot be replayed within its validity window. $scopeKey isolates the
     * marker per account (e.g. 'u' . $userId).
     *
     * @param string $secret
     * @param string $code
     * @param string $scopeKey
     * @param int $window
     * @return bool
     */
    public static function verifyOnce($secret, $code, $scopeKey, $window = 1)
    {
        if (!self::verify($secret, $code, $window)) {
            return false;
        }
        $marker = 'totp_used:' . $scopeKey . ':' . hash('sha256', preg_replace('/\s+/', '', (string) $code));
        if (Cache::get($marker) !== null) {
            return false;
        }
        Cache::set($marker, time());
        return true;
    }

    /**
     * TOTP code for a RAW binary key at a given time (RFC 6238).
     *
     * @param string $key
     * @param int $time
     * @param int $step
     * @param int $digits
     */
    public static function totpAt($key, $time = null, $step = self::STEP, $digits = self::DIGITS)
    {
        $time = ($time === null) ? time() : $time;
        $counter = (int) floor($time / $step);
        return self::hotp($key, $counter, $digits);
    }

    /**
     * HOTP code for a RAW binary key and counter (RFC 4226).
     *
     * @param string $key
     * @param int $counter
     * @param int $digits
     */
    public static function hotp($key, $counter, $digits = self::DIGITS)
    {

        $binCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $binCounter, $key, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        $otp = $binary % pow(10, $digits);
        return str_pad((string) $otp, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * base32-encode raw bytes.
     * 
     * @param array $data
     * @return string
     */
    public static function base32Encode($data)
    {
        if ($data === '') {
            return '';
        }

        $binary = '';
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $binary .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($binary, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $output .= self::ALPHABET[bindec($chunk)];
        }
        return $output;
    }

    /**
     * base32-decode a string into raw bytes.
     * 
     * @param array $data
     * @return string
     */
    public static function base32Decode($data)
    {
        $data = strtoupper(rtrim((string) $data, '='));
        if ($data === '') {
            return '';
        }

        $binary = '';
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $position = strpos(self::ALPHABET, $data[$i]);
            if ($position === false) {
                return '';
            }
            $binary .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) {
                $output .= chr(bindec($byte));
            }
        }
        return $output;
    }
}
