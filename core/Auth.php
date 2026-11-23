<?php
class Auth
{

    private static $user = null;

    private static $token = null;

    /**
     * Whether requireAuth() has validated a bearer token for this request.
     */
    private static $didAuth = false;

    /**
     * Login rate-limit: attempts per window (seconds).
     */
    const LOGIN_MAX = 10;
    const LOGIN_WINDOW = 300;

    /**
     * Pending-2FA challenge lifetime (seconds).
     */
    const CHALLENGE_TTL = 300;

    /**
     * Require a valid bearer token. Loads the owning user and token into.
     *
     * @throws HttpException
     */
    public static function requireAuth()
    {
        $raw = self::bearerToken();
        if ($raw === null) {
            throw new HttpException(401, 'Missing or malformed Authorization header');
        }

        $token = Token::findValidByRaw($raw);
        if (!$token) {
            throw new HttpException(401, 'Invalid or expired token');
        }

        $user = User::find($token['user_id']);
        if (!$user) {

            Token::revokeById($token['id']);
            throw new HttpException(401, 'Invalid or expired token');
        }

        if (User::isBanned($user)) {
            throw new HttpException(403, 'This account is disabled');
        }

        Token::touch($token['id']);

        self::$user = $user;
        self::$token = $token;
        self::$didAuth = true;

        return $user;
    }

    /**
     * The authenticated user row (null when not authenticated).
     *
     * @return array|null
     */
    public static function user()
    {
        return self::$user;
    }

    /**
     * @return int|null
     */
    public static function id()
    {
        return self::$user ? (int) self::$user['id'] : null;
    }

    /**
     * @return bool
     */
    public static function can($capability)
    {
        return Permissions::can(self::$user, $capability);
    }

    /**
     * The current token row (null when not authenticated).
     */
    public static function token()
    {
        return self::$token;
    }

    /**
     * Reset request context (used by tests).
     */
    public static function reset()
    {
        self::$user = null;
        self::$token = null;
        self::$didAuth = false;
    }

    /**
     * Whether a bearer token was validated for this request.
     */
    public static function didAuth()
    {
        return self::$didAuth;
    }

    /**
     * Extract the raw bearer token from the Authorization header.
     *
     * @return string|null
     */
    public static function bearerToken()
    {
        $header = self::authorizationHeader();
        if ($header === '' ) {
            return null;
        }
        if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $m)) {
            $token = trim($m[1]);
            return $token !== '' ? $token : null;
        }
        return null;
    }

    /**
     * Read the Authorization header across SAPIs (Apache may hide it).
     *
     * @return string
     */
    private static function authorizationHeader()
    {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if (strcasecmp($name, 'Authorization') === 0) {
                        return $value;
                    }
                }
            }
        }
        return '';
    }

    /**
     * Attempt a login. See the class docblock for the full flow.
     *
     * @param string $username
     * @param string $password
     * @param string $code
     * @param string $ip
     * @throws HttpException
     */
    public static function login($username, $password, $code = null, $ip = '0.0.0.0')
    {
        $username = is_string($username) ? trim($username) : '';

        self::rateLimitLogin($ip, $username);
        $password = is_string($password) ? $password : '';

        $user = ($username !== '') ? User::findByUsername($username) : null;

        $hash = ($user && isset($user['password'])) ? $user['password'] : '$2y$12$'.str_repeat('.', 53);
        $passwordOk = password_verify($password, $hash);

        if (!$user || !$passwordOk) {
            throw new HttpException(401, 'Invalid username or password');
        }

        if (User::has2fa($user)) {
            if ($code === null || $code === '') {
                return self::createChallenge($user['id']);
            }
            if (!Totp::verify($user['twofa_secret'], $code)) {
                throw new HttpException(401, 'Invalid two-factor code');
            }
        }

        if (User::isBanned($user)) {
            throw new HttpException(403, 'This account is disabled');
        }

        self::clearLoginRateLimit($username);

        $session = self::issueSession($user);

        Log::event('logins', (int) $user['id'], 'Logged in as ' . $user['username'], $ip);
        return $session;
    }

    /**
     * Complete a pending 2FA challenge and issue the session token.
     *
     * @param array $challenge
     * @param string $code
     * @param string $ip
     * @throws HttpException
     */
    public static function completeTwofa($challenge, $code, $ip = '0.0.0.0')
    {
        $challenge = is_string($challenge) ? $challenge : '';

        self::rateLimitLogin($ip, 'twofa:' . hash('sha256', $challenge));

        if ($challenge === '') {
            throw new HttpException(401, 'Invalid or expired challenge');
        }

        $key = self::challengeKey($challenge);
        $stored = Cache::get($key);
        if (!is_array($stored) || !isset($stored['user_id'], $stored['exp'])) {
            throw new HttpException(401, 'Invalid or expired challenge');
        }
        if ((int) $stored['exp'] <= time()) {
            Cache::forget($key);
            throw new HttpException(401, 'Invalid or expired challenge');
        }

        $user = User::find((int) $stored['user_id']);
        if (!$user || !User::has2fa($user)) {
            Cache::forget($key);
            throw new HttpException(401, 'Invalid or expired challenge');
        }

        if (!Totp::verify($user['twofa_secret'], $code)) {
            throw new HttpException(401, 'Invalid two-factor code');
        }

        if (User::isBanned($user)) {
            Cache::forget($key);
            throw new HttpException(403, 'This account is disabled');
        }

        Cache::forget($key);

        $session = self::issueSession($user);

        Log::event('logins', (int) $user['id'], 'Logged in as ' . $user['username'] . ' (2FA)', $ip);
        return $session;
    }

    /**
     * Issue a session token for a user and build the login success payload.
     *
     * @param array $user
     * @return array
     */
    private static function issueSession($user)
    {

        try {
            Token::purgeExpired();
        } catch (Throwable $e) {

        }

        $ttl = (int) Config::get('SESSION_TTL', 604800);
        $issued = Token::issue($user['id'], 'session', null, $ttl > 0 ? $ttl : null);

        return array(
            'token'      => $issued['raw'],
            'token_type' => 'Bearer',
            'expires_at' => $issued['expires_at'],
            'user'       => User::safe($user),
        );
    }

    /**
     * Create a short-lived pending-2FA challenge and return the client payload.
     *
     * @param int $userId
     * @return array
     */
    private static function createChallenge($userId)
    {
        $challenge = bin2hex(random_bytes(32));
        Cache::set(self::challengeKey($challenge), array(
            'user_id' => (int) $userId,
            'exp'     => time() + self::CHALLENGE_TTL,
        ));

        return array(
            'twofa_required' => true,
            'challenge'      => $challenge,
            'expires_in'     => self::CHALLENGE_TTL,
        );
    }

    /**
     * Cache key for a challenge token.
     *
     * @param array $challenge
     * @return string
     */
    private static function challengeKey($challenge)
    {
        return 'twofa_challenge:' . hash('sha256', $challenge);
    }

    /**
     * Apply login rate limiting by IP and by username. Throws 429 on exceed.
     *
     * @param string $ip
     * @param string $username
     * @throws HttpException
     */
    private static function rateLimitLogin($ip, $username)
    {
        $okIp = Security::rateLimit('login:ip:' . $ip, self::LOGIN_MAX, self::LOGIN_WINDOW);
        $okUser = Security::rateLimit('login:user:' . strtolower((string) $username), self::LOGIN_MAX, self::LOGIN_WINDOW);

        if (!$okIp || !$okUser) {
            throw new HttpException(429, 'Too many login attempts. Try again later.');
        }
    }

    /**
     * Reset the per-username login throttle after a successful authentication.
     *
     * @param string $username
     */
    private static function clearLoginRateLimit($username)
    {
        Security::clearRateLimit('login:user:' . strtolower((string) $username), self::LOGIN_WINDOW);
    }
}
