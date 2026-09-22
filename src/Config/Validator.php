<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * Compares a configuration array against a schema.
 *
 * Reports every problem rather than stopping at the first: a developer fixing
 * a configuration wants the whole list, not one error per run.
 */
final class Validator
{
    /**
     * @param  array<string, mixed>  $config
     * @return list<ValidationError>
     */
    public function validate(array $config, Schema $schema, string $prefix = ''): array
    {
        $errors = [];

        foreach ($config as $name => $value) {
            $path = $prefix === '' ? (string) $name : "{$prefix}.{$name}";
            $key = $schema->key((string) $name);

            if ($key === null) {
                $errors[] = new ValidationError($path, $this->unknownKeyMessage((string) $name, $schema));

                continue;
            }

            if ($value === null) {
                if (!$key->nullable) {
                    $errors[] = new ValidationError($path, \sprintf(
                        'Expected %s, got null. A placeholder for an unset environment '
                        . 'variable resolves to null; declare the key nullable if that is intended.',
                        $key->type->value,
                    ));
                }

                continue;
            }

            if ($value instanceof Placeholder) {
                if (!$key->deferrable) {
                    $errors[] = new ValidationError($path, \sprintf(
                        'Expected %s, got Placeholder. A workspace or user placeholder '
                        . 'binds per request; declare the key deferrable if that is intended.',
                        $key->type->value,
                    ));
                }

                continue;
            }

            if (!$this->matches($value, $key->type)) {
                $errors[] = new ValidationError($path, \sprintf(
                    'Expected %s, got %s.',
                    $key->type->value,
                    get_debug_type($value),
                ));

                continue;
            }

            if (\is_array($value)) {
                $errors = [...$errors, ...$this->descend($value, $key, $path)];
            }
        }

        return [...$errors, ...$this->missingRequired($config, $schema, $prefix)];
    }

    /**
     * @param  array<array-key, mixed> $value
     * @return list<ValidationError>
     */
    private function descend(array $value, SchemaKey $key, string $path): array
    {
        if ($key->children !== null) {
            /** @var array<string, mixed> $value narrows array<mixed, mixed> — argument.type without it */
            return $this->validate($value, $key->children, $path);
        }

        if ($key->each === null) {
            return [];
        }

        $errors = [];

        foreach ($value as $entryName => $entry) {
            $entryPath = "{$path}.{$entryName}";

            if (!\is_array($entry)) {
                $errors[] = new ValidationError($entryPath, \sprintf(
                    'Expected array, got %s.',
                    get_debug_type($entry),
                ));

                continue;
            }

            /** @var array<string, mixed> $entry narrows array<mixed, mixed> — argument.type without it */
            $errors = [...$errors, ...$this->validate($entry, $key->each, $entryPath)];
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<ValidationError>
     */
    private function missingRequired(array $config, Schema $schema, string $prefix): array
    {
        $errors = [];

        foreach ($schema->names() as $name) {
            $key = $schema->key($name);

            if ($key?->required === true && !\array_key_exists($name, $config)) {
                $path = $prefix === '' ? $name : "{$prefix}.{$name}";
                $errors[] = new ValidationError($path, 'Required key is missing.');
            }
        }

        return $errors;
    }

    private function unknownKeyMessage(string $name, Schema $schema): string
    {
        $nearest = $schema->nearest($name);

        return $nearest === null
            ? 'Unknown key.'
            : "Unknown key. Did you mean '{$nearest}'?";
    }

    private function matches(mixed $value, ValueType $type): bool
    {
        return match ($type) {
            ValueType::String => \is_string($value),
            ValueType::Int => \is_int($value),
            ValueType::Bool => \is_bool($value),
            ValueType::Array => \is_array($value),
            ValueType::Mixed => true,
        };
    }
}
