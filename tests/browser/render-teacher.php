<?php

declare(strict_types=1);

// Actual teacher views, isolated fixture data, no application database connection.
chdir(dirname(__DIR__, 2));
require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';
$origin = $argv[1] ?? '';
if (! preg_match('~^http://127\.0\.0\.1:[0-9]+/$~D', $origin)) throw new RuntimeException('Loopback fixture origin required.');
config('App')->baseURL = $origin;
config('App')->indexPage = '';
$uri = new \CodeIgniter\HTTP\SiteURI(config('App'), '', '127.0.0.1', 'http');
\Config\Services::injectMock('request', new \CodeIgniter\HTTP\IncomingRequest(config('App'), $uri, null, new \CodeIgniter\HTTP\UserAgent()));
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$view = match ($input['page']) {
    'dashboard' => 'teacher/dashboard',
    'builder' => 'teacher/builder',
    'responses' => 'teacher/results/quiz',
    'attempt' => 'teacher/results/attempt',
    default => throw new RuntimeException('Unknown fixture page.'),
};
echo view($view, $input['data']);
