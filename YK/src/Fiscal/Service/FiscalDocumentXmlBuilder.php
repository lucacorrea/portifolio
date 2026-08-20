<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

use App\Fiscal\Tax\Decimal;
use App\Fiscal\Tax\GtinValidator;
use App\Fiscal\Tax\IbsCbsCalculation;
use InvalidArgumentException;
use NFePHP\NFe\Make;
use stdClass;

final class FiscalDocumentXmlBuilder
{
    private readonly FiscalTechnicalResponsible $technicalResponsible;

    public function __construct(
        ?FiscalTechnicalResponsible $technicalResponsible = null
    ) {
        $this->technicalResponsible =
            $technicalResponsible
            ?? FiscalTechnicalResponsible::fromEnvironment();
    }

    private const UF_CODES = [
        'RO' => 11,
        'AC' => 12,
        'AM' => 13,
        'RR' => 14,
        'PA' => 15,
        'AP' => 16,
        'TO' => 17,
        'MA' => 21,
        'PI' => 22,
        'CE' => 23,
        'RN' => 24,
        'PB' => 25,
        'PE' => 26,
        'AL' => 27,
        'SE' => 28,
        'BA' => 29,
        'MG' => 31,
        'ES' => 32,
        'RJ' => 33,
        'SP' => 35,
        'PR' => 41,
        'SC' => 42,
        'RS' => 43,
        'MS' => 50,
        'MT' => 51,
        'GO' => 52,
        'DF' => 53,
    ];
    private const PAYMENT_CODES = [
        'dinheiro' => '01',
        'cheque' => '02',
        'cartao_credito' => '03',
        'cartao_debito' => '04',
        'boleto' => '15',
        'pix' => '17',
        'transferencia' => '18',
        'outro' => '99',
    ];

    /** @param array<string,mixed> $document @return array{xml:string,key:string} */
    public function build(array $document): array
    {
        $snapshot = json_decode((string) ($document['snapshot_json'] ?? ''), true);
        if (!is_array($snapshot)) {
            throw new InvalidArgumentException('Snapshot fiscal inválido.');
        }
        $company = $snapshot['company'] ?? [];
        $customer = $snapshot['customer'] ?? [];
        $items = $snapshot['items'] ?? [];
        $fiscal = $snapshot['fiscal'] ?? [];
        $operation = $snapshot['operation'] ?? [];

        $taxApplicability = is_array($snapshot['tax_applicability'] ?? null)
            ? $snapshot['tax_applicability']
            : [];

        $ibsCbsApplicability = is_array($taxApplicability['ibs_cbs'] ?? null)
            ? $taxApplicability['ibs_cbs']
            : [];

        $requiresIbsCbs = (bool) ($ibsCbsApplicability['required'] ?? false);

        if (!is_array($company) || !is_array($customer) || !is_array($items) || $items === []) {
            throw new InvalidArgumentException('O documento fiscal não possui dados suficientes para gerar o XML.');
        }

        $model = (string) ($document['modelo'] ?? $fiscal['model'] ?? '');
        $environment = (string) ($document['ambiente'] ?? $fiscal['environment'] ?? '');
        $uf = strtoupper((string) ($company['endereco_uf'] ?? ''));
        $cUf = self::UF_CODES[$uf] ?? null;
        if ($cUf === null) {
            throw new InvalidArgumentException('UF do emitente inválida para emissão fiscal.');
        }

        $make = new Make('PL_010_V1.30');
        $ibsCbsTotals = null;
        $std = $this->std(['Id' => null, 'versao' => '4.00']);
        $make->taginfNFe($std);
        $make->tagide($this->std([
            'cUF' => $cUf,
            'cNF' => (string) ($document['cnf'] ?? $fiscal['cnf'] ?? ''),
            'natOp' => (string) ($operation['nature'] ?? 'VENDA DE MERCADORIA'),
            'mod' => $model,
            'serie' => (int) $document['serie'],
            'nNF' => (int) $document['numero'],
            'dhEmi' => $this->dateTime((string) ($fiscal['issued_at'] ?? $snapshot['captured_at'] ?? '')),
            'tpNF' => 1,
            'idDest' => (int) ($operation['destination'] ?? 1),
            'cMunFG' => (string) $company['codigo_municipio_ibge'],
            'tpImp' => $model === '65' ? 4 : 1,
            'tpEmis' => 1,
            'tpAmb' => $environment === 'producao' ? 1 : 2,
            'finNFe' => 1,
            'indFinal' => (int) ($operation['final_consumer'] ?? 1),
            'indPres' => (int) ($operation['presence'] ?? ($model === '65' ? 1 : 9)),
            'procEmi' => 0,
            'verProc' => 'OSMais 1.0',
        ]));
        $make->tagEmit($this->std([
            'CNPJ' => $this->digits((string) $company['documento']),
            'xNome' => $company['razao_social'],
            'xFant' => $company['nome_fantasia'] ?? null,
            'IE' => $company['inscricao_estadual'],
            'IM' => $company['inscricao_municipal'] ?? null,
            'CNAE' => $company['cnae_principal'] ?? null,
            'CRT' => (int) $company['crt'],
        ]));
        $make->tagenderEmit($this->std([
            'xLgr' => $company['endereco_logradouro'],
            'nro' => $company['endereco_numero'],
            'xCpl' => $company['endereco_complemento'] ?? null,
            'xBairro' => $company['endereco_bairro'],
            'cMun' => $company['codigo_municipio_ibge'],
            'xMun' => $company['endereco_cidade'],
            'UF' => $uf,
            'CEP' => $this->digits((string) $company['endereco_cep']),
            'cPais' => '1058',
            'xPais' => 'BRASIL',
            'fone' => $this->digits((string) ($company['telefone'] ?? '')) ?: null,
        ]));

        $technicalResponsible =
            $this->technicalResponsible
                ->toNfePhpData();

        $make->taginfRespTec($this->std([
            'CNPJ' => $technicalResponsible['CNPJ'],
            'xContato' => $technicalResponsible['xContato'],
            'email' => $technicalResponsible['email'],
            'fone' => $technicalResponsible['fone'],
            'idCSRT' => $technicalResponsible['idCSRT'],
            'CSRT' => $technicalResponsible['CSRT'],
        ]));

        $this->addCustomer($make, $customer, $model);

        foreach (array_values($items) as $offset => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Item fiscal inválido.');
            }
            $calculation = $this->addItem(
                $make,
                $item,
                $offset + 1,
                (int) $company['crt'],
                $requiresIbsCbs
            );
            if ($calculation !== null) {
                $ibsCbsTotals ??= ['base'=>0, 'uf'=>0, 'city'=>0, 'ibs'=>0, 'cbs'=>0];
                foreach ($ibsCbsTotals as $field => $unused) {
                    $ibsCbsTotals[$field] += Decimal::moneyToCents($calculation[$field]);
                }
            }
        }
        $make->tagICMSTot(new stdClass());
        if ($ibsCbsTotals !== null) {
            $make->tagIBSCBSTot($this->std([
                'vBCIBSCBS' => Decimal::formatCents($ibsCbsTotals['base']),
                'gIBS_vIBS' => Decimal::formatCents($ibsCbsTotals['ibs']),
                'gIBSUF_vDif' => '0.00',
                'gIBSUF_vDevTrib' => '0.00',
                'gIBSUF_vIBSUF' => Decimal::formatCents($ibsCbsTotals['uf']),
                'gIBSMun_vDif' => '0.00',
                'gIBSMun_vDevTrib' => '0.00',
                'gIBSMun_vIBSMun' => Decimal::formatCents($ibsCbsTotals['city']),
                'gCBS_vDif' => '0.00',
                'gCBS_vDevTrib' => '0.00',
                'gCBS_vCBS' => Decimal::formatCents($ibsCbsTotals['cbs']),
                'gIBS_vCredPres' => '0.00',
                'gIBS_vCredPresCondSus' => '0.00',
                'gCBS_vCredPres' => '0.00',
                'gCBS_vCredPresCondSus' => '0.00',
            ]));
        }
        $make->tagtransp($this->std(['modFrete' => 9]));
        $this->addPayments($make, is_array($snapshot['payments'] ?? null) ? $snapshot['payments'] : [], (string) $snapshot['totals']['invoice']);
        $services = is_array($snapshot['services'] ?? null) ? $snapshot['services'] : [];
        $note = 'Documento referente à ' . (string) ($snapshot['service_order']['number'] ?? 'OS');
        if ($services !== []) {
            $note .= '. Serviços da OS devem ser emitidos em NFS-e separada e vinculada.';
        }
        $make->taginfAdic($this->std(['infCpl' => $note]));

        $xml = $make->getXML();
        $errors = $make->getErrors();
        if ($errors !== []) {
            throw new InvalidArgumentException('O XML fiscal não passou na validação estrutural: ' . implode('; ', array_slice($errors, 0, 3)));
        }
        $key = $make->getChave();
        if (preg_match('/^\d{44}$/', $key) !== 1) {
            throw new InvalidArgumentException('A chave de acesso fiscal não pôde ser gerada.');
        }
        return ['xml' => $xml, 'key' => $key];
    }

    /** @param array<string,mixed> $customer */
    private function addCustomer(
        Make $make,
        array $customer,
        string $model
    ): void {
        $document = $this->digits(
            (string) ($customer['cliente_documento'] ?? '')
        );

        /*
         * NFC-e sem consumidor identificado:
         * não cria grupo dest/enderDest.
         */
        if (
            $model === '65'
            && $document === ''
        ) {
            return;
        }

        $indicatorKey =
            (string) (
                $customer['indicador_ie']
                ?? 'nao_contribuinte'
            );

        $indicator = [
            'contribuinte' => 1,
            'isento' => 2,
            'nao_contribuinte' => 9,
        ][$indicatorKey] ?? 9;

        /*
         * IE só acompanha o destinatário quando ele estiver
         * classificado como contribuinte.
         */
        $stateRegistration =
            $indicator === 1
                ? trim(
                    (string) (
                        $customer['inscricao_estadual']
                        ?? ''
                    )
                )
                : '';

        $make->tagdest(
            $this->std([
                strlen($document) === 14
                    ? 'CNPJ'
                    : 'CPF'
                    => $document,

                'xNome' =>
                    trim(
                        (string) (
                            $customer['cliente_nome']
                            ?? ''
                        )
                    ),

                'indIEDest' =>
                    $indicator,

                'IE' =>
                    $stateRegistration !== ''
                        ? $stateRegistration
                        : null,

                'email' =>
                    trim(
                        (string) (
                            $customer['cliente_email']
                            ?? ''
                        )
                    ) ?: null,
            ])
        );

        /*
        |--------------------------------------------------------------------------
        | Endereço do destinatário
        |--------------------------------------------------------------------------
        |
        | NF-e 55:
        | sempre gera enderDest; o Service já validou os campos.
        |
        | NFC-e 65:
        | só gera enderDest quando o endereço estiver completo e
        | estruturalmente válido. Endereço parcial não derruba a NFC-e.
        |
        */
        $includeAddress =
            $model === '55'
            || $this->hasUsableOptionalNfceAddress(
                $customer
            );

        if (!$includeAddress) {
            return;
        }

        $cep = $this->digits(
            (string) (
                $customer['cep']
                ?? ''
            )
        );

        $make->tagenderDest(
            $this->std([
                'xLgr' =>
                    trim(
                        (string) (
                            $customer['endereco']
                            ?? ''
                        )
                    ),

                'nro' =>
                    trim(
                        (string) (
                            $customer['cliente_numero']
                            ?? ''
                        )
                    ),

                'xCpl' =>
                    trim(
                        (string) (
                            $customer['complemento']
                            ?? ''
                        )
                    ) ?: null,

                'xBairro' =>
                    trim(
                        (string) (
                            $customer['bairro']
                            ?? ''
                        )
                    ),

                'cMun' =>
                    trim(
                        (string) (
                            $customer['cliente_codigo_municipio']
                            ?? ''
                        )
                    ),

                'xMun' =>
                    trim(
                        (string) (
                            $customer['cidade']
                            ?? ''
                        )
                    ),

                'UF' =>
                    strtoupper(
                        trim(
                            (string) (
                                $customer['uf']
                                ?? ''
                            )
                        )
                    ),

                'CEP' =>
                    $cep !== ''
                        ? $cep
                        : null,

                'cPais' =>
                    '1058',

                'xPais' =>
                    'BRASIL',
            ])
        );
    }

    /**
     * Para NFC-e o endereço é opcional.
     *
     * Se estiver parcial ou inconsistente, ele é omitido do XML em vez
     * de impedir a emissão. Quando estiver completo e válido, é enviado.
     *
     * @param array<string,mixed> $customer
     */
    private function hasUsableOptionalNfceAddress(
        array $customer
    ): bool {
        foreach ([
            'endereco',
            'cliente_numero',
            'bairro',
            'cidade',
            'uf',
            'cliente_codigo_municipio',
        ] as $field) {
            if (
                trim(
                    (string) (
                        $customer[$field]
                        ?? ''
                    )
                ) === ''
            ) {
                return false;
            }
        }

        $state = strtoupper(
            trim(
                (string) (
                    $customer['uf']
                    ?? ''
                )
            )
        );

        if (
            preg_match(
                '/^[A-Z]{2}$/',
                $state
            ) !== 1
        ) {
            return false;
        }

        if (
            !FiscalConfigurationService::isValidIbgeCityCode(
                (string) (
                    $customer['cliente_codigo_municipio']
                    ?? ''
                ),
                $state
            )
        ) {
            return false;
        }

        $rawCep = trim(
            (string) (
                $customer['cep']
                ?? ''
            )
        );

        if ($rawCep !== '') {
            $cep = $this->digits(
                $rawCep
            );

            if (
                preg_match(
                    '/^\d{8}$/',
                    $cep
                ) !== 1
            ) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $item @return array<string,string>|null */
    private function addItem(
        Make $make,
        array $item,
        int $number,
        int $crt,
        bool $requiresIbsCbs
    ): ?array
    {
        $subtotalCents = Decimal::moneyToCents((string) $item['subtotal']);
        $discountCents = Decimal::moneyToCents((string) ($item['desconto'] ?? '0'));
        $subtotal = Decimal::formatCents($subtotalCents);
        $discount = Decimal::formatCents($discountCents);
        $gross = Decimal::formatCents($subtotalCents + $discountCents);
        $quantity = Decimal::normalizeUnsigned((string) $item['quantidade'], 4, 'quantidade');
        $unit = Decimal::normalizeUnsigned((string) $item['valor_unitario'], 10, 'valor unitário');
        $gtin = GtinValidator::normalize((string) ($item['gtin_tributavel'] ?? ''));
        $make->tagprod($this->std([
            'item' => $number,
            'cProd' => (string) ($item['codigo'] ?? $item['produto_id']),
            'cEAN' => $gtin,
            'xProd' => (string) $item['descricao'],
            'NCM' => (string) $item['ncm'],
            'CEST' => $item['cest'] ?: null,
            'CFOP' => (string) $item['cfop_padrao'],
            'uCom' => (string) $item['unidade'],
            'qCom' => $quantity,
            'vUnCom' => $unit,
            'vProd' => $gross,
            'cEANTrib' => $gtin,
            'uTrib' => (string) $item['unidade_tributavel'],
            'qTrib' => $quantity,
            'vUnTrib' => $unit,
            'vDesc' => $discountCents > 0 ? $discount : null,
            'indTot' => 1,
        ]));
        $make->tagimposto($this->std(['item' => $number, 'vTotTrib' => null]));
        if (in_array($crt, [1, 2, 4], true)) {
            $csosn = (string) ($item['csosn'] ?? '');
            if (!in_array($csosn, ['102', '103', '300', '400'], true)) {
                throw new InvalidArgumentException('O CSOSN ' . $csosn . ' exige uma regra tributária específica antes da emissão.');
            }
            $make->tagICMSSN($this->std(['item' => $number, 'orig' => $item['origem_mercadoria'], 'CSOSN' => $csosn]));
        } else {
            $cst = $this->normalIcmsCst($item);
            if (!in_array($cst, ['00', '40', '41', '50'], true)) {
                throw new InvalidArgumentException('O CST ICMS ' . $cst . ' exige uma regra tributária específica antes da emissão.');
            }
            $rate = Decimal::rateToUnits((string) ($item['aliquota_icms'] ?? '0'));
            $base = $cst === '00' ? $subtotal : '0.00';
            $baseCents = $cst === '00' ? $subtotalCents : 0;
            $make->tagICMS($this->std([
                'item' => $number,
                'orig' => $item['origem_mercadoria'],
                'CST' => $cst,
                'modBC' => 3,
                'vBC' => $base,
                'pICMS' => Decimal::formatRate($rate),
                'vICMS' => Decimal::formatCents(Decimal::taxCents($baseCents, $rate)),
            ]));
        }
        $this->addPisCofins($make, $item, $number, $subtotal, $crt);

        if (!isset($item['ibs_cbs_rule'])) {
            if ($requiresIbsCbs) {
                throw new InvalidArgumentException(
                    'IBS/CBS é obrigatório nesta emissão, mas o item "'
                    . (string) ($item['descricao'] ?? $item['codigo'] ?? 'sem descrição')
                    . '" não possui regra tributária IBS/CBS resolvida.'
                );
            }

            return null;
        }

        $calculation = (new IbsCbsCalculation())->calculate($item);
        $make->tagIBSCBS($this->std([
            'item'=>$number,
            'CST'=>(string) $item['cst_ibs_cbs'],
            'cClassTrib'=>(string) $item['classificacao_tributaria_ibs_cbs'],
            'vBC'=>$calculation['base'],
            'vIBS'=>$calculation['ibs'],
            'gIBSUF_pIBSUF'=>$calculation['ibs_uf_rate'],
            'gIBSUF_vIBSUF'=>$calculation['ibs_uf'],
            'gIBSMun_pIBSMun'=>$calculation['ibs_city_rate'],
            'gIBSMun_vIBSMun'=>$calculation['ibs_city'],
            'gCBS_pCBS'=>$calculation['cbs_rate'],
            'gCBS_vCBS'=>$calculation['cbs'],
        ]));
        return [
            'base'=>$calculation['base'], 'uf'=>$calculation['ibs_uf'],
            'city'=>$calculation['ibs_city'], 'ibs'=>$calculation['ibs'], 'cbs'=>$calculation['cbs'],
        ];
    }

    /** @param array<string,mixed> $item */
    private function addPisCofins(
        Make $make,
        array $item,
        int $number,
        string $base,
        int $crt
    ): void {
        $baseCents = Decimal::moneyToCents($base);

        $pisCst = trim((string) ($item['cst_pis'] ?? ''));
        $cofinsCst = trim((string) ($item['cst_cofins'] ?? ''));

        $pis = [
            'item' => $number,
            'CST' => $pisCst,
        ];

        if (in_array($pisCst, ['01', '02'], true)) {
            $pisRate = Decimal::rateToUnits(
                (string) ($item['aliquota_pis'] ?? '0')
            );

            $pis += [
                'vBC' => $base,
                'pPIS' => Decimal::formatRate($pisRate),
                'vPIS' => Decimal::formatCents(
                    Decimal::taxCents($baseCents, $pisRate)
                ),
            ];
        } elseif (in_array($pisCst, ['04', '05', '06', '07', '08', '09'], true)) {
            // PISNT: o NFePHP gera somente o CST.
        } elseif ($crt === 1 && $pisCst === '99') {
            $pis += [
                'qBCProd' => '0.0000',
                'vAliqProd' => '0.0000',
                'vPIS' => '0.00',
            ];
        } else {
            throw new InvalidArgumentException(
                'O CST PIS '
                . ($pisCst !== '' ? $pisCst : '(vazio)')
                . ' não possui regra segura implementada para o CRT ' . $crt . '.'
            );
        }

        $make->tagPIS($this->std($pis));

        $cofins = [
            'item' => $number,
            'CST' => $cofinsCst,
        ];

        if (in_array($cofinsCst, ['01', '02'], true)) {
            $cofinsRate = Decimal::rateToUnits(
                (string) ($item['aliquota_cofins'] ?? '0')
            );

            $cofins += [
                'vBC' => $base,
                'pCOFINS' => Decimal::formatRate($cofinsRate),
                'vCOFINS' => Decimal::formatCents(
                    Decimal::taxCents($baseCents, $cofinsRate)
                ),
            ];
        } elseif (in_array($cofinsCst, ['04', '05', '06', '07', '08', '09'], true)) {
            // COFINSNT: o NFePHP gera somente o CST.
        } elseif ($crt === 1 && $cofinsCst === '99') {
            $cofins += [
                'qBCProd' => '0.0000',
                'vAliqProd' => '0.0000',
                'vCOFINS' => '0.00',
            ];
        } else {
            throw new InvalidArgumentException(
                'O CST COFINS '
                . ($cofinsCst !== '' ? $cofinsCst : '(vazio)')
                . ' não possui regra segura implementada para o CRT ' . $crt . '.'
            );
        }

        $make->tagCOFINS($this->std($cofins));
    }

    /** @param array<int,mixed> $payments */
    private function addPayments(Make $make, array $payments, string $invoiceTotal): void
    {
        $make->tagpag($this->std(['vTroco' => null]));
        $remaining = $this->cents($invoiceTotal);
        foreach ($payments as $payment) {
            if (!is_array($payment) || $remaining <= 0) {
                break;
            }
            $allocated = min($remaining, $this->cents((string) $payment['valor']));
            if ($allocated <= 0) {
                continue;
            }
            $method = (string) $payment['forma_pagamento'];
            $data = [
                'indPag' => 0,
                'tPag' => self::PAYMENT_CODES[$method] ?? '99',
                'xPag' => $method === 'outro' ? 'Outro' : null,
                'vPag' => $this->formatCents($allocated)
            ];
            if (in_array($method, ['cartao_credito', 'cartao_debito'], true)) {
                $data['tpIntegra'] = 2;
            }
            $make->tagdetPag($this->std($data));
            $remaining -= $allocated;
        }
        if ($remaining > 0) {
            $make->tagdetPag($this->std([
                'indPag' => 1,
                'tPag' => '91',
                'vPag' => $this->formatCents($remaining),
            ]));
        }
    }

    /** @param array<string,mixed> $data */
    private function std(array $data): stdClass
    {
        return (object) $data;
    }
    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function cents(string $value): int
    {
        return Decimal::moneyToCents($value);
    }

    private function formatCents(int $value): string
    {
        return Decimal::formatCents($value);
    }

    /** @param array<string,mixed> $item */
    private function normalIcmsCst(array $item): string
    {
        $cst = trim((string) ($item['cst_icms'] ?? ''));
        $origin = (string) ($item['origem_mercadoria'] ?? '');
        if (preg_match('/^\d{3}$/', $cst) === 1 && $cst[0] === $origin) {
            return substr($cst, 1);
        }
        return $cst;
    }

    private function dateTime(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $value) !== 1) {
            throw new InvalidArgumentException('Data de emissão inválida no snapshot fiscal.');
        }
        return $value;
    }
}
