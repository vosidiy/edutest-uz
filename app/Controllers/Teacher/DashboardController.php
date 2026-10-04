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
        $library = service('teacherQueries')->library(
            (int) service('auth')->id(),
            $this->request->getGet(),
            (string) $user['timezone'],
        );
        $pagination = $library['pagination'];
        $paginationNavigation = [
            'pageCount' => (int) $pagination['pageCount'],
            'previousUrl' => null,
            'nextUrl' => null,
        ];
        if ($paginationNavigation['pageCount'] > 1) {
            $pager = service('pager')->only(['status', 'mode', 'sort']);
            $pager->store(
                'default',
                (int) $pagination['page'],
                (int) $pagination['pageSize'],
                (int) $pagination['total'],
            );
            $paginationNavigation['previousUrl'] = $pager->getPreviousPageURI();
            $paginationNavigation['nextUrl'] = $pager->getNextPageURI();
        }
        $this->response->setHeader('Cache-Control', 'private, no-store, max-age=0');

        return view('teacher/dashboard', [
            'title'      => lang('Workspace.dashboardTitle') . ' — EduTest',
            'quizWorkspace' => false,
            'builderHeader' => false,
            'user'       => $user,
            'library'    => $library,
            'paginationNavigation' => $paginationNavigation,
            'dateLabel'   => (new DateTimeImmutable('now', new DateTimeZone((string) $user['timezone'])))->format('l, j F'),
        ]);
    }
}
