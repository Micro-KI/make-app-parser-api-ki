# make-app-parser-api-ki

Official [Make](https://www.make.com/) Custom App for [Parser API KI+](https://parser-api.micro-ki.de). It sends a business document to `https://parser-api.micro-ki.de` and returns a fixed, predictable JSON schema (`invoice.v1`, `creditnote.v1`, `deliverynote.v1`, `purchaseorder.v1`, `bankstatement.v1`, `cv.v1`). It also supports calculating a price quote, checking your credit balance, searching recent API usage, and making arbitrary authorized API calls.

- **Structured documents** (ZUGFeRD 1.0 & 2.x, Factur-X, XRechnung CII/UBL, Peppol BIS, EDIFACT INVOIC/DESADV, camt.052/053/054, SWIFT MT940/MT942) are parsed deterministically without AI at the single-file rate.
- **Office documents and plain text** (`DOCX`, `ODT`, `TXT`) and **PDFs, scans, and photos** (`PDF`, `JPG`, `PNG`, `WEBP`, `TIFF`, `HEIC`) are extracted via OCR and AI hosted in the EU.

The app definition in this repository is free and MIT-licensed. Parser API KI+ is a paid service: parsing real files spends prepaid credits from your account.

## Installation in Make

Install the app directly in your Make organization via the official invite link:

👉 **[Install Parser API KI+ in Make](https://www.make.com/en/hq/app-invitation/14daf32a9d18de978f6d1a4d2a23c015)**

## Connection & API Keys

Create a **Parser API KI+** connection in Make and paste your API key without the `Bearer` prefix:

| Key | Use |
| --- | --- |
| `pak_live_…` | Live key from your [Parser API KI+ account](https://parser-api.micro-ki.de/account/zugang). Parsing real files spends credits. New to Parser API KI+? [Get the Starter Package](https://parser-api.micro-ki.de/starten) (prepaid credits, no subscription). |
| `pak_test_X0001` | Public sandbox key. Ignores the uploaded file, runs no AI, spends `0` credits, and always returns the official sample JSON for the chosen endpoint. Use it to build and test scenarios free of charge. |

Saving or editing a connection validates the key against `GET /api/v1/account` (`0` credits) and displays the account email address next to the connection name. The server host is fixed so your API key is only ever sent to `parser-api.micro-ki.de`.

## Modules

Each document type has its own module so Make provides a **100% static output interface** in the mapping dialog immediately—without requiring a test run with a real file first.

| Group | Module | Endpoint | Output | English Spec |
| --- | --- | --- | --- | --- |
| **Documents** | **Parse an invoice** | `POST /api/v1/invoice` | Fixed schema `invoice.v1` | [PDF](https://parser-api.micro-ki.de/doku/rechnung.en.pdf) · [MD](https://parser-api.micro-ki.de/doku/rechnung.en.md) |
| **Documents** | **Parse a credit note** | `POST /api/v1/credit-note` | Fixed schema `creditnote.v1` | [PDF](https://parser-api.micro-ki.de/doku/gutschrift.en.pdf) · [MD](https://parser-api.micro-ki.de/doku/gutschrift.en.md) |
| **Documents** | **Parse a delivery note** | `POST /api/v1/delivery-note` | Fixed schema `deliverynote.v1` | [PDF](https://parser-api.micro-ki.de/doku/lieferschein.en.pdf) · [MD](https://parser-api.micro-ki.de/doku/lieferschein.en.md) |
| **Documents** | **Parse a purchase order** | `POST /api/v1/purchase-order` | Fixed schema `purchaseorder.v1` | [PDF](https://parser-api.micro-ki.de/doku/bestellung.en.pdf) · [MD](https://parser-api.micro-ki.de/doku/bestellung.en.md) |
| **Documents** | **Parse a bank statement** | `POST /api/v1/bank-statement` | Fixed schema `bankstatement.v1` | [PDF](https://parser-api.micro-ki.de/doku/kontoauszug.en.pdf) · [MD](https://parser-api.micro-ki.de/doku/kontoauszug.en.md) |
| **Documents** | **Parse a CV** | `POST /api/v1/cv` | Fixed schema `cv.v1` | [PDF](https://parser-api.micro-ki.de/doku/lebenslauf.en.pdf) · [MD](https://parser-api.micro-ki.de/doku/lebenslauf.en.md) |
| **Account** | **Get a quote** | `POST /api/v1/quote` | Calculates what parsing the file would cost (`0` credits) | [PDF](https://parser-api.micro-ki.de/doku/make.en.pdf) · [MD](https://parser-api.micro-ki.de/doku/make.en.md) |
| **Account** | **Get an account** | `GET /api/v1/account` | Credit balance, available credits, alert threshold (`0` credits) | [PDF](https://parser-api.micro-ki.de/doku/make.en.pdf) · [MD](https://parser-api.micro-ki.de/doku/make.en.md) |
| **Account** | **Search usage entries** | `GET /api/v1/usage` | Search module iterating over `entries[]` from today and yesterday (up to 50, newest first) | [PDF](https://parser-api.micro-ki.de/doku/make.en.pdf) · [MD](https://parser-api.micro-ki.de/doku/make.en.md) |
| **Other** | **Make an API call** | `/api/v1/…` | Arbitrary authorized call to `parser-api.micro-ki.de` (fixed host) | [PDF](https://parser-api.micro-ki.de/doku/make.en.pdf) · [MD](https://parser-api.micro-ki.de/doku/make.en.md) |

## Quick Start

1. Add a module that outputs a file, for example **Gmail > Watch Attachments**, **Google Drive > Download a File**, or **HTTP > Download a file**.
2. Add a **Parser API KI+** parse module (for example **Parse an invoice**) and map **File** (`data`) and **File name** (`fileName`, including the file extension such as `invoice.pdf`).
3. Run the scenario or map the static output pills (`Invoice number`, `Amount due`, `IBAN`, `Lines`, `Confidence`, `Warnings`, etc.) into the next module.

### Public Sample Files for Testing

To test a scenario with **HTTP > Download a file** without connecting your own mailbox or drive, use one of the synthetic sample files in [`samples/`](samples/) of this repository:

- Invoice (hybrid ZUGFeRD/Factur-X PDF): `https://raw.githubusercontent.com/Micro-KI/make-app-parser-api-ki/main/samples/invoice.pdf`
- Invoice (XRechnung XML): `https://raw.githubusercontent.com/Micro-KI/make-app-parser-api-ki/main/samples/invoice.xml`
- Credit note (PDF): `https://raw.githubusercontent.com/Micro-KI/make-app-parser-api-ki/main/samples/credit-note.pdf`
- Delivery note (PDF): `https://raw.githubusercontent.com/Micro-KI/make-app-parser-api-ki/main/samples/delivery-note.pdf`
- Purchase order (PDF): `https://raw.githubusercontent.com/Micro-KI/make-app-parser-api-ki/main/samples/purchase-order.pdf`
- Bank statement (PDF): `https://raw.githubusercontent.com/Micro-KI/make-app-parser-api-ki/main/samples/bank-statement.pdf`
- CV (PDF): `https://raw.githubusercontent.com/Micro-KI/make-app-parser-api-ki/main/samples/cv.pdf`

## Advanced Settings

Toggle **Show advanced settings** at the bottom of a parse or quote module to configure optional headers:

| Setting | Parameter | Available in | Effect |
| --- | --- | --- | --- |
| **Read lines with OCR** | `linesOcr` (Boolean, default `false`) | **Parse an invoice**, **Get a quote** | Sends `X-Parser-Lines: ocr`. For hybrid ZUGFeRD or Factur-X PDFs in the `MINIMUM` or `BASIC WL` profile whose XML contains header and total amounts but no line items (`warnings` contains `xml_without_lines`). The AI reads line items and missing fields from the visible page while keeping all XML header and total values intact. Billed at the per-page PDF-OCR rate. |
| **Force fresh read** | `force` (Boolean, default `false`) | All **Parse** modules, **Get a quote** | Sends `X-Parser-Force: 1`. Skips the cached response from the last 15 minutes and runs a fresh parse (spending credits again). On hybrid ZUGFeRD/Factur-X PDFs, skips the embedded XML and reads the visible page via OCR (`xml_forced_ocr`). In **Get a quote**, calculates the price of a fresh parse (`0` credits). |
| **Idempotency key** | `idempotencyKey` (Text, optional) | All **Parse** modules | Sends `Idempotency-Key` (`8` to `128` characters: `A–Z`, `a–z`, `0–9`, `.`, `_`, `-`). Leave empty to send no header; identical files are still deduplicated automatically via their SHA-256 hash for 15 minutes at `0` credits. If set, use a unique value per file that stays identical across retries; reusing the same key for a different file returns `409 idempotency_key_conflict`. |

## Error Handling & Automatic Retries

All API errors return the HTTP status code, the stable snake_case `error.code`, the English `error.message`, and—when a document was sent to the wrong endpoint—the `detected_type` hint:

- **Automatic Retry (`RateLimitError` / `ConnectionError`):** `429 rate_limited`, `409 parse_in_progress` (identical file currently being processed), `409 account_busy`, and transient `5xx` errors. Make pauses the scenario and retries automatically.
- **Immediate Stop (`RuntimeError`, not retried):** `401`, `402 insufficient_credits`, `409 idempotency_key_conflict`, `413 document_too_large`, `415`, `422` (`not_an_invoice`, `unsupported_document_type`, etc.), and `500 account_corrupt` / `capture_corrupt`.

## Timeout (280 Seconds)

The app configures a **280-second (`280,000 ms`) HTTP timeout** via [`src/general/common.json`](src/general/common.json) and [`src/general/base.iml.json`](src/general/base.iml.json) (`"timeout": "{{common.timeout}}"`), matching the 270-second server processing budget of Parser API KI+:
- **Structured files** (ZUGFeRD, Factur-X, XRechnung, EDIFACT, camt, MT940) finish in `< 2 s`.
- **PDFs, scans, and photos** (up to 20 pages / 15 MB) have the full 280-second window for OCR and AI extraction.

## Documentation

- **English Make Integration Guide:** [PDF](https://parser-api.micro-ki.de/doku/make.en.pdf) · [Markdown](https://parser-api.micro-ki.de/doku/make.en.md)
- **English Endpoint Specifications (full field tables, examples, warnings & error codes):**
  - Invoice (`invoice.v1`): [PDF](https://parser-api.micro-ki.de/doku/rechnung.en.pdf) · [Markdown](https://parser-api.micro-ki.de/doku/rechnung.en.md)
  - Credit Note (`creditnote.v1`): [PDF](https://parser-api.micro-ki.de/doku/gutschrift.en.pdf) · [Markdown](https://parser-api.micro-ki.de/doku/gutschrift.en.md)
  - Delivery Note (`deliverynote.v1`): [PDF](https://parser-api.micro-ki.de/doku/lieferschein.en.pdf) · [Markdown](https://parser-api.micro-ki.de/doku/lieferschein.en.md)
  - Purchase Order (`purchaseorder.v1`): [PDF](https://parser-api.micro-ki.de/doku/bestellung.en.pdf) · [Markdown](https://parser-api.micro-ki.de/doku/bestellung.en.md)
  - Bank Statement (`bankstatement.v1`): [PDF](https://parser-api.micro-ki.de/doku/kontoauszug.en.pdf) · [Markdown](https://parser-api.micro-ki.de/doku/kontoauszug.en.md)
  - CV (`cv.v1`): [PDF](https://parser-api.micro-ki.de/doku/lebenslauf.en.pdf) · [Markdown](https://parser-api.micro-ki.de/doku/lebenslauf.en.md)
- [Development and schema generator](docs/development.md)
- [Parser API KI+ online API reference](https://parser-api.micro-ki.de/doku#make) (German page with English downloads)
- [Make integration overview](https://parser-api.micro-ki.de/make)

## License

[MIT](LICENSE). The license covers this Make app definition, not the Parser API KI+ service.
