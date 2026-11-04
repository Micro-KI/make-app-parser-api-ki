# Parser API KI+

Send one document to `https://parser-api.micro-ki.de` and map the fixed JSON in the next module.

Paste an API key (`pak_live_…`) from the Parser API KI+ account. The public test key `pak_test_X0001` ignores the file and always returns the sample JSON, so you can build the scenario without spending credits.

Make stops a custom-app call after 40 seconds. Structured files (ZUGFeRD, XRechnung, EDIFACT, camt, MT940) and short PDFs finish in that time. A long multi-page scan can take longer: use the HTTP blueprint from the account, which waits up to 280 seconds.

Make an API call takes a path that starts with `/api/v1`, for example `/api/v1/account`. The host stays `parser-api.micro-ki.de`, so the key is not sent to another server.
