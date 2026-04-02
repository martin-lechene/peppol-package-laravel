<?php

namespace PeppolPackage\EInvoices\Support;

class TransmissionResult
{
    public function __construct(
        public bool $success,
        public ?string $message = null
    ) {}
}
