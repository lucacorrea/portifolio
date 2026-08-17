<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

use App\Fiscal\Tax\Decimal;
use App\Fiscal\Tax\IbsCbsCalculation;
use InvalidArgumentException;
use NFePHP\NFe\Make;
use stdClass;

final class FiscalDocumentXmlBuilder
{
    private const UF_CODES = [
        'RO'=>11,'AC'=>12,'AM'=>13,'RR'=>14,'PA'=>15,'AP'=>16,'TO'=>17,'MA'=>21,'PI'=>22,
        'CE'=>23,'RN'=>24,'PB'=>25,'PE'=>26,'AL'=>27,'SE'=>28,'BA'=>29,'MG'=>31,'ES'=>32,
        'RJ'=>33,'SP'=>35,'PR'=>41,'SC'=>42,'RS'=>43,'MS'=>50,'MT'=>51,'GO'=>52,'DF'=>53,
    ];
    private const PAYMENT_CODES = [
        'dinheiro'=>'01','cheque'=>'02','cartao_credito'=>'03','cartao_debito'=>'04',
        'boleto'=>'15','pix'=>'17','transferencia'=>'18','outro'=>'99',
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
        $std = $this->std(['Id'=>null,'versao'=>'4.00']);
        $make->taginfNFe($std);
        $make->tagide($this->std([
            'cUF'=>$cUf, 'cNF'=>(string) ($document['cnf'] ?? $fiscal['cnf'] ?? ''),
            'natOp'=>'VENDA DE MERCADORIA', 'mod'=>$model, 'serie'=>(int) $document['serie'],
            'nNF'=>(int) $document['numero'],
            'dhEmi'=>$this->dateTime((string)($fiscal['issued_at'] ?? $snapshot['captured_at'] ?? '')),
            'tpNF'=>1, 'idDest'=>$uf === strtoupper((string) ($customer['uf'] ?? $uf)) ? 1 : 2,
            'cMunFG'=>(string) $company['codigo_municipio_ibge'], 'tpImp'=>$model === '65' ? 4 : 1,
            'tpEmis'=>1, 'tpAmb'=>$environment === 'producao' ? 1 : 2, 'finNFe'=>1,
            'indFinal'=>1, 'indPres'=>$model === '65' ? 1 : 9, 'procEmi'=>0, 'verProc'=>'OSMais 1.0',
        ]));
        $make->tagEmit($this->std([
            'CNPJ'=>$this->digits((string) $company['documento']), 'xNome'=>$company['razao_social'],
            'xFant'=>$company['nome_fantasia'] ?? null, 'IE'=>$company['inscricao_estadual'],
            'IM'=>$company['inscricao_municipal'] ?? null, 'CNAE'=>$company['cnae_principal'] ?? null,
            'CRT'=>(int) $company['crt'],
        ]));
        $make->tagenderEmit($this->std([
            'xLgr'=>$company['endereco_logradouro'], 'nro'=>$company['endereco_numero'],
            'xCpl'=>$company['endereco_complemento'] ?? null, 'xBairro'=>$company['endereco_bairro'],
            'cMun'=>$company['codigo_municipio_ibge'], 'xMun'=>$company['endereco_cidade'],
            'UF'=>$uf, 'CEP'=>$company['endereco_cep'], 'cPais'=>'1058', 'xPais'=>'BRASIL',
            'fone'=>$this->digits((string) ($company['telefone'] ?? '')) ?: null,
        ]));
        $this->addCustomer($make, $customer, $model);

        foreach (array_values($items) as $offset => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Item fiscal inválido.');
            }
            $calculation = $this->addItem($make, $item, $offset + 1, (int) $company['crt']);
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
            'vBCIBSCBS'=>Decimal::formatCents($ibsCbsTotals['base']),
            'gIBS_vIBS'=>Decimal::formatCents($ibsCbsTotals['ibs']),
            'gIBSUF_vDif'=>'0.00', 'gIBSUF_vDevTrib'=>'0.00',
            'gIBSUF_vIBSUF'=>Decimal::formatCents($ibsCbsTotals['uf']),
            'gIBSMun_vDif'=>'0.00', 'gIBSMun_vDevTrib'=>'0.00',
            'gIBSMun_vIBSMun'=>Decimal::formatCents($ibsCbsTotals['city']),
            'gCBS_vDif'=>'0.00', 'gCBS_vDevTrib'=>'0.00',
            'gCBS_vCBS'=>Decimal::formatCents($ibsCbsTotals['cbs']),
            'gIBS_vCredPres'=>'0.00', 'gIBS_vCredPresCondSus'=>'0.00',
            'gCBS_vCredPres'=>'0.00', 'gCBS_vCredPresCondSus'=>'0.00',
            ]));
        }
        $make->tagtransp($this->std(['modFrete'=>9]));
        $this->addPayments($make, is_array($snapshot['payments'] ?? null) ? $snapshot['payments'] : [], (string) $snapshot['totals']['invoice']);
        $services = is_array($snapshot['services'] ?? null) ? $snapshot['services'] : [];
        $note = 'Documento referente à ' . (string) ($snapshot['service_order']['number'] ?? 'OS');
        if ($services !== []) {
            $note .= '. Serviços da OS devem ser emitidos em NFS-e separada e vinculada.';
        }
        $make->taginfAdic($this->std(['infCpl'=>$note]));

        $xml = $make->getXML();
        $errors = $make->getErrors();
        if ($errors !== []) {
            throw new InvalidArgumentException('O XML fiscal não passou na validação estrutural: ' . implode('; ', array_slice($errors, 0, 3)));
        }
        $key = $make->getChave();
        if (preg_match('/^\d{44}$/', $key) !== 1) {
            throw new InvalidArgumentException('A chave de acesso fiscal não pôde ser gerada.');
        }
        return ['xml'=>$xml, 'key'=>$key];
    }

    /** @param array<string,mixed> $customer */
    private function addCustomer(Make $make, array $customer, string $model): void
    {
        $document = $this->digits((string) ($customer['cliente_documento'] ?? ''));
        if ($model === '65' && $document === '') {
            return;
        }
        $indicator = ['contribuinte'=>1,'isento'=>2,'nao_contribuinte'=>9][(string) ($customer['indicador_ie'] ?? '')] ?? 9;
        $make->tagdest($this->std([
            strlen($document) === 14 ? 'CNPJ' : 'CPF'=>$document,
            'xNome'=>$customer['cliente_nome'] ?? '', 'indIEDest'=>$indicator,
            'IE'=>$customer['inscricao_estadual'] ?? null, 'email'=>$customer['cliente_email'] ?? null,
        ]));
        if ($model === '55' || trim((string) ($customer['endereco'] ?? '')) !== '') {
            $make->tagenderDest($this->std([
                'xLgr'=>$customer['endereco'] ?? '', 'nro'=>$customer['cliente_numero'] ?? 'SN',
                'xCpl'=>$customer['complemento'] ?? null, 'xBairro'=>$customer['bairro'] ?? '',
                'cMun'=>$customer['cliente_codigo_municipio'] ?? '', 'xMun'=>$customer['cidade'] ?? '',
                'UF'=>$customer['uf'] ?? '', 'CEP'=>$this->digits((string) ($customer['cep'] ?? '')), 'cPais'=>'1058', 'xPais'=>'BRASIL',
            ]));
        }
    }

    /** @param array<string,mixed> $item @return array<string,string>|null */
    private function addItem(Make $make, array $item, int $number, int $crt): ?array
    {
        $subtotalCents = Decimal::moneyToCents((string) $item['subtotal']);
        $discountCents = Decimal::moneyToCents((string) ($item['desconto'] ?? '0'));
        $subtotal = Decimal::formatCents($subtotalCents);
        $discount = Decimal::formatCents($discountCents);
        $gross = Decimal::formatCents($subtotalCents + $discountCents);
        $quantity = Decimal::normalizeUnsigned((string) $item['quantidade'], 4, 'quantidade');
        $unit = Decimal::normalizeUnsigned((string) $item['valor_unitario'], 10, 'valor unitário');
        $gtin = trim((string) ($item['gtin_tributavel'] ?? $item['codigo_barras'] ?? '')) ?: 'SEM GTIN';
        $make->tagprod($this->std([
            'item'=>$number, 'cProd'=>(string) ($item['codigo'] ?? $item['produto_id']),
            'cEAN'=>$gtin, 'xProd'=>(string) $item['descricao'], 'NCM'=>(string) $item['ncm'],
            'CEST'=>$item['cest'] ?: null, 'CFOP'=>(string) $item['cfop_padrao'],
            'uCom'=>(string) $item['unidade'], 'qCom'=>$quantity, 'vUnCom'=>$unit, 'vProd'=>$gross,
            'cEANTrib'=>$gtin, 'uTrib'=>(string) $item['unidade_tributavel'],
            'qTrib'=>$quantity, 'vUnTrib'=>$unit, 'vDesc'=>$discountCents > 0 ? $discount : null, 'indTot'=>1,
        ]));
        $make->tagimposto($this->std(['item'=>$number, 'vTotTrib'=>null]));
        if (in_array($crt, [1,2,4], true)) {
            $csosn = (string) ($item['csosn'] ?? '');
            if (!in_array($csosn, ['102','103','300','400'], true)) {
                throw new InvalidArgumentException('O CSOSN ' . $csosn . ' exige uma regra tributária específica antes da emissão.');
            }
            $make->tagICMSSN($this->std(['item'=>$number,'orig'=>$item['origem_mercadoria'],'CSOSN'=>$csosn]));
        } else {
            $cst = (string) ($item['cst_icms'] ?? '');
            if (!in_array($cst, ['00','40','41','50'], true)) {
                throw new InvalidArgumentException('O CST ICMS ' . $cst . ' exige uma regra tributária específica antes da emissão.');
            }
            $rate = Decimal::rateToUnits((string) ($item['aliquota_icms'] ?? '0'));
            $base = $cst === '00' ? $subtotal : '0.00';
            $baseCents = $cst === '00' ? $subtotalCents : 0;
            $make->tagICMS($this->std([
                'item'=>$number,'orig'=>$item['origem_mercadoria'],'CST'=>$cst,'modBC'=>3,
                'vBC'=>$base,'pICMS'=>Decimal::formatRate($rate),
                'vICMS'=>Decimal::formatCents(Decimal::taxCents($baseCents, $rate)),
            ]));
        }
        $this->addPisCofins($make, $item, $number, $subtotal);
        if (!isset($item['ibs_cbs_rule'])) {
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
    private function addPisCofins(Make $make, array $item, int $number, string $base): void
    {
        $baseCents = Decimal::moneyToCents($base);
        $pisRate = Decimal::rateToUnits((string) ($item['aliquota_pis'] ?? '0'));
        $cofinsRate = Decimal::rateToUnits((string) ($item['aliquota_cofins'] ?? '0'));
        $make->tagPIS($this->std([
            'item'=>$number,'CST'=>(string) $item['cst_pis'],'vBC'=>$base,
            'pPIS'=>Decimal::formatRate($pisRate),'vPIS'=>Decimal::formatCents(Decimal::taxCents($baseCents, $pisRate)),
        ]));
        $make->tagCOFINS($this->std([
            'item'=>$number,'CST'=>(string) $item['cst_cofins'],'vBC'=>$base,
            'pCOFINS'=>Decimal::formatRate($cofinsRate),'vCOFINS'=>Decimal::formatCents(Decimal::taxCents($baseCents, $cofinsRate)),
        ]));
    }

    /** @param array<int,mixed> $payments */
    private function addPayments(Make $make, array $payments, string $invoiceTotal): void
    {
        $make->tagpag($this->std(['vTroco'=>null]));
        $remaining = $this->cents($invoiceTotal);
        $paid = 0;
        foreach ($payments as $payment) {
            if (is_array($payment)) {
                $paid += $this->cents((string) ($payment['valor'] ?? '0'));
            }
        }
        foreach ($payments as $payment) {
            if (!is_array($payment) || $remaining <= 0) {
                break;
            }
            $allocated = min($remaining, $this->cents((string) $payment['valor']));
            if ($allocated <= 0) {
                continue;
            }
            $method = (string) $payment['forma_pagamento'];
            $data = ['indPag'=>0,'tPag'=>self::PAYMENT_CODES[$method] ?? '99',
                'xPag'=>$method === 'outro' ? 'Outro' : null,'vPag'=>$this->formatCents($allocated)];
            if (in_array($method, ['cartao_credito','cartao_debito'], true)) {
                $data['tpIntegra'] = 2;
            }
            $make->tagdetPag($this->std($data));
            $remaining -= $allocated;
        }
        if ($remaining > 0) {
            $make->tagdetPag($this->std(['indPag'=>1,'tPag'=>'90','vPag'=>'0.00']));
        }
    }

    /** @param array<string,mixed> $data */
    private function std(array $data): stdClass { return (object) $data; }
    private function digits(string $value): string { return preg_replace('/\D+/', '', $value) ?? ''; }
    private function cents(string $value): int { return Decimal::moneyToCents($value); }
    private function formatCents(int $value): string { return Decimal::formatCents($value); }
    private function dateTime(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $value) !== 1) {
            throw new InvalidArgumentException('Data de emissão inválida no snapshot fiscal.');
        }
        return $value;
    }
}
