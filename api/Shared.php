<?php
class SharedController extends Controller
{
    /**
     * Web-reachable actions (the public route resolves through PublicRouter).
     */
    public static $routable = array('view');

    /**
     * Render the public share JSON. The slug is the second URI segment.
     *
     * @throws HttpException
     */
    public function view()
    {
        Security::corsOpen();

        $slug = $this->tokenFromUri();

        if (!ReportShare::isValidSlug($slug)) {
            throw new HttpException(404, 'Share not found.');
        }

        $share = ReportShare::findBySlug($slug);
        if (!$share) {
            throw new HttpException(404, 'Share not found.');
        }

        if (ReportShare::isExpired($share)) {
            throw new HttpException(410, 'This share has expired.');
        }

        $values = Report::columnValues((int) $share['report_id']);
        if ($values === null) {
            throw new HttpException(404, 'Share not found.');
        }

        $allowed = ReportShare::columnsOf($share);
        $obfuscate = (int) $share['obfuscate_ip'] === 1;

        $out = self::buildProjection($allowed, $values, $obfuscate, $slug);

        $this->respond(array(
            'columns' => array_keys($out),
            'report'  => $out,
        ));
    }

    /**
     * Project a report's values down to exactly what a share exposes. Plain.
     *
     * @param array $allowed
     * @param array $values
     * @param bool $obfuscate
     */
    public static function buildProjection(array $allowed, array $values, $obfuscate, $slug = null)
    {
        $out = array();
        $extraAll = false;
        $extraKeys = array();
        foreach ($allowed as $col) {

            if ($col === 'extra') {
                $extraAll = true;
                continue;
            }
            if (strpos($col, 'extra:') === 0) {
                $sub = substr($col, 6);
                if ($sub !== '') {
                    $extraKeys[] = $sub;
                }
                continue;
            }

            if (!in_array($col, Report::SHAREABLE_COLUMNS, true) || !array_key_exists($col, $values)) {
                continue;
            }

            $value = $values[$col];

            if ($col === 'ip' && $obfuscate) {
                $value = Report::obfuscateIp((string) $value);
            } elseif ($col === 'screenshot') {
                if ($slug !== null && Compression::isStoredScreenshotFile((string) $value)) {
                    $value = array('type' => 'file', 'url' => '/s/' . $slug . '.png');
                } else {
                    $value = Report::presentScreenshot((string) $value);
                }
            } elseif ($col === 'created_at') {
                $value = $value === '' ? null : (int) $value;
            }

            $out[$col] = $value;
        }

        if (($extraAll || !empty($extraKeys))
            && in_array('extra', Report::SHAREABLE_COLUMNS, true)
            && array_key_exists('extra', $values)) {
            $decoded = json_decode((string) $values['extra'], true);
            if ($extraAll) {

                $out['extra'] = is_array($decoded) ? $decoded : $values['extra'];
            } elseif (is_array($decoded)) {
                $filtered = array();
                foreach ($extraKeys as $sub) {
                    if (array_key_exists($sub, $decoded)) {
                        $filtered[$sub] = $decoded[$sub];
                    }
                }
                if (!empty($filtered)) {
                    $out['extra'] = $filtered;
                }
            }

        }

        return $out;
    }

    /**
     * Extract the slug from the request URI (second path segment), stripping.
     *
     * @return string
     */
    private function tokenFromUri()
    {
        if (!defined('URI') || URI === '') {
            return '';
        }
        $parts = explode('/', URI);
        $seg = isset($parts[1]) ? $parts[1] : '';
        if (substr($seg, -5) === '.json') {
            $seg = substr($seg, 0, -5);
        }
        return $seg;
    }
}
