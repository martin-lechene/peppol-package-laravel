<?php

namespace PeppolPackage\EInvoices\Tests\Unit;

use PeppolPackage\EInvoices\Support\PeppolParticipantScheme;
use PHPUnit\Framework\TestCase;

class PeppolParticipantSchemeTest extends TestCase
{
    public function test_belgian_vat_uses_scheme_9925(): void
    {
        $this->assertSame('9925', PeppolParticipantScheme::schemeForPartyId('BE0123456789'));
    }

    public function test_ten_digit_numeric_uses_scheme_0208(): void
    {
        $this->assertSame('0208', PeppolParticipantScheme::schemeForPartyId('0123456789'));
    }

    public function test_empty_falls_back_to_0088(): void
    {
        $this->assertSame('0088', PeppolParticipantScheme::schemeForPartyId(''));
    }
}
