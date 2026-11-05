# Parser API KI+

[Parser API KI+](https://parser-api.micro-ki.de) extracts structured data from business documents and returns a fixed, predictable JSON schema (`invoice.v1`, `creditnote.v1`, `deliverynote.v1`, `purchaseorder.v1`, `bankstatement.v1`, `cv.v1`).

- **Structured documents** (ZUGFeRD 1.0 & 2.x, Factur-X, XRechnung CII/UBL, Peppol BIS, EDIFACT INVOIC/DESADV, camt.052/053/054, SWIFT MT940/MT942) are parsed deterministically without AI at the single-file rate.
- **Office documents and plain text** (`DOCX`, `ODT`, `TXT`) and **PDFs, scans, and photos** (`PDF`, `JPG`, `PNG`, `WEBP`, `TIFF`, `HEIC`) are extracted via OCR and AI hosted in the EU.

See the [API documentation](https://parser-api.micro-ki.de/doku#make) (German overview page) or download the complete **English specifications**:
- Make integration guide: [PDF](https://parser-api.micro-ki.de/doku/make.en.pdf) · [Markdown](https://parser-api.micro-ki.de/doku/make.en.md)
- Endpoint specifications (English): [Invoice (PDF)](https://parser-api.micro-ki.de/doku/rechnung.en.pdf) · [Credit Note (PDF)](https://parser-api.micro-ki.de/doku/gutschrift.en.pdf) · [Delivery Note (PDF)](https://parser-api.micro-ki.de/doku/lieferschein.en.pdf) · [Purchase Order (PDF)](https://parser-api.micro-ki.de/doku/bestellung.en.pdf) · [Bank Statement (PDF)](https://parser-api.micro-ki.de/doku/kontoauszug.en.pdf) · [CV (PDF)](https://parser-api.micro-ki.de/doku/lebenslauf.en.pdf)

---

## Connection & API Keys

Create a **Parser API KI+** connection and enter your API key without the `Bearer` prefix:

| Key | Purpose |
| --- | --- |
| `pak_live_…` | Live key from your [Parser API KI+ account](https://parser-api.micro-ki.de/account/zugang). Parsing real files spends credits. New to Parser API KI+? [Get the Starter Package](https://parser-api.micro-ki.de/starten) (prepaid credits, no subscription). |
| `pak_test_X0001` | Public sandbox key. Ignores the uploaded file, runs no AI, spends `0` credits, and always returns the official sample JSON for the chosen endpoint. Ideal for building and testing scenarios. |

Saving or editing a connection validates the key against `GET /api/v1/account` (`0` credits) and displays the account email address next to the connection name.

---

## Modules

### Documents

Each document type has its own module so Make provides a **100% static output interface** immediately in the mapping dialog without requiring a test run first:

| Module | Endpoint | Output Schema | English Spec |
| --- | --- | --- | --- |
| **Parse an invoice** | `POST /api/v1/invoice` | `invoice.v1` | [PDF](https://parser-api.micro-ki.de/doku/rechnung.en.pdf) · [MD](https://parser-api.micro-ki.de/doku/rechnung.en.md) |
| **Parse a credit note** | `POST /api/v1/credit-note` | `creditnote.v1` | [PDF](https://parser-api.micro-ki.de/doku/gutschrift.en.pdf) · [MD](https://parser-api.micro-ki.de/doku/gutschrift.en.md) |
| **Parse a delivery note** | `POST /api/v1/delivery-note` | `deliverynote.v1` | [PDF](https://parser-api.micro-ki.de/doku/lieferschein.en.pdf) · [MD](https://parser-api.micro-ki.de/doku/lieferschein.en.md) |
| **Parse a purchase order** | `POST /api/v1/purchase-order` | `purchaseorder.v1` | [PDF](https://parser-api.micro-ki.de/doku/bestellung.en.pdf) · [MD](https://parser-api.micro-ki.de/doku/bestellung.en.md) |
| **Parse a bank statement** | `POST /api/v1/bank-statement` | `bankstatement.v1` | [PDF](https://parser-api.micro-ki.de/doku/kontoauszug.en.pdf) · [MD](https://parser-api.micro-ki.de/doku/kontoauszug.en.md) |
| **Parse a CV** | `POST /api/v1/cv` | `cv.v1` | [PDF](https://parser-api.micro-ki.de/doku/lebenslauf.en.pdf) · [MD](https://parser-api.micro-ki.de/doku/lebenslauf.en.md) |

Map **File** (`data`) and **File name** (`fileName`, including the file extension such as `invoice.pdf`) from a preceding module such as **Gmail > Watch Attachments**, **Google Drive > Download a File**, or **HTTP > Download a file**.

### Account

| Module | Endpoint | Description |
| --- | --- | --- |
| **Get a quote** | `POST /api/v1/quote` | Calculates how many credits parsing a file would cost (`needed`, `available`, `billable_units`, `kind`, `page_count`, `source_hint`, `pricing.rule`). Spends `0` credits. |
| **Get an account** | `GET /api/v1/account` | Returns `credits`, `available` credits, `alert_threshold`, `alert_enabled`, key prefixes, and `mail`. Spends `0` credits. |
| **Search usage entries** | `GET /api/v1/usage` | Search module that iterates over `entries[]` from today and yesterday (up to 50 calls, newest first). Supports filtering by **Document type** and limiting bundles via **Limit**. |

### Other

| Module | Endpoint | Description |
| --- | --- | --- |
| **Make an API call** | `https://parser-api.micro-ki.de/{{parameters.url}}` | Performs an arbitrary authorized API call to `parser-api.micro-ki.de` (for example `/api/v1/account`). The host is fixed so your API key is never sent to a third-party server. |

---

## Advanced Settings

Toggle **Show advanced settings** at the bottom of a parse or quote module to configure optional headers:

1. **Read lines with OCR** (`linesOcr`, available in **Parse an invoice** and **Get a quote**)
   - Default: `false`
   - When enabled, sends the header `X-Parser-Lines: ocr`.
   - Use this for hybrid ZUGFeRD or Factur-X PDFs in the `MINIMUM` or `BASIC WL` profile whose embedded XML contains header and total amounts but no line items (`warnings` contains `xml_without_lines`).
   - The AI reads the line items and any missing fields from the visible PDF page while keeping all header and total values from the XML intact. The call is billed at the per-page PDF-OCR rate.

2. **Force fresh read** (`force`, available in all **Parse** modules and **Get a quote**)
   - Default: `false`
   - By default, identical files uploaded to the same endpoint within **15 minutes** are served from the server cache at `0` credits.
   - When enabled, sends `X-Parser-Force: 1` to bypass the 15-minute cache and run a fresh parse (spending credits again). On a hybrid ZUGFeRD/Factur-X PDF, `X-Parser-Force: 1` skips the embedded XML and reads the visible PDF via OCR (`warnings` contains `xml_forced_ocr`).
   - In **Get a quote**, enabling **Force fresh read** calculates the price of a fresh parse without spending credits.

3. **Idempotency key** (`idempotencyKey`, available in all **Parse** modules)
   - Optional string of `8` to `128` characters (`A–Z`, `a–z`, `0–9`, `.`, `_`, `-`), sent as the `Idempotency-Key` header.
   - **If left empty (recommended for most scenarios):** No `Idempotency-Key` header is sent. Identical files are still deduplicated automatically by their SHA-256 file hash for 15 minutes at `0` credits.
   - **If set:** Provide a unique value per file that stays identical when retrying the same file (for example `gmail-{{messageId}}-{{attachmentIndex}}`). Reusing the same idempotency key for a different file returns HTTP `409 idempotency_key_conflict`.

---

## Error Handling & Automatic Retries

All API errors include the HTTP status code, the stable snake_case `error.code`, the English `error.message`, and—when a document was sent to the wrong endpoint (such as a credit note sent to **Parse an invoice**)—the `detected_type` hint:

- **Automatic Retry (`RateLimitError` / `ConnectionError`):**
  - `429 rate_limited` (too many parallel parses or requests per minute)
  - `409 parse_in_progress` (the identical file is currently being processed in another call)
  - `409 account_busy` (short lock contention while reserving credits)
  - `502` / `503` / `504` and transient `500` server errors
- **Immediate Stop (`RuntimeError`, not retried):**
  - `401` (missing or invalid API key)
  - `402 insufficient_credits` (account balance is lower than required credits; no AI is run)
  - `409 idempotency_key_conflict` (same `Idempotency-Key` used with a different file)
  - `413 document_too_large` / `415` (unsupported file type or missing file)
  - `422` (`not_an_invoice`, `unsupported_document_type`, `password_protected`, `page_limit_exceeded`, etc.)
  - `500 account_corrupt` / `capture_corrupt`

---

## Timeout (280 Seconds)

The app configures a **280-second (`280,000 ms`) HTTP timeout** via `general/common.json` and `general/base.iml.json` (`"timeout": "{{common.timeout}}"`), matching the 270-second server processing budget of Parser API KI+:
- **Structured files** (ZUGFeRD, Factur-X, XRechnung, EDIFACT, camt, MT940) finish in `< 2 s`.
- **PDFs, scans, and photos** (up to 20 pages / 15 MB) have the full 280-second window for OCR and AI extraction.
