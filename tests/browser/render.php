<?php

declare(strict_types=1);

// Render actual student views for the isolated browser fixture server. No database connection.
chdir(dirname(__DIR__, 2));
require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';
$origin = $argv[1] ?? '';
if (! preg_match('~^http://127\.0\.0\.1:[0-9]+/$~D', $origin)) throw new RuntimeException('Loopback fixture origin required.');
config('App')->baseURL = $origin;
config('App')->indexPage = '';
$uri = new \CodeIgniter\HTTP\SiteURI(config('App'), '', '127.0.0.1', 'http');
\Config\Services::injectMock('request', new \CodeIgniter\HTTP\IncomingRequest(config('App'), $uri, null, new \CodeIgniter\HTTP\UserAgent()));
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$page = $input['page'];
echo view($page === 'intro' ? 'public/quiz' : 'student/player', ['quiz' => $input['quiz'], 'shareToken' => $input['quiz']['shareToken'], 'page' => $page, 'title' => $input['quiz']['title'] . ' — EduTest']);
