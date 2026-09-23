<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\View\FlashBag;
use RockAdmin\View\FlashView;
use RockAdmin\View\ViewException;

#[CoversClass(FlashBag::class)]
final class FlashBagTest extends TestCase
{
    public function testAMessageSurvivesUntilItIsTaken(): void
    {
        $session = new ArraySessionStore();

        (new FlashBag($session))->success('Saved.');

        // A different FlashBag over the same session: this is what a redirect
        // is, one request writing and the next one reading.
        $taken = (new FlashBag($session))->take();

        $this->assertCount(1, $taken);
        $this->assertSame('success', $taken[0]->level);
        $this->assertSame('Saved.', $taken[0]->message);
    }

    public function testTakingDrainsTheBag(): void
    {
        // A toast that reappears on the next page is worse than no toast.
        $session = new ArraySessionStore();
        $bag = new FlashBag($session);
        $bag->info('Hello.');

        $this->assertCount(1, $bag->take());
        $this->assertSame([], $bag->take());
        $this->assertSame([], (new FlashBag($session))->take());
    }

    public function testMessagesKeepTheOrderTheyWereAddedIn(): void
    {
        $bag = new FlashBag(new ArraySessionStore());
        $bag->warning('First.');
        $bag->danger('Second.');

        $taken = $bag->take();

        $this->assertSame(['First.', 'Second.'], array_map(
            static fn (FlashView $flash): string => $flash->message,
            $taken,
        ));
    }

    public function testIsEmptyDoesNotDrain(): void
    {
        $bag = new FlashBag(new ArraySessionStore());

        $this->assertTrue($bag->isEmpty());

        $bag->info('Hello.');

        $this->assertFalse($bag->isEmpty());
        $this->assertCount(1, $bag->take());
    }

    public function testAnUnknownLevelIsRefusedWhenItIsAdded(): void
    {
        // Refuse at the point of writing, where the stack trace names the
        // caller — not one request later while rendering.
        $this->expectException(ViewException::class);

        (new FlashBag(new ArraySessionStore()))->add('purple', 'Saved.');
    }

    public function testRubbishInTheSessionIsDiscardedRatherThanRendered(): void
    {
        // The session is data from outside: another application sharing the
        // cookie, an old format after an upgrade, a hand-edited file.
        $session = new ArraySessionStore(['rockadmin.flashes' => 'not an array']);

        $this->assertSame([], (new FlashBag($session))->take());
    }

    public function testAMalformedEntryIsSkippedAndTheRestSurvive(): void
    {
        $session = new ArraySessionStore(['rockadmin.flashes' => [
            ['level' => 'success', 'message' => 'Kept.'],
            ['level' => 'purple', 'message' => 'Dropped.'],
            ['message' => 'Also dropped.'],
            'not an entry',
            ['level' => 'info', 'message' => 'Kept too.'],
        ]]);

        $taken = (new FlashBag($session))->take();

        $this->assertSame(['Kept.', 'Kept too.'], array_map(
            static fn (FlashView $flash): string => $flash->message,
            $taken,
        ));
    }

    public function testTheSessionKeyIsRemovedOnceTheBagIsEmpty(): void
    {
        // Leaving an empty array behind grows every session file by a key that
        // will never be read again.
        $session = new ArraySessionStore();
        $bag = new FlashBag($session);
        $bag->info('Hello.');
        $bag->take();

        $this->assertNull($session->get('rockadmin.flashes'));
    }
}
