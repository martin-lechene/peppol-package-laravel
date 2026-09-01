# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added

- EN16931 / UBL 2.1 validation:
  - `En16931Validator` validates against the bundled official UBL 2.1 Invoice XSD
    plus core cross-field business rules (BR-B-02, BR-CO-04, BR-CO-13, BR-CO-15,
    BR-CO-10, BR-118/119, BR-135, BR-129 to BR-133).
  - `En16931Validator::validateSigned()` strips the XAdES envelope so a signed
    invoice can still be structurally validated.
  - Bundled official UBL 2.1 schemas in `resources/schemas/ubl2.1`.
- XAdES-EPES enveloped signing (`sr/Signing/XadesSigner`):
  - Signature placed in `ext:UBLExtensions`, referenced by a `wsu:Id` on the
    Invoice root, SHA-256 + RSA, exclusive canonicalization.
  - Qualifying properties: `SigningTime`, `SigningCertificate` (digest +
    issuer serial), explicit `SignaturePolicyIdentifier`, and
    `DataObjectFormat`.
  - Policy identifier/description configurable via constructor.
- `InvoiceManager::validate()` (config-driven schema path) and
  `InvoiceManager::sign()` (config-driven certificate/private key).
- Test infrastructure: `orchestra/testbench`, `phpunit.xml` (SQLite memory),
  signer certificate fixtures and `generate_certs.php`.

### Changed

- `XadesSigner` upgraded from XAdES-BES to XAdES-EPES.
- `config/e-invoices.php` gains `validation` and `signature` blocks.
- `InvoiceManager::sign()` arguments are now optional when config defaults are set.

### Fixed

- XAdES reference options use `prefix`/`prefix_ns` (not the invalid `id_ns`),
  `overwrite=false`, and the QualifyingProperties block is emitted before
  `addReference()` so the digest stays stable across sign/verify.

## [0.1.0] - 2026-04-02

### Added

- Initial publishable Laravel package: Peppol BIS 3.0 / UBL 2.1 XML skeleton
  generation, `Invoice`/`InvoiceLineItem`/`InvoiceTransmission` models,
  stub or HTTP transmission to an access point, and the `Invoice` facade.