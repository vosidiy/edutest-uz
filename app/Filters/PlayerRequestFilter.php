<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class PlayerRequestFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');
        $base = parse_url(site_url());
        $expected = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');
        $method = strtoupper($request->getMethod());
        if ($request->getHeaderLine('X-EduTest-Player') !== '1' || ($origin !== '' && $origin !== $expected)
            || in_array($request->getHeaderLine('Sec-Fetch-Site'), ['cross-site', 'same-site'], true)) return $this->error('origin_rejected', 403);
        if ($method === 'POST' && ! preg_match('~^application/json(?:\s*;|$)~i', $request->getHeaderLine('Content-Type'))) return $this->error('invalid_json', 415);
        if (strlen((string) $request->getBody()) > 1048576) return $this->error('request_too_large', 413);
        // Deliberately endpoint-wide, without IP, user-agent, credential, or practice-session keys.
        $parts = explode('/', trim($request->getUri()->getPath(), '/'));
        $index = array_search('player', $parts, true);
        $endpoint = $index === false ? 'unknown' : ($parts[$index + 1] ?? 'unknown');
        $bucket = 'player-request-' . hash('sha256', $endpoint);
        if (! service('throttler')->check($bucket, 600, MINUTE)) return $this->error('rate_limited', 429)->setHeader('Retry-After', '10');
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        $response->setHeader('Cache-Control', 'no-store, private')->setHeader('X-Content-Type-Options', 'nosniff')->setHeader('Referrer-Policy', 'no-referrer');
    }

    private function error(string $code, int $status): ResponseInterface
    {
        return service('response')->setStatusCode($status)->setHeader('Cache-Control', 'no-store')->setJSON([
            'error' => ['code' => $code, 'message' => lang('Player.errors.' . $code), 'fields' => (object) []],
            'meta' => ['timestamp' => \App\Services\Player\PlayerStore::iso(\App\Services\Player\PlayerStore::now())],
        ]);
    }
}
