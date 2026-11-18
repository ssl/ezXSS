<?php

class RegisterController extends Controller
{
    /**
     * Web-reachable actions.
     */
    public static $routable = array('index');

    /**
     * Rate-limit budget for the public register surface (per IP).
     */
    const RATE_MAX = 10;
    const RATE_WINDOW = 300;

    /**
     * POST /api/register - create a normal user honouring the toggle/mode.
     *
     * @throws HttpException
     */
    public function index()
    {
        Security::corsAdmin();
        $this->requireMethod('POST');

        try {
            $allowed = Security::rateLimit('register:ip:' . USER_IP, self::RATE_MAX, self::RATE_WINDOW);
        } catch (Throwable $e) {
            $allowed = true;
        }
        if (!$allowed) {
            throw new HttpException(429, 'Too many signup attempts. Try again in a few minutes.');
        }

        return $this->respond($this->doRegister($this->input()), array(), 201);
    }

    /**
     * Validate + create the account, or throw a "CODE: message" exception.
     *
     * @param array $body
     * @throws HttpException
     * @return array
     */
    public function doRegister(array $body)
    {

        if (!Setting::enabled('signup_enabled')) {
            throw new HttpException(403, 'Signup is disabled');
        }

        if ((string) Setting::get('signup_mode', 'public') === 'code') {
            $code = isset($body['code']) && is_string($body['code']) ? $body['code'] : '';
            $hash = (string) Setting::get('signup_code_hash', '');
            if ($code === '' || $hash === '' || !password_verify($code, $hash)) {
                throw new HttpException(403, 'Invalid signup code');
            }
        }

        $username = isset($body['username']) && is_string($body['username']) ? trim($body['username']) : '';
        $password = isset($body['password']) && is_string($body['password']) ? $body['password'] : '';

        try {
            InstallController::validateUsername($username);
            InstallController::validatePassword($password);
        } catch (Exception $e) {
            throw new HttpException(422, $e->getMessage());
        }

        if (User::findByUsername($username) !== null) {
            throw new HttpException(409, 'That username is already taken');
        }

        User::createUser($username, $password, 'user');

        return array('created' => true);
    }
}
