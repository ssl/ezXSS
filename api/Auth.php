<?php
class AuthController extends Controller
{
    /**
     * Web-reachable actions.
     */
    public static $routable = array('login', 'twofa', 'logout', 'enable2fa', 'disable2fa');

    /**
     * POST /api/auth/login.
     */
    public function login()
    {
        Security::corsAdmin();
        $this->requireMethod('POST');

        $body = $this->input();
        $username = isset($body['username']) ? $body['username'] : '';
        $password = isset($body['password']) ? $body['password'] : '';
        $code = isset($body['code']) ? $body['code'] : null;

        $result = Auth::login($username, $password, $code, USER_IP);
        $this->respond($result);
    }

    /**
     * POST /api/auth/twofa - complete a pending login challenge.
     */
    public function twofa()
    {
        Security::corsAdmin();
        $this->requireMethod('POST');

        $body = $this->input();
        $challenge = isset($body['challenge']) ? $body['challenge'] : '';
        $code = isset($body['code']) ? $body['code'] : '';

        $result = Auth::completeTwofa($challenge, $code, USER_IP);
        $this->respond($result);
    }

    /**
     * POST /api/auth/logout - revoke the current bearer token.
     */
    public function logout()
    {
        Security::corsAdmin();
        $this->requireMethod('POST');

        Auth::requireAuth();
        $token = Auth::token();
        Token::revokeById($token['id']);

        $this->respond(array('loggedout' => true));
    }

    /**
     * POST /api/auth/enable2fa - two-step enablement.
     *
     * @throws HttpException
     */
    public function enable2fa()
    {
        Security::corsAdmin();
        $this->requireMethod('POST');

        $user = Auth::requireAuth();
        if (User::has2fa($user)) {
            throw new HttpException(409, 'Two-factor authentication is already enabled.');
        }

        $body = $this->input();
        $secret = isset($body['secret']) ? trim((string) $body['secret']) : '';
        $code = isset($body['code']) ? (string) $body['code'] : '';

        if ($secret === '' || $code === '') {
            $newSecret = Totp::generateSecret();
            return $this->respond(array(
                'secret'      => $newSecret,
                'otpauth_uri' => Totp::otpauthUri($newSecret, $user['username']),
                'confirmed'   => false,
            ));
        }

        if (!Security::rateLimit('enable2fa:' . (int) $user['id'], 5, 300)) {
            throw new HttpException(429, 'Too many attempts. Please wait and try again.');
        }
        $password = isset($body['password']) ? (string) $body['password'] : '';
        $hash = isset($user['password']) ? (string) $user['password'] : '';
        if ($password === '' || !password_verify($password, $hash)) {
            throw new HttpException(422, 'Enter your current account password to enable two-factor authentication.');
        }

        if (preg_match('/[^A-Za-z2-7]/', $secret) || !Totp::verify($secret, $code)) {
            throw new HttpException(422, 'Invalid two-factor code. Scan the QR again and retry.');
        }

        User::setTwofaSecret($user['id'], strtoupper($secret));
        $this->respond(array('confirmed' => true, 'twofa_enabled' => true));
    }

    /**
     * POST /api/auth/disable2fa - requires a valid current authenticator code.
     *
     * @throws HttpException
     */
    public function disable2fa()
    {
        Security::corsAdmin();
        $this->requireMethod('POST');

        $user = Auth::requireAuth();
        if (!User::has2fa($user)) {
            throw new HttpException(409, 'Two-factor authentication is not enabled.');
        }

        $body = $this->input();
        $code = isset($body['code']) ? (string) $body['code'] : '';

        if (!Security::rateLimit('disable2fa:' . (int) $user['id'], 5, 300)) {
            throw new HttpException(429, 'Too many attempts. Please wait and try again.');
        }

        $codeOk = $code !== '' && Totp::verify($user['twofa_secret'], $code);
        if (!$codeOk) {
            throw new HttpException(422, 'Enter a valid current authenticator code to disable 2FA.');
        }

        User::clearTwofaSecret($user['id']);
        $this->respond(array('twofa_enabled' => false));
    }
}
