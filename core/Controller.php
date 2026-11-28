<?php
abstract class Controller
{
    /**
     * Web-reachable actions; the router refuses anything not listed here.
     */
    public static $routable = array();

    /**
     * Send a success envelope and stop.
     *
     * @param array $data
     * @param array $meta
     */
    protected function respond($data = null, $meta = array(), $status = 200)
    {
        $this->send(array(
            'success' => true,
            'data'    => $data,
            'error'   => null,
            'meta'    => (object) $meta,
        ), $status);
    }

    /**
     * Send an error envelope and stop.
     *
     * @param string $message
     * @param int $status
     * @param array $meta
     */
    protected function error($message, $status = 400, $meta = array())
    {
        $this->send(array(
            'success' => false,
            'data'    => null,
            'error'   => $message,
            'meta'    => (object) $meta,
        ), $status);
    }

    /**
     * Emit a JSON envelope with the given HTTP status and exit.
     *
     * @param array $envelope
     * @param int $status
     */
    protected function send($envelope, $status = 200)
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');

            if (class_exists('Auth') && Auth::didAuth()) {
                header('Cache-Control: no-store');
                header('Pragma: no-cache');
            }
        }
        echo json_encode($envelope);
        exit;
    }

    /**
     * Read and JSON-decode the request body.
     */
    protected function input()
    {
        return jsonBody();
    }

    /**
     * Management API preamble: CORS, Bearer auth and an optional method gate. Returns the actor.
     *
     * @param array $methods
     */
    protected function begin($methods = null)
    {
        Security::corsAdmin();
        $actor = Auth::requireAuth();
        if ($methods !== null) {
            $this->requireMethod($methods);
        }
        return $actor;
    }

    /**
     * Enforce the allowed HTTP method(s); emit the standard 405 otherwise.
     *
     * @param array $methods
     */
    protected function requireMethod($methods)
    {
        $allowed = is_array($methods) ? $methods : array($methods);
        if (!in_array($this->method(), $allowed, true)) {
            $this->error('Method not allowed. Use ' . implode(' or ', $allowed) . '.', 405);
        }
    }

    /**
     * Current HTTP method.
     */
    protected function method()
    {
        return isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    }

    /**
     * Throw 403 unless the authenticated user holds a capability.
     *
     * @param string $cap
     * @throws HttpException
     */
    protected function cap($cap)
    {
        if (!Permissions::can(Auth::user(), $cap)) {
            throw new HttpException(403, 'You do not have permission to do that');
        }
    }

    /**
     * Coerce a request flag to bool.
     *
     * @param string $value
     */
    protected function asBool($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }
        $value = strtolower(trim((string) $value));
        return $value === '1' || $value === 'true' || $value === 'on' || $value === 'yes';
    }

    /**
     * Read a clamped integer query parameter.
     *
     * @param string $key
     * @param string $default
     * @param int $min
     * @param int $max
     */
    protected function intParam($key, $default, $min, $max)
    {
        $raw = queryParam($key, null);
        if ($raw === null || !ctype_digit((string) $raw)) {
            return $default;
        }
        $v = (int) $raw;
        if ($v < $min) {
            $v = $min;
        }
        if ($v > $max) {
            $v = $max;
        }
        return $v;
    }

    /**
     * Validate + coerce a path id to a positive int.
     *
     * @param int $id
     * @param string $label
     * @throws HttpException
     * @return int
     */
    protected function validId($id, $label = 'id')
    {
        if (!ctype_digit((string) $id) || (int) $id <= 0) {
            throw new HttpException(422, 'Invalid ' . $label . ' id');
        }
        return (int) $id;
    }

    /**
     * Validate a bulk id list: a non-empty array of positive ints, capped at 200.
     *
     * @param string $raw
     * @throws HttpException
     */
    protected function validBulkIds($raw)
    {
        if (!is_array($raw) || empty($raw)) {
            throw new HttpException(422, 'ids must be a non-empty array');
        }
        if (count($raw) > 200) {
            throw new HttpException(422, 'too many ids (max 200)');
        }
        $ids = array();
        foreach ($raw as $value) {
            if ((!is_int($value) && !ctype_digit((string) $value)) || (int) $value <= 0) {
                throw new HttpException(422, 'ids must be positive integers');
            }
            $ids[] = (int) $value;
        }
        return $ids;
    }

    /**
     * Map a per-id failure to a stable skip reason for a bulk response.
     *
     * @param Exception $e
     * @return string
     */
    protected function skipReason(Exception $e)
    {
        if ($e instanceof HttpException) {
            if ($e->status === 404) {
                return 'not_found';
            }
            if ($e->status === 403) {
                return 'forbidden';
            }
        }
        return 'error';
    }

    /**
     * Build the pagination meta block.
     *
     * @param int $total
     * @param int $page
     * @param int $perPage
     * @return array
     */
    protected function pagination($total, $page, $perPage)
    {
        $pages = $perPage > 0 ? (int) ceil($total / $perPage) : 0;
        return array(
            'total'    => (int) $total,
            'page'     => (int) $page,
            'per_page' => (int) $perPage,
            'pages'    => $pages,
        );
    }
}
