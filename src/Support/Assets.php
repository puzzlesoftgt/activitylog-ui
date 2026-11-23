<?php

namespace Puzzlesoft\ActivitylogUi\Support;

class Assets
{
    /**
     * URL of a compiled asset. Published assets (public/vendor/activitylog-ui)
     * are used only when they match the packaged build, so updating the
     * package never serves stale CSS or JS.
     */
    public static function url(string $file): string
    {
        $packaged = __DIR__.'/../../resources/dist/'.$file;
        $published = public_path('vendor/activitylog-ui/'.$file);

        if (is_file($published) && (! is_file($packaged) || static::sameFile($published, $packaged))) {
            return asset('vendor/activitylog-ui/'.$file).'?v='.filemtime($published);
        }

        return route('activitylog-ui.asset', ['file' => $file]).(is_file($packaged) ? '?v='.filemtime($packaged) : '');
    }

    protected static function sameFile(string $first, string $second): bool
    {
        if (filesize($first) !== filesize($second)) {
            return false;
        }

        $algo = in_array('xxh3', hash_algos(), true) ? 'xxh3' : 'md5';

        return hash_file($algo, $first) === hash_file($algo, $second);
    }
}
