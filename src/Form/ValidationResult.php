<?php

declare(strict_types=1);

namespace RockAdmin\Form;

use LogicException;

/**
 * What a submission turned out to be: either a list of reasons it was
 * refused, or the coerced values a `WriteHandler` may act on. Never both.
 *
 * This exists to make one mistake impossible rather than to document it.
 * The shape it replaced was two calls — `validate()` then `values()` — with
 * nothing but a docblock between a caller and a value the validator had just
 * refused: a select that never offered `deleted` would still coerce
 * `'deleted'` and hand it over. A handler that forgot the first call, or ran
 * it and ignored the answer, wrote whatever arrived.
 *
 * So `values()` raises when anything failed, and raises for the whole
 * submission rather than only the offending field: a partially valid form is
 * not a thing a row may be written from. The exception is a `LogicException`
 * because reaching it is a bug in the calling code, not something a person
 * filling in a form can cause.
 */
final class ValidationResult
{
    /**
     * @param list<ValidationError> $errors
     * @param array<array-key, mixed> $values array-key, not string: PHP
     *                                        normalises a numeric key such as
     *                                        '123' to the integer 123 on the
     *                                        way in, and no cast can undo it
     */
    public function __construct(
        private readonly array $errors,
        private readonly array $values,
    ) {
    }

    public function passed(): bool
    {
        return $this->errors === [];
    }

    public function failed(): bool
    {
        return $this->errors !== [];
    }

    /** @return list<ValidationError> empty when everything passed */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * The coerced values, safe to hand a `WriteHandler`.
     *
     * @return array<array-key, mixed>
     *
     * @throws LogicException when anything failed — redraw the form with
     *                        errors() instead
     */
    public function values(): array
    {
        if ($this->errors !== []) {
            throw new LogicException(\sprintf(
                'This submission did not pass validation: %d field(s) were refused. Redraw the form with '
                    . 'errors() instead of writing what arrived.',
                \count($this->errors),
            ));
        }

        return $this->values;
    }
}
