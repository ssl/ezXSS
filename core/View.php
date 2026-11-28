<?php
class View
{
    private static function dir()
    {
        return FRAMEWORK_ROOT . '/views';
    }

    /**
     * Render a panel page. Returns the final HTML, or null when the name is invalid or the body file is missing.
     *
     * @param string $name
     * @param array $opts
     * @return string|null
     */
    public static function page($name, array $opts = array())
    {
        if (preg_match('/[^a-z0-9_-]/', $name)) {
            return null;
        }
        $bodyFile = self::dir() . '/panel/' . $name . '.html';
        if (!is_file($bodyFile)) {
            return null;
        }

        $title = isset($opts['title']) ? (string) $opts['title'] : 'ezXSS 5.0';
        $scripts = isset($opts['scripts']) && is_array($opts['scripts']) ? $opts['scripts'] : array();

        $partials = self::dir() . '/partials';
        $head = str_replace(
            array('{{title}}', '{{scripts}}', '{{htmlattr}}'),
            array($title, self::scriptTags($scripts), self::themeAttr() . ' data-panel="/' . Config::panelPath() . '"'),
            file_get_contents($partials . '/head.html')
        );

        $html = $head
            . file_get_contents($partials . '/logo.html')
            . file_get_contents($bodyFile)
            . file_get_contents($partials . '/foot.html');

        if (isset($opts['tokens']) && is_array($opts['tokens'])) {
            $html = str_replace(array_keys($opts['tokens']), array_values($opts['tokens']), $html);
        }
        $html = str_replace('{{panelPath}}', Config::panelPath(), $html);

        return self::versionAssets($html);
    }

    /**
     * The instance default theme as an <html> attribute, baked in server-side.
     *
     * @return string
     */
    private static function themeAttr()
    {
        $theme = 'classic';
        try {
            $t = strtolower(preg_replace('/[^a-z0-9]/', '', (string) Setting::get('theme', 'classic')));
            if ($t !== '') {
                $theme = $t;
            }
        } catch (Throwable $e) {
        }
        if ($theme === 'default' || $theme === 'dark') {
            return '';
        }
        return ' data-theme="' . $theme . '"';
    }

    /**
     * Build the deferred <script> tags for a page. The 'bundle' token resolves to /{panel}/bundle.js.
     *
     * @param array $scripts
     */
    private static function scriptTags(array $scripts)
    {
        $out = '';
        foreach ($scripts as $s) {
            $src = ($s === 'bundle') ? '/' . Config::panelPath() . '/bundle.js' : '/assets/js/' . $s;
            $out .= '<script defer src="' . $src . '?v={{assetVersion}}"></script>' . "\n";
        }
        return $out;
    }

    /**
     * Replace {{assetVersion}} tokens with the newest asset mtime.
     *
     * @param string $html
     * @return string
     */
    private static function versionAssets($html)
    {
        return str_replace('{{assetVersion}}', (string) self::assetVersion(), $html);
    }

    /**
     * Newest mtime across the js and css files in /assets, used to cache-bust the panel asset URLs.
     * Images, such as report screenshots, are skipped.
     */
    private static function assetVersion()
    {
        $max = 0;
        $dir = FRAMEWORK_ROOT . '/assets';
        if (is_dir($dir)) {
            try {
                $it = new RecursiveIteratorIterator(
                    new RecursiveCallbackFilterIterator(
                        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                        function ($current) {
                            if ($current->isDir()) {
                                return $current->getFilename() !== 'img';
                            }
                            return preg_match('/\.(js|css)$/i', $current->getFilename()) === 1;
                        }
                    )
                );
                foreach ($it as $f) {
                    if ($f->getMTime() > $max) {
                        $max = $f->getMTime();
                    }
                }
            } catch (Exception $e) {
                $max = 0;
            }
        }
        return $max > 0 ? $max : time();
    }
}
