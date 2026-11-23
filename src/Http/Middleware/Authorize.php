<?php

namespace Puzzlesoft\ActivitylogUi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Puzzlesoft\ActivitylogUi\ActivitylogUi;

class Authorize
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(ActivitylogUi::check($request), 403);

        return $next($request);
    }
}
