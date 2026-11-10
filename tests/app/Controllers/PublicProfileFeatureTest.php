<?php

declare(strict_types=1);

namespace Tests\App\Controllers;

use App\Exceptions\AuthoringException;
use App\Models\UserModel;
use App\Services\AuthService;
use App\Services\PublicProfileService;
use App\Services\TeacherQueryService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Filters;
use Config\Services;
use Tests\Support\PlayerTestCase;

final class PublicProfileFeatureTest extends PlayerTestCase
{
    use FeatureTestTrait;

    private PublicProfileService $profiles;

    protected function setUp(): void
    {
        parent::setUp();
        service('session')->destroy();
        $_SESSION = [];
        $this->profiles = new PublicProfileService($this->db, $this->media);
        Services::injectMock('publicProfiles', $this->profiles);
        Services::injectMock('quizAuthoring', $this->authoring);
        Services::injectMock('media', $this->media);
        Services::injectMock('teacherQueries', new TeacherQueryService($this->db, $this->media));
        Services::injectMock('auth', new AuthService(new UserModel($this->db), service('session')));
    }

    protected function tearDown(): void
    {
        foreach (['publicProfiles', 'quizAuthoring', 'media', 'teacherQueries', 'auth'] as $name) {
            Services::resetSingle($name);
        }
        service('session')->destroy();
        $_SESSION = [];
        parent::tearDown();
    }

    public function testAvailabilityNormalizesAndRespectsCurrentOwnership(): void
    {
        $first = $this->quiz();
        $second = $this->quiz();

        $available = $this->profiles->availability($first['owner'], ' Ada-Lovelace ');
        self::assertSame(['username' => 'ada-lovelace', 'valid' => true, 'available' => true], $available);
        self::assertFalse($this->profiles->availability($first['owner'], 'bad--name')['valid']);

        $saved = $this->profiles->update($first['owner'], [
            'username' => 'Ada-Lovelace', 'bio' => 'First teacher', 'publicPage' => true,
        ]);
        self::assertSame('ada-lovelace', $saved['username']);
        self::assertTrue($this->profiles->availability($first['owner'], 'ada-lovelace')['available']);
        self::assertFalse($this->profiles->availability($second['owner'], 'ada-lovelace')['available']);

        try {
            $this->profiles->update($second['owner'], [
                'username' => 'ada-lovelace', 'bio' => '', 'publicPage' => true,
            ]);
            self::fail('A duplicate username must be rejected.');
        } catch (AuthoringException $exception) {
            self::assertArrayHasKey('username', $exception->fields);
        }

        $this->profiles->update($first['owner'], [
            'username' => 'new-teacher-link', 'bio' => 'First teacher', 'publicPage' => false,
        ]);
        self::assertTrue($this->profiles->availability($second['owner'], 'ada-lovelace')['available']);
        self::assertNull($this->profiles->page('new-teacher-link'));
    }

    public function testPublicPageShowsOnlyOwnersActiveQuizzesAndEscapesProfileText(): void
    {
        $quiz = $this->quiz();
        $draft = $this->authoring->create($quiz['owner'], 'Private draft');
        self::assertNotEmpty($draft['publicId']);
        $other = $this->quiz();
        $this->db->table('quizzes')->where('public_id', $other['publicId'])->update(['title' => 'Other teacher quiz']);
        $this->profiles->update($quiz['owner'], [
            'username' => 'teacher-one',
            'bio' => "Helpful <script>alert(1)</script>\nSecond line",
            'publicPage' => true,
        ]);

        $response = $this->get('/user/teacher-one');
        $response->assertOK();
        $response->assertSee('Quiz Teacher');
        $response->assertSee('Capital cities');
        $response->assertDontSee('Private draft');
        $response->assertDontSee('Other teacher quiz');
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;<br', $response->getBody());
        self::assertStringNotContainsString('<script>alert(1)</script>', $response->getBody());
        self::assertStringContainsString('no-store', $response->response()->getHeaderLine('Cache-Control'));

        $intro = $this->get('/q/' . $quiz['share']);
        $intro->assertOK();
        $intro->assertSee(site_url('user/teacher-one'));
    }

    public function testDisabledAndInvalidProfilesStayUnavailableWhileEnabledEmptyPageRenders(): void
    {
        $quiz = $this->quiz();
        $this->profiles->update($quiz['owner'], [
            'username' => 'hidden-teacher', 'bio' => '', 'publicPage' => false,
        ]);
        self::assertNull($this->profiles->page('hidden-teacher'));
        self::assertNull($this->profiles->page('Hidden-Teacher'));

        $now = '2026-01-01 00:00:00';
        $this->db->table('users')->insert([
            'email' => 'empty@example.test', 'password_hash' => 'unused', 'display_name' => 'Empty Teacher',
            'username' => null, 'bio' => '', 'public_page' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $owner = (int) $this->db->insertID();
        $this->profiles->update($owner, ['username' => 'empty-teacher', 'bio' => '', 'publicPage' => true]);
        $empty = $this->get('/user/empty-teacher');
        $empty->assertOK();
        $empty->assertSee('No Active quizzes yet');

        $this->db->table('users')->where('id', $owner)->update(['active' => 0]);
        self::assertNull($this->profiles->page('empty-teacher'));
        $this->db->table('users')->where('id', $owner)->update(['active' => 1, 'deleted_at' => $now]);
        self::assertNull($this->profiles->page('empty-teacher'));
    }

    public function testPublicQuizCardsArePaginatedTwentyPerPage(): void
    {
        $quiz = $this->quiz();
        $this->profiles->update($quiz['owner'], [
            'username' => 'many-quizzes', 'bio' => '', 'publicPage' => true,
        ]);
        for ($index = 1; $index <= 20; $index++) {
            $this->db->table('quizzes')->insert([
                'user_id' => $quiz['owner'],
                'public_id' => md5('profile-quiz-' . $index),
                'share_token' => str_pad((string) (500000000 + $index), 9, '0', STR_PAD_LEFT),
                'status' => 'published',
                'title' => 'Extra quiz ' . $index,
                'description' => '',
                'created_at' => '2026-01-02 00:00:00',
                'updated_at' => '2026-01-02 00:00:00',
            ]);
        }

        $first = $this->profiles->page('many-quizzes', 1);
        $second = $this->profiles->page('many-quizzes', 2);
        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame(21, $first['pagination']['total']);
        self::assertSame(2, $first['pagination']['pageCount']);
        self::assertCount(20, $first['quizzes']);
        self::assertCount(1, $second['quizzes']);
    }

    public function testAuthenticatedApiAndDashboardExposePublicPageState(): void
    {
        $quiz = $this->quiz();
        $session = [AuthService::SESSION_KEY => $quiz['owner']];

        $availability = $this->withSession($session)
            ->get('/api/v1/account/public-page/availability?username=teacher-api');
        $availability->assertOK();
        $availabilityData = json_decode((string) $availability->getJSON(), true, flags: JSON_THROW_ON_ERROR)['data'];
        self::assertSame('teacher-api', $availabilityData['username']);
        self::assertTrue($availabilityData['valid']);
        self::assertTrue($availabilityData['available']);

        $saved = $this->withoutGlobalFilters(fn () => $this->withSession($session)
            ->withBodyFormat('json')
            ->put('/api/v1/account/public-page', [
                'username' => 'teacher-api', 'bio' => 'API bio', 'publicPage' => true,
            ]));
        $saved->assertOK();
        $savedData = json_decode((string) $saved->getJSON(), true, flags: JSON_THROW_ON_ERROR)['data'];
        self::assertSame('teacher-api', $savedData['username']);
        self::assertTrue($savedData['publicPage']);

        $dashboard = $this->withSession($session)->get('/dashboard');
        $dashboard->assertOK();
        $dashboard->assertSee('View my page');
        $dashboard->assertDontSee('data-open-public-page');
    }

    public function testUnknownPublicProfileReturnsNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);
        $this->get('/user/missing-teacher');
    }

    private function withoutGlobalFilters(callable $request): mixed
    {
        $filters = config(Filters::class);
        $before = $filters->globals['before'];
        $after = $filters->globals['after'];
        $filters->globals['before'] = [];
        $filters->globals['after'] = [];

        try {
            return $request();
        } finally {
            $filters->globals['before'] = $before;
            $filters->globals['after'] = $after;
        }
    }
}
