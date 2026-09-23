<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\View\Classes;
use RockAdmin\View\ViewException;

#[CoversClass(Classes::class)]
final class ClassesTest extends TestCase
{
    public function testAStructuralClassAlone(): void
    {
        $this->assertSame('ra-grid-row', Classes::of('grid-row'));
    }

    public function testAStructuralClassAndAnIdentityClass(): void
    {
        // The pair the specification asks for: one selector restyles every
        // grid cell, another restyles only the price cell.
        $this->assertSame('ra-grid-cell ra-grid-cell-price', Classes::of('grid-cell', 'price'));
    }

    public function testExtraClassesComeLast(): void
    {
        // Bootstrap classes and configuration's own 'class' key are appended,
        // so they win where specificity ties.
        $this->assertSame(
            'ra-btn ra-btn-create btn btn-primary',
            Classes::of('btn', 'create', ['btn', 'btn-primary']),
        );
    }

    public function testEmptyExtrasAreDropped(): void
    {
        $this->assertSame('ra-btn', Classes::of('btn', null, ['', '  ']));
    }

    public function testDuplicatesAreCollapsed(): void
    {
        $this->assertSame('ra-btn btn', Classes::of('btn', null, ['btn', 'btn']));
    }

    /** @return array<string, array{string}> */
    public static function malformedNames(): array
    {
        return [
            'a quote would end the attribute' => ['grid"cell'],
            'a space would add a class nobody wrote' => ['grid cell'],
            'an angle bracket would end the tag' => ['grid<cell'],
            'empty' => [''],
        ];
    }

    #[DataProvider('malformedNames')]
    public function testAMalformedNameIsRefused(string $name): void
    {
        // These names come from configuration keys — a column named by a
        // person. Refusing beats escaping, because a class attribute full of
        // entities is not a class anyone can target.
        $this->expectException(ViewException::class);

        Classes::of($name);
    }

    public function testIdentityReturnsASingleClass(): void
    {
        $this->assertSame('ra-page-type-list', Classes::identity('page-type', 'list'));
    }

    public function testIdentityRefusesMalformedStructural(): void
    {
        $this->expectException(ViewException::class);

        Classes::identity('page"type', 'list');
    }

    public function testIdentityRefusesMalformedIdentity(): void
    {
        $this->expectException(ViewException::class);

        Classes::identity('page-type', 'list invalid');
    }
}
