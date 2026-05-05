# NextLedger 1.6.3 — Upgrade & smoke test

## What changed
- New columns on `nl_settings_company`: `logo_data`, `logo_mime`, `logo_size`, `invoice_format`, `country_code`, `mail_attachment`
- New columns on `nl_customers`: `country_code`, `vat_id`
- New PHP service `OCA\NextLedger\Service\ZugferdXmlService`
- New PHP composer dependencies: `horstoeko/zugferd ^1.0`, `setasign/fpdi ^2.6`
- New routes: `GET/POST/DELETE /api/settings/company/logo`, `GET /api/invoices/{id}/zugferd-xml`

## Pre-flight (one-time, in the running Docker stack)
```sh
# 1. Pull new composer deps inside the container
docker compose exec -T --user root nextcloud bash -lc '
  cd /var/www/html/custom_apps/nextledger \
  && chown -R www-data:www-data . \
  && sudo -u www-data composer install --no-dev --prefer-dist --no-interaction
'

# 2. Run the migration (adds the columns)
docker compose exec -T --user www-data nextcloud php /var/www/html/occ migrations:execute nextledger 0022Date20260505

# 3. Rebuild the JS bundle on the host
npm run build
```

## PHPUnit (host or container)
```sh
docker compose exec -T --user www-data nextcloud bash -lc '
  cd /var/www/html/custom_apps/nextledger \
  && composer install --prefer-dist --no-interaction \
  && vendor/bin/phpunit
'
```

## Manual smoke test
1. **Logo** — Settings → Firma → Logo: upload a PNG ≤ 1.5 MB, switch the size between Klein/Mittel/Groß, generate any invoice or offer PDF and verify the header layout.
2. **Country code** — Settings → Firma: set Country code = `DE`. On a customer, also set Country code + VAT-ID.
3. **ZUGFeRD hybrid PDF** — Settings → Firma → Rechnungsausgabe = `ZUGFeRD EN16931`. Generate an invoice. The filename should end with `-zugferd.pdf`. If the hybrid PDF/A-3 generator cannot run on the host (rare; libxml/permissions edge case), the file is delivered as a regular PDF and the warning is logged via `error_log`.
4. **Sidecar XML** — independently of the company's invoice format, fetch `GET /api/invoices/{id}/zugferd-xml`. This always returns the EN16931 CII-XML on its own and is the most robust path for B2G/XRechnung workflows.
5. **Email attachment policy** — Settings → Firma → "E-Mail-Anhang beim Versand": pick PDF only, ZUGFeRD-XML only, or both. Send a test invoice via the email button in the invoice list and verify the attachment(s).
6. **Validate** the PDF/A-3 + embedded XML, e.g. with [Mustang validator](https://www.mustangproject.org/) or [KoSIT validator](https://github.com/itplr-kosit/validator):
   ```sh
   java -jar Mustang-CLI.jar --action=validate --source=Rechnung-…-zugferd.pdf
   ```

## Common gotchas

- **`composer install` fails with `requires PHP >= 8.4`** — `composer.json` ships with `config.platform.php = "8.3"` to keep dependency resolution compatible. Run `composer update --no-dev --prefer-dist` once after pulling.
- **ZUGFeRD hybrid generation logs `simplexml_load_file` warning** — caused by restrictive permissions on `vendor/horstoeko/zugferd/src/assets/facturx_extension_schema.xmp` (e.g. when composer installed as a different user than the web server). Fix once with:
  ```sh
  find apps/nextledger/vendor -type f -exec chmod 644 {} \;
  find apps/nextledger/vendor -type d -exec chmod 755 {} \;
  ```
  The service also tries to copy the asset into a writable temp directory as a defensive fallback.
- **`<img>` doesn't appear in the PDF** — this is dompdf 2.x with libpng failing on some PNG variants (e.g. those produced by certain server-side renderers with non-standard alpha chunks). Re-encoding the logo via your browser (PNG export from any image editor) typically resolves it.

## Rollback
`migrations:execute` can be re-run with the previous version (`0021Date20260503`) — the new columns are nullable / have defaults, so existing data continues to work even after a version downgrade.
