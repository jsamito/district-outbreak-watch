<?php

if (! function_exists('asset_ver')) {
    /** Asset URL with a content-based version so browsers can cache static files for a long time. */
    function asset_ver(string $path): string
    {
        $file = public_path($path);

        return asset($path).(is_file($file) ? '?v='.substr(md5_file($file), 0, 10) : '');
    }
}
