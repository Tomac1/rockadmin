<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\ArraySessionStore;

#[CoversClass(ArraySessionStore::class)]
final class ArraySessionStoreTest extends TestCase
{
    public function testStoresAndReturnsValues(): void
    {
        $session = new ArraySessionStore();

        $this->assertNull($session->get('missing'));
        $this->assertSame('fallback', $session->get('missing', 'fallback'));

        $session->set('user_id', 7);
        $this->assertSame(7, $session->get('user_id'));

        $session->forget('user_id');
        $this->assertNull($session->get('user_id'));
    }

    public function testRegenerateKeepsTheData(): void
    {
        $session = new ArraySessionStore();
        $session->set('user_id', 7);

        $session->regenerate();

        $this->assertSame(7, $session->get('user_id'));
    }
}
