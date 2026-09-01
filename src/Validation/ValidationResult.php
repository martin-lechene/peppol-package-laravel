<?php

namespace PeppolPackage\EInvoices\Validation;

/**
 * Immutable result of an EN 16931 / PEPPOL validation pass.
 */
final class ValidationResult
{
    /** @param list<string> $errors */
    public function __construct(
        public readonly bool $valid,
        public readonly array $errors = [],
        public readonly array $warnings = []
    ) {}

    public function passes(): bool
    {
        return $this->valid;
    }

    public function fails(): bool
    {
        return ! $this->valid;
    }

    public function message(): string
    {
        return $this->valid ? 'Validation passed'
            : (implode(' | ', $this->errors) ?: 'Validation failed');
    }
}
