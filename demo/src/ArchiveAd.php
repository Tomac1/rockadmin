<?php

declare(strict_types=1);

namespace RockAdmin\Demo;

use RockAdmin\Data\Operation;
use RockAdmin\Data\OperationContext;
use RockAdmin\Data\OperationRefused;
use RockAdmin\Db\WriteHandler;
use RockAdmin\Db\WriteResult;
use RockAdmin\View\FlashBag;

/**
 * Archiving an ad, as a project would write it.
 *
 * The demo's one custom operation, so the seam is exercised by something that
 * ships rather than only by tests. It is named `@handler:archive_ad` in
 * `demo/config/pages/ads.php`, and it does the four things a real one does:
 *
 * 1. **Refuses, in words.** An ad with no price is not ready to be archived,
 *    and that rule cannot be written as a `when` condition -- `when` compares
 *    a column to one value, and "has a price at all" is not that. So the
 *    button is offered and the operation explains. `OperationRefused` rolls
 *    everything back and puts the sentence where the person is.
 * 2. **Writes more than one column, in one transaction.** The state changes
 *    and the feature comes off, because an archived ad on the front page would
 *    be a bug the admin created. Either both happen or neither does.
 * 3. **Takes the project's own collaborators.** This one has a `FlashBag`;
 *    Bazik's would have its mailer and its repositories. An operation is the
 *    project's object, constructed by the project, and RockAdmin only calls
 *    it.
 * 4. **Leaves its side effect until after the commit.** A transaction can take
 *    back a row and cannot take back an e-mail.
 *
 * What it does *not* do is the rest of the write: the CSRF check, the
 * permission, the `when` re-check, the scope, the transaction, the audit row,
 * the flash and the redirect are all still RockAdmin's. That is the trade this
 * seam makes -- the inside of the write path rather than a way around it.
 */
final class ArchiveAd implements Operation
{
    public function __construct(private readonly FlashBag $flashes)
    {
    }

    /**
     * @param array<string, mixed> $row    the columns about to be written, as they are now
     * @param array<string, mixed> $values empty here: the action carries a handler instead of a `set`
     */
    public function perform(
        OperationContext $context,
        array $row,
        array $values,
        WriteHandler $writes,
    ): WriteResult {
        // Read defensively, because a column is whatever the driver hands
        // back: one returns an integer price where another returns the string
        // '12000', and a row with no price at all returns null.
        $price = $row['price'] ?? null;
        $amount = is_numeric($price) ? (float) $price : 0.0;

        if ($amount <= 0.0) {
            throw new OperationRefused(
                'This ad has no price, so archiving it would lose the only thing left to fix. '
                . 'Give it a price first, or delete it.',
            );
        }

        $write = $writes->update(
            $context->page->entity,
            (string) $context->key,
            ['state' => 'archived', 'featured' => false],
            $context->scope,
        );

        // After the commit, where a side effect belongs. In a real project
        // this is the line that sends mail; section 10 ships the mailer, and
        // until then the demo says what would have been sent.
        $context->afterCommit(function () use ($context): void {
            $this->flashes->info(\sprintf(
                'The seller of ad %s would be told it was archived%s.',
                (string) $context->key,
                $context->workspace === null ? '' : ', as ' . $context->workspace->label,
            ));
        });

        return $write;
    }
}
