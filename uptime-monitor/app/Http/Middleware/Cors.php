<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Cors
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Izinkan semua origin secara default; dapat diganti via env jika perlu
        $origin = '*';
        $headers = [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Origin, X-Requested-With, Content-Type, X-Token-Auth, Authorization, Accept, Application',
        ];

        // Preflight request: balas segera agar tidak lewat ke stack lain
        if ($request->isMethod('OPTIONS')) {
            return response()->json(['success' => true], 200, $headers);
        }

        $response = $next($request);
        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}