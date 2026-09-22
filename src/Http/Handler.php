<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/** Something that can answer one kind of route. */
interface Handler
{
    public function handle(Route $route, Request $request): Response;
}
