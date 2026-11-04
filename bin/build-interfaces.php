#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Builds the Make Apps SDK tree from the Parser API KI+ schemas.
 *
 * Field labels stay aligned with SchemaFieldMeanings, SchemaFieldMeaningsEn
 * and ResponseEnvelopeFields. Re-run after a schema or meaning change:
 *
 *   php bin/build-interfaces.php
 *   php bin/build-interfaces.php --check
 *
 * PARSER_API_DIR defaults to the sibling checkout ../parser-api_micro-ki_de.
 */

use App\Service\Parse\DocumentType;
use App\Service\Parse\ResponseEnvelopeFields;
use App\Service\Parse\SchemaCatalog;
use App\Service\Parse\SchemaFieldMeanings;
use App\Service\Parse\SchemaFieldMeaningsEn;
use App\Service\Parse\TestFixtures;
use App\Service\Pricing\PricingCopy;

final class MakeAppBuilder
{
    private const SRC = __DIR__ . '/../src';

    /**
     * Make output labels are short English sentence case. The German meaning stays in help.
     *
     * @var array<string, string>
     */
    private const SHORT_LABELS = [
        'schema'           => 'Schema',
        'source'           => 'Source',
        'request_id'       => 'Request ID',
        'billed'           => 'Credits billed',
        'available'        => 'Credits available',
        'warnings[]'       => 'Warnings',
        'pricing'          => 'Pricing',
        'pricing.rule'     => 'Rule',
        'pricing.label'    => 'Label',
        'confidence'       => 'Confidence',
        'balances.opening' => 'Opening balance',
        'balances.closing' => 'Closing balance',
    ];

    private const ERROR_MESSAGE = "[{{statusCode}} {{ifempty(body.error.code, 'error')}}] {{ifempty(body.error.message, ifempty(body.message, 'Request failed'))}}{{if(body.error.detected_type, ' (detected_type: ' + body.error.detected_type + ')', '')}}";

    /** @var array<string, string> */
    private const ACRONYMS = [
        'iban' => 'IBAN',
        'bic'  => 'BIC',
        'gln'  => 'GLN',
        'gtin' => 'GTIN',
        'vat'  => 'VAT',
        'id'   => 'ID',
        'url'  => 'URL',
        'pdf'  => 'PDF',
        'ocr'  => 'OCR',
        'cv'   => 'CV',
        'gvc'  => 'GVC',
        'iso'  => 'ISO',
    ];

    public function __construct(private readonly string $apiDir)
    {
    }

    public function run(bool $check): int
    {
        $files   = $this->files();
        $changed = [];
        foreach ($files as $relative => $contents) {
            $path    = self::SRC . '/' . $relative;
            $current = is_file($path) ? (string) file_get_contents($path) : null;
            if ($current !== $contents) {
                $changed[] = $relative;
                if (!$check) {
                    $dir = dirname($path);
                    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                        throw new RuntimeException('Cannot create ' . $dir);
                    }
                    file_put_contents($path, $contents);
                }
            }
        }

        if ($check && [] !== $changed) {
            fwrite(STDERR, "Make app is out of date. Run php bin/build-interfaces.php\n");
            foreach ($changed as $relative) {
                fwrite(STDERR, '  ' . $relative . "\n");
            }

            return 1;
        }

        $mode = $check ? 'up to date' : ([] === $changed ? 'unchanged' : 'wrote ' . count($changed) . ' file(s)');
        fwrite(STDOUT, count($files) . ' files, ' . $mode . ".\n");

        return 0;
    }

    /**
     * @return array<string, string>
     */
    private function files(): array
    {
        $files = [
            'makecomapp.json'                                          => $this->json($this->manifest()),
            'general/base.iml.json'                                    => $this->json($this->base()),
            'general/readme.md'                                        => $this->appReadme(),
            'connections/parserApiKi/parserApiKi.params.iml.json'      => $this->json($this->connectionParams()),
            'connections/parserApiKi/parserApiKi.communication.iml.json' => $this->json($this->connectionCommunication()),
            'modules/groups.json'                                      => $this->json($this->groups()),
        ];

        foreach ($this->parseModules() as $id => $module) {
            $type     = $module['document'];
            $meanings = $this->meanings($type);
            $interface = $this->interfaceFromSchema($this->schema($type), $meanings, '');
            $this->assertCoversSchema($id, $type, $interface);
            $files["modules/{$id}/{$id}.mappable-params.iml.json"] = $this->json($this->parseParams($module['linesOcr']));
            $files["modules/{$id}/{$id}.communication.iml.json"]   = $this->json($this->parseCommunication($type, $module['linesOcr']));
            $files["modules/{$id}/{$id}.interface.iml.json"]       = $this->json($interface);
            $files["modules/{$id}/{$id}.samples.iml.json"]         = $this->json($this->parseSample($type));
        }

        $files['modules/getQuote/getQuote.mappable-params.iml.json'] = $this->json($this->quoteParams());
        $files['modules/getQuote/getQuote.communication.iml.json']   = $this->json($this->quoteCommunication());
        $files['modules/getQuote/getQuote.interface.iml.json']       = $this->json($this->quoteInterface());
        $files['modules/getQuote/getQuote.samples.iml.json']         = $this->json($this->quoteSample());

        $files['modules/getAccount/getAccount.mappable-params.iml.json'] = $this->json([]);
        $files['modules/getAccount/getAccount.communication.iml.json']   = $this->json($this->accountCommunication());
        $files['modules/getAccount/getAccount.interface.iml.json']       = $this->json($this->accountInterface());
        $files['modules/getAccount/getAccount.samples.iml.json']         = $this->json($this->accountSample());

        $files['modules/getUsage/getUsage.mappable-params.iml.json'] = $this->json($this->usageParams());
        $files['modules/getUsage/getUsage.communication.iml.json']   = $this->json($this->usageCommunication());
        $files['modules/getUsage/getUsage.interface.iml.json']       = $this->json($this->usageInterface());
        $files['modules/getUsage/getUsage.samples.iml.json']         = $this->json($this->usageSample());

        $files['modules/makeApiCall/makeApiCall.mappable-params.iml.json'] = $this->json($this->universalParams());
        $files['modules/makeApiCall/makeApiCall.communication.iml.json']   = $this->json($this->universalCommunication());
        $files['modules/makeApiCall/makeApiCall.interface.iml.json']       = $this->json($this->universalInterface());
        $files['modules/makeApiCall/makeApiCall.samples.iml.json']         = $this->json(new stdClass());

        return $files;
    }

    private function json(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . "\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        $modules = [];
        foreach ($this->parseModules() as $id => $module) {
            $modules[$id] = $this->moduleMeta($id, $module['label'], $module['description']);
        }
        $modules['getQuote']   = $this->moduleMeta('getQuote', 'Get a quote', 'Returns what parsing the file would cost. Spends no credits.');
        $modules['getAccount'] = $this->moduleMeta('getAccount', 'Get an account', 'Returns the credit balance, alert threshold, and key prefixes of the account.');
        $modules['getUsage']   = [
            'label'       => 'Search usage entries',
            'description' => 'Returns a list of usage entries from today and yesterday.',
            'moduleType'  => 'search',
            'connection'  => 'parserApiKi',
            'codeFiles'   => [
                'communication'  => 'modules/getUsage/getUsage.communication.iml.json',
                'mappableParams' => 'modules/getUsage/getUsage.mappable-params.iml.json',
                'interface'      => 'modules/getUsage/getUsage.interface.iml.json',
                'samples'        => 'modules/getUsage/getUsage.samples.iml.json',
            ],
        ];
        $modules['makeApiCall'] = [
            'label'       => 'Make an API call',
            'description' => 'Performs an arbitrary authorized API call.',
            'moduleType'  => 'universal',
            'connection'  => 'parserApiKi',
            'codeFiles'   => [
                'communication'  => 'modules/makeApiCall/makeApiCall.communication.iml.json',
                'mappableParams' => 'modules/makeApiCall/makeApiCall.mappable-params.iml.json',
                'interface'      => 'modules/makeApiCall/makeApiCall.interface.iml.json',
                'samples'        => 'modules/makeApiCall/makeApiCall.samples.iml.json',
            ],
        ];

        return [
            'fileVersion'      => 1,
            'generalCodeFiles' => [
                'base'   => 'general/base.iml.json',
                'common' => null,
                'readme' => 'general/readme.md',
                'groups' => 'modules/groups.json',
            ],
            'components' => [
                'connection' => [
                    'parserApiKi' => [
                        'label'          => 'Parser API KI+',
                        'connectionType' => 'basic',
                        'codeFiles'      => [
                            'communication' => 'connections/parserApiKi/parserApiKi.communication.iml.json',
                            'params'        => 'connections/parserApiKi/parserApiKi.params.iml.json',
                        ],
                    ],
                ],
                'endpoint' => new stdClass(),
                'function' => new stdClass(),
                'module'   => $modules,
                'rpc'      => new stdClass(),
                'webhook'  => new stdClass(),
            ],
            'origins' => [
                [
                    'label'      => 'Make EU1',
                    'baseUrl'    => 'https://eu1.make.com/api',
                    'appId'      => 'parser-api-ki-m0evy9',
                    'appVersion' => 1,
                    'apikeyFile' => '../.secrets/apikey',
                    'idMapping'  => [
                        'connection' => [
                            [
                                'local'  => 'parserApiKi',
                                'remote' => 'parser-api-ki-m0evy9',
                            ],
                        ],
                        'module' => array_values(array_map(
                            static fn (string $id): array => ['local' => $id, 'remote' => $id],
                            array_keys($modules),
                        )),
                        'function' => [],
                        'rpc'      => [],
                        'webhook'  => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function moduleMeta(string $id, string $label, string $description): array
    {
        return [
            'label'       => $label,
            'description' => $description,
            'moduleType'  => 'action',
            'actionCrud'  => 'read',
            'connection'  => 'parserApiKi',
            'codeFiles'   => [
                'communication'  => "modules/{$id}/{$id}.communication.iml.json",
                'mappableParams' => "modules/{$id}/{$id}.mappable-params.iml.json",
                'interface'      => "modules/{$id}/{$id}.interface.iml.json",
                'samples'        => "modules/{$id}/{$id}.samples.iml.json",
            ],
        ];
    }

    /**
     * @return list<array{label: string, modules: list<string>}>
     */
    private function groups(): array
    {
        return [
            [
                'label'   => 'Documents',
                'modules' => array_keys($this->parseModules()),
            ],
            [
                'label'   => 'Account',
                'modules' => ['getQuote', 'getAccount', 'getUsage'],
            ],
            [
                'label'   => 'Other',
                'modules' => ['makeApiCall'],
            ],
        ];
    }

    /**
     * @return array<string, array{document: DocumentType, label: string, description: string, linesOcr: bool}>
     */
    private function parseModules(): array
    {
        return [
            'parseInvoice' => [
                'document'    => DocumentType::Invoice,
                'label'       => 'Parse an invoice',
                'description' => 'Parses an invoice (ZUGFeRD, Factur-X, XRechnung, EDIFACT, PDF or scan) and returns the invoice.v1 JSON. The call stops after 40 seconds.',
                'linesOcr'    => true,
            ],
            'parseCreditNote' => [
                'document'    => DocumentType::CreditNote,
                'label'       => 'Parse a credit note',
                'description' => 'Parses a credit note and returns the creditnote.v1 JSON. The call stops after 40 seconds.',
                'linesOcr'    => false,
            ],
            'parseDeliveryNote' => [
                'document'    => DocumentType::DeliveryNote,
                'label'       => 'Parse a delivery note',
                'description' => 'Parses a delivery note (Peppol despatch advice, DESADV, PDF or scan) and returns the deliverynote.v1 JSON. The call stops after 40 seconds.',
                'linesOcr'    => false,
            ],
            'parsePurchaseOrder' => [
                'document'    => DocumentType::PurchaseOrder,
                'label'       => 'Parse a purchase order',
                'description' => 'Parses a purchase order (UBL Order, PDF or scan) and returns the purchaseorder.v1 JSON. The call stops after 40 seconds.',
                'linesOcr'    => false,
            ],
            'parseBankStatement' => [
                'document'    => DocumentType::BankStatement,
                'label'       => 'Parse a bank statement',
                'description' => 'Parses a bank statement (camt, MT940, MT942, PDF or scan) and returns the bankstatement.v1 JSON. The call stops after 40 seconds.',
                'linesOcr'    => false,
            ],
            'parseCv' => [
                'document'    => DocumentType::Cv,
                'label'       => 'Parse a CV',
                'description' => 'Parses a CV and returns the cv.v1 JSON. Every CV is read by the AI. The call stops after 40 seconds.',
                'linesOcr'    => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function base(): array
    {
        return [
            'baseUrl' => 'https://parser-api.micro-ki.de/api/v1',
            'headers' => [
                'Authorization' => 'Bearer {{connection.apiKey}}',
                'Accept'        => 'application/json',
            ],
            'response' => [
                'error' => $this->errorHandler(),
            ],
            'log' => [
                'sanitize' => ['request.headers.authorization'],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function connectionParams(): array
    {
        return [[
            'name'     => 'apiKey',
            'type'     => 'password',
            'label'    => 'API key',
            'editable' => true,
            'required' => true,
            'help'     => 'Enter your API key without the `Bearer` prefix. For example, `pak_live_…` or the public test key `pak_test_X0001`. To create an account and get a live API key, see the [Starter Package](https://parser-api.micro-ki.de/starten) and the [Parser API KI+ API documentation](https://parser-api.micro-ki.de/doku).',
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionCommunication(): array
    {
        return [
            'url'    => 'https://parser-api.micro-ki.de/api/v1/account',
            'method' => 'GET',
            'headers' => [
                'Authorization' => 'Bearer {{parameters.apiKey}}',
            ],
            'response' => [
                'valid' => '{{statusCode == 200}}',
                'error' => $this->errorHandler(),
                'metadata' => [
                    'type'  => 'email',
                    'value' => '{{body.mail}}',
                ],
            ],
            'log' => [
                'sanitize' => ['request.headers.authorization'],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseParams(bool $linesOcr): array
    {
        $params = [
            $this->fileParam(),
            $this->fileNameParam(),
        ];
        if ($linesOcr) {
            $params[] = [
                'name'     => 'linesOcr',
                'type'     => 'boolean',
                'label'    => 'Read lines with OCR',
                'help'     => 'Reads line items from the visible page of a ZUGFeRD or Factur-X PDF whose XML has no line items. Sends `X-Parser-Lines: ocr` and charges the per-page rate.',
                'default'  => false,
                'advanced' => true,
            ];
        }
        $params[] = $this->forceParam(
            'Skips the cached result from the last 15 minutes and spends credits again. Sends `X-Parser-Force: 1`.',
        );
        $params[] = [
            'name'     => 'idempotencyKey',
            'type'     => 'text',
            'label'    => 'Idempotency key',
            'help'     => 'Optional unique key per file (`8` to `128` characters: letters, digits, `.`, `_`, `-`) to safely retry a request. If left empty, no `Idempotency-Key` header is sent and identical files are still cached for 15 minutes.',
            'advanced' => true,
        ];

        return $params;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCommunication(DocumentType $type, bool $linesOcr): array
    {
        $headers = [
            'Idempotency-Key' => '{{ifempty(parameters.idempotencyKey, omit)}}',
            'X-Parser-Force'  => "{{if(parameters.force, '1', omit)}}",
        ];
        if ($linesOcr) {
            $headers['X-Parser-Lines'] = "{{if(parameters.linesOcr, 'ocr', omit)}}";
        }

        return [
            'url'     => '/' . $type->pathSlug(),
            'method'  => 'POST',
            'type'    => 'multipart/form-data',
            'headers' => $headers,
            'body'    => [[
                'name'  => 'file',
                'value' => [
                    'value'   => '{{parameters.data}}',
                    'options' => [
                        'filename' => '{{parameters.fileName}}',
                    ],
                ],
            ]],
            'response' => [
                'output' => '{{body}}',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function quoteParams(): array
    {
        return [
            $this->typeParam(true),
            $this->fileParam(),
            $this->fileNameParam(),
            [
                'name'     => 'linesOcr',
                'type'     => 'boolean',
                'label'    => 'Read lines with OCR',
                'help'     => 'Includes OCR line-item extraction in the quote when **Document type** is `invoice`. Sends `X-Parser-Lines: ocr`.',
                'default'  => false,
                'advanced' => true,
            ],
            $this->forceParam(
                'Calculates the price for a fresh read instead of a cached result. Sends `X-Parser-Force: 1`. Getting a quote spends no credits.',
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function quoteCommunication(): array
    {
        return [
            'url'     => '/quote',
            'method'  => 'POST',
            'type'    => 'multipart/form-data',
            'headers' => [
                'X-Parser-Force' => "{{if(parameters.force, '1', omit)}}",
                'X-Parser-Lines' => "{{if(parameters.type == 'invoice' && parameters.linesOcr, 'ocr', omit)}}",
            ],
            'body' => [
                [
                    'name'  => 'file',
                    'value' => [
                        'value'   => '{{parameters.data}}',
                        'options' => [
                            'filename' => '{{parameters.fileName}}',
                        ],
                    ],
                ],
                [
                    'name'  => 'type',
                    'value' => '{{parameters.type}}',
                ],
            ],
            'response' => [
                'output' => '{{body}}',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function quoteInterface(): array
    {
        return [
            $this->textField('request_id', 'Request ID'),
            $this->integerField('needed', 'Credits needed'),
            $this->integerField('available', 'Credits available'),
            $this->integerField('billable_units', 'Billable units'),
            $this->textField('kind', 'Charge kind'),
            $this->integerField('page_count', 'Page count'),
            $this->textField('source_hint', 'Source hint'),
            [
                'name'  => 'pricing',
                'type'  => 'collection',
                'label' => 'Pricing',
                'spec'  => [
                    $this->textField('rule', 'Rule'),
                    $this->textField('label', 'Label'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function quoteSample(): array
    {
        $copy = new PricingCopy(1, 2, 2);

        return [
            'request_id'     => 'req_example',
            'needed'         => 1,
            'available'      => 100,
            'billable_units' => 1,
            'kind'           => 'file',
            'page_count'     => 0,
            'source_hint'    => 'xrechnung',
            'pricing'        => [
                'rule'  => PricingCopy::RULE_STRUCTURED,
                'label' => $copy->label(PricingCopy::RULE_STRUCTURED),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accountCommunication(): array
    {
        return [
            'url'      => '/account',
            'method'   => 'GET',
            'response' => [
                'output' => '{{body}}',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function accountInterface(): array
    {
        return [
            $this->textField('request_id', 'Request ID'),
            $this->integerField('credits', 'Credits'),
            $this->integerField('available', 'Credits available'),
            $this->integerField('alert_threshold', 'Alert threshold'),
            $this->boolField('alert_enabled', 'Alert enabled'),
            $this->textField('live_key_prefix', 'Live key prefix'),
            $this->textField('test_key_prefix', 'Test key prefix'),
            $this->emailField('mail', 'Email address'),
            $this->boolField('test', 'Test key'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accountSample(): array
    {
        return [
            'request_id'      => 'req_example',
            'credits'         => 100,
            'available'       => 100,
            'alert_threshold' => 20,
            'alert_enabled'   => true,
            'live_key_prefix' => 'pak_live_ab12',
            'test_key_prefix' => 'pak_test_cd34',
            'mail'            => 'konto@example.com',
            'test'            => false,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function usageParams(): array
    {
        return [
            $this->typeParam(false),
            [
                'name'    => 'limit',
                'type'    => 'uinteger',
                'label'   => 'Limit',
                'help'    => 'Maximum number of results to return and work with during one execution cycle. For information about setting limits, see [our Help Center](https://help.make.com/types-of-modules#b3ZEQ).',
                'default' => 10,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function usageCommunication(): array
    {
        return [
            'url'    => '/usage',
            'method' => 'GET',
            'qs'     => [
                'type' => '{{ifempty(parameters.type, omit)}}',
            ],
            'response' => [
                'iterate' => '{{body.entries}}',
                'output'  => '{{item}}',
                'limit'   => '{{parameters.limit}}',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function usageInterface(): array
    {
        return [
            $this->textField('request_id', 'Request ID'),
            $this->dateField('ts', 'Timestamp'),
            $this->textField('type', 'Document type'),
            $this->integerField('status', 'HTTP status'),
            $this->textField('source', 'Source'),
            $this->boolField('cache', 'From cache'),
            $this->integerField('billed', 'Credits billed'),
            $this->integerField('pages', 'Pages'),
            $this->textField('error_code', 'Error code'),
            $this->textField('fingerprint', 'Fingerprint'),
            $this->boolField('lines_ocr', 'Lines OCR'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function usageSample(): array
    {
        return [
            'request_id'  => 'req_earlier',
            'ts'          => '2026-11-04T14:00:00+01:00',
            'type'        => 'invoice',
            'status'      => 200,
            'source'      => 'xrechnung',
            'cache'       => false,
            'billed'      => 1,
            'pages'       => 0,
            'error_code'  => null,
            'fingerprint' => 'ab12cd34',
            'lines_ocr'   => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fileParam(): array
    {
        return [
            'name'     => 'data',
            'type'     => 'buffer',
            'label'    => 'File',
            'help'     => 'File content mapped from a previous module, for example Gmail or Google Drive.',
            'required' => true,
            'semantic' => 'file:data',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fileNameParam(): array
    {
        return [
            'name'     => 'fileName',
            'type'     => 'filename',
            'label'    => 'File name',
            'help'     => 'File name with extension. For example, `invoice.pdf`.',
            'required' => true,
            'semantic' => 'file:name',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function forceParam(string $help): array
    {
        return [
            'name'     => 'force',
            'type'     => 'boolean',
            'label'    => 'Force fresh read',
            'help'     => $help,
            'default'  => false,
            'advanced' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function typeParam(bool $required): array
    {
        $options = [];
        foreach ($this->parseModules() as $module) {
            $type      = $module['document'];
            $options[] = [
                'label' => $this->englishDocument($type),
                'value' => $type->pathSlug(),
            ];
        }

        return [
            'name'      => 'type',
            'type'      => 'select',
            'label'     => 'Document type',
            'help'      => $required
                ? 'Document type to calculate the price for. For example, `credit-note`.'
                : 'Filter usage entries by document type. If left empty, all document types are returned.',
            'required'  => $required,
            'mappable'  => false,
            'options'   => $options,
        ];
    }

    private function englishDocument(DocumentType $type): string
    {
        return match ($type) {
            DocumentType::Invoice       => 'Invoice',
            DocumentType::CreditNote    => 'Credit note',
            DocumentType::DeliveryNote  => 'Delivery note',
            DocumentType::PurchaseOrder => 'Purchase order',
            DocumentType::BankStatement => 'Bank statement',
            DocumentType::Cv            => 'CV',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(DocumentType $type): array
    {
        $schema = (new SchemaCatalog($this->apiDir))->get($type);
        $props  = $schema['properties'] ?? null;
        if (!is_array($props)) {
            throw new RuntimeException('Schema has no properties: ' . $type->value);
        }

        return $schema;
    }

    /**
     * @return array{de: array<string, string>, en: array<string, string>}
     */
    private function meanings(DocumentType $type): array
    {
        $de = SchemaFieldMeanings::for($type);
        $en = SchemaFieldMeaningsEn::for($type);
        foreach (ResponseEnvelopeFields::rows($type, 'de') as $row) {
            $de[$row['path']] = $row['meaning'];
        }
        foreach (ResponseEnvelopeFields::rows($type, 'en') as $row) {
            $en[$row['path']] = $row['meaning'];
        }
        $de['pricing.rule']  = 'structured, office oder pdf_ocr.';
        $en['pricing.rule']  = 'structured, office, or pdf_ocr.';
        $de['pricing.label'] = 'Derselbe Satz auf Deutsch.';
        $en['pricing.label'] = 'The same rate as a German sentence.';

        return ['de' => $de, 'en' => $en];
    }

    /**
     * @param array<string, mixed>                                      $schema
     * @param array{de: array<string, string>, en: array<string, string>} $meanings
     *
     * @return list<array<string, mixed>>
     */
    private function interfaceFromSchema(array $schema, array $meanings, string $prefix): array
    {
        $props = $schema['properties'] ?? null;
        if (!is_array($props)) {
            return [];
        }

        $out = [];
        foreach ($props as $name => $spec) {
            if (!is_string($name) || !is_array($spec)) {
                continue;
            }
            $path  = '' === $prefix ? $name : $prefix . '.' . $name;
            $out[] = $this->field($name, $path, $spec, $meanings);
            if ('schema' === $name && '' === $prefix) {
                foreach ($this->envelopeAfterSchema() as $extraName => $extraSpec) {
                    if (!isset($props[$extraName])) {
                        $out[] = $this->field($extraName, $extraName, $extraSpec, $meanings);
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Ledger fields the HTTP envelope adds. Same position as the public field table:
     * directly after schema.
     *
     * @return array<string, array<string, mixed>>
     */
    private function envelopeAfterSchema(): array
    {
        return [
            'source'     => ['type' => ['string', 'null']],
            'request_id' => ['type' => 'string'],
            'billed'     => ['type' => 'integer'],
            'available'  => ['type' => 'integer'],
            'pricing'    => [
                'type'       => 'object',
                'properties' => [
                    'rule'  => ['type' => 'string'],
                    'label' => ['type' => 'string'],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed>                                      $spec
     * @param array{de: array<string, string>, en: array<string, string>} $meanings
     *
     * @return array<string, mixed>
     */
    private function field(string $name, string $path, array $spec, array $meanings): array
    {
        $types       = $this->jsonTypes($spec);
        $meaningPath = in_array('array', $types, true) || isset($spec['items']) ? $path . '[]' : $path;
        $field       = [
            'name'  => $name,
            'type'  => $this->makeType($name, $path, $types, $spec),
            'label' => $this->label($name, $meaningPath, $meanings),
        ];

        if ('array' === $field['type']) {
            $items = $spec['items'] ?? null;
            if (is_array($items)) {
                $itemTypes = $this->jsonTypes($items);
                if (in_array('object', $itemTypes, true) || isset($items['properties'])) {
                    $field['spec'] = $this->interfaceFromSchema($items, $meanings, $path . '[]');
                } else {
                    $itemPath = $path . '[]';
                    $field['spec'] = [
                        'type'  => $this->scalarType($itemTypes),
                        'label' => $this->itemLabel($name),
                    ];
                }
            }
        } elseif ('collection' === $field['type']) {
            $field['spec'] = $this->interfaceFromSchema($spec, $meanings, $path);
        }

        $this->assertPlainLabel($meaningPath, $field['label']);

        return $field;
    }

    /**
     * Make's default classes stay in place. A type on the top level would
     * replace the 429 rate-limit retry and the 5xx connection retry.
     * 409 is a runtime error by default, so a busy account or a parse still
     * running has to be named as a rate-limit error. A wrong idempotency key
     * and a corrupt account are real failures and must not be retried.
     *
     * @return array<string, mixed>
     */
    private function errorHandler(): array
    {
        return [
            'message' => self::ERROR_MESSAGE,
            '409'     => [
                'message' => self::ERROR_MESSAGE,
                'type'    => "{{if(body.error.code == 'idempotency_key_conflict', 'RuntimeError', 'RateLimitError')}}",
            ],
            '500' => [
                'message' => self::ERROR_MESSAGE,
                'type'    => "{{if(body.error.code == 'account_corrupt' || body.error.code == 'capture_corrupt', 'RuntimeError', 'ConnectionError')}}",
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function universalParams(): array
    {
        $pair = [
            ['name' => 'key', 'type' => 'text', 'label' => 'Key'],
            ['name' => 'value', 'type' => 'text', 'label' => 'Value'],
        ];

        return [
            [
                'name'     => 'url',
                'type'     => 'text',
                'label'    => 'URL',
                'required' => true,
                'help'     => 'Enter a path relative to `https://parser-api.micro-ki.de`. For example, `/api/v1/account`.',
            ],
            [
                'name'     => 'method',
                'type'     => 'select',
                'label'    => 'Method',
                'required' => true,
                'default'  => 'GET',
                'options'  => [
                    ['label' => 'GET', 'value' => 'GET'],
                    ['label' => 'POST', 'value' => 'POST'],
                    ['label' => 'PUT', 'value' => 'PUT'],
                    ['label' => 'PATCH', 'value' => 'PATCH'],
                    ['label' => 'DELETE', 'value' => 'DELETE'],
                ],
            ],
            [
                'name'    => 'headers',
                'type'    => 'array',
                'label'   => 'Headers',
                'help'    => "You don't have to add authorization headers; we already did that for you.",
                'spec'    => $pair,
                'default' => [
                    ['key' => 'Content-Type', 'value' => 'application/json'],
                ],
            ],
            [
                'name'  => 'qs',
                'type'  => 'array',
                'label' => 'Query String',
                'spec'  => $pair,
            ],
            [
                'name'  => 'body',
                'type'  => 'any',
                'label' => 'Body',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function universalCommunication(): array
    {
        return [
            'url'     => 'https://parser-api.micro-ki.de/{{parameters.url}}',
            'method'  => '{{parameters.method}}',
            'headers' => [
                '{{...}}' => "{{toCollection(parameters.headers, 'key', 'value')}}",
            ],
            'qs' => [
                '{{...}}' => "{{toCollection(parameters.qs, 'key', 'value')}}",
            ],
            'body'     => '{{parameters.body}}',
            'type'     => 'text',
            'response' => [
                'output' => [
                    'body'       => '{{body}}',
                    'headers'    => '{{headers}}',
                    'statusCode' => '{{statusCode}}',
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function universalInterface(): array
    {
        return [
            ['name' => 'body', 'type' => 'any', 'label' => 'Body'],
            ['name' => 'headers', 'type' => 'collection', 'label' => 'Headers'],
            ['name' => 'statusCode', 'type' => 'number', 'label' => 'Status code'],
        ];
    }

    private function assertPlainLabel(string $path, string $label): void
    {
        if (str_contains($label, '·') || mb_strlen($label) > 48) {
            throw new RuntimeException($path . ' label breaks the Make rules: ' . $label);
        }
    }

    /**
     * @param array<string, mixed> $spec
     *
     * @return list<string>
     */
    private function jsonTypes(array $spec): array
    {
        $type = $spec['type'] ?? 'any';
        if (is_string($type)) {
            return [$type];
        }
        if (!is_array($type)) {
            return ['any'];
        }
        $out = [];
        foreach ($type as $item) {
            if (is_string($item) && 'null' !== $item) {
                $out[] = $item;
            }
        }

        return [] === $out ? ['any'] : $out;
    }

    /**
     * @param list<string>         $types
     * @param array<string, mixed> $spec
     */
    private function makeType(string $name, string $path, array $types, array $spec): string
    {
        if (in_array('array', $types, true) || isset($spec['items'])) {
            return 'array';
        }
        if (in_array('object', $types, true) || isset($spec['properties'])) {
            return 'collection';
        }

        return $this->scalarType($types);
    }

    /**
     * Document dates are calendar dates without time (YYYY-MM-DD). Make's Date
     * parameter rule requires date-only strings in the Interface to use type
     * "text" so midnight timezone conversion cannot shift the date.
     *
     * @param list<string> $types
     */
    private function scalarType(array $types): string
    {
        if (in_array('boolean', $types, true)) {
            return 'boolean';
        }
        if (in_array('number', $types, true)) {
            return 'number';
        }
        if (in_array('integer', $types, true)) {
            return 'integer';
        }

        return 'text';
    }

    /**
     * @param array{de: array<string, string>, en: array<string, string>} $meanings
     */
    private function label(string $name, string $path, array $meanings): string
    {
        if (isset(self::SHORT_LABELS[$path])) {
            return self::SHORT_LABELS[$path];
        }

        $sentence = $this->firstSentence($meanings['en'][$path] ?? '');
        if ($this->isPlainLabel($sentence)) {
            return $sentence;
        }
        $cut = preg_split('/\s+\(|\s*;|:\s+/u', $sentence, 2);
        if (is_array($cut) && $this->isPlainLabel(rtrim($cut[0])) && mb_strlen(rtrim($cut[0])) >= 8) {
            return rtrim($cut[0]);
        }

        return $this->humanize($name);
    }

    /**
     * A mapping pill is a 1–3 word noun phrase without articles or prepositions.
     */
    private function isPlainLabel(string $sentence): bool
    {
        if ('' === $sentence || mb_strlen($sentence) > 28 || count(preg_split('/\s+/u', $sentence) ?: []) > 3) {
            return false;
        }
        if (1 === preg_match('/^(Always|Immer|\d)/u', $sentence)) {
            return false;
        }
        if (str_contains($sentence, ':') || str_contains($sentence, ';') || str_contains($sentence, ',') || 1 === preg_match('/["\'„“”`]/u', $sentence) || str_contains($sentence, 'E-mail')) {
            return false;
        }
        if (1 === preg_match('/\b(a|an|the|of|with|or|and|this|that|already|from|by|in|on|for|if|when|only|otherwise)\b/iu', $sentence)) {
            return false;
        }

        return true;
    }

    private function firstSentence(string $text): string
    {
        $text   = trim($text);
        $length = mb_strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1);
            if ('.' !== $char && '!' !== $char && '?' !== $char) {
                continue;
            }
            $rest = mb_substr($text, $i + 1);
            if ('' === $rest) {
                return rtrim(mb_substr($text, 0, $i));
            }
            if (1 === preg_match('/(?:^|[\s(])(?:\p{L}{1,4}\.)*\p{L}{1,4}$/u', mb_substr($text, 0, $i))) {
                continue;
            }
            if (1 === preg_match('/^\s+(?:\p{Lu}|\d)/u', $rest)) {
                return rtrim(mb_substr($text, 0, $i));
            }
        }

        return rtrim($text, '.');
    }

    private function humanize(string $name): string
    {
        $parts = explode('_', $name);
        $words = [];
        foreach ($parts as $index => $part) {
            if (isset(self::ACRONYMS[$part])) {
                $words[] = self::ACRONYMS[$part];
                continue;
            }
            $words[] = 0 === $index
                ? mb_convert_case($part, MB_CASE_TITLE, 'UTF-8')
                : mb_strtolower($part);
        }

        return implode(' ', $words);
    }

    private function itemLabel(string $arrayName): string
    {
        return match ($arrayName) {
            'notes'        => 'Note',
            'warnings'     => 'Warning',
            'highlights'   => 'Highlight',
            'links'        => 'Link',
            'adjustments'  => 'Adjustment',
            'transactions' => 'Transaction',
            default        => 'Item',
        };
    }

    /**
     * @param list<array<string, mixed>> $interface
     */
    private function assertCoversSchema(string $moduleId, DocumentType $type, array $interface): void
    {
        $expected = [];
        foreach ((new SchemaCatalog($this->apiDir))->fields($type) as $row) {
            $expected[$row['path']] = true;
        }
        foreach (['source', 'request_id', 'billed', 'available', 'pricing', 'pricing.rule', 'pricing.label'] as $path) {
            $expected[$path] = true;
        }

        $actual = [];
        $this->collectPaths($interface, '', $actual);
        $missing = array_keys(array_diff_key($expected, $actual));
        $extra   = array_keys(array_diff_key($actual, $expected));
        if ([] !== $missing || [] !== $extra) {
            throw new RuntimeException(
                $moduleId . ' interface drift. Missing: ' . implode(', ', $missing) . '. Extra: ' . implode(', ', $extra),
            );
        }

        $flat = [];
        $this->indexFields($interface, '', $flat);
        $amountPath = match ($type) {
            DocumentType::Invoice, DocumentType::CreditNote => 'totals.due',
            DocumentType::PurchaseOrder => 'totals.gross',
            DocumentType::BankStatement => 'balances.closing',
            DocumentType::DeliveryNote  => 'lines.quantity',
            DocumentType::Cv            => null,
        };
        if (null !== $amountPath && 'number' !== ($flat[$amountPath]['type'] ?? '')) {
            throw new RuntimeException($moduleId . ' expected number at ' . $amountPath . '.');
        }
        $datePath = match ($type) {
            DocumentType::Invoice       => 'invoice.date',
            DocumentType::CreditNote    => 'credit_note.date',
            DocumentType::DeliveryNote  => 'delivery.date',
            DocumentType::PurchaseOrder => 'order.date',
            DocumentType::BankStatement => 'statement.date',
            DocumentType::Cv            => 'person.date_of_birth',
        };
        if ('text' !== ($flat[$datePath]['type'] ?? '')) {
            throw new RuntimeException($moduleId . ' expected text for date-only field at ' . $datePath . '.');
        }
        if ('parseInvoice' === $moduleId) {
            $this->assertLabel($flat, 'invoice.number', 'Invoice number');
            $this->assertLabel($flat, 'lines', 'Lines');
            $this->assertLabel($flat, 'source', 'Source');
            $this->assertLabel($flat, 'invoice.date', 'Invoice date');
            $clipped = $flat['totals.vat_breakdown.exemption_reason']['label'] ?? '';
            if (str_contains($clipped, '(e.g') || str_contains($clipped, '(z.')) {
                throw new RuntimeException('Abbreviation was treated as the end of a label: ' . $clipped);
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $flat
     */
    private function assertLabel(array $flat, string $path, string $expected): void
    {
        $actual = $flat[$path]['label'] ?? '';
        if ($actual !== $expected) {
            throw new RuntimeException($path . ' label is "' . $actual . '", expected "' . $expected . '".');
        }
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param array<string, true>        $out
     */
    private function collectPaths(array $fields, string $prefix, array &$out): void
    {
        foreach ($fields as $field) {
            $name = $field['name'];
            $path = '' === $prefix ? $name : $prefix . '.' . $name;
            if ('array' === $field['type']) {
                $path .= '[]';
            }
            $out[$path] = true;
            if (isset($field['spec']) && is_array($field['spec']) && array_is_list($field['spec'])) {
                $next = 'array' === $field['type'] ? $path : ('' === $prefix ? $name : $prefix . '.' . $name);
                $this->collectPaths($field['spec'], $next, $out);
            }
        }
    }

    /**
     * @param list<array<string, mixed>>     $fields
     * @param array<string, array<string, mixed>> $out
     */
    private function indexFields(array $fields, string $prefix, array &$out): void
    {
        foreach ($fields as $field) {
            $path       = '' === $prefix ? $field['name'] : $prefix . '.' . $field['name'];
            $out[$path] = $field;
            if (isset($field['spec']) && is_array($field['spec']) && array_is_list($field['spec'])) {
                $this->indexFields($field['spec'], $path, $out);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseSample(DocumentType $type): array
    {
        $fixtures = new TestFixtures();
        $copy     = new PricingCopy(1, 2, 2);
        $rule     = $fixtures->pricingRule($type);
        $body     = $fixtures->for($type);
        $body['request_id'] = 'req_example';
        $body['pricing']    = [
            'rule'  => $rule,
            'label' => $copy->label($rule),
        ];
        $body['billed']    = 0;
        $body['available'] = 100;

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    private function textField(string $name, string $label): array
    {
        return $this->manualField($name, 'text', $label);
    }

    /**
     * @return array<string, mixed>
     */
    private function integerField(string $name, string $label): array
    {
        return $this->manualField($name, 'integer', $label);
    }

    /**
     * @return array<string, mixed>
     */
    private function boolField(string $name, string $label): array
    {
        return $this->manualField($name, 'boolean', $label);
    }

    /**
     * @return array<string, mixed>
     */
    private function dateField(string $name, string $label): array
    {
        return $this->manualField($name, 'date', $label);
    }

    /**
     * @return array<string, mixed>
     */
    private function emailField(string $name, string $label): array
    {
        return $this->manualField($name, 'email', $label);
    }

    /**
     * @return array<string, mixed>
     */
    private function manualField(string $name, string $type, string $label): array
    {
        return [
            'name'  => $name,
            'type'  => $type,
            'label' => $label,
        ];
    }

    private function appReadme(): string
    {
        return <<<'MD'
# Parser API KI+

[Parser API KI+](https://parser-api.micro-ki.de) extracts structured data from business documents and returns a fixed, predictable JSON schema (`invoice.v1`, `creditnote.v1`, `deliverynote.v1`, `purchaseorder.v1`, `bankstatement.v1`, `cv.v1`).

- **Structured documents** (ZUGFeRD 1.0 & 2.x, Factur-X, XRechnung CII/UBL, Peppol BIS, EDIFACT INVOIC/DESADV, camt.052/053/054, SWIFT MT940/MT942) are parsed deterministically without AI at the single-file rate.
- **Office documents and plain text** (`DOCX`, `ODT`, `TXT`) and **PDFs, scans, and photos** (`PDF`, `JPG`, `PNG`, `WEBP`, `TIFF`, `HEIC`) are extracted via OCR and AI hosted in the EU.

See the full [API documentation](https://parser-api.micro-ki.de/doku) for field tables, limits, and error codes.

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

| Module | Endpoint | Output Schema |
| --- | --- | --- |
| **Parse an invoice** | `POST /api/v1/invoice` | `invoice.v1` |
| **Parse a credit note** | `POST /api/v1/credit-note` | `creditnote.v1` |
| **Parse a delivery note** | `POST /api/v1/delivery-note` | `deliverynote.v1` |
| **Parse a purchase order** | `POST /api/v1/purchase-order` | `purchaseorder.v1` |
| **Parse a bank statement** | `POST /api/v1/bank-statement` | `bankstatement.v1` |
| **Parse a CV** | `POST /api/v1/cv` | `cv.v1` |

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

## 40-Second Timeout Limit

Make enforces a hard platform timeout of **40 seconds** for all Custom App HTTP calls:
- **95% of everyday documents**—all structured files (`< 2 s`) and normal 1–4 page PDFs, scans, and photos (`5–20 s`)—finish well within 40 seconds.
- **Very long multi-page scans** (such as a 15-page scanned bank statement requiring `> 40 s` of OCR) should use the **Make HTTP Blueprint** available in your [Parser API KI+ account](https://parser-api.micro-ki.de/account/anbindung), which uses Make's native HTTP module with a **280-second timeout**.

MD;
    }
}

$apiDir = getenv('PARSER_API_DIR');
if (!is_string($apiDir) || '' === $apiDir) {
    $apiDir = dirname(__DIR__, 2) . '/parser-api_micro-ki_de';
}
$apiDir   = rtrim($apiDir, '/');
$autoload = $apiDir . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Parser API checkout not found: {$autoload}\nSet PARSER_API_DIR.\n");
    exit(1);
}
require $autoload;

$check = in_array('--check', $argv, true);
exit((new MakeAppBuilder($apiDir))->run($check));
