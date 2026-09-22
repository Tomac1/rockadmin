<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\ConfigException;
use RockAdmin\Config\EnumReference;
use RockAdmin\Config\Placeholder;
use RockAdmin\Config\Resolver;

#[CoversClass(Resolver::class)]
#[CoversClass(Placeholder::class)]
#[CoversClass(EnumReference::class)]
final class ResolverTest extends TestCase
{
    /**
     * @param array<string, string>  $env
     * @param array<string, mixed>   $raw
     */
    private function resolver(array $env = [], array $raw = []): Resolver
    {
        return new Resolver(
            static fn (string $name): ?string => $env[$name] ?? null,
            $raw,
        );
    }

    public function testAnEnvironmentPlaceholderResolvesToItsValue(): void
    {
        $resolved = $this->resolver(['MAIL_HOST' => 'smtp.example.com'])
            ->resolve(['mail' => ['host' => '{{env.MAIL_HOST}}']]);

        $this->assertSame(['mail' => ['host' => 'smtp.example.com']], $resolved);
    }

    public function testAMissingEnvironmentVariableResolvesToNull(): void
    {
        $resolved = $this->resolver()->resolve(['host' => '{{env.MAIL_HOST}}']);

        $this->assertSame(['host' => null], $resolved);
    }

    public function testAPlaceholderInsideTextIsInterpolated(): void
    {
        $resolved = $this->resolver(['CDN' => 'cdn.example.com'])
            ->resolve(['url' => 'https://{{env.CDN}}/assets']);

        $this->assertSame(['url' => 'https://cdn.example.com/assets'], $resolved);
    }

    public function testAConfigPlaceholderReadsTheRawValue(): void
    {
        $resolved = $this->resolver(raw: ['brand' => 'RockAdmin'])
            ->resolve(['title' => '{{config.brand}} admin']);

        $this->assertSame(['title' => 'RockAdmin admin'], $resolved);
    }

    public function testADeferredPlaceholderBecomesAnObject(): void
    {
        $resolved = $this->resolver()->resolve(['scope' => ['site_id' => '{{workspace.site_id}}']]);

        /** @var array<string, mixed> $scope */
        $scope = $resolved['scope'];
        $placeholder = $scope['site_id'];

        $this->assertInstanceOf(Placeholder::class, $placeholder);
        $this->assertSame('workspace', $placeholder->namespace);
        $this->assertSame('site_id', $placeholder->name);
        $this->assertSame('{{workspace.site_id}}', (string) $placeholder);
    }

    public function testADeferredPlaceholderInsideTextIsRefused(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('{{workspace.site_id}}');

        $this->resolver()->resolve(['label' => 'site {{workspace.site_id}}']);
    }

    public function testAnUnknownNamespaceIsRefused(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('magic');

        $this->resolver()->resolve(['x' => '{{magic.thing}}']);
    }

    public function testAnEnumReferenceBecomesAnObject(): void
    {
        $resolved = $this->resolver()->resolve(['options' => '@enum:ad_state']);

        $this->assertInstanceOf(EnumReference::class, $resolved['options']);
        $this->assertSame('ad_state', $resolved['options']->key);
    }

    public function testNonStringValuesArePassedThroughUnchanged(): void
    {
        $resolved = $this->resolver()->resolve(['per_page' => 50, 'debug' => true, 'none' => null]);

        $this->assertSame(['per_page' => 50, 'debug' => true, 'none' => null], $resolved);
    }

    public function testASecretLookingVariableIsWarnedAbout(): void
    {
        $resolver = $this->resolver(['MAIL_PASSWORD' => 'hunter2']);
        $resolver->resolve(['mail' => ['password' => '{{env.MAIL_PASSWORD}}']]);

        $this->assertCount(1, $resolver->warnings());
        $this->assertStringContainsString('MAIL_PASSWORD', $resolver->warnings()[0]);
    }

    public function testAnOrdinaryVariableIsNotWarnedAbout(): void
    {
        $resolver = $this->resolver(['MAIL_HOST' => 'smtp.example.com']);
        $resolver->resolve(['mail' => ['host' => '{{env.MAIL_HOST}}']]);

        $this->assertSame([], $resolver->warnings());
    }

    public function testObjectsSurviveVarExport(): void
    {
        $placeholder = new Placeholder('workspace', 'site_id');
        $enum = new EnumReference('ad_state');

        /** @var Placeholder $restoredPlaceholder */
        $restoredPlaceholder = eval('return ' . var_export($placeholder, true) . ';');
        /** @var EnumReference $restoredEnum */
        $restoredEnum = eval('return ' . var_export($enum, true) . ';');

        $this->assertEquals($placeholder, $restoredPlaceholder);
        $this->assertEquals($enum, $restoredEnum);
    }

    public function testANonScalarCannotBeInterpolatedIntoAString(): void
    {
        $resolver = $this->resolver(raw: ['nested' => ['a' => 1]]);

        $this->expectException(ConfigException::class);

        $resolver->resolve(['label' => 'id {{config.nested}}']);
    }

    public function testTwoAdjacentPlaceholdersAreBothResolved(): void
    {
        $resolved = $this->resolver(['A' => 'foo', 'B' => 'bar'])->resolve(['x' => '{{env.A}}{{env.B}}']);

        $this->assertSame(['x' => 'foobar'], $resolved);
    }

    public function testAConfigPlaceholderPointingAtADeferredOneYieldsAPlaceholder(): void
    {
        $resolved = $this->resolver(raw: ['tenant' => '{{workspace.site_id}}'])
            ->resolve(['scope' => '{{config.tenant}}']);

        $this->assertInstanceOf(Placeholder::class, $resolved['scope']);
        $this->assertSame('workspace', $resolved['scope']->namespace);
        $this->assertSame('site_id', $resolved['scope']->name);
    }

    public function testADeferredPlaceholderReachedThroughConfigIsStillRefusedInsideText(): void
    {
        $resolver = $this->resolver(raw: ['tenant' => '{{workspace.site_id}}']);

        $this->expectException(ConfigException::class);

        $resolver->resolve(['label' => 'site-{{config.tenant}}']);
    }

    public function testAConfigPlaceholderResolvesAnEnvironmentValueThroughIt(): void
    {
        $resolved = $this->resolver(['MAIL_HOST' => 'smtp.example.com'], ['host' => '{{env.MAIL_HOST}}'])
            ->resolve(['mail' => '{{config.host}}']);

        $this->assertSame(['mail' => 'smtp.example.com'], $resolved);
    }

    public function testAConfigPlaceholderCycleIsReportedWithItsChain(): void
    {
        $resolver = $this->resolver(raw: ['a' => '{{config.b}}', 'b' => '{{config.a}}']);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('a -> b -> a');

        $resolver->resolve(['x' => '{{config.a}}']);
    }

    public function testALowercaseSecretLookingVariableIsWarnedAboutToo(): void
    {
        $resolver = $this->resolver(['db_password' => 'hunter2']);
        $resolver->resolve(['db' => ['password' => '{{env.db_password}}']]);

        $this->assertCount(1, $resolver->warnings());
        $this->assertStringContainsString('db_password', $resolver->warnings()[0]);
    }

    public function testTheSameVariableIsWarnedAboutOnlyOnce(): void
    {
        $resolver = $this->resolver(['API_KEY' => 'k']);
        $resolver->resolve(['a' => '{{env.API_KEY}}', 'b' => '{{env.API_KEY}}', 'c' => '{{env.API_KEY}}']);

        $this->assertCount(1, $resolver->warnings());
    }
}
