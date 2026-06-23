<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Api
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }
}
