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
        $this->response->setHeader('Cache-Control', 'private, no-store, max-age=0');

        return view('teacher/dashboard', [
            'title'      => lang('Workspace.dashboardTitle') . ' — EduTest',
            'quizWorkspace' => false,
            'builderHeader' => false,
            'user'       => $user,
            'dashboard'  => service('teacherQueries')->dashboard((int) service('auth')->id(), $this->request->getGet(), (string) $user['timezone']),
            'dateLabel'   => (new DateTimeImmutable('now', new DateTimeZone((string) $user['timezone'])))->format('l, j F'),
        ]);
    }
}
