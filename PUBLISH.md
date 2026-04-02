# Publish on Packagist

1. Push this tree **only** (no `demo/` folder) — e.g. `https://github.com/martin-lechene/peppol-package-laravel`.

2. On [packagist.org](https://packagist.org), submit the package using the Git URL:
   `https://github.com/martin-lechene/peppol-package-laravel.git`

3. Packagist reads `composer.json` → package name **`peppol-package/laravel-peppol-invoices`**.

4. Tag a release (semantic versioning):
   ```bash
   git tag v1.0.0
   git push origin v1.0.0
   ```

5. In consuming apps:
   ```bash
   composer require peppol-package/laravel-peppol-invoices:^1.0
   ```

6. Update `homepage` / `support` URLs in `composer.json` if your GitHub path differs.

7. Optional: enable Packagist webhook on the GitHub repo for auto-updates.
