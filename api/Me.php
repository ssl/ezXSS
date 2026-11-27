<?php
class MeController extends Controller
{
    /**
     * Routable HTTP actions (everything else is an internal helper).
     */
    public static $routable = array('index');

    /**
     * GET /api/me.
     */
    public function index()
    {
        $user = $this->begin();
        $this->respond($this->payload($user, Auth::token()));
    }

    /**
     * Build the /me response body from a user row and the current token row.
     *
     * @param array $user
     * @param string $token
     * @return array
     */
    public function payload($user, $token)
    {
        $data = User::safe($user);
        $data['auth'] = array(
            'token_type' => (is_array($token) && isset($token['type'])) ? $token['type'] : null,
        );
        $data['capabilities'] = Permissions::effective($user);

        return $data;
    }
}
