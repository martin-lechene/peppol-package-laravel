<?php

namespace PeppolPackage\EInvoices\Validation;

use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;

/**
 * EN 16931 (UBL 2.1) validator: structural conformance against the official
 * UBL 2.1 Invoice XSD plus the core cross-field business rules that a PEPPOL
 * access point enforces before accepting a document.
 */
class En16931Validator
{
    private const INVOICE_NS = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    private const CAC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private const CBC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private const EXT_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';

    private const WSU_NS = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';

    public function __construct(
        private ?string $schemaPath = null
    ) {
        $this->schemaPath ??= dirname(__DIR__, 2)
            .'/resources/schemas/ubl2.1/maindoc/UBL-Invoice-2.1.xsd';
    }

    /**
     * Validate an XAdES-signed invoice.
     *
     * The XAdES envelope (wsu:Id on the root plus ext:UBLExtensions) is a
     * PEPPOL-network convention that the raw UBL 2.1 XSD does not declare, so
     * it is stripped before running the EN16931 structural and business checks.
     */
    public function validateSigned(string $xml): ValidationResult
    {
        $document = new DOMDocument;
        $document->preserveWhiteSpace = false;

        if (! $document->loadXML($xml)) {
            return new ValidationResult(false, ['Document is not well-formed XML.']);
        }

        $root = $document->documentElement;
        if ($root !== null) {
            $root->removeAttributeNS(self::WSU_NS, 'Id');

            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('i', self::INVOICE_NS);
            $xpath->registerNamespace('ext', self::EXT_NS);

            $extensions = $xpath->query('/i:Invoice/ext:UBLExtensions')->item(0);
            if ($extensions instanceof DOMNode) {
                $extensions->parentNode?->removeChild($extensions);
            }
        }

        return $this->validate($document->saveXML());
    }

    public function validate(string $xml): ValidationResult
    {
        $errors = [];
        $warnings = [];

        libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->preserveWhiteSpace = false;

        if (! $document->loadXML($xml)) {
            libxml_clear_errors();

            return new ValidationResult(false, ['Document is not well-formed XML.']);
        }

        if (! $document->schemaValidate($this->schemaPath)) {
            foreach (libxml_get_errors() as $error) {
                $errors[] = sprintf('XSD L%d: %s', $error->line, trim($error->message));
            }
        }
        libxml_clear_errors();

        $businessErrors = $this->validateBusinessRules($document);
        $errors = array_merge($errors, $businessErrors);

        return new ValidationResult($errors === [], $errors, $warnings);
    }

    /** @return list<string> */
    private function validateBusinessRules(DOMDocument $document): array
    {
        $errors = [];
        $xpath = $this->xpath($document);

        $customization = $this->text($xpath->query('/i:Invoice/cbc:CustomizationID'));
        if ($customization === '' || stripos($customization, 'en16931') === false) {
            $errors[] = 'BR-B-02: CustomizationID must reference an EN 16931 conformant customization.';
        }

        $profile = $this->text($xpath->query('/i:Invoice/cbc:ProfileID'));
        if ($profile === '') {
            $errors[] = 'BR-CO-13: ProfileID is required.';
        }

        $invoiceId = $this->text($xpath->query('/i:Invoice/cbc:ID'));
        if ($invoiceId === '') {
            $errors[] = 'BR-CO-04: Invoice ID is required.';
        }

        $issueDate = $this->text($xpath->query('/i:Invoice/cbc:IssueDate'));
        if ($issueDate === '') {
            $errors[] = 'BR-CO-03: Invoice IssueDate is required.';
        } elseif (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $issueDate)) {
            $errors[] = 'BR-CO-03: Invoice IssueDate must be a valid date (YYYY-MM-DD).';
        }

        $sender = $this->text($xpath->query('/i:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyIdentification/cbc:ID'));
        if ($sender === '') {
            $errors[] = 'BR-118: AccountingSupplierParty identification is required.';
        }

        $recipient = $this->text($xpath->query('/i:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PartyIdentification/cbc:ID'));
        if ($recipient === '') {
            $errors[] = 'BR-119: AccountingCustomerParty identification is required.';
        }

        $lineCount = $xpath->query('/i:Invoice/cac:InvoiceLine')->length;
        if ($lineCount < 1) {
            $errors[] = 'BR-135: At least one InvoiceLine is required.';
        }

        $errors = array_merge($errors, $this->validateTotals($xpath));

        return $errors;
    }

    /** @return list<string> */
    private function validateTotals(DOMXPath $xpath): array
    {
        $errors = [];

        $line = $this->amount($xpath->query('/i:Invoice/cac:LegalMonetaryTotal/cbc:LineExtensionAmount'));
        $exclusive = $this->amount($xpath->query('/i:Invoice/cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount'));
        $inclusive = $this->amount($xpath->query('/i:Invoice/cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount'));
        $payable = $this->amount($xpath->query('/i:Invoice/cac:LegalMonetaryTotal/cbc:PayableAmount'));

        if ($line === null) {
            $errors[] = 'BR-129: LegalMonetaryTotal/LineExtensionAmount is required.';
        }
        if ($exclusive === null) {
            $errors[] = 'BR-130: LegalMonetaryTotal/TaxExclusiveAmount is required.';
        }
        if ($inclusive === null) {
            $errors[] = 'BR-131: LegalMonetaryTotal/TaxInclusiveAmount is required.';
        }
        if ($payable === null) {
            $errors[] = 'BR-133: LegalMonetaryTotal/PayableAmount is required.';
        }

        if ($line !== null && $exclusive !== null && abs($line - $exclusive) > 0.005) {
            $errors[] = 'BR-CO-15: LineExtensionAmount must equal TaxExclusiveAmount.';
        }

        $taxTotal = 0.0;
        $taxAmounts = $xpath->query('/i:Invoice/cac:TaxTotal/cbc:TaxAmount');
        foreach ($taxAmounts as $node) {
            $taxTotal += (float) $node->textContent;
        }

        $declaredTax = 0.0;
        $subtotalNodes = $xpath->query('/i:Invoice/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxableAmount');
        if ($subtotalNodes->length > 0) {
            foreach ($xpath->query('/i:Invoice/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxAmount') as $node) {
                $declaredTax += (float) $node->textContent;
            }

            $declared = $xpath->query('/i:Invoice/cac:TaxTotal/cbc:TaxAmount')->item(0);
            if ($declared !== null && abs($declaredTax - (float) $declared->textContent) > 0.005) {
                $errors[] = 'BR-CO-08: TaxSubtotal amounts must sum to the TaxTotal TaxAmount.';
            }
        }

        if ($exclusive !== null && $inclusive !== null) {
            $expectedInclusive = $exclusive + $taxTotal;
            if (abs($expectedInclusive - $inclusive) > 0.005) {
                $errors[] = 'BR-CO-15: TaxInclusiveAmount must equal TaxExclusiveAmount plus TaxTotal.';
            }
        }

        if ($inclusive !== null && $payable !== null && abs($inclusive - $payable) > 0.005) {
            $errors[] = 'BR-130: PayableAmount must equal TaxInclusiveAmount (no prepayment).';
        }

        $currency = $this->text($xpath->query('/i:Invoice/cbc:DocumentCurrencyCode'));
        if ($currency !== '' && ! preg_match('/^[A-Z]{3}$/', $currency)) {
            $errors[] = 'BR-CO-10: DocumentCurrencyCode must be a valid ISO 4217 code.';
        }

        return $errors;
    }

    private function amount(?DOMNodeList $nodes): ?float
    {
        if (! $nodes || $nodes->length === 0) {
            return null;
        }

        return (float) $nodes->item(0)->textContent;
    }

    private function text(?DOMNodeList $nodes): string
    {
        if (! $nodes || $nodes->length === 0) {
            return '';
        }

        return trim($nodes->item(0)->textContent);
    }

    private function xpath(DOMDocument $document): DOMXPath
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('i', self::INVOICE_NS);
        $xpath->registerNamespace('cac', self::CAC_NS);
        $xpath->registerNamespace('cbc', self::CBC_NS);

        return $xpath;
    }
}
