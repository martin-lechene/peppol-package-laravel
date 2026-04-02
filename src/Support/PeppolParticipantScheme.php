<?php

namespace PeppolPackage\EInvoices\Support;

/**
 * Peppol / ISO 6523 scheme hints for cbc:ID@schemeID (simplified heuristics).
 */
final class PeppolParticipantScheme
{
    public static function schemeForPartyId(?string $raw): string
    {
        $id = strtoupper(preg_replace('/[\s.]+/', '', (string) $raw));

        if ($id === '') {
            return '0088';
        }

        if (preg_match('/^BE0?\d{9,10}$/', $id)) {
            return '9925';
        }

        if (preg_match('/^\d{10}$/', $id)) {
            return '0208';
        }

        if (preg_match('/^\d{9}$/', $id) || preg_match('/^\d{14}$/', $id)) {
            return '0009';
        }

        return '0088';
    }
}
