# Changelog

## Unreleased

## 1.6.3
- Add per-company logo upload (PNG, JPEG, SVG, GIF, WebP up to 1.5 MB) stored alongside the company record.
- Add three PDF layout sizes for the logo (`small`, `medium`, `large`) which adjust the offer and invoice header automatically: small/medium place the logo next to the address block, large renders a banner above the address.
- Add per-company invoice format selector: classic PDF (default, unchanged) or **ZUGFeRD EN16931** (PDF/A-3 with embedded Cross Industry Invoice XML) for German B2B/B2G electronic invoicing. Falls back to a regular PDF if the host's PHP environment cannot generate a hybrid PDF/A-3.
- Add a sidecar XML download per invoice at `GET /api/invoices/{id}/zugferd-xml` — returns the EN16931 CII-XML on its own, regardless of the invoice format setting. Useful for pure XRechnung delivery or when a recipient prefers separate XML and PDF.
- Add per-company `country_code` (ISO-3166-1 alpha-2, default `DE`) and per-customer `country_code` and `vat_id` to satisfy EN16931 mandatory fields.
- New service `OCA\NextLedger\Service\ZugferdXmlService` builds CII-XML (profile EN16931) from invoice + items + customer + company. Includes a defensive workaround for hosts where the `horstoeko/zugferd` XMP asset is not directly readable (the file is copied into a writable temp directory before use).
- New endpoints under `/api/settings/company/logo` (GET/POST/DELETE) for upload, retrieval, and removal.
- Add per-company **mail attachment** setting (PDF only / ZUGFeRD-XML only / both). Direct email sends from NextLedger now respect this preference and attach the corresponding file(s) automatically. Defaults to PDF for legacy behaviour.
- Add a **Download ZUGFeRD-XML** button next to the Download-PDF button on every invoice in the list view.
- Composer dependencies added: `horstoeko/zugferd ^1.0`, `setasign/fpdi ^2.6`. The `composer.json` pins `config.platform.php` to `8.3` so dependency resolution stays compatible with the typical Nextcloud 32 environment.

## 1.6.1
- Add a per-company document language setting, defaulting to the Nextcloud language.
- Render invoice and offer PDF labels, dates, amounts, and totals in the company's selected document language.
- Localize generated invoice and offer PDF filenames according to the company's document language.
- Add NGN and ten additional major currencies to company settings, including PDF formatting support.

## 1.6.0
- Add optional holding/group assignment for companies so related companies can be managed together in company settings
- Show the holding/group directly in the company overview; this is an organizational grouping only, no consolidated balance sheet is introduced
- Add two GÜB/EÜR PDF export variants: with or without Einzelauflistung
- Add polished recovery/company-sharing user pickers with selectable Nextcloud users instead of manual user ID entry
- Keep separate per-company exports as the intended path instead of consolidated statements
- Restrict company and ledger data to users with explicit access instead of exposing all companies globally
- Add a recovery dialog after update to assign legacy companies without owner to a Nextcloud user before entering the app
- Allow inviting additional Nextcloud users to individual companies, including access to company-specific mail settings
- Add an optional free invoice field (label + value) and render the invoice footer/bank details as a repeated footer on every PDF page
- Add a per-company currency setting (default EUR) and use it consistently in lists, forms, invoices, offers, and GÜB PDFs
- Add case archiving with a dedicated archive toggle in the case overview, plus improved GÜB summary tables for both export variants

## 1.5.1
- Fix auto-stored PDF files in Nextcloud Files so generated invoice/offer PDFs open correctly
- Improve invoice PDF versioning: one folder per invoice number, latest file without suffix, older files as `_v1`, `_v2`, ...

## 1.5.0
- Add language-file based DE/EN support with centralized i18n runtime translation
- Improve email behavior UX with explicit sender logic and optional sender/reply-to override switch

## 1.4.0
- Add per-company email delivery settings with selectable mode per company
- Add Nextcloud Mail Provider integration for direct offer/invoice sending
- Keep Admin SMTP as selectable fallback per company
- Add bilingual (DE/EN) labels in the E-Mail behavior settings UI

## 1.2.0
- Add multi-company support with one active company context at a time
- Scope core data by active company (customers, cases, offers, invoices, products, fiscal years, incomes, expenses)
- Add company management overview table in settings (status + switch action)
- Add document settings tab with optional automatic PDF backup
- Add optional PDF versioning (`..._v1`, `..._v2`, ...)
- Store generated PDFs under `Files/NextLedger/<Firma>/<Wirtschaftsjahr>`
- Add advance and final invoices linked to accepted offers
- Support service period on advance invoices
- Show offer reference on invoice PDF when present
- Add optional billing email for customers and invoice recipient flags on cases
- Rename case elements UI to "Korrespondenz/Notizen"

## 1.0.0
- Initial release
