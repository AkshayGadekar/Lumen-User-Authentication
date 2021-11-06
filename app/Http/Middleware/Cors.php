<?php

namespace App\Http\Middleware;

use Closure;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Pre-Middleware Action
        $headers = [
            'Access-Control-Allow-Origin'      => 'http://localhost:3000',
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age'           => '86400',
            'Access-Control-Allow-Headers'     => 'Content-Type, Accept, Authorization, Origin, X-Requested-With'
        ];
        
        //Sometimes, your user-agent (browser) sends an OPTIONS request first as a form of verification request. 
        //However, lumen, somehow, does not recognize the OPTIONS request method and rejects it with a 508. 
        //Hence the need to specifically check this request and grant it access.
        //though OPTIONS request verification in necessary
        if ($request->isMethod('OPTIONS'))
        {
            return response()->json(["method" => "OPTIONS"], 200, $headers);
        }

        $response = $next($request);

        // Post-Middleware Action
        if ($response instanceof \Illuminate\Http\Response || $response instanceof \Illuminate\Http\JsonResponse) {
            foreach ($headers as $key => $value) {
                $response->header($key, $value);
            }
        } else if($response instanceof \Symfony\Component\HttpFoundation\Response) {
            foreach ($headers as $key => $value) {
                $response->headers->set($key, $value);
            }
        }

        return $response;

    }
}
