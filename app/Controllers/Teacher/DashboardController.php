<?php

declare(strict_types=1);

namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use DateTimeImmutable;
use DateTimeZone;

final class DashboardController extends BaseController
{
    public function index(): string
    {
        $user = service('auth')->user();

        return view('teacher/dashboard', [
            'title'      => 'Overview — EduTest',
            'activeNav'  => 'dashboard',
            'user'       => $user,
            'dashboard'  => service('teacherQueries')->dashboard((int) $user['id']),
            'dateLabel'   => (new DateTimeImmutable('now', new DateTimeZone((string) $user['timezone'])))->format('l, j F'),
        ]);
    }
}
