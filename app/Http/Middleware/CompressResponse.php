<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pages and JSON go out gzipped. The web server compresses some pages but not all of them,
 * so the app does it itself: about 75% fewer bytes on slow connections.
 */
class CompressResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse
            || ! function_exists('gzencode')
            || ! str_contains((string) $request->header('Accept-Encoding'), 'gzip')
            || $response->headers->has('Content-Encoding')
            || ! preg_match('#^(text/|application/(json|xml|ld\+json))#', (string) $response->headers->get('Content-Type', 'text/html'))) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false || strlen($content) < 1024) {
            return $response;
        }

        $response->setContent(gzencode($content, 6));
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($response->getContent()));
        $response->setVary('Accept-Encoding', false);
        // a filter of the web server unpacks small pages again; this asks it to leave the page as it is
        $response->headers->addCacheControlDirective('no-transform');

        return $response;
    }
}
