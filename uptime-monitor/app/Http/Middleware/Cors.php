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
        // Allow frontend origin
        $allowedOrigins = [
            'http://localhost:5173',
            'http://localhost:8000',
            'http://127.0.0.1:5173',
            'http://127.0.0.1:8000'
        ];
        
        $origin = $request->header('Origin');
        $allowOrigin = in_array($origin, $allowedOrigins) ? $origin : $allowedOrigins[0];
        
        $headers = [
            'Access-Control-Allow-Origin' => $allowOrigin,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Origin, X-Requested-With, Content-Type, X-Token-Auth, Authorization, Accept, Application',
            'Access-Control-Allow-Credentials' => 'true',
        ];

        // Preflight request: return immediately
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