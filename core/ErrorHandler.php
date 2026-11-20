<?php
class HttpException extends Exception
{
    public $status;

    public function __construct($status, $message = '', $code = 0, $previous = null)
    {
        $this->status = $status;
        parent::__construct($message, $code, $previous);
    }
}

class ErrorHandler
{
    private static $statusText = array(
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        410 => 'Gone',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    );

    /**
     * Map a thrown message to an [HTTP status, client message] pair.
     *
     * @param string $raw
     * @param string $knownStatus
     * @return array
     */
    public static function resolve($raw, $knownStatus = null)
    {
        $message = (string) $raw;
        $status = 500;

        if ($knownStatus !== null) {
            $status = (int) $knownStatus;
            if ($message === '') {
                $message = self::statusText($status);
            }
        } elseif (stripos($message, 'Database connection failed') === 0) {
            $status = 503;
            $message = 'Database not configured. Edit .env with valid database credentials.';
        } else {

            if (!Config::get('DEBUG', false)) {
                $message = self::statusText(500);
            }
        }

        if (!isset(self::$statusText[$status])) {
            $status = 500;
        }

        return array($status, $message);
    }

    /**
     * Handle a throwable and emit the JSON error envelope.
     *
     * @param string $e
     */
    public static function handle($e)
    {
        $knownStatus = ($e instanceof HttpException) ? $e->status : null;
        $resolved = self::resolve($e->getMessage(), $knownStatus);
        $status = $resolved[0];
        $message = $resolved[1];

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        $envelope = array(
            'success' => false,
            'data'    => null,
            'error'   => $message,
            'meta'    => (object) array('status' => $status),
        );

        if (Config::get('DEBUG', false)) {
            $envelope['meta'] = array(
                'status' => $status,
                'type'   => get_class($e),
                'file'   => $e->getFile(),
                'line'   => $e->getLine(),
            );
        }

        echo json_encode($envelope);
        exit;
    }

    /**
     * Friendly text for a status code.
     *
     * @param int $status
     */
    private static function statusText($status)
    {
        return isset(self::$statusText[$status]) ? self::$statusText[$status] : 'Error';
    }
}
