<?php

declare(strict_types=1);

namespace App\Controllers\PublicSite;

use App\Controllers\BaseController;
use App\Exceptions\AuthoringException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

final class MediaController extends BaseController
{
    public function show(string $token): ResponseInterface
    {
        try {
            $media = service('media')->resolveSigned($token);
        } catch (AuthoringException) {
            throw PageNotFoundException::forPageNotFound();
        }

        $start = 0;
        $end   = $media['size'] - 1;
        $range = $this->request->getHeaderLine('Range');
        $status = 200;

        if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', $range, $match)) {
            if ($match[1] === '' && $match[2] !== '') {
                $suffixLength = min((int) $match[2], $media['size']);
                $start = $media['size'] - $suffixLength;
            } else {
                $start = $match[1] === '' ? 0 : (int) $match[1];
                $end   = $match[2] === '' ? $end : min((int) $match[2], $end);
            }
            if ($start > $end || $start >= $media['size']) {
                return $this->response->setStatusCode(416)
                    ->setHeader('Content-Range', 'bytes */' . $media['size']);
            }
            $status = 206;
        }

        $length = $end - $start + 1;
        $handle = fopen($media['path'], 'rb');
        if ($handle === false) {
            throw PageNotFoundException::forPageNotFound();
        }
        fseek($handle, $start);
        $body = stream_get_contents($handle, $length);
        fclose($handle);

        $response = $this->response
            ->setStatusCode($status)
            ->setHeader('Content-Type', $media['mime'])
            ->setHeader('Content-Length', (string) $length)
            ->setHeader('Accept-Ranges', 'bytes')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody($body === false ? '' : $body);

        if ($status === 206) {
            $response->setHeader('Content-Range', "bytes {$start}-{$end}/{$media['size']}");
        }

        return $response;
    }
}
