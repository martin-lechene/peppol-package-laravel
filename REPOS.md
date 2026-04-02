# Repository split

This tree is **`peppol-package-laravel`**: the Composer package only.

| Directory / repo | Purpose |
|------------------|---------|
| **peppol-package-laravel** (here) | `peppol-package/laravel-peppol-invoices` on Packagist |
| **../peppol-package-demo** | Laravel app: landing + `/playground` |
| **../peppol-package-landingpage** | Static site (`index.html`) for GitHub Pages |

## GitHub + push

```bash
# Package
cd peppol-package-laravel
git init
git add .
git commit -m "feat: initial release of laravel-peppol-invoices package"
git branch -M main
git remote add origin https://github.com/martin-lechene/peppol-package-laravel.git
git push -u origin main
git tag v1.0.0
git push origin v1.0.0
```

```bash
# Demo
cd ../peppol-package-demo
git init
git add .
git commit -m "chore: Laravel demo for peppol invoices package"
git branch -M main
git remote add origin https://github.com/martin-lechene/peppol-package-demo.git
git push -u origin main
```

```bash
# Landing
cd ../peppol-package-landingpage
git init
git add .
git commit -m "docs: static landing and integration overview"
git branch -M main
git remote add origin https://github.com/martin-lechene/peppol-package-landingpage.git
git push -u origin main
```

## Packagist

After the package repo is public, submit `https://github.com/martin-lechene/peppol-package-laravel` on [packagist.org](https://packagist.org). See `PUBLISH.md`.

Then update **peppol-package-demo** `composer.json`: remove the `repositories` path block and use `"peppol-package/laravel-peppol-invoices": "^1.0"`.
