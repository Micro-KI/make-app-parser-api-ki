# Entwicklung

`bin/build-interfaces.php` liest die API nebenan:

- `config/schemas/*.json` über `SchemaCatalog`
- `SchemaFieldMeanings` und `SchemaFieldMeaningsEn`
- `ResponseEnvelopeFields`
- `TestFixtures` für die Beispielausgaben

Die Hülle (`source`, `request_id`, `billed`, `available`, `pricing`) setzt das Skript an dieselbe Stelle wie die öffentliche Feldtabelle: direkt hinter `schema`. `pricing.rule` und `pricing.label` stehen nicht im Dokument-Schema, das Skript ergänzt sie.

`--check` bricht mit Exit-Code 1 ab, wenn eine Datei unter `src/` nicht mehr zum Skript passt. Die Prüfung vergleicht außerdem jede Schema-Pfadliste mit dem Make-Interface. Fehlt ein Feld oder kommt eines dazu, bricht der Lauf ab.

Beispiele in den Samples nutzen den Satzbau von `PricingCopy` mit den Beispielzahlen 1 und 2 Credits. Der laufende Tarif des Kontos kann anders sein. Die Samples zeigen die Form, nicht die aktuelle Preistafel.

Komponenten-IDs, die nach dem ersten Deploy stabil bleiben:

| Lokal | Rolle |
| --- | --- |
| `parserApiKi` | Verbindung, Feld `apiKey` |
| `parseInvoice` | `POST /invoice` |
| `parseCreditNote` | `POST /credit-note` |
| `parseDeliveryNote` | `POST /delivery-note` |
| `parsePurchaseOrder` | `POST /purchase-order` |
| `parseBankStatement` | `POST /bank-statement` |
| `parseCv` | `POST /cv` |
| `getQuote` | `POST /quote` |
| `getAccount` | `GET /account` |
| `getUsage` | `GET /usage` (Search-Modul, iteriert `body.entries`, Parameter `type` und `limit`) |
| `makeApiCall` | Pfad ab der Domain, zum Beispiel `/api/v1/account`, Host fest |

Parameter, die gespeicherte Szenarien ansprechen: `data`, `fileName`, `type`, `limit`, `linesOcr`, `force`, `idempotencyKey`. `linesOcr` gibt es nur bei der Rechnung und bei der Kosten-Anfrage. `limit` nur bei `getUsage`. Die Merknummer nur beim Auslesen, und nur wenn sie ausgefüllt ist. Leer heißt: kein `Idempotency-Key`. `makeApiCall` lässt den Nutzer nur den Pfad setzen, nicht den Host, damit der Schlüssel nicht an einen anderen Server geht.
