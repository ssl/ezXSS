<?php
class BeaconController
{
    /**
     * Web-reachable actions (the public route resolves through PublicRouter).
     */
    public static $routable = array('handle');

    /**
     * Hard cap for the carried `d` core-signal query param (bytes).
     */
    const D_MAX = 2048;

    /**
     * 1x1 transparent PNG (hardcoded bytes), base64-encoded.
     */
    const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNgAAIAAAUAAen63NgAAAAASUVORK5CYII=';

    /**
     * The beacon channel table: type => [content-type, body, status, directive].
     *
     * @return array
     */
    public static function channels()
    {
        return array(
            'img'      => array('image/png', null, 200, 'img-src'),
            'css'      => array('text/css; charset=utf-8', '/* */', 200, 'style-src'),
            'font'     => array('font/woff2', '', 200, 'font-src'),
            'js'       => array('application/javascript; charset=utf-8', '// ', 200, 'script-src'),
            'script'   => array('application/javascript; charset=utf-8', '// ', 200, 'script-src'),
            'connect'  => array('application/json; charset=utf-8', '{}', 200, 'connect-src'),
            'beacon'   => array('text/plain; charset=utf-8', '', 204, 'connect-src'),
            'manifest' => array('application/manifest+json; charset=utf-8', '{}', 200, 'manifest-src'),
            'media'    => array('video/mp4', '', 200, 'media-src'),
            'ping'     => array('text/plain; charset=utf-8', '', 204, 'connect-src'),
            'report'   => array('application/reports+json; charset=utf-8', '{}', 200, ''),
            'frame'    => array('text/html; charset=utf-8', '', 200, 'frame-src'),
            'object'   => array('text/html; charset=utf-8', '', 200, 'object-src'),
            'prefetch' => array('text/plain; charset=utf-8', '', 200, ''),
            'form'     => array('text/plain; charset=utf-8', '', 204, 'form-action'),
            'eventsource' => array('text/event-stream; charset=utf-8', '', 204, 'connect-src'),
            'preload'  => array('text/plain; charset=utf-8', '', 200, ''),
        );
    }

    /**
     * HTTP entry point. Emits the typed resource for {type}, then records.
     */
    public function handle()
    {
        Security::corsOpen();

        list($type, $nonce) = $this->parsePath();

        $channels = self::channels();
        $known = isset($channels[$type]);

        $spec = $known ? $channels[$type] : $channels['img'];
        if (!$known) {
            $type = 'img';
        }

        try {
            if ($nonce !== '') {
                Report::recordBeaconSignal($this->buildSignal($type, $nonce, $spec[3]));
            }
        } catch (Throwable $e) {
        }

        $this->emit($spec);
        exit;
    }

    /**
     * Build the signal array for Report::recordBeaconSignal from the request.
     *
     * @param string $type
     * @param string $nonce
     * @param string $directive
     */
    private function buildSignal($type, $nonce, $directive)
    {
        return array(
            'nonce'     => $nonce,
            'type'      => $type,
            'directive' => $directive,
            'sec_fetch' => $this->secFetch(),
            'core'      => $this->coreData(),
        );
    }

    /**
     * Parse the current URI into (type, nonce). An invalid nonce becomes ''.
     *
     * @return array
     */
    private function parsePath()
    {
        $parts = (defined('URI') && URI !== '') ? explode('/', URI) : array();

        $type = isset($parts[1]) ? $parts[1] : '';
        $nonce = isset($parts[2]) ? $parts[2] : '';
        if (!preg_match('/^[a-z0-9]{1,64}$/', $nonce)) {
            $nonce = '';
        }
        if (!preg_match('/^[a-z]{1,16}$/', $type)) {
            $type = '';
        }
        return array($type, $nonce);
    }

    /**
     * The request's Sec-Fetch-* headers (same mapping as the callback).
     */
    private function secFetch()
    {
        return secFetchHeaders();
    }

    /**
     * Decode the capped base64 core-signal param.
     *
     * @return array|null
     */
    private function coreData()
    {
        $d = queryParam('d');
        if (!is_string($d) || $d === '' || strlen($d) > self::D_MAX) {
            return null;
        }
        $decoded = base64_decode(strtr($d, '-_', '+/'), true);
        if ($decoded === false || $decoded === '') {
            return null;
        }
        $obj = json_decode($decoded, true);
        if (!is_array($obj)) {
            return null;
        }

        $out = array();
        $limits = array(
            'payload'   => 255,
            'uri'       => 1000,
            'origin'    => 255,
            'referer'   => 1000,
            'cookies'   => 2000,
            'useragent' => 500,
            'ip'        => 50,
        );
        foreach ($limits as $key => $max) {
            if (isset($obj[$key]) && is_string($obj[$key]) && $obj[$key] !== '') {
                $out[$key] = utf8Cut(utf8Clean($obj[$key]), $max);
            }
        }
        return empty($out) ? null : $out;
    }

    /**
     * Emit a channel's typed response and body.
     *
     * @param array $spec
     */
    private function emit(array $spec)
    {
        $contentType = $spec[0];
        $body = $spec[1];
        $status = (int) $spec[2];

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: ' . $contentType);
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('X-Content-Type-Options: nosniff');
        }

        if ($status === 204) {
            return;
        }

        if ($body === null) {

            echo base64_decode(self::PNG_1X1);
            return;
        }
        echo $body;
    }

    /**
     * Emit the minimal typed resource for a channel (img PNG, css, ...), so another.
     *
     * @param string $type
     */
    public static function emitChannel($type)
    {
        $channels = self::channels();
        $spec = isset($channels[$type]) ? $channels[$type] : $channels['img'];
        $instance = new self();
        $instance->emit($spec);
    }
}
