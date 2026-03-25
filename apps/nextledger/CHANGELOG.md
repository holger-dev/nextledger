# Changelog

## Unreleased

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
