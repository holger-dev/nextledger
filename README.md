# NextLedger

A lightweight, Nextcloud‑native bookkeeping app for freelancers and small teams. Track customers, cases, offers, invoices, products, fiscal years, and GÜB (EÜR) — all inside Nextcloud with a familiar UI.

## Screenshots
Below are a few highlights from the UI and PDF output. The first four are intended for store listings; the rest are extra context for GitHub.

**Store (4 Bilder)**

**1) Vorgänge**
Filterbare Vorgangsliste mit Schnellaktionen und klarer Struktur.
![Vorgänge](docs/screenshots/image1.png)

**2) Vorgang-Details**
Vorgangsdaten plus verknüpfte Rechnungen & Angebote auf einen Blick.
![Vorgang-Details](docs/screenshots/image2.png)

**3) Wirtschaftsjahr**
Einnahmen, Ausgaben und Status in der Jahresübersicht.
![Wirtschaftsjahr](docs/screenshots/image3.png)

**4) Angebot (PDF)**
Sauberes Layout für Angebote mit Positionen und Summenblock.
![Angebot PDF](docs/screenshots/image4.png)

**Weitere Screenshots (GitHub)**

**5) Rechnung (PDF)**
Rechnungslayout mit Steuerlogik und Abschlusstext.
![Rechnung PDF](docs/screenshots/image5.png)

**6) GÜB (PDF)**
Einnahmenübersicht als PDF für das Wirtschaftsjahr.
![GÜB PDF](docs/screenshots/image6.png)

**7) Texte & Vorlagen**
PDF- und E-Mail-Texte zentral pflegen.
![Texte](docs/screenshots/image7.png)

**8) Steuer-Einstellungen**
Kleinunternehmerregelung und Standard-USt.
![Steuer](docs/screenshots/image8.png)

## Highlights
- **Nextcloud‑native UI** using Nextcloud Vue Components
- **Multi-Company support** with one active company context at a time
- **Customers, Products/DL, Cases, Elements** scoped to the active company
- **Offers & Invoices** with positions, tax logic, and PDF export
- **Per‑Company logo** with three PDF layout sizes (1.6.3)
- **ZUGFeRD EN16931 e‑invoicing** as PDF/A‑3 with embedded CII‑XML, switchable per company (1.6.3)
- **Optional PDF auto-storage** to Nextcloud Files with overwrite or versioning
- **Numbering** `YYYYMMDD-####` for offers and invoices
- **Mail‑to workflow** for offers/invoices (auto‑download PDF + template mail)
- **Fiscal Years** with **Einnahmen/Ausgaben** and **GÜB PDF** export
- **Kleinunternehmerregelung** supported (with custom note)

## What You Can Do
### Core Workflow
1. Select or create the active **Company** in settings
2. Create a **Customer**
3. Open a **Case** for the customer
4. Create **Offers** and **Invoices** from the case
5. Export PDF and send via **mailto** template
6. Track **Einnahmen/Ausgaben** in the fiscal year and export **GÜB**

### Documents
- **Offer / Invoice PDFs** follow a clean A4 layout
- Tax block adapts to **USt** or **Kleinunternehmer**
- Footer shows your closing text and (optional) bank info
- Optional automatic filing to `Files/NextLedger/<Firma>/<Wirtschaftsjahr>`
- Storage mode can be configured:
  overwrite existing file, or create versioned files (`..._v1`, `..._v2`, ...)

### Logo (1.6.3)
- Upload one logo per company under **Settings → Firma → Logo** (PNG, JPEG, SVG, GIF, WebP, max. 1.5 MB)
- Choose one of three layout sizes:
  - `small` — 32 px high, sits next to the right‑aligned address block
  - `medium` — 64 px high, sits next to the right‑aligned address block (default)
  - `large` — 110 px high banner placed above the address block
- The logo is stored Base64‑encoded inside the company record (no extra Files storage needed) and is embedded into every offer and invoice PDF as a data‑URI

### ZUGFeRD / E‑Rechnung (1.6.3)
- Per company, choose **Settings → Firma → Rechnungsausgabe**: classic PDF (default) or **ZUGFeRD EN16931**
- ZUGFeRD generates a PDF/A‑3 with an embedded Cross Industry Invoice XML (UN/CEFACT, profile EN16931)
- Mandatory data: company name + address + country code, VAT‑ID **or** tax number, customer name + address + country code, VAT‑ID for B2B; bank IBAN is included as payment means when present
- Generated filenames are suffixed `-zugferd.pdf`
- A **sidecar XML download** is always available at `GET /api/invoices/{id}/zugferd-xml`. Use this when the recipient wants pure XRechnung XML, or when the hybrid PDF/A‑3 cannot be produced on your host (the regular PDF is then used and the XML can be attached separately).
- Validation against the [Mustang validator](https://github.com/ZUGFeRD/mustangproject) or [KoSIT XRechnung Validator](https://github.com/itplr-kosit/validator) is recommended before going live
- After updating, run `composer install --no-dev --prefer-dist` inside `apps/nextledger` to pull `horstoeko/zugferd` and `setasign/fpdi`

### Multi-Company
- Manage multiple companies under **Settings → Firma**
- Exactly one company is active at a time
- Active company is shown in the left navigation at **Vorgänge**
- Core data is company-scoped: customers, cases, offers, invoices, products, fiscal years, incomes, expenses

### Mail Templates
Mail text supports placeholders like:
- `{{offerNumber}}`, `{{invoiceNumber}}`
- `{{customerName}}`, `{{customerContact}}`, `{{customerSalutation}}`
- `{{caseName}}`, `{{total}}`, `{{issueDate}}`

## Installation (Nextcloud)
1. Copy or clone the app into `apps/nextledger`
2. Enable the app in Nextcloud admin
3. Open **NextLedger** from the app menu

## Quick Start (Dev)
```sh
docker compose up -d
```
Open `http://localhost:8080` and log in with:
- user: `admin`
- password: `admin`

The app is mounted at `apps/nextledger` and loaded in Nextcloud as `nextledger`.

### Enable Nextcloud Mail App (local Docker)
If you want to test NextLedger's Nextcloud Mail provider integration locally, enable the `mail` app once in the container:

```sh
docker compose exec -T --user root nextcloud chown -R www-data:www-data /var/www/html/custom_apps
docker compose exec -T nextcloud php occ config:system:set appstoreenabled --type=boolean --value=true
docker compose exec -T nextcloud php occ app:install mail
```

Verify:

```sh
docker compose exec -T nextcloud php occ app:list | rg mail
```

## Development
```sh
npm install
npm run dev
```

## Demo Data
Generate demo content for screenshots:
```sh
NEXTLEDGER_BASE_URL=http://localhost:8080/apps/nextledger/api \
NEXTCLOUD_USER=admin \
NEXTCLOUD_APP_PASSWORD=YOUR_APP_PASSWORD \
node scripts/seed-demo.js
```

The script creates:
- 1 active fiscal year
- 5 customers
- 5 cases (one per customer)
- 10 offers (2 per customer)
- 10 invoices (2 per customer)

Create an app password in Nextcloud under **Settings → Security**.

## PDF Export
PDF generation uses **dompdf** (server‑side). Ensure dependencies are installed:
```sh
cd apps/nextledger
composer install
```

## Data Model (Short)
- **Customers**: company, contact, address, email
- **Cases**: customer, description, deck link, kollektiv link
- **Elements**: notes and attachments per case
- **Products/DL**: name, description, unit price
- **Offers/Invoices**: positions, tax, status
- **Fiscal Years**: income/expense + GÜB

## Folder Structure
- `apps/nextledger`: Nextcloud app (backend, migrations, templates)
- `src/`: Vue UI (frontend)
- `docker-compose.yml`: Local Nextcloud 32 environment

## Privacy
All data stays in your Nextcloud instance. Mail sending is handled via `mailto:` (no server‑side email). PDFs are generated server‑side in your instance.

## License
GPL-3.0
