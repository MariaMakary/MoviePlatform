<?php

if (! function_exists('secret')) {
    function secret(string $name, mixed $default = null): mixed
    {
        $dir = rtrim(env('SECRETS_DIR', '/run/secrets'), '/\\');

        if (! preg_match('#^([a-zA-Z]:)?[/\\\\]#', $dir)) {
            $dir = (function_exists('base_path') ? base_path() : getcwd()).DIRECTORY_SEPARATOR.$dir;
        }

        $path = $dir.DIRECTORY_SEPARATOR.$name;

        if (is_file($path) && is_readable($path)) {
            $value = trim((string) file_get_contents($path));

            if ($value !== '') {
                return $value;
            }
        }

        return env(strtoupper($name), $default);
    }
}
