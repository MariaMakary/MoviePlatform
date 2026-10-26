<?php

if (! function_exists('secret')) {
    /**
     * Read a Docker secret: the trimmed content of /run/secrets/<name>.
     *
     * Falls back to env('<NAME>') when the file is missing or empty, so the app also runs
     * without Docker. SECRETS_DIR overrides the folder (used by the tests).
     *
     * Only call this from config/*.php files, so `php artisan config:cache` keeps working.
     */
    function secret(string $name, mixed $default = null): mixed
    {
        $path = rtrim(env('SECRETS_DIR', '/run/secrets'), '/\\').DIRECTORY_SEPARATOR.$name;

        if (is_file($path) && is_readable($path)) {
            $value = trim((string) file_get_contents($path));

            if ($value !== '') {
                return $value;
            }
        }

        return env(strtoupper($name), $default);
    }
}
