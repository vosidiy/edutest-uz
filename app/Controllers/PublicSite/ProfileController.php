<?php

declare(strict_types=1);

namespace App\Controllers\PublicSite;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;

final class ProfileController extends BaseController
{
    public function show(string $username): string
    {
        $profile = service('publicProfiles')->page($username, $this->request->getGet('page'));
        if ($profile === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $this->response
            ->setHeader('Cache-Control', 'no-store')
            ->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->setHeader('X-Content-Type-Options', 'nosniff');

        return view('public/profile', [
            'title'   => $profile['teacher']['displayName'] . ' — 123test',
            'profile' => $profile,
        ]);
    }
}
