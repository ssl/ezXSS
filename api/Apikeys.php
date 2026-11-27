<?php
class ApikeysController extends Controller
{
    /**
     * Web-reachable actions.
     */
    public static $routable = array('index', 'delete');

    /**
     * GET lists, POST creates the current user's API keys.
     */
    public function index()
    {
        $user = $this->begin();
        if (!Permissions::can($user, 'apikey.manage')) {
            return $this->error('You do not have permission to manage API keys.', 403);
        }

        if (isPOST()) {
            return $this->create($user);
        }

        $this->requireMethod(array('GET', 'POST'));

        $keys = Token::listForUser($user['id'], 'apikey');
        $this->respond($keys);
    }

    /**
     * DELETE /api/apikeys/delete/{id} - revoke one of the user's keys.
     *
     * @param int $id
     */
    public function delete($id)
    {
        $user = $this->begin();
        if (!Permissions::can($user, 'apikey.manage')) {
            return $this->error('You do not have permission to manage API keys.', 403);
        }

        $this->requireMethod('DELETE');

        if (!ctype_digit((string) $id) || (int) $id <= 0) {
            return $this->error('Invalid key id.', 422);
        }

        $row = Token::query()
            ->where('id', (int) $id)
            ->where('user_id', (int) $user['id'])
            ->where('type', 'apikey')
            ->first();

        if (!$row) {
            return $this->error('API key not found.', 404);
        }

        Token::revokeById((int) $id, (int) $user['id']);
        $this->respond(array('revoked' => true, 'id' => (int) $id));
    }

    /**
     * Create a named API key and return the raw token once.
     *
     * @param array $user
     */
    private function create($user)
    {
        $body = $this->input();
        $name = isset($body['name']) ? trim((string) $body['name']) : '';
        if ($name === '' || strlen($name) > 100) {
            return $this->error('A key name (1-100 chars) is required.', 422);
        }

        $ttl = null;
        if (isset($body['ttl']) && is_numeric($body['ttl']) && (int) $body['ttl'] > 0) {
            $ttl = (int) $body['ttl'];
        }

        $issued = Token::issue($user['id'], 'apikey', $name, $ttl);

        $this->respond(array(
            'id'         => $issued['id'],
            'name'       => $name,
            'token'      => $issued['raw'],
            'token_type' => 'Bearer',
            'expires_at' => $issued['expires_at'],
        ), array(), 201);
    }
}
