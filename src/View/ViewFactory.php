<?php

declare(strict_types=1);

namespace RockAdmin\View;

use RockAdmin\Config\Config;
use RockAdmin\Http\UrlGenerator;

/**
 * Builds the view layer from a loaded configuration.
 *
 * `template_paths`, `assets.css`, `assets.js` and `theme.dark` are declared
 * in RootSchema and otherwise read by nothing: as shipped, a project has no
 * way to reach the cascade this milestone exists to provide. This is the
 * wiring that reads them — a small, obvious constructor call, not a
 * container, because the SDK still has none.
 */
final class ViewFactory
{
    private readonly TemplateResolver $templates;

    private readonly Renderer $renderer;

    private readonly Assets $assets;

    private readonly string $brand;

    private readonly string $darkMode;

    public function __construct(Config $config, private readonly UrlGenerator $urls)
    {
        $this->templates = new TemplateResolver($this->stringList($config, 'template_paths'));
        $this->renderer = new Renderer($this->templates, new Escaper(), $this->urls);
        $this->assets = new Assets(
            $this->urls,
            $this->stringList($config, 'assets.css'),
            $this->stringList($config, 'assets.js'),
        );
        $this->brand = $this->string($config, 'brand', 'RockAdmin');
        $this->darkMode = $this->string($config, 'theme.dark', 'auto');
    }

    public function templates(): TemplateResolver
    {
        return $this->templates;
    }

    public function renderer(): Renderer
    {
        return $this->renderer;
    }

    public function assets(): Assets
    {
        return $this->assets;
    }

    /** The brand configuration would otherwise build a ShellView with. */
    public function brand(): string
    {
        return $this->brand;
    }

    /** The dark mode a ShellView would otherwise be built with. */
    public function darkMode(): string
    {
        return $this->darkMode;
    }

    /**
     * A configuration value is `mixed` however the schema types it; this is
     * the one place that narrows it back down honestly rather than casting
     * past it, the same way demo/index.php did before this class existed.
     *
     * @return list<string>
     */
    private function stringList(Config $config, string $path): array
    {
        $value = $config->get($path, []);

        if (!\is_array($value)) {
            return [];
        }

        $clean = [];

        foreach ($value as $item) {
            if (\is_string($item)) {
                $clean[] = $item;
            }
        }

        return $clean;
    }

    private function string(Config $config, string $path, string $default): string
    {
        $value = $config->get($path, $default);

        return \is_string($value) ? $value : $default;
    }
}
