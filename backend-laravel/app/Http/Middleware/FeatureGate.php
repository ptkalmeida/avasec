<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Equivalente ao requireFeature do Node (src/server/middlewares/featureGate.ts):
 * rota com feature flag desligada devolve 404 FEATURE_DISABLED, não apenas some do menu.
 * Uso na rota: ->middleware('feature:materiaisComplementares')
 */
final class FeatureGate
{
    public function handle(Request $request, Closure $next, string $flag): Response
    {
        if (config("features.$flag") !== true) {
            throw ApiException::featureDisabled();
        }

        return $next($request);
    }
}
