# Development

`bin/build-interfaces.php` generates all Make Apps SDK files under `src/` directly from the Parser API KI+ schema definitions and field descriptions:

- `config/schemas/*.json` via `SchemaCatalog`
- `SchemaFieldMeaningsEn` and `SchemaFieldMeanings`
- `ResponseEnvelopeFields`
- `TestFixtures` for the module sample outputs

The HTTP response envelope fields (`source`, `request_id`, `billed`, `available`, `pricing.rule`, `pricing.label`) are inserted at the same position as in the public field tables: immediately after `schema`.

## Regenerating and Checking Interfaces

Place the API checkout next to this repository at `../parser-api_micro-ki_de` (or set `PARSER_API_DIR`):

```bash
php bin/build-interfaces.php
php bin/build-interfaces.php --check
```

`--check` exits with status `1` if any file under `src/` is out of sync with the generator or if any schema path is missing from a module interface. The generated files under `src/` are committed to the repository so the app can be deployed to Make without running PHP first.

Sample outputs use illustrative rate labels from `PricingCopy` (`1` and `2` credits) to demonstrate the response structure.

## Stable Component and Parameter IDs

Do not rename internal component IDs or parameter names once scenarios are saved in Make (visible labels and descriptions may change):

| Component ID | Role |
| --- | --- |
| `parserApiKi` | Basic connection (`apiKey` parameter) |
| `parseInvoice` | Action module (`POST /invoice`) |
| `parseCreditNote` | Action module (`POST /credit-note`) |
| `parseDeliveryNote` | Action module (`POST /delivery-note`) |
| `parsePurchaseOrder` | Action module (`POST /purchase-order`) |
| `parseBankStatement` | Action module (`POST /bank-statement`) |
| `parseCv` | Action module (`POST /cv`) |
| `getQuote` | Action module (`POST /quote`) |
| `getAccount` | Action module (`GET /account`) |
| `getUsage` | Search module (`GET /usage`, iterates `body.entries`, parameters `type` and `limit`) |
| `makeApiCall` | Universal REST module (relative path on fixed host `https://parser-api.micro-ki.de`) |

Stable parameter names: `data`, `fileName`, `type`, `limit`, `linesOcr`, `force`, `idempotencyKey`.

- `linesOcr` is only present on `parseInvoice` and `getQuote`.
- `limit` is only present on `getUsage`.
- `idempotencyKey` is only present on the six parse modules and is omitted when left empty.
- `makeApiCall` only accepts a path relative to `https://parser-api.micro-ki.de` so the API key is never sent to another host.

## Deploying with the Make Apps SDK

1. Install the VS Code / Cursor extension **Make Apps Editor** (`Integromat.apps-sdk`).
2. Store your Make API token (`sdk-apps:read`, `sdk-apps:write`) in `.secrets/apikey` (ignored by git).
3. Right-click `src/makecomapp.json` and choose **Deploy to Make**.
4. For the app icon, upload `assets/icon.png` (512 × 512 px black on transparent PNG; Make renders black as white on `#00e8ff`). The vector source is in `assets/icon.svg`.
