<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * The one place a row changes.
 *
 * Inline editing, a form and a bulk action all end here — there is no second
 * way to write. A project may replace this wholesale (an ORM-backed
 * implementation is a documented recipe, kept out of the package so no ORM
 * enters the dependency list) or leave it as the default `SqlWriteHandler`.
 *
 * **Every write takes a scope, and it is not optional.** Specification 9.3
 * says the workspace scope applies "to update, delete and preview as well as
 * list", and the reason it has to reach this far is the one 9.3 gives about
 * filters: a boundary that can be edited out of a request is not a boundary.
 * `POST /a/ads/update` carrying another tenant's `_id` passes the gate,
 * carries a valid token and names a row that exists — and without a scope
 * here it writes across the boundary with nothing to notice it.
 *
 * It is a required argument rather than one defaulting to `[]`, which breaks
 * every implementation and every caller. That is deliberate: a default
 * meaning "unscoped" is exactly the trap this exists to close, and the places
 * it breaks are the places somebody has to look at. An admin with no
 * workspaces passes `[]` and nothing changes for it.
 *
 * A scope's values are already bound by then — `Binder` turned
 * `{{workspace.tenant_id}}` into a value before it got here — so an
 * implementation only ever sees columns and values.
 */
interface WriteHandler
{
    /**
     * A new row.
     *
     * No scope argument, because an INSERT has no WHERE. The caller puts the
     * workspace's own columns into `$values`, after validation and over
     * anything submitted — see `ActionHandler`, which is where a create is
     * placed into the active workspace rather than into whichever one a
     * hidden input claimed.
     *
     * @param array<string, mixed> $values
     */
    public function insert(Entity $entity, array $values): WriteResult;

    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $scope  column => value, every one of which the row must already
     *                                     match; `[]` for an admin with no workspaces
     *
     * @throws NoSuchRowException when the key names no row *inside the scope* — which is what a row
     *                            in another workspace is, from the asker's side
     */
    public function update(Entity $entity, string $key, array $values, array $scope): WriteResult;

    /**
     * @param array<string, mixed> $scope
     *
     * @throws NoSuchRowException when the key names no row inside the scope
     */
    public function delete(Entity $entity, string $key, array $scope): WriteResult;
}
