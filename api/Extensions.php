<?php
class ExtensionsController extends Controller
{
    /**
     * Web-reachable actions.
     */
    public static $routable = array('index', 'get', 'update', 'delete', 'toggle', 'install', 'remote');

    /**
     * Extension header markers (one metadata field per line).
     */
    const HEADER_OPEN  = '// <ezXSS extension>';
    const HEADER_CLOSE = '// </ezXSS extension>';

    /**
     * Test/transport seam. When set it replaces the real curl call and returns array('status' => int, 'body' => string|false).
     */
    public static $transport = null;

    /**
     * GET lists, POST creates extensions.
     */
    public function index()
    {
        $this->begin(array('GET', 'POST'));

        if (isPOST()) {
            return $this->respond($this->doCreate($this->input()), array(), 201);
        }
        return $this->respond($this->doList());
    }

    /**
     * GET /api/extensions/get/{id}.
     *
     * @param int $id
     */
    public function get($id)
    {
        $this->begin('GET');
        return $this->respond($this->doGet($id));
    }

    /**
     * POST /api/extensions/update/{id}.
     *
     * @param int $id
     */
    public function update($id)
    {
        $this->begin('POST');
        return $this->respond($this->doUpdate($id, $this->input()));
    }

    /**
     * DELETE /api/extensions/delete/{id}.
     *
     * @param int $id
     */
    public function delete($id)
    {
        $this->begin('DELETE');
        return $this->respond($this->doDelete($id));
    }

    /**
     * POST /api/extensions/toggle/{id}.
     *
     * @param int $id
     */
    public function toggle($id)
    {
        $this->begin('POST');
        return $this->respond($this->doToggle($id));
    }

    /**
     * POST /api/extensions/install - install extension(s) from a GitHub repo or Gist URL.
     */
    public function install()
    {
        $this->begin('POST');
        return $this->respond($this->doInstall($this->input()), array(), 201);
    }

    /**
     * GET /api/extensions/remote/{id} - check the remote source; POST applies the remote version.
     *
     * @param int $id
     */
    public function remote($id)
    {
        $this->begin(array('GET', 'POST'));
        if ($this->method() === 'POST') {
            return $this->respond($this->doRemoteApply($id));
        }
        return $this->respond($this->doRemoteCheck($id));
    }

    /**
     * All extensions (metadata only, no code).
     */
    public function doList()
    {
        $this->cap('settings.manage');
        return $this->shape(Extension::allExtensions(false));
    }

    /**
     * One extension including its code + declared fields.
     *
     * @param int $id
     */
    public function doGet($id)
    {
        $this->cap('settings.manage');
        $row = $this->ensure($id);
        return $this->shapeOne($row, true);
    }

    /**
     * Create a new extension from the request body (validates metadata).
     *
     * @param array $body
     */
    public function doCreate(array $body)
    {
        $this->cap('settings.manage');
        $id = Extension::add(array(
            'name'        => isset($body['name']) ? $body['name'] : '',
            'description' => isset($body['description']) ? $body['description'] : '',
            'version'     => isset($body['version']) ? $body['version'] : '',
            'author'      => isset($body['author']) ? $body['author'] : '',
            'source'      => isset($body['source']) ? $body['source'] : 'custom',
            'code'        => isset($body['code']) ? $body['code'] : '',
            'enabled'     => isset($body['enabled']) ? (bool) $body['enabled'] : true,
        ));
        return $this->shapeOne(Extension::find((int) $id), true);
    }

    /**
     * Update an extension's editable fields (validates metadata).
     *
     * @param int $id
     * @param array $body
     */
    public function doUpdate($id, array $body)
    {
        $this->cap('settings.manage');
        $this->ensure($id);

        $fields = array();
        foreach (array('name', 'description', 'version', 'author', 'code') as $key) {
            if (array_key_exists($key, $body)) {
                $fields[$key] = $body[$key];
            }
        }
        if (array_key_exists('enabled', $body)) {
            $fields['enabled'] = (bool) $body['enabled'];
        }

        Extension::edit((int) $id, $fields);
        return $this->shapeOne(Extension::find((int) $id), true);
    }

    /**
     * Delete an extension.
     *
     * @param int $id
     * @return array
     */
    public function doDelete($id)
    {
        $this->cap('settings.manage');
        $this->ensure($id);
        Extension::delete((int) $id);
        return array('deleted' => true, 'id' => (int) $id);
    }

    /**
     * Toggle the enabled flag; returns the new state.
     *
     * @param int $id
     * @return array
     */
    public function doToggle($id)
    {
        $this->cap('settings.manage');
        $row = $this->ensure($id);
        $new = Extension::toggle((int) $row['id']);
        return array('id' => (int) $row['id'], 'enabled' => (bool) $new);
    }

    /**
     * Install extension(s) from a GitHub repo or Gist URL: a repo blob URL installs that one file, a repo URL every .js file.
     *
     * @param array $body
     * @throws HttpException
     * @return array
     */
    public function doInstall(array $body)
    {
        $this->cap('settings.manage');
        $url = isset($body['url']) ? trim((string) $body['url']) : '';
        if ($url === '') {
            throw new HttpException(422, 'A GitHub or Gist URL is required');
        }

        $target = self::parseInstallUrl($url);
        $installed = ($target['type'] === 'gist')
            ? $this->installFromGist($target['id'])
            : $this->installFromRepo($target['user'], $target['repo'], $target['file']);

        return array('installed' => $installed, 'count' => count($installed));
    }

    /**
     * Check a remote-sourced extension against its upstream source and return the current and remote info with an up_to_date flag.
     *
     * @param int $id
     * @return array
     */
    public function doRemoteCheck($id)
    {
        $this->cap('settings.manage');
        $row = $this->ensure($id);
        $current = $this->currentInfo($row);

        if ($row['source'] === 'custom') {
            return array(
                'id'         => (int) $row['id'],
                'is_custom'  => true,
                'up_to_date' => true,
                'current'    => $current,
                'remote'     => null,
            );
        }

        $remote = $this->fetchInfoFromSource($row['source']);
        return array(
            'id'         => (int) $row['id'],
            'is_custom'  => false,
            'up_to_date' => (string) $row['version'] === (string) $remote['version'],
            'current'    => $current,
            'remote'     => $remote,
        );
    }

    /**
     * Apply the remote version to a remote-sourced extension. Refuses custom extensions.
     *
     * @param int $id
     * @throws HttpException
     */
    public function doRemoteApply($id)
    {
        $this->cap('settings.manage');
        $row = $this->ensure($id);
        if ($row['source'] === 'custom') {
            throw new HttpException(422, 'Custom extensions cannot be remotely updated');
        }

        $remote = $this->fetchInfoFromSource($row['source']);
        Extension::edit((int) $row['id'], array(
            'name'        => $remote['name'],
            'description' => $remote['description'],
            'version'     => $remote['version'],
            'author'      => $remote['author'],
            'code'        => $remote['code'],
        ));
        return $this->shapeOne(Extension::find((int) $row['id']), true);
    }

    /**
     * Install every suitable .js file from a repo.
     *
     * @param array $user
     * @param string $repo
     * @param string $targetFile
     * @throws HttpException
     */
    private function installFromRepo($user, $repo, $targetFile)
    {
        $listing = $this->fetchJson("https://api.github.com/repos/{$user}/{$repo}/contents/");
        if (isset($listing['message']) && $listing['message'] === 'Not Found') {
            throw new HttpException(404, 'Repository not found (it may be private)');
        }
        if (!is_array($listing)) {
            throw new HttpException(502, 'Unexpected repository listing from GitHub');
        }

        $installed = array();
        foreach ($listing as $file) {
            if (!is_array($file) || !isset($file['name'], $file['download_url'])) {
                continue;
            }
            $name = $file['name'];
            if (substr($name, -3) !== '.js'
                || ($targetFile !== null && $targetFile !== $name)
                || !preg_match('/^[a-zA-Z0-9-_.]+$/', $name)) {
                continue;
            }

            $source = "repo/{$user}/{$repo}/{$name}";
            if (Extension::getBySource($source)) {
                continue;
            }

            try {
                $info = self::parseExtensionCode($this->fetchRaw($file['download_url']));
                $installed[] = $this->store($info, $source);
            } catch (Exception $e) {

                continue;
            }
        }

        if (empty($installed)) {
            throw new HttpException(422, 'No new extensions installed: no valid (new) extensions found');
        }
        return $installed;
    }

    /**
     * Install the single file behind a Gist.
     *
     * @param string $gistId
     * @throws HttpException
     * @return array
     */
    private function installFromGist($gistId)
    {
        $source = "gist/{$gistId}";
        if (Extension::getBySource($source)) {
            throw new HttpException(422, 'Extension already installed');
        }
        $info = $this->fetchInfoFromSource($source);
        return array($this->store($info, $source));
    }

    /**
     * Persist a freshly-parsed remote extension; returns the shaped row.
     *
     * @param array $info
     * @param string $source
     */
    private function store(array $info, $source)
    {
        $id = Extension::add(array(
            'name'        => $info['name'],
            'description' => $info['description'],
            'version'     => $info['version'],
            'author'      => $info['author'],
            'source'      => $source,
            'code'        => $info['code'],
            'enabled'     => false,
        ));
        return $this->shapeOne(Extension::find((int) $id), false);
    }

    /**
     * Fetch + parse the remote extension metadata for a stored source key.
     *
     * @param string $source
     * @throws HttpException
     */
    private function fetchInfoFromSource($source)
    {
        if (strpos($source, 'repo/') === 0) {
            $parts = explode('/', $source);
            if (count($parts) !== 4) {
                throw new HttpException(422, 'Invalid repository source');
            }
            $apiUrl = "https://api.github.com/repos/{$parts[1]}/{$parts[2]}/contents/{$parts[3]}";
            $meta = $this->fetchJson($apiUrl);
            if (!isset($meta['download_url'])) {
                throw new HttpException(502, 'Unable to resolve the extension file on GitHub');
            }
            return self::parseExtensionCode($this->fetchRaw($meta['download_url']));
        }

        if (strpos($source, 'gist/') === 0) {
            $parts = explode('/', $source);
            if (count($parts) !== 2) {
                throw new HttpException(422, 'Invalid gist source');
            }
            $meta = $this->fetchJson("https://api.github.com/gists/{$parts[1]}");
            if (!isset($meta['files']) || !is_array($meta['files']) || count($meta['files']) === 0) {
                throw new HttpException(502, 'Gist contains no files');
            }
            $file = reset($meta['files']);
            return self::parseExtensionCode($this->gistFileContent($file));
        }

        throw new HttpException(422, 'Unknown extension source');
    }

    /**
     * Resolve a gist file's content, following raw_url for truncated files.
     *
     * @param string $file
     * @throws HttpException
     */
    private function gistFileContent($file)
    {
        if (isset($file['truncated']) && $file['truncated'] === false && isset($file['content'])) {
            return (string) $file['content'];
        }
        $raw = isset($file['raw_url']) ? (string) $file['raw_url'] : '';
        if ($raw === '') {
            throw new HttpException(502, 'Gist file content unavailable');
        }
        return $this->fetchRaw($raw);
    }

    /**
     * Shape a stored row into the {name,description,version,author,code} info.
     *
     * @param array $row
     */
    private function currentInfo($row)
    {
        return array(
            'name'        => $row['name'],
            'description' => $row['description'],
            'version'     => $row['version'],
            'author'      => $row['author'],
            'code'        => isset($row['code']) ? $row['code'] : '',
        );
    }

    /**
     * Classify + validate a GitHub/Gist install URL into a repo (user, repo, file) or gist (id) descriptor.
     *
     * @param string $url
     * @throws HttpException
     * @return array
     */
    public static function parseInstallUrl($url)
    {
        $url = (string) $url;
        if (strpos($url, 'https://github.com/') === 0) {
            $repo = self::parseRepositoryUrl($url);
            $repo['type'] = 'repo';
            return $repo;
        }
        if (strpos($url, 'https://gist.github.com/') === 0) {
            return array('type' => 'gist', 'id' => self::parseGistUrl($url));
        }
        throw new HttpException(422, 'Invalid GitHub URL');
    }

    /**
     * Parse https://github.com/{user}/{repo}.
     *
     * @param string $url
     * @throws HttpException
     * @return array
     */
    public static function parseRepositoryUrl($url)
    {
        $rest = str_replace('https://github.com/', '', (string) $url);
        $parts = explode('/', $rest);
        if (!in_array(count($parts), array(2, 5), true)) {
            throw new HttpException(422, 'Invalid GitHub URL');
        }

        $user = $parts[0];
        $repo = $parts[1];
        if (!preg_match('/^[a-zA-Z0-9-]+$/', $user) || !preg_match('/^[a-zA-Z0-9-_.]+$/', $repo)) {
            throw new HttpException(422, 'Invalid GitHub URL');
        }

        $file = null;
        if (count($parts) === 5) {
            if ($parts[2] !== 'blob') {
                throw new HttpException(422, 'Invalid GitHub URL');
            }
            $file = $parts[4];
            if (substr($file, -3) !== '.js' || !preg_match('/^[a-zA-Z0-9-_.]+$/', $file)) {
                throw new HttpException(422, 'Invalid GitHub URL');
            }
        }

        return array('user' => $user, 'repo' => $repo, 'file' => $file);
    }

    /**
     * Parse https://gist.github.com/{user}/{id} and return the gist id.
     *
     * @param string $url
     * @throws HttpException
     */
    public static function parseGistUrl($url)
    {
        $rest = str_replace('https://gist.github.com/', '', (string) $url);
        $parts = explode('/', $rest);
        if (count($parts) !== 2) {
            throw new HttpException(422, 'Invalid GitHub URL');
        }
        $gistId = $parts[1];
        if (!preg_match('/^[a-z0-9]+$/', $gistId)) {
            throw new HttpException(422, 'Invalid GitHub URL');
        }
        return $gistId;
    }

    /**
     * Extract the name/description/author/version header block and the code that follows it.
     *
     * @param string $code
     * @throws HttpException
     */
    public static function parseExtensionCode($code)
    {
        if ($code === null || $code === '') {
            throw new HttpException(422, 'Invalid extension code');
        }

        $lines = preg_split("/\r\n|\n|\r/", (string) $code);
        if (count($lines) < 6) {
            throw new HttpException(422, 'Invalid extension code');
        }
        if ($lines[0] !== self::HEADER_OPEN || $lines[5] !== self::HEADER_CLOSE) {
            throw new HttpException(422, 'Invalid extension code');
        }

        $markers = array(
            1 => array('@name', 'name'),
            2 => array('@description', 'description'),
            3 => array('@author', 'author'),
            4 => array('@version', 'version'),
        );
        $meta = array();
        foreach ($markers as $lineNum => $pair) {
            if (strpos($lines[$lineNum], $pair[0]) === false) {
                throw new HttpException(422, 'Invalid extension code');
            }
            $meta[$pair[1]] = trim(str_replace('// ' . $pair[0], '', $lines[$lineNum]));
        }

        $start = (trim(isset($lines[6]) ? $lines[6] : '') === '') ? 7 : 6;
        $meta['code'] = implode("\n", array_slice($lines, $start));
        return $meta;
    }

    /**
     * SSRF allow-list: only github.com, api.github.com, gist.github.com.
     *
     * @param string $host
     * @return bool
     */
    public static function isAllowedHost($host)
    {
        $host = strtolower((string) $host);
        if ($host === 'github.com' || $host === 'api.github.com' || $host === 'gist.github.com') {
            return true;
        }
        $suffix = '.githubusercontent.com';
        return strlen($host) > strlen($suffix)
            && substr($host, -strlen($suffix)) === $suffix;
    }

    /**
     * Guard a URL before any fetch: must be https and target an allowed host.
     *
     * @param string $url
     * @throws HttpException
     * @return bool
     */
    public static function assertAllowedUrl($url)
    {
        $parts = parse_url((string) $url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])
            || strtolower($parts['scheme']) !== 'https'
            || !self::isAllowedHost($parts['host'])) {
            throw new HttpException(422, 'Refusing to fetch a non-GitHub URL');
        }
        return true;
    }

    /**
     * Fetch JSON from an allowed GitHub endpoint and decode it.
     *
     * @param string $url
     * @throws HttpException
     */
    private function fetchJson($url)
    {
        $res = $this->httpGet($url);
        $decoded = json_decode((string) $res, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpException(502, 'Invalid JSON response from GitHub');
        }
        return $decoded;
    }

    /**
     * Fetch a raw body from an allowed GitHub endpoint.
     *
     * @param string $url
     */
    private function fetchRaw($url)
    {
        return $this->httpGet($url);
    }

    /**
     * Perform one GET against an allow-listed host, mapping GitHub error statuses to an HttpException.
     *
     * @param string $url
     * @throws HttpException
     * @return string
     */
    private function httpGet($url)
    {
        self::assertAllowedUrl($url);

        if (self::$transport !== null) {
            $res = call_user_func(self::$transport, $url);
            $status = isset($res['status']) ? (int) $res['status'] : 0;
            $body = isset($res['body']) ? $res['body'] : false;
        } else {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'ezXSS');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        }

        if ($body === false || $status === 0) {
            throw new HttpException(502, 'Unable to connect to GitHub');
        }
        if ($status === 404) {
            throw new HttpException(404, 'GitHub resource not found (it may be private)');
        }
        if ($status === 403) {
            throw new HttpException(403, 'GitHub rate limit or access forbidden; try again later');
        }
        if ($status !== 200) {
            throw new HttpException(502, 'Unexpected GitHub response (' . $status . ')');
        }
        return (string) $body;
    }

    /**
     * Validate the id and load the extension or throw.
     *
     * @param int $id
     * @throws HttpException
     */
    private function ensure($id)
    {
        $id = $this->validId($id, 'extension');
        $row = Extension::find($id);
        if (!$row) {
            throw new HttpException(404, 'Extension not found');
        }
        return $row;
    }

    /**
     * Normalize a list of extension rows for output (no code).
     *
     * @param array $rows
     */
    private function shape($rows)
    {
        $out = array();
        foreach ($rows as $row) {
            $out[] = $this->shapeOne($row, false);
        }
        return $out;
    }

    /**
     * Normalize a single extension row; include code only when requested.
     *
     * @param array $row
     * @param int $withCode
     */
    private function shapeOne($row, $withCode)
    {
        $shaped = array(
            'id'          => (int) $row['id'],
            'name'        => $row['name'],
            'description' => $row['description'],
            'version'     => $row['version'],
            'author'      => $row['author'],
            'source'      => $row['source'],
            'enabled'     => (int) $row['enabled'] === 1,
        );
        if ($withCode) {
            $shaped['code'] = isset($row['code']) ? $row['code'] : '';
        }
        return $shaped;
    }
}
