<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();
        $request->attributes->remove('activity_recorded');
        $response = $next($request);
        $actor ??= $request->user();
        if ($actor && $response->getStatusCode() < 400 && ! $request->attributes->get('activity_recorded')
            && ! ($response->isRedirection() && $request->session()->has('errors'))) {
            $action = match ($request->route()?->getName()) {
                'login.store' => 'login',
                'logout' => 'logout',
                default => $request->isMethod('GET') ? 'visited' : 'executed',
            };
            ActivityLog::record($actor, $action, 'request', 0, metadata: [
                'method' => $request->method(), 'status' => $response->getStatusCode(),
            ]);
        }

        return $response;
    }
}
