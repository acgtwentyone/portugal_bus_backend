<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileApi
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Validate: Check if the Header 'X-App-Name' exists and its value is 'PortugalBus'
        $appName = $request->header('X-App-Name');

        if ($appName !== 'PortugalBus') {
            return response()->json([
                'message' => 'Unauthorized. Only PortugalBus app can access this API.'
            ], 403);
        }

        return $next($request);
    }
}
