# Make Custom App für Parser API KI+

Quellen der Make-App **Parser API KI+**. Make spricht sie über das [Apps SDK](https://developers.make.com/custom-apps-documentation/get-started/make-apps-editor/apps-sdk/local-development-for-apps) an. Die API bleibt `https://parser-api.micro-ki.de`. Dieses Repo ist der Baustein, nicht der Dienst: echte Dateien kosten Credits.

Die App hat sechs Auslese-Module, weil jedes Modul in Make ein festes Ausgabeschema braucht. Sobald jemand **Parse an invoice** ins Szenario zieht, sind die Felder da, ohne einen Probelauf mit einer echten Datei. Alle sichtbaren Texte in der App sind Englisch, wie Make es im Katalog verlangt.

| Modul | Aufruf | Antwort |
| --- | --- | --- |
| Parse an invoice | `POST /api/v1/invoice` | `invoice.v1` |
| Parse a credit note | `POST /api/v1/credit-note` | `creditnote.v1` |
| Parse a delivery note | `POST /api/v1/delivery-note` | `deliverynote.v1` |
| Parse a purchase order | `POST /api/v1/purchase-order` | `purchaseorder.v1` |
| Parse a bank statement | `POST /api/v1/bank-statement` | `bankstatement.v1` |
| Parse a CV | `POST /api/v1/cv` | `cv.v1` |
| Get a quote | `POST /api/v1/quote` | Preis, ohne Abbuch |
| Get an account | `GET /api/v1/account` | Guthaben, 0 Credits |
| Search usage entries | `GET /api/v1/usage` | Einträge von heute und gestern, je Eintrag ein Bundle |
| Make an API call | Pfad ab der Domain, zum Beispiel `/api/v1/account` | Body, Header, Status |

Die Verbindung prüft den Schlüssel mit `GET /api/v1/account`. Eintragen nur den Schlüssel, ohne das Wort `Bearer`. Zum Bauen der Szenarien reicht der öffentliche Test-Key `pak_test_X0001`: die Datei wird ignoriert, es läuft keine KI, zurück kommt das Beispiel-JSON.

## Grenze: 40 Sekunden

Make bricht HTTP-Aufrufe einer Custom App nach 40 Sekunden ab. Das lässt sich in der App nicht auf 280 Sekunden hochsetzen. Strukturierte Belege (ZUGFeRD, XRechnung, EDIFACT, camt, MT940) und kurze PDFs sind damit bedient. Ein langer, mehrseitiger Scan bleibt im Make-Blueprint mit dem HTTP-Modul (Timeout 280 Sekunden) im Kundenkonto.

## Felder

Die Ausgabefelder kommen aus den Schemas und den Bedeutungstexten der API, nicht von Hand. Das Pill trägt einen kurzen englischen Namen (1 bis 3 Wörter). Nach einer Änderung an `config/schemas/` oder `SchemaFieldMeaningsEn` neu erzeugen:

```bash
php bin/build-interfaces.php
php bin/build-interfaces.php --check
```

Dafür liegt der Checkout der API daneben unter `../parser-api_micro-ki_de`. Sonst `PARSER_API_DIR` setzen. Die erzeugten Dateien unter `src/` gehören ins Repo, damit die App ohne PHP-Lauf nach Make geht.

Interne Namen (`parseInvoice`, `parserApiKi`, `data`, `fileName`) nicht mehr umbenennen, sobald ein Szenario sie gespeichert hat. Sichtbare Bezeichnungen dürfen sich ändern.

## Nach Make bringen

1. In Make eine Custom App anlegen, Anzeigename **Parser API KI+**, Farbe `#00e8ff`. Das Icon für den Upload liegt unter `assets/icon.png` (512 × 512 Pixel, schwarz auf transparent, Make färbt es weiß auf der Themenfarbe). Die Vektorvorlage liegt unter `assets/icon.svg`.
2. Die VS-Code-Erweiterung **Make Apps SDK** installieren, den API-Token von Make nach `.secrets/apikey` legen (der Ordner ist in `.gitignore`).
3. In `src/makecomapp.json` unter `origins` die Zone eintragen, zum Beispiel `https://eu1.make.com/api`, plus `appId` und `appVersion`. Die Erweiterung kann den Eintrag auch anlegen. `idMapping` nicht von Hand pflegen.
4. `src/makecomapp.json` rechtsklicken und **Deploy to Make** wählen.
5. Mit `pak_test_X0001` und einem Live-Key je ein kurzes PDF und eine E-Rechnung durch **Parse an invoice** schicken. Die Merknummer bleibt leer, dann geht kein `Idempotency-Key` mit. Dieselbe Datei erkennt der Server 15 Minuten lang auch ohne Merknummer. Für eine eigene Nummer den Wert im Modul eintragen: 8 bis 128 Zeichen, pro Datei ein eigener Wert, bei einer Wiederholung derselbe.
6. Den Invite-Link aus Make auf `/make` und im Konto unter Anbindung neben den Blueprint legen. Solange der Link fehlt, bleibt die Website unverändert.
7. Für den öffentlichen Katalog dieselbe App bei Make zur Prüfung geben.

Make wiederholt `429` von sich aus (`RateLimitError`) und `5xx` als vorübergehenden Verbindungsfehler. Zusätzlich gilt `409` als `RateLimitError`, außer `idempotency_key_conflict`: der ist ein `RuntimeError` und wird nicht wiederholt. `500` mit `account_corrupt` oder `capture_corrupt` ist ebenfalls `RuntimeError`. `401`, `402`, `413`, `415` und `422` bleiben `RuntimeError`.

## Lizenz

[MIT](LICENSE). Die Lizenz gilt für diese App-Definition, nicht für den Dienst Parser API KI+.
