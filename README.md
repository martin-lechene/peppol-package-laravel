# Laravel Peppol invoices (UBL)

[![Latest Stable Version](https://poser.pugx.org/peppol-package/laravel-peppol-invoices/v)](https://packagist.org/packages/peppol-package/laravel-peppol-invoices)
[![License](https://poser.pugx.org/peppol-package/laravel-peppol-invoices/license)](LICENSE)

`peppol-package/laravel-peppol-invoices` is a Laravel package that helps you **model invoices**, generate a **Peppol BIS 3.0 / UBL 2.1 XML** skeleton, **validate** it against the **EN16931 / UBL 2.1 XSD** plus core cross-field business rules, **sign** it with an **XAdES-BES** enveloped signature, and optionally **POST** it to an Access Point HTTP endpoint. It does **not** replace a certified Peppol Access Point or a full XAdES-EPES/CiR catalogue from a certification body.

## Requirements

- PHP `^8.2`
- Laravel `10.x`, `11.x`, or `12.x`

## Install (Composer / Packagist)

```bash
composer require peppol-package/laravel-peppol-invoices
```

After Packagist submission, the package auto-discovers the service provider. If you disabled discovery:

```php
// bootstrap/providers.php (Laravel 11+)
return [
    // ...
    PeppolPackage\EInvoices\InvoiceServiceProvider::class,
];
```

### Optional: publish config

```bash
php artisan vendor:publish --tag=e-invoices-config
```

### Migrations

The package registers migrations for `invoices`, `invoice_line_items`, and `invoice_transmissions`. Run:

```bash
php artisan migrate
```

## Environment

| Variable | Description |
|----------|-------------|
| `E_INVOICES_TX_MODE` | `stub` (default, simulated success) or `http` |
| `E_INVOICES_AP_ENDPOINT` | URL for HTTP mode (raw UBL XML `POST`) |
| `E_INVOICES_AP_KEY` | Optional Bearer token |
| `E_INVOICES_CERT_PATH` | X.509 certificate (PEM) used for XAdES signing |
| `E_INVOICES_KEY_PATH` | Matching private key (PEM) used for XAdES signing |
| `E_INVOICES_KEY_PASSWORD` | Optional private key passphrase |

## Usage

```php
use PeppolPackage\EInvoices\Facades\Invoice;
use PeppolPackage\EInvoices\Models\Invoice as InvoiceModel;

$invoice = InvoiceModel::create([/* ... */]);
$invoice->lineItems()->create([/* ... */]);
$invoice->calculateTotals();

$xml = Invoice::generate($invoice, format: 'PEPPOL_BIS');

// EN16931 / UBL 2.1 XSD + business rule validation
$result = Invoice::validate($xml);
if (! $result->passes()) {
    foreach ($result->errors as $error) {
        echo $error.PHP_EOL;
    }
}

// XAdES-BES enveloped signing (credentials from config or explicit)
$signed = Invoice::sign($xml, $certificatePem, $privateKeyPem, $keyPassword = null);

$result = Invoice::transmit($invoice); // stub or HTTP depending on config
```

The bundled UBL 2.1 schemas live in `resources/schemas/ubl2.1`; point `config('e-invoices.validation.schema')` at a custom XSD if needed.

Facade alias: `Invoice` (configurable via `config/app.php` if you remove auto-alias).

## Demo & docs

- **Live demo (Laravel):** [`martin-lechene/peppol-package-demo`](https://github.com/martin-lechene/peppol-package-demo)
- **Marketing / integration doc (static):** [`martin-lechene/peppol-package-landingpage`](https://github.com/martin-lechene/peppol-package-landingpage)

## Contributing

Issues and PRs welcome on GitHub.

## Legal

This software is not affiliated with OpenPeppol. Production Peppol access requires a **certified Access Point** and compliance with local law (e.g. Belgium e-invoicing from 2026).
