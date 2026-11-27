<?php
class ServerExtensions
{
    /**
     * Load every server extension from /extensions at bootstrap.
     *
     * A plain name.php is loaded directly; a name/ folder loads its extension.php.
     *
     * @return void
     */
    public static function boot()
    {
        $dir = FRAMEWORK_ROOT . '/extensions';
        if (!is_dir($dir)) {
            return;
        }
        $entries = scandir($dir);
        if ($entries === false) {
            return;
        }
        sort($entries);
        foreach ($entries as $entry) {
            if ($entry === '' || $entry[0] === '.') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_file($path) && substr($entry, -4) === '.php') {
                self::load($path);
            } elseif (is_dir($path) && is_file($path . '/extension.php')) {
                self::load($path . '/extension.php');
            }
        }
    }

    private static function load($file)
    {
        try {
            require_once $file;
        } catch (Throwable $e) {
            error_log('ezXSS extension failed to load (' . $file . '): ' . $e->getMessage());
        }
    }
}
