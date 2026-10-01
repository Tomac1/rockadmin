<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

use InvalidArgumentException;
use RockAdmin\Auth\Gate;
use RockAdmin\Auth\Identity;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\ActionDefinition;
use RockAdmin\View\ActionView;

/**
 * Turns the actions a page declared into the ones it actually draws.
 *
 * Three filters and one substitution, in that order, and each of them is a
 * decision this task was told not to re-derive:
 *
 * **The gate decides what is drawn** (spec 9.2), through the same
 * `Gate::allows()` the kernel calls at step 7 — so a control drawn here is one
 * the kernel will accept, and one it would refuse never appears. Milestone 5
 * built that for the create, edit and delete controls; actions join it rather
 * than growing a second rule.
 *
 * **`when` decides what is offered**, per row, from that row's own values. It
 * is a presentation decision only: `ActionHandler` re-checks it on the server,
 * because a client that was never shown an action can still post it.
 *
 * **A row placeholder is substituted by `RowPlaceholders`**, never here. That
 * class refuses a column the row does not carry, a value with a slash in it, a
 * dot segment, a `{{…}}` written where `{…}` was meant, and a brace in the
 * data. Building the URL straight from the reference would bypass all of it,
 * and the only symptom would be `UrlGenerator` blaming the template for a
 * placeholder "nobody substituted".
 *
 * It is one class for the grid and the page header because the three filters
 * are the same in both places; what differs is only whether there is a row.
 */
final class ActionBuilder
{
    public function __construct(
        private readonly Gate $gate,
        private readonly Identity $identity,
        private readonly UrlGenerator $urls,
        /**
         * The session's token, needed by every `post` action and by nothing
         * else. Nullable because most of what builds a `ListRegion` draws no
         * action at all, and a constructor argument nobody can supply is worse
         * than one that is absent — but a `post` with no token would be a
         * visible control that answers 403, so `forRow()` refuses to draw one
         * rather than drawing it unsigned.
         */
        private readonly ?Csrf $csrf = null,
    ) {
    }

    /**
     * The actions one row of a grid draws.
     *
     * @param  list<ActionDefinition> $actions
     * @param  array<string, mixed>   $row      the row being rendered, as the RowSource gave it
     * @return list<ActionView>
     */
    public function forRow(string $pageName, array $actions, array $row): array
    {
        $views = [];

        foreach ($actions as $action) {
            if (!$this->allowed($action) || !$this->offeredFor($action, $row)) {
                continue;
            }

            $views[] = $this->view(
                $action,
                $this->urls->reference(RowPlaceholders::reference($action->to, $row), $pageName),
            );
        }

        return $views;
    }

    /**
     * The actions a page header draws, where there is no row.
     *
     * `PageRepository` refuses a `when` and a row placeholder on one of these,
     * so neither case can arrive here — there is nothing to filter by and
     * nothing to substitute.
     *
     * @param  list<ActionDefinition> $actions
     * @return list<ActionView>
     */
    public function forPage(string $pageName, array $actions): array
    {
        $views = [];

        foreach ($actions as $action) {
            if (!$this->allowed($action)) {
                continue;
            }

            $views[] = $this->view($action, $this->urls->reference($action->to, $pageName));
        }

        return $views;
    }

    private function view(ActionDefinition $action, string $url): ActionView
    {
        return new ActionView(
            key: $action->key,
            label: $action->label,
            url: $url,
            isPost: $action->type->isWrite(),
            confirm: $action->confirm,
            icon: $action->icon === '' ? null : $action->icon,
            token: $action->type->isWrite() ? $this->token($action) : '',
        );
    }

    /**
     * An action with no `permission` is drawn to anyone who can see the page.
     *
     * That is the loader's decision rather than this one's: the schema tells
     * whoever writes an action to set `permission` on every action that changes
     * data, and refusing an unset one here would make every `link` action need
     * a permission to navigate to a page that gates itself.
     */
    private function allowed(ActionDefinition $action): bool
    {
        return $action->permission === ''
            || $this->gate->allows($this->identity->user(), $action->permission);
    }

    /**
     * Whether this row's values match every condition the action set.
     *
     * The comparison is by text, because a value's PHP type depends on the
     * driver and the column: MySQL hands back `'1'` for a TINYINT where
     * PostgreSQL hands back `true`, and a page file writing
     * `'when' => ['active' => true]` means the same thing on both. A boolean is
     * spelled `'1'` and `'0'` on both sides of the comparison so `false` does
     * not become the empty string and match a NULL column.
     *
     * A row that does not carry the column is refused rather than treated as
     * not matching: the loader has already established that the region declares
     * it, so its absence means the region and the row disagree, and silently
     * hiding every action is the kind of emptiness nobody can explain.
     *
     * @param array<string, mixed> $row
     */
    private function offeredFor(ActionDefinition $action, array $row): bool
    {
        foreach ($action->when as $column => $expected) {
            if (!\array_key_exists($column, $row)) {
                throw new InvalidArgumentException(\sprintf(
                    "The action '%s' is offered when '%s' holds '%s', and the row being rendered carries no "
                    . "'%s'. The row carries: %s. A condition is tested against the row, so the region has "
                    . 'to select the column.',
                    $action->key,
                    $column,
                    $this->text($expected),
                    $column,
                    $row === [] ? 'nothing at all' : implode(', ', array_keys($row)),
                ));
            }

            $value = $row[$column];

            if (!\is_scalar($value) && $value !== null) {
                return false;
            }

            if ($this->text($value) !== $this->text($expected)) {
                return false;
            }
        }

        return true;
    }

    private function text(bool|float|int|string|null $value): string
    {
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    /**
     * The session's token for a form this builder did not itself draw.
     *
     * The selection form is one: it carries the token once for every bulk
     * action on it, because a submit button chooses which action runs with
     * `formaction` and they all post the same body. Empty when there is no
     * `Csrf`, which is also when `forPage()` has refused to draw any `post`
     * action, so an empty token can only ever reach a form with no buttons.
     */
    public function sessionToken(): string
    {
        return $this->csrf?->token() ?? '';
    }

    private function token(ActionDefinition $action): string
    {
        if ($this->csrf === null) {
            throw new InvalidArgumentException(\sprintf(
                "The action '%s' is a 'post', so the control that performs it has to carry a CSRF token, and "
                . 'this region was built without a Csrf. Pass one to ListRegion, or the button would be a '
                . 'visible control that answers 403.',
                $action->key,
            ));
        }

        return $this->csrf->token();
    }
}
