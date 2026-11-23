<?php

namespace Puzzlesoft\ActivitylogUi\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the compiled assets straight from the package when they have not
 * been published to public/vendor/activitylog-ui.
 */
class AssetController
{
    public function __invoke(string $file): BinaryFileResponse
    {
        $path = __DIR__.'/../../../resources/dist/'.$file;

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => str_ends_with($file, '.css') ? 'text/css; charset=UTF-8' : 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
