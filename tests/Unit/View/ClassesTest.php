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

    public function testSeveralAppearanceClassesMayArriveInOneString(): void
    {
        // This is how configuration writes them: 'class' => 'text-end fw-bold'.
        $this->assertSame('ra-grid-cell text-end fw-bold', Classes::of('grid-cell', null, ['text-end fw-bold']));
    }

    public function testAnAppearanceClassKeepsTheSpellingItsFrameworkUses(): void
    {
        // Utility frameworks use capitals, colons and slashes. ra- classes are
        // kebab-case; nothing else here has to be.
        $this->assertSame(
            'ra-grid-cell md:w-1/2 textLarge',
            Classes::of('grid-cell', null, ['md:w-1/2', 'textLarge']),
        );
    }

    /** @return array<string, array{string}> */
    public static function classesThatWouldEndTheAttribute(): array
    {
        return [
            'double quote' => ['btn" onclick=alert(1) x="'],
            'single quote' => ["btn' onclick=alert(1) x='"],
            'angle bracket' => ['btn><script'],
            'backtick' => ['btn`x'],
            'backslash' => ['btn\\x'],
            'newline' => ["btn\nx"],
            'null byte' => ["btn\0x"],
        ];
    }

    #[DataProvider('classesThatWouldEndTheAttribute')]
    public function testAnAppearanceClassThatWouldEndTheAttributeIsRefused(string $class): void
    {
        // These come from configuration, which a person or an agent writes.
        // Escaping is not the answer: a class attribute full of entities is
        // not a class anyone can target, so the only honest move is to refuse.
        $this->expectException(ViewException::class);

        Classes::of('grid-cell', null, [$class]);
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

    /** @return array<string, array{string, string}> */
    public static function identitiesFromConfigurationKeys(): array
    {
        return [
            'already kebab-case' => ['user-accounts', 'ra-page-user-accounts'],
            'snake_case, as this project writes configuration keys' => ['user_accounts', 'ra-page-user-accounts'],
            'a single word' => ['users', 'ra-page-users'],
            'capitals' => ['Users', 'ra-page-users'],
            'mixed' => ['User_Accounts', 'ra-page-user-accounts'],
            'a digit' => ['step_2', 'ra-page-step-2'],
        ];
    }

    #[DataProvider('identitiesFromConfigurationKeys')]
    public function testAnIdentityIsTranslatedFromAConfigurationKey(string $key, string $expected): void
    {
        // Configuration keys are snake_case in this project and CSS classes
        // are kebab-case. A page named user_accounts.php used to throw, which
        // took down the whole page for following the project's own rules.
        $this->assertSame($expected, Classes::identity('page', $key));
    }

    /** @return array<string, array{string}> */
    public static function identitiesThatAreNotWords(): array
    {
        return [
            'a space' => ['user accounts'],
            'a quote' => ['user"accounts'],
            'an angle bracket' => ['user<accounts'],
            'a slash' => ['user/accounts'],
            'empty' => [''],
            'only punctuation' => ['--'],
        ];
    }

    #[DataProvider('identitiesThatAreNotWords')]
    public function testAnIdentityThatIsNotAWordIsStillRefused(string $key): void
    {
        // Translating case and underscores is a convention mapping. Anything
        // that is not a word cannot become a class name by any honest
        // transformation, so it is refused rather than mangled.
        $this->expectException(ViewException::class);

        Classes::identity('page', $key);
    }

    public function testTheStructuralHalfIsStillHeldToKebabCase(): void
    {
        // A structural name is written by a template author, not taken from
        // configuration, so there is no convention to translate.
        $this->expectException(ViewException::class);

        Classes::of('grid_cell');
    }
}
