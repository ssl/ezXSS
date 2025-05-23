<?php

class Extensions extends Controller
{
    private const ERROR_INVALID_URL = 'Invalid GitHub URL';
    private const ERROR_INVALID_FORMAT = 'Invalid extension code';

    public function __construct()
    {
        parent::__construct();
        $this->isAdminOrExit();
    }

    /**
     * Renders the extensions index and handles installation requests.
     *
     * @return string
     */
    public function index()
    {
        $this->view->setTitle('Extensions');
        $this->view->renderTemplate('extensions/index');

        if (isPOST()) {
            $this->handleExtensionInstallation();
        }

        return $this->showContent();
    }

    /**
     * Renders the edit extension content and handles updates.
     * 
     * @param string $id The extension id
     * @return string
     */
    public function edit($id)
    {
        $this->view->setTitle('Edit Extension');
        $this->view->renderTemplate('extensions/edit');

        $extension = $this->model('Extension')->getById($id);

        if (isPOST()) {
            $this->handleExtensionEdit($id, $extension);
            $extension = $this->model('Extension')->getById($id);
        }

        $isCustom = $extension['source'] === 'custom';
        $version = !$isCustom ? $this->getEditedVersion($extension['version']) : $extension['version'];

        $this->view->renderCondition('isCustom', $isCustom);
        $this->view->renderData('name', $extension['name']);
        $this->view->renderData('description', $extension['description']);
        $this->view->renderData('version', $version);
        $this->view->renderData('author', $extension['author']);
        $this->view->renderData('code', $extension['code']);
        return $this->showContent();
    }

    /**
     * Updates extension - checks for newer versions and shows diff view
     * 
     * @param string $id The extension id
     * @return string
     */
    public function update($id)
    {
        $this->view->setTitle('Update Extension');
        $this->view->renderTemplate('extensions/update');

        $extension = $this->model('Extension')->getById($id);

        if (isPOST()) {
            $this->handleUpdateAction($id, $extension);
            return $this->showContent();
        }

        $this->processExtensionUpdate($extension);
        return $this->showContent();
    }

    /**
     * Deletes extension
     * 
     * @param string $id The extension id
     * @return string
     */
    public function delete($id)
    {
        $this->view->setTitle('Delete Extension');
        $this->view->renderTemplate('extensions/delete');

        $extension = $this->model('Extension')->getById($id);
        $this->view->renderData('name', $extension['name']);

        if (isPOST()) {
            $this->validateCsrfToken();
            $this->model('Extension')->deleteById($id);
            $this->log("Deleted extension #{$id}");
            redirect('/manage/extensions');
        }

        return $this->showContent();
    }

    /**
     * Returns extensions data as JSON.
     * 
     * @return string
     */
    public function data()
    {
        $this->isAPIRequest();

        $extensions = $this->model('Extension')->getAll();

        foreach ($extensions as $key => $extension) {
            unset($extensions[$key]['code']);
        }

        return jsonResponse('data', $extensions);
    }

    /**
     * Handles POST actions for extension updates (accept/reject)
     */
    private function handleUpdateAction($id, $extension)
    {
        try {
            $this->validateCsrfToken();
            $action = _POST('action');

            if ($action === 'reject') {
                redirect('/manage/extensions');
                return;
            }

            if ($action === 'accept') {
                $this->applyExtensionUpdate($id, $extension);
                $this->log("Updated extension #{$id} from remote source");
                $this->view->renderMessage("Extension updated successfully!");
                redirect('/manage/extensions');
                return;
            }

            throw new Exception('Invalid action');
        } catch (Exception $e) {
            $this->view->renderMessage($e->getMessage());
        }
    }

    /**
     * Processes extension update check and prepares diff view if needed
     */
    private function processExtensionUpdate($extension)
    {
        $isCustom = $extension['source'] === 'custom';
        $this->view->renderCondition('isCustom', $isCustom);
        $this->view->renderData('id', $extension['id']);

        if ($isCustom) {
            $this->view->renderConditions([
                'upToDate' => false,
                'updateError' => false,
                'showDiff' => false
            ]);
            return;
        }

        try {
            $remoteInfo = $this->getRemoteExtensionInfo($extension);
            
            if ($extension['version'] !== $remoteInfo['version']) {
                $this->view->renderConditions([
                    'showDiff' => true,
                    'upToDate' => false,
                    'updateError' => false
                ]);
                $this->prepareDiffView($extension, $remoteInfo);
            } else {
                $this->view->renderConditions([
                    'upToDate' => true,
                    'showDiff' => false,
                    'updateError' => false
                ]);
                $this->view->renderData('currentVersion', $extension['version']);
            }
        } catch (Exception $e) {
            $this->view->renderConditions([
                'updateError' => true,
                'upToDate' => false,
                'showDiff' => false
            ]);
            $this->view->renderData('updateError', $e->getMessage());
        }
    }

    /**
     * Gets remote extension information from GitHub
     */
    private function getRemoteExtensionInfo($extension)
    {
        $source = $extension['source'];
        return $this->fetchExtensionInfoFromSource($source);
    }

    /**
     * Unified method to fetch extension info from any GitHub source
     */
    private function fetchExtensionInfoFromSource($source)
    {
        if (strpos($source, 'repo/') === 0) {
            return $this->fetchFromRepository($source);
        } elseif (strpos($source, 'gist/') === 0) {
            return $this->fetchFromGist($source);
        } else {
            throw new Exception("Unknown extension source type");
        }
    }

    /**
     * Fetches extension info from GitHub repository
     */
    private function fetchFromRepository($source)
    {
        $parts = explode('/', $source);
        if (count($parts) !== 4) {
            throw new Exception('Invalid repository source format');
        }

        $user = $parts[1];
        $repo = $parts[2];
        $filename = $parts[3];

        $apiUrl = "https://api.github.com/repos/{$user}/{$repo}/contents/{$filename}";
        $result = $this->makeApiRequest($apiUrl);

        if (!isset($result['download_url'])) {
            throw new Exception('Unable to fetch extension from repository');
        }

        $code = $this->makeApiRequest($result['download_url'], false);
        return $this->getInfoFromCode($code);
    }

    /**
     * Fetches extension info from GitHub gist
     */
    private function fetchFromGist($source)
    {
        // source format: gist/gistId
        $parts = explode('/', $source);
        if (count($parts) !== 2) {
            throw new Exception('Invalid gist source format');
        }

        $gistId = $parts[1];
        $apiUrl = "https://api.github.com/gists/{$gistId}";
        $result = $this->makeApiRequest($apiUrl);

        if (!isset($result['files']) || count($result['files']) === 0) {
            throw new Exception('Unable to fetch extension from gist');
        }

        $file = reset($result['files']);
        $code = $this->getGistFileContent($file);
        return $this->getInfoFromCode($code);
    }

    /**
     * Parses and validates a GitHub repository URL.
     */
    private function parseRepositoryUrl($url)
    {
        $parts = str_replace('https://github.com/', '', $url);
        $parts = explode('/', $parts);

        if (!in_array(count($parts), [2, 5])) {
            throw new Exception(self::ERROR_INVALID_URL);
        }

        $user = $parts[0];
        $repo = $parts[1];

        if (!preg_match('/^[a-zA-Z0-9-]+$/', $user) || !preg_match('/^[a-zA-Z0-9-_.]+$/', $repo)) {
            throw new Exception(self::ERROR_INVALID_URL);
        }

        $file = null;
        if (count($parts) === 5) {
            if ($parts[2] !== 'blob') {
                throw new Exception(self::ERROR_INVALID_URL);
            }
            $file = $parts[4];
        }

        return ['user' => $user, 'repo' => $repo, 'file' => $file];
    }

    /**
     * Parses and validates a GitHub gist URL.
     */
    private function parseGistUrl($url)
    {
        $gist = str_replace('https://gist.github.com/', '', $url);
        $parts = explode('/', $gist);

        if (count($parts) !== 2) {
            throw new Exception(self::ERROR_INVALID_URL);
        }

        $gistId = $parts[1];
        if (!preg_match('/^[a-z0-9]+$/', $gistId)) {
            throw new Exception(self::ERROR_INVALID_URL);
        }

        return $gistId;
    }

    /**
     * Prepares diff view data for template
     */
    private function prepareDiffView($extension, $remoteInfo)
    {
        // Current extension data
        $this->view->renderData('currentName', $extension['name']);
        $this->view->renderData('currentDescription', $extension['description']);
        $this->view->renderData('currentVersion', $extension['version']);
        $this->view->renderData('currentAuthor', $extension['author']);
        $this->view->renderData('currentCode', $extension['code']);
        
        // Remote extension data
        $this->view->renderData('remoteName', $remoteInfo['name']);
        $this->view->renderData('remoteDescription', $remoteInfo['description']);
        $this->view->renderData('remoteVersion', $remoteInfo['version']);
        $this->view->renderData('remoteAuthor', $remoteInfo['author']);
        $this->view->renderData('remoteCode', $remoteInfo['code']);
    }

    /**
     * Applies the extension update from remote source
     */
    private function applyExtensionUpdate($id, $extension)
    {
        $remoteInfo = $this->fetchExtensionInfoFromSource($extension['source']);
        $this->saveExtension($remoteInfo, $extension['source'], $id);
    }

    /**
     * Handles extension installation based on method type.
     */
    private function handleExtensionInstallation()
    {
        try {
            $this->validateCsrfToken();
            $method = _POST('method');

            switch ($method) {
                case 'custom':
                    $this->installCustomExtension();
                    break;
                case 'github':
                    $this->installFromGitHub();
                    break;
                default:
                    throw new Exception('Invalid installation method');
            }
        } catch (Exception $e) {
            $this->view->renderMessage($e->getMessage());
        }
    }

    /**
     * Installs a custom extension from form data.
     */
    private function installCustomExtension()
    {
        $name = _POST('name');
        $description = _POST('description');
        $author = _POST('author');
        $version = _POST('version');
        $code = _POST('code');

        $this->model('Extension')->add($name, $description, $version, $author, 'custom', $code);
        $this->log("Installed custom '{$name}' extension.");
    }

    /**
     * Installs extension(s) from GitHub URL.
     */
    private function installFromGitHub()
    {
        $url = _POST('url');

        if (strpos($url, 'https://github.com/') === 0) {
            $installed = $this->installFromRepository($url);
            $this->log("Installed {$installed} extension(s) from GitHub.");
            $this->view->renderMessage("Installed {$installed} new extension(s).");
        } elseif (strpos($url, 'https://gist.github.com/') === 0) {
            $this->installFromGist($url);
            $this->log("Installed extension from Gist.");
            $this->view->renderMessage("Installed extension.");
        } else {
            throw new Exception('Invalid GitHub URL');
        }
    }

    /**
     * Installs extensions from a GitHub repository.
     */
    private function installFromRepository($url)
    {
        $repoInfo = $this->parseRepositoryUrl($url);
        return $this->grabFromRepo($repoInfo['user'], $repoInfo['repo'], $repoInfo['file']);
    }

    /**
     * Installs extension from a GitHub gist.
     */
    private function installFromGist($url)
    {
        $gistId = $this->parseGistUrl($url);
        if (!$this->grabFromGist($gistId)) {
            throw new Exception('Failed to install extension from Gist');
        }
    }

    /**
     * Grabs extensions from a GitHub repository
     */
    private function grabFromRepo($user, $repo, $targetFile = null)
    {
        $apiUrl = "https://api.github.com/repos/{$user}/{$repo}/contents/";
        $results = $this->makeApiRequest($apiUrl);

        if (isset($results['message']) && $results['message'] === 'Not Found') {
            throw new Exception(self::ERROR_INVALID_URL);
        }

        $installed = 0;

        foreach ($results as $file) {
            if (substr($file['name'], -3) !== '.js' || 
                ($targetFile !== null && $targetFile !== $file['name']) ||
                !preg_match('/^[a-zA-Z0-9-_.]+$/', $file['name'])) {
                continue;
            }

            $source = "repo/{$user}/{$repo}/{$file['name']}";
            if ($this->model('Extension')->getBySource($source)) {
                continue;
            }

            try {
                $extensionInfo = $this->fetchFromRepository($source);
                $this->saveExtension($extensionInfo, $source);
                $installed++;
            } catch (Exception $e) {
                continue;
            }
        }

        if ($installed === 0) {
            throw new Exception('No extensions installed: no valid (new) extensions found.');
        }

        return $installed;
    }

    /**
     * Grabs extension from a GitHub gist
     */
    private function grabFromGist($gistId)
    {
        $source = "gist/{$gistId}";
        
        if ($this->model('Extension')->getBySource($source)) {
            throw new Exception('Extension already installed.');
        }

        try {
            $extensionInfo = $this->fetchFromGist($source);
            $this->saveExtension($extensionInfo, $source);
            return true;
        } catch (Exception $e) {
            throw new Exception('No extension installed: ' . $e->getMessage());
        }
    }

    /**
     * Saves extension (install new or update existing)
     */
    private function saveExtension($extensionInfo, $source, $id = null)
    {
        try {
            if ($id === null) {
                // Install new extension
                $this->model('Extension')->add(
                    $extensionInfo['name'], 
                    $extensionInfo['description'], 
                    $extensionInfo['version'], 
                    $extensionInfo['author'], 
                    $source,
                    $extensionInfo['code']
                );
            } else {
                // Update existing extension
                $this->model('Extension')->update(
                    $id,
                    $extensionInfo['name'],
                    $extensionInfo['description'],
                    $extensionInfo['version'],
                    $extensionInfo['author'],
                    $extensionInfo['code']
                );
            }
        } catch (Exception $e) {
            throw new Exception($id === null ? 'invalid extension settings.' : 'Failed to apply update: ' . $e->getMessage());
        }
    }

    /**
     * Gets content from gist file, handling truncated files.
     */
    private function getGistFileContent($file)
    {
        if ($file['truncated'] === false) {
            return $file['content'];
        }

        $rawUrl = $file['raw_url'] ?? '';
        if (strpos($rawUrl, 'https://gist.githubusercontent.com/') !== 0) {
            throw new Exception(self::ERROR_INVALID_URL);
        }

        return $this->makeApiRequest($rawUrl, false);
    }

    /**
     * Extracts extension metadata from code.
     */
    private function getInfoFromCode($code)
    {
        if ($code === null) {
            throw new Exception(self::ERROR_INVALID_FORMAT);
        }

        $lines = preg_split("/\r\n|\n|\r/", $code);

        if (count($lines) < 6) {
            throw new Exception(self::ERROR_INVALID_FORMAT);
        }

        $this->validateExtensionStructure($lines);
        $metadata = $this->extractMetadata($lines);
        $codeStartLine = trim($lines[6] ?? '') === '' ? 7 : 6;
        
        return [
            'name' => $metadata['name'],
            'description' => $metadata['description'],
            'author' => $metadata['author'],
            'version' => $metadata['version'],
            'code' => implode("\n", array_slice($lines, $codeStartLine))
        ];
    }

    /**
     * Validates extension header and footer structure.
     */
    private function validateExtensionStructure($lines)
    {
        if ($lines[0] !== '// <ezXSS extension>' || $lines[5] !== '// </ezXSS extension>') {
            throw new Exception(self::ERROR_INVALID_FORMAT);
        }
    }

    /**
     * Extracts metadata from extension header.
     */
    private function extractMetadata($lines)
    {
        $fields = [
            1 => ['@name', 'name'],
            2 => ['@description', 'description'], 
            3 => ['@author', 'author'],
            4 => ['@version', 'version']
        ];

        $metadata = [];
        
        foreach ($fields as $lineNum => [$marker, $key]) {
            if (strpos($lines[$lineNum], $marker) === false) {
                throw new Exception(self::ERROR_INVALID_FORMAT);
            }
            $metadata[$key] = trim(str_replace("// {$marker}", '', $lines[$lineNum]));
        }

        return $metadata;
    }

    /**
     * Makes HTTP request and decodes JSON response if needed.
     */
    private function makeApiRequest($url, $decodeJson = true)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ezXSS');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            if ($httpCode === 404) {
                throw new Exception(self::ERROR_INVALID_URL . ' (repository might be private or not found)');
            }
            if ($httpCode === 403) {
                throw new Exception('Access forbidden (403). You may have hit rate limits. Try again later.');
            }
            throw new Exception('Unable to connect to GitHub. ' . $httpCode);
        }
        
        if (!$decodeJson) {
            return $response;
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from GitHub API');
        }
        
        return $result;
    }

    /**
     * Handles extension editing based on source type.
     */
    private function handleExtensionEdit($id, $extension)
    {
        try {
            $this->validateCsrfToken();

            if ($extension['source'] === 'custom') {
                $this->updateCustomExtension($id);
            } else {
                $this->updateDownloadedExtension($id, $extension);
            }

            $this->log("Edited extension #{$id}");
        } catch (Exception $e) {
            $this->view->renderMessage($e->getMessage());
        }
    }

    /**
     * Updates a custom extension with all editable fields.
     */
    private function updateCustomExtension($id)
    {
        $name = _POST('name');
        $description = _POST('description');
        $version = _POST('version');
        $author = _POST('author');
        $code = _POST('code');

        $this->model('Extension')->update($id, $name, $description, $version, $author, $code);
    }

    /**
     * Updates a downloaded extension (code only) and marks version as edited.
     */
    private function updateDownloadedExtension($id, $extension)
    {
        $code = _POST('code');
        $version = $this->getEditedVersion($extension['version']);

        $this->model('Extension')->set($id, 'code', $code);
        $this->model('Extension')->set($id, 'version', $version);
    }

    /**
     * Gets the edited version string, adding suffix if not already present.
     */
    private function getEditedVersion($currentVersion)
    {
        return substr($currentVersion, -5) !== '-edit' 
            ? $currentVersion . '-edit' 
            : $currentVersion;
    }
}