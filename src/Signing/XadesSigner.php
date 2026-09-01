<?php

namespace PeppolPackage\EInvoices\Signing;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

/**
 * XAdES-EPES enveloped signature for an UBL invoice.
 *
 * Produces a <ds:Signature> placed inside <ext:UBLExtensions> and referenced
 * by an Id on the Invoice root, using RSASSA-PKCS1-v1_5 + SHA-256 with
 * exclusive canonicalization, embedding the signer certificate in KeyInfo and
 * an xades:QualifyingProperties block carrying the signing time, signing
 * certificate, explicit signature policy and data object format.
 */
class XadesSigner
{
    private const INVOICE_NS = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    private const EXT_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';

    private const WSU_NS = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';

    private const XADES_NS = 'http://uri.etsi.org/01903/v1.3.2#';

    private const DS_NS = 'http://www.w3.org/2000/09/xmldsig#';

    private const DEFAULT_POLICY_IDENTIFIER = 'urn:peppol:policy:authorization:1.0';

    private const DEFAULT_POLICY_DESCRIPTION = 'PEPPOL BIS 3.0 access point signature policy';

    public function __construct(
        private string $certificate,
        private string $privateKey,
        private ?string $keyPassword = null,
        private ?string $policyIdentifier = null,
        private ?string $policyDescription = null
    ) {
        $this->policyIdentifier ??= self::DEFAULT_POLICY_IDENTIFIER;
        $this->policyDescription ??= self::DEFAULT_POLICY_DESCRIPTION;
    }

    public function sign(string $xml): string
    {
        $cert = $this->readCertificate();
        $document = new DOMDocument;
        $document->preserveWhiteSpace = false;

        if (! $document->loadXML($xml)) {
            throw new InvalidArgumentException('Unable to parse the invoice XML to sign.');
        }

        $root = $document->documentElement;
        if ($root === null || $root->localName !== 'Invoice') {
            throw new InvalidArgumentException('The document root must be an UBL <Invoice>.');
        }

        $extensions = $this->createExtensionsShell($document, $root);
        $extensionContent = $this->createExtensionContent($document, $extensions);

        $signatureId = 'Id-'.bin2hex(random_bytes(8));
        $root->setAttributeNS(self::WSU_NS, 'wsu:Id', $signatureId);
        $this->appendQualifyingProperties($document, $extensionContent, $signatureId);

        $privateKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $privateKey->loadKey($this->privateKey);

        $signer = new XMLSecurityDSig;
        $signer->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $signer->addReference(
            $root,
            XMLSecurityDSig::SHA256,
            [XMLSecurityDSig::ENVELOPED, XMLSecurityDSig::EXC_C14N],
            [
                'prefix' => 'wsu',
                'prefix_ns' => self::WSU_NS,
                'id_name' => 'Id',
                'overwrite' => false,
            ]
        );

        $signer->sign($privateKey);
        $signer->add509Cert($this->certificate, true);

        $signer->insertSignature($extensionContent);

        return $document->saveXML();
    }

    private function createExtensionsShell(DOMDocument $document, DOMElement $root): DOMElement
    {
        $extensions = $document->createElementNS(self::EXT_NS, 'ext:UBLExtensions');
        $root->insertBefore($extensions, $root->firstChild);

        return $extensions;
    }

    private function createExtensionContent(DOMDocument $document, DOMElement $extensions): DOMElement
    {
        $extension = $document->createElementNS(self::EXT_NS, 'ext:UBLExtension');
        $extensionContent = $document->createElementNS(self::EXT_NS, 'ext:ExtensionContent');
        $extension->appendChild($extensionContent);
        $extensions->appendChild($extension);

        return $extensionContent;
    }

    private function readCertificate(): string
    {
        return $this->readKey($this->certificate, 'certificate');
    }

    private function readKey(string $pem, string $label): string
    {
        if (str_contains($pem, 'BEGIN')) {
            return $pem;
        }

        $path = realpath($pem);
        if ($path === false) {
            throw new InvalidArgumentException("Unable to read $label from: $pem");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new InvalidArgumentException("Unable to read $label file: $pem");
        }

        return $contents;
    }

    private function appendQualifyingProperties(
        DOMDocument $document,
        DOMElement $extensionContent,
        string $signatureId
    ): DOMElement {
        $qualifying = $document->createElementNS(self::XADES_NS, 'xades:QualifyingProperties');
        $qualifying->setAttribute('Target', '#'.$signatureId);

        $signedProps = $document->createElementNS(self::XADES_NS, 'xades:SignedProperties');

        $signedSigProps = $document->createElementNS(self::XADES_NS, 'xades:SignedSignatureProperties');
        $signedSigProps->appendChild(
            $document->createElementNS(self::XADES_NS, 'xades:SigningTime', gmdate('Y-m-d\TH:i:s\Z'))
        );
        $signedSigProps->appendChild($this->buildSigningCertificate($document));
        $signedSigProps->appendChild($this->buildSignaturePolicy($document));
        $signedProps->appendChild($signedSigProps);

        $signedDataProps = $document->createElementNS(self::XADES_NS, 'xades:SignedDataObjectProperties');
        $signedDataProps->appendChild($this->buildDataObjectFormat($document, $signatureId));
        $signedProps->appendChild($signedDataProps);

        $qualifying->appendChild($signedProps);

        $object = $document->createElementNS(self::DS_NS, 'ds:Object');
        $object->appendChild($qualifying);
        $extensionContent->appendChild($object);

        return $qualifying;
    }

    private function buildSigningCertificate(DOMDocument $document): DOMElement
    {
        $details = $this->certificateDetails();

        $signingCert = $document->createElementNS(self::XADES_NS, 'xades:SigningCertificate');
        $cert = $document->createElementNS(self::XADES_NS, 'xades:Cert');
        $certDigest = $document->createElementNS(self::XADES_NS, 'xades:CertDigest');
        $digestMethod = $document->createElementNS(self::DS_NS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $certDigest->appendChild($digestMethod);
        $certDigest->appendChild(
            $document->createElementNS(self::DS_NS, 'ds:DigestValue', base64_encode(hash('sha256', $details['der'], true)))
        );
        $cert->appendChild($certDigest);

        $issuerSerial = $document->createElementNS(self::XADES_NS, 'xades:IssuerSerial');
        $issuerSerial->appendChild($document->createElementNS(self::DS_NS, 'ds:X509IssuerName', $details['issuer']));
        $issuerSerial->appendChild($document->createElementNS(self::DS_NS, 'ds:X509SerialNumber', $details['serial']));
        $cert->appendChild($issuerSerial);

        $signingCert->appendChild($cert);

        return $signingCert;
    }

    private function buildSignaturePolicy(DOMDocument $document): DOMElement
    {
        $policyId = $document->createElementNS(self::XADES_NS, 'xades:SignaturePolicyIdentifier');
        $sigPolicyId = $document->createElementNS(self::XADES_NS, 'xades:SignaturePolicyId');

        $id = $document->createElementNS(self::XADES_NS, 'xades:SigPolicyId');
        $id->appendChild(
            $document->createElementNS(self::XADES_NS, 'xades:Identifier', $this->policyIdentifier)
        );
        $id->appendChild(
            $document->createElementNS(self::XADES_NS, 'xades:Description', $this->policyDescription)
        );
        $sigPolicyId->appendChild($id);

        $sigPolicyHash = $document->createElementNS(self::XADES_NS, 'xades:SigPolicyHash');
        $digestMethod = $document->createElementNS(self::DS_NS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $sigPolicyHash->appendChild($digestMethod);
        $sigPolicyHash->appendChild(
            $document->createElementNS(
                self::DS_NS,
                'ds:DigestValue',
                base64_encode(hash('sha256', $this->policyIdentifier."\n".$this->policyDescription, true))
            )
        );
        $sigPolicyId->appendChild($sigPolicyHash);

        $policyId->appendChild($sigPolicyId);

        return $policyId;
    }

    private function buildDataObjectFormat(DOMDocument $document, string $signatureId): DOMElement
    {
        $format = $document->createElementNS(self::XADES_NS, 'xades:DataObjectFormat');
        $format->setAttribute('ObjectReference', '#'.$signatureId);
        $format->appendChild($document->createElementNS(self::XADES_NS, 'xades:Description', 'UBL invoice'));
        $format->appendChild(
            $document->createElementNS(self::XADES_NS, 'xades:MimeType', 'application/xml')
        );
        $format->appendChild(
            $document->createElementNS(self::XADES_NS, 'xades:Encoding', 'UTF-8')
        );

        return $format;
    }

    /**
     * @return array{der: string, issuer: string, serial: string}
     */
    private function certificateDetails(): array
    {
        $pem = $this->readCertificate();
        $parsed = openssl_x509_parse($pem);
        if ($parsed === false) {
            throw new InvalidArgumentException('Unable to parse the certificate for XAdES properties.');
        }

        return [
            'der' => $this->certificateDer($pem),
            'issuer' => (string) ($parsed['name'] ?? ''),
            'serial' => strtoupper((string) ($parsed['serialNumberHex'] ?? '')),
        ];
    }

    private function certificateDer(string $pem): string
    {
        $lines = array_map('trim', preg_split('/\r?\n/', $pem) ?: []);
        $body = [];
        $inside = false;

        foreach ($lines as $line) {
            if (str_starts_with($line, '-----BEGIN')) {
                $inside = true;

                continue;
            }
            if ($inside && str_starts_with($line, '-----END')) {
                break;
            }
            if ($inside) {
                $body[] = $line;
            }
        }

        $decoded = base64_decode(implode('', $body), true);

        return $decoded === false ? '' : $decoded;
    }
}
