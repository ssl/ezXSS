<?php
class ReportsController extends Controller
{
    /**
     * Web-reachable actions.
     */
    public static $routable = array('index', 'get', 'dom', 'archive', 'delete', 'share', 'shares', 'stats');

    /**
     * GET /api/reports - paginated, searchable, archive/payload filtered.
     */
    public function index()
    {
        $this->begin('GET');

        $result = $this->doList();
        $this->respond($result['data'], array('pagination' => $result['pagination']));
    }

    /**
     * GET /api/reports/get/{id} - full detail (DOM omitted when oversized).
     *
     * @param int $id
     */
    public function get($id)
    {
        $this->begin();
        $this->respond($this->doGet($id));
    }

    /**
     * GET /api/reports/dom/{id} - stream the full DOM (used when oversized).
     *
     * @param int $id
     */
    public function dom($id)
    {
        $this->begin();

        $id = $this->validId($id, 'report');
        $this->cap('report.view');
        $this->ensureReport($id);
        $this->access($id, 'view');

        $data = Report::dataFor($id);
        $dom = (string) (isset($data['dom']) ? $data['dom'] : '');

        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="report-' . $id . '-dom.html"');
            header('Content-Length: ' . strlen($dom));
            header('X-Content-Type-Options: nosniff');
        }
        echo $dom;
        exit;
    }

    /**
     * POST /api/reports/archive/{id} - toggle the archive flag.
     *
     * @param int $id
     */
    public function archive($id)
    {
        $this->begin('POST');

        $this->respond($this->doArchive($id));
    }

    /**
     * DELETE /api/reports/delete/{id} - delete report + data + shares + file.
     *
     * @param int $id
     */
    public function delete($id)
    {
        $this->begin('DELETE');

        $this->respond($this->doDelete($id));
    }

    /**
     * POST /api/reports/share/{id} - create an on-request share.
     *
     * @param int $id
     */
    public function share($id)
    {
        $this->begin('POST');

        $this->respond($this->doCreateShare($id, $this->input()), array(), 201);
    }

    /**
     * GET /api/reports/shares/{id} - list a report's shares.
     *
     * @param string $a
     * @param string $b
     * @throws HttpException
     */
    public function shares($a, $b = null)
    {
        $this->begin(array('GET', 'DELETE'));

        if ($this->method() === 'DELETE') {
            if ((string) $a !== 'delete' || $b === null) {
                throw new HttpException(404, 'Not found.');
            }
            return $this->respond($this->doRevokeShare($b));
        }

        $this->respond($this->doListShares($a));
    }

    /**
     * GET /api/reports/stats - cached dashboard aggregates.
     *
     * @throws HttpException
     */
    public function stats()
    {
        $this->begin();

        if (!Permissions::can(Auth::user(), 'report.viewall')) {
            throw new HttpException(403, 'You do not have permission to do that');
        }

        $this->respond(array(
            'active'      => Report::countCached(0),
            'archived'    => Report::countCached(1),
            'per_payload' => Report::perPayloadCached(),
        ));
    }

    /**
     * Build a paginated list for the actor. report.viewall lists all reports; others.
     *
     * @return array
     */
    public function doList()
    {
        $actor = Auth::user();

        $perPage = $this->intParam('per_page', 25, 1, 200);
        $page = $this->intParam('page', 1, 1, PHP_INT_MAX);
        $offset = ($page - 1) * $perPage;

        $archive = queryParam('archive', '0') === '1' ? 1 : 0;
        $search = queryParam('search', '');
        $payload = queryParam('payload', '');
        $payloadStr = is_string($payload) ? trim($payload) : '';

        $viewAll = Permissions::can($actor, 'report.viewall');

        $catchall = $viewAll && $payloadStr === Payload::CATCHALL_FILTER;

        $opts = array(
            'archive'  => $archive,
            'search'   => is_string($search) ? trim($search) : '',
            'payload'  => $catchall ? '' : $payloadStr,
            'catchall' => $catchall,
            'limit'    => $perPage,
            'offset'   => $offset,
        );

        if ($viewAll) {
            $opts['cacheTotal'] = true;
        } else {
            $opts['ids'] = Report::accessibleIds((int) $actor['id']);
        }

        $result = Report::listFor($opts);
        $total = (int) $result['total'];

        $pagination = $this->pagination($total, $page, $perPage);
        $pagination['capped'] = !empty($result['capped']);

        return array(
            'data'       => $result['rows'],
            'pagination' => $pagination,
        );
    }

    /**
     * Full detail for a report. Omits the DOM inline when it exceeds.
     *
     * @param int $id
     */
    public function doGet($id)
    {
        $id = $this->validId($id, 'report');
        $this->cap('report.view');
        $report = $this->ensureReport($id);
        $this->access($id, 'view');

        $data = Report::dataFor($id);

        $meta = (isset($report['meta']) && $report['meta'] !== null && $report['meta'] !== '')
            ? json_decode((string) $report['meta'], true)
            : null;

        $domSize = Report::domSize($report, $data);
        $threshold = Report::domThreshold();

        $out = array(
            'id'             => (int) $report['id'],
            'payload'        => $report['payload'],
            'uri'            => $report['uri'],
            'ip'             => $report['ip'],
            'referer'        => $report['referer'],
            'useragent'      => $report['useragent'],
            'cookies'        => $report['cookies'],
            'origin'         => $report['origin'],
            'archive'        => (int) $report['archive'],
            'created_at'     => isset($report['created_at']) ? (int) $report['created_at'] : null,
            'meta'           => $meta,
            'localstorage'   => $data['localstorage'],
            'sessionstorage' => $data['sessionstorage'],
            'extra'          => $data['extra'],
            'screenshot'     => Report::presentScreenshot($data['screenshot']),
            'dom_size'       => $domSize,
        );

        if ($domSize > $threshold) {

            $out['dom'] = null;
            $out['dom_truncated'] = true;
            $out['dom_url'] = '/api/reports/dom/' . $id;
        } else {
            $out['dom'] = $data['dom'];
            $out['dom_truncated'] = false;
            $out['dom_url'] = null;
        }

        return $out;
    }

    /**
     * Toggle a report's archive flag (needs EDIT access).
     *
     * @param int $id
     * @return array
     */
    public function doArchive($id)
    {
        $actor = Auth::user();

        $this->cap('report.view');
        $this->ensureReport($id);
        $this->access($id, 'edit');

        $flag = Report::toggleArchive($id);
        Log::event('reports', (int) $actor['id'], ($flag ? 'Archived' : 'Unarchived') . ' report #' . $id, USER_IP);
        return array('id' => $id, 'archive' => $flag);
    }

    /**
     * Delete a report and everything attached to it (needs EDIT access).
     *
     * @param int $id
     * @return array
     */
    public function doDelete($id)
    {
        $actor = Auth::user();

        $this->cap('report.view');
        $this->ensureReport($id);
        $this->access($id, 'edit');

        Report::deleteFull($id);
        Log::event('reports', (int) $actor['id'], 'Deleted report #' . $id, USER_IP);
        return array('deleted' => true, 'id' => $id);
    }

    /**
     * Create an on-request share (needs report.share AND edit access). Validates.
     *
     * @param int $id
     * @param string $body
     * @throws HttpException
     */
    public function doCreateShare($id, $body)
    {
        $actor = Auth::user();

        $this->cap('report.share');
        $this->ensureReport($id);
        $this->access($id, 'edit');

        $requested = isset($body['columns']) ? $body['columns'] : array();
        if (is_string($requested)) {
            $requested = ($requested === '') ? array() : explode(',', $requested);
        }
        if (!is_array($requested)) {
            throw new HttpException(422, 'columns must be an array of shareable column names');
        }

        $columns = array();
        $extraCount = 0;
        foreach ($requested as $raw) {
            $raw = trim((string) $raw);
            if ($raw === '') {
                continue;
            }
            $colonAt = strpos($raw, ':');
            if ($colonAt !== false && strtolower(substr($raw, 0, $colonAt)) === 'extra') {
                $subKey = substr($raw, $colonAt + 1);
                if ($subKey === '' || strlen($subKey) > 128) {
                    throw new HttpException(422, 'invalid extra data key');
                }
                if (!in_array('extra', Report::SHAREABLE_COLUMNS, true)) {
                    throw new HttpException(422, 'extra data is not shareable');
                }
                if (++$extraCount > 50) {
                    throw new HttpException(422, 'too many extra data columns');
                }
                $columns['extra:' . $subKey] = 'extra:' . $subKey;
                continue;
            }
            $col = strtolower($raw);
            if (!in_array($col, Report::SHAREABLE_COLUMNS, true)) {
                throw new HttpException(422, '"' . $col . '" is not a shareable column');
            }
            $columns[$col] = $col;
        }
        $columns = array_values($columns);
        if (empty($columns)) {
            throw new HttpException(422, 'Select at least one column to share');
        }

        $expiresTs = $this->parseExpiry(isset($body['expires_at']) ? $body['expires_at'] : null);

        $share = ReportShare::createShare($id, $columns, false, $expiresTs);
        $share['url'] = $this->publicShareUrl($share['slug']);

        Log::event('reports', (int) $actor['id'], 'Shared report #' . $id, USER_IP);
        return $share;
    }

    /**
     * List a report's shares (needs EDIT access - share management).
     *
     * @param int $id
     */
    public function doListShares($id)
    {
        $id = $this->validId($id, 'report');
        $this->cap('report.view');
        $this->ensureReport($id);
        $this->access($id, 'edit');

        return ReportShare::listForReport($id);
    }

    /**
     * Revoke a share by its id (needs EDIT access to the share's report).
     *
     * @param int $shareId
     * @throws HttpException
     * @return array
     */
    public function doRevokeShare($shareId)
    {
        $actor = Auth::user();

        $share = ReportShare::find($shareId);
        if (!$share) {
            throw new HttpException(404, 'Share not found');
        }

        $this->cap('report.share');
        $reportId = (int) $share['report_id'];

        if (Report::find($reportId)) {
            $this->access($reportId, 'edit');
        } elseif (!Permissions::can($actor, 'report.viewall')) {
            throw new HttpException(403, 'Forbidden');
        }

        ReportShare::delete($shareId);
        Log::event('reports', (int) $actor['id'], 'Revoked a share of report #' . $reportId, USER_IP);
        return array('deleted' => true, 'id' => $shareId);
    }

    /**
     * Load a report row or throw 404.
     *
     * @param int $id
     * @throws HttpException
     */
    private function ensureReport($id)
    {
        $report = Report::find($id);
        if (!$report) {
            throw new HttpException(404, 'Report not found');
        }
        return $report;
    }

    /**
     * Enforce per-report access. report.viewall bypasses; everyone else relies.
     *
     * @param int $id
     * @param string $type
     */
    private function access($id, $type)
    {
        $actor = Auth::user();

        if (Permissions::can($actor, 'report.viewall')) {
            return;
        }
        Report::ensureAccess((int) $actor['id'], $id, $type, 'reports');
    }

    /**
     * Build the public share URL for a slug: {proto}://{domain}/s/{slug}.
     *
     * @param string $slug
     */
    private function publicShareUrl($slug)
    {
        $domain = trim((string) Config::get('DOMAIN', ''));
        if ($domain === '') {
            $domain = defined('HOST') ? HOST : 'localhost';
        }
        $https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
        $proto = $https ? 'https' : 'http';
        return $proto . '://' . $domain . '/s/' . $slug;
    }

    /**
     * Parse an optional expiry into a future unix timestamp, or null. Accepts.
     *
     * @param string $value
     * @throws HttpException
     * @return string|null
     */
    private function parseExpiry($value)
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }
        if (is_int($value) || ctype_digit((string) $value)) {
            $ts = (int) $value;
        } else {
            $ts = strtotime((string) $value);
            if ($ts === false) {
                throw new HttpException(422, 'expires_at is not a valid date/time');
            }
        }
        if ($ts <= time()) {
            throw new HttpException(422, 'expires_at must be in the future');
        }
        return $ts;
    }
}
