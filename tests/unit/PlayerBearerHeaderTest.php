<?php

declare(strict_types=1);

use App\Services\Player\BearerHeader;
use CodeIgniter\Test\CIUnitTestCase;

final class PlayerBearerHeaderTest extends CIUnitTestCase
{
    public function testNormalAndApacheRedirectedHeaders(): void
    {
        $this->assertSame('primary', BearerHeader::token('Bearer primary', ['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer other']));
        $this->assertSame('redirected', BearerHeader::token('', ['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer redirected']));
        $this->assertSame('nested', BearerHeader::token('', ['REDIRECT_REDIRECT_HTTP_AUTHORIZATION' => 'Bearer nested']));
        $this->assertNull(BearerHeader::token('malformed', ['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer valid']));
        $this->assertNull(BearerHeader::token('', ['HTTP_REDIRECT_HTTP_AUTHORIZATION' => 'Bearer spoofed']));
        foreach (['', 'Basic abc', "Bearer abc\n", 'Bearer a b', 'Bearer abc,Bearer def'] as $value) {
            $this->assertNull(BearerHeader::token($value, []));
        }
    }
}
