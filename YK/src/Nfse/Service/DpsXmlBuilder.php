<?php

declare(strict_types=1);

namespace App\Nfse\Service;

use App\Fiscal\Tax\Decimal;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;

final class DpsXmlBuilder
{
    private const NS = 'http://www.betha.com.br/e-nota-dps';

    /** @param array<string,mixed> $snapshot */
    public function build(array $snapshot): string
    {
        $company = $this->array($snapshot, 'company');
        $customer = $this->array($snapshot, 'customer');
        $fiscal = $this->array($snapshot, 'fiscal');
        $items = $snapshot['items'] ?? null;
        if (!is_array($items) || $items === []) {
            throw new InvalidArgumentException('A DPS exige ao menos uma linha de serviço.');
        }
        $profile = $this->sameFiscalProfile($items);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $root = $dom->createElementNS(self::NS, 'RecepcionarDpsEnvio');
        $dom->appendChild($root);
        $dps = $this->add($dom, $root, 'DPS');
        $dps->setAttribute('versao', $this->required($fiscal, 'schema_version'));
        $info = $this->add($dom, $dps, 'infDPS');
        $id = $this->required($fiscal, 'dps_id');
        if (preg_match('/^DPS[A-Z0-9]{20,60}$/', $id) !== 1) {
            throw new InvalidArgumentException('Identificador oficial da DPS inválido.');
        }
        $info->setAttribute('id', $id);
        $this->add($dom, $info, 'tpAmb', ($fiscal['environment'] ?? '') === 'producao' ? '1' : '2');
        $this->add($dom, $info, 'dhEmi', $this->required($fiscal, 'issued_at'));
        $this->add($dom, $info, 'verAplic', 'OSMais-' . $this->required($fiscal, 'provider_version'));
        $this->add($dom, $info, 'serie', $this->required($fiscal, 'series'));
        $this->add($dom, $info, 'nDPS', $this->required($fiscal, 'number'));
        $this->add($dom, $info, 'dCompet', $this->required($fiscal, 'competence_date'));
        $this->add($dom, $info, 'tpEmit', '1');
        $municipality = $this->digits($this->required($fiscal, 'municipality'));
        $this->add($dom, $info, 'cLocEmi', $municipality);
        $this->provider($dom, $info, $company);
        $this->customer($dom, $info, $customer);
        $this->service($dom, $info, $profile, $items);
        $payments = is_array($snapshot['payments'] ?? null) ? $snapshot['payments'] : [];
        $this->values($dom, $info, $profile, $items, $payments);
        $this->ibsCbs($dom, $info, $profile, $fiscal);
        return (string) $dom->saveXML($dom->documentElement, LIBXML_NOXMLDECL);
    }

    /** @param array<string,mixed> $company */
    private function provider(DOMDocument $dom, DOMElement $info, array $company): void
    {
        $node = $this->add($dom, $info, 'prest');
        $this->document($dom, $node, $this->required($company, 'documento'));
        $this->optional($dom, $node, 'fone', $this->digits((string)($company['telefone'] ?? '')));
        $this->optional($dom, $node, 'email', (string)($company['email'] ?? ''));
        $regime = $this->add($dom, $node, 'regTrib');
        $this->add($dom, $regime, 'opSimpNac', (string)($company['opcao_simples_nacional'] ?? '3'));
        $this->add($dom, $regime, 'regEspTrib', (string)($company['regime_especial_tributacao'] ?? '0'));
    }

    /** @param array<string,mixed> $customer */
    private function customer(DOMDocument $dom, DOMElement $info, array $customer): void
    {
        $node = $this->add($dom, $info, 'toma');
        $this->document($dom, $node, $this->required($customer, 'cliente_documento'));
        $this->add($dom, $node, 'xNome', $this->required($customer, 'cliente_nome'));
        $address = $this->add($dom, $node, 'end');
        $national = $this->add($dom, $address, 'endNac');
        $this->add($dom, $national, 'cMun', $this->digits($this->required($customer, 'cliente_codigo_municipio')));
        $this->add($dom, $national, 'CEP', $this->digits($this->required($customer, 'cep')));
        $this->add($dom, $address, 'xLgr', $this->required($customer, 'endereco'));
        $this->add($dom, $address, 'nro', $this->required($customer, 'cliente_numero'));
        $this->optional($dom, $address, 'xCpl', (string)($customer['complemento'] ?? ''));
        $this->add($dom, $address, 'xBairro', $this->required($customer, 'bairro'));
        $this->optional($dom, $node, 'fone', $this->digits((string)($customer['telefone'] ?? '')));
        $this->optional($dom, $node, 'email', (string)($customer['cliente_email'] ?? ''));
    }

    /** @param array<string,mixed> $profile @param array<int,array<string,mixed>> $items */
    private function service(DOMDocument $dom, DOMElement $info, array $profile, array $items): void
    {
        $service = $this->add($dom, $info, 'serv');
        $location = $this->add($dom, $service, 'locPrest');
        $this->add($dom, $location, 'cLocPrestacao', $this->digits($this->required($profile, 'municipio_incidencia_ibge')));
        $code = $this->add($dom, $service, 'cServ');
        $this->add($dom, $code, 'cTribNac', $this->required($profile, 'codigo_tributacao_nacional'));
        $descriptions = array_map(static fn(array $item): string => trim((string)($item['descricao'] ?? '')), $items);
        $this->add($dom, $code, 'xDescServ', implode(' | ', array_filter($descriptions)));
        $this->add($dom, $code, 'cNBS', $this->required($profile, 'nbs'));
    }

    /**
     * @param array<string,mixed> $profile
     * @param array<int,array<string,mixed>> $items
     * @param array<int,array<string,mixed>> $payments
     */
    private function values(DOMDocument $dom, DOMElement $info, array $profile, array $items, array $payments): void
    {
        $total = 0;
        foreach ($items as $item) $total += Decimal::moneyToCents((string)($item['subtotal'] ?? ''));
        $values = $this->add($dom, $info, 'valores');
        $services = $this->add($dom, $values, 'vServPrest');
        $received = 0;
        foreach ($payments as $payment) {
            if (is_array($payment)) $received += Decimal::moneyToCents((string)($payment['valor'] ?? '0'));
        }
        if ($received > 0) $this->add($dom, $services, 'vReceb', Decimal::formatCents($received));
        $this->add($dom, $services, 'vServ', Decimal::formatCents($total));
        $tax = $this->add($dom, $values, 'trib');
        $city = $this->add($dom, $tax, 'tribMun');
        $this->add($dom, $city, 'tribISSQN', (string)($profile['tributacao_iss'] ?? '1'));
        $this->optional($dom, $city, 'pAliq', (string)($profile['aliquota_iss'] ?? ''));
        $this->add($dom, $city, 'tpRetISSQN', !empty($profile['iss_retido']) ? '1' : '2');
        $pisCst = trim((string)($profile['cst_pis_servico'] ?? ''));
        $cofinsCst = trim((string)($profile['cst_cofins_servico'] ?? ''));
        if ($pisCst !== '' || $cofinsCst !== '') {
            if ($pisCst === '' || $cofinsCst === '' || $pisCst !== $cofinsCst) {
                throw new InvalidArgumentException('A DPS exige CST PIS/COFINS compatíveis no perfil fiscal implementado.');
            }
            $federal = $this->add($dom, $tax, 'tribFed');
            $pisCofins = $this->add($dom, $federal, 'piscofins');
            $this->add($dom, $pisCofins, 'CST', ltrim($pisCst, '0') ?: '0');
            $this->add($dom, $pisCofins, 'vBCPisCofins', Decimal::formatCents($total));
            $pisRate = Decimal::rateToUnits((string)($profile['aliquota_pis_servico'] ?? '0'));
            $cofinsRate = Decimal::rateToUnits((string)($profile['aliquota_cofins_servico'] ?? '0'));
            $this->add($dom, $pisCofins, 'pAliqPis', Decimal::formatRate($pisRate));
            $this->add($dom, $pisCofins, 'pAliqCofins', Decimal::formatRate($cofinsRate));
            $this->add($dom, $pisCofins, 'vPis', Decimal::formatCents(Decimal::taxCents($total, $pisRate)));
            $this->add($dom, $pisCofins, 'vCofins', Decimal::formatCents(Decimal::taxCents($total, $cofinsRate)));
            $this->add($dom, $pisCofins, 'tpRetPisCofins', '2');
        }
        $totals = $this->add($dom, $tax, 'totTrib');
        $this->add($dom, $totals, 'indTotTrib', '0');
    }

    /** @param array<string,mixed> $profile @param array<string,mixed> $fiscal */
    private function ibsCbs(DOMDocument $dom, DOMElement $info, array $profile, array $fiscal): void
    {
        if (($fiscal['issued_at'] ?? '') < '2026-08-01') return;
        foreach (['finalidade_nfse','cindop','tipo_operacao','cst_ibs_cbs','classificacao_tributaria_ibs_cbs'] as $field) {
            $this->required($profile, $field);
        }
        $tax = $this->add($dom, $info, 'IBSCBS');
        $this->add($dom, $tax, 'finNFSe', (string)$profile['finalidade_nfse']);
        $this->add($dom, $tax, 'cIndOp', (string)$profile['cindop']);
        $this->add($dom, $tax, 'tpOper', (string)$profile['tipo_operacao']);
        // Neste fluxo o tomador identificado também é adquirente e destinatário (NT 004, indDest=0).
        $this->add($dom, $tax, 'indDest', '0');
        $values = $this->add($dom, $tax, 'valores');
        $tribute = $this->add($dom, $values, 'trib');
        $group = $this->add($dom, $tribute, 'gIBSCBS');
        $this->add($dom, $group, 'CST', (string)$profile['cst_ibs_cbs']);
        $this->add($dom, $group, 'cClassTrib', (string)$profile['classificacao_tributaria_ibs_cbs']);
    }

    /** @param array<int,array<string,mixed>> $items @return array<string,mixed> */
    private function sameFiscalProfile(array $items): array
    {
        $first = $items[0];
        foreach ($items as $item) {
            foreach (['codigo_tributacao_nacional','nbs','municipio_incidencia_ibge','aliquota_iss','iss_retido',
                'cst_pis_servico','cst_cofins_servico','aliquota_pis_servico','aliquota_cofins_servico',
                'cst_ibs_cbs','classificacao_tributaria_ibs_cbs','cindop','finalidade_nfse','tipo_operacao'] as $field) {
                if ((string)($item[$field] ?? '') !== (string)($first[$field] ?? '')) {
                    throw new InvalidArgumentException('Uma DPS só pode conter serviços do mesmo perfil fiscal.');
                }
            }
        }
        return $first;
    }

    /** @param array<string,mixed> $data */
    private function required(array $data, string $field): string
    {
        $value = trim((string)($data[$field] ?? ''));
        if ($value === '') throw new InvalidArgumentException('Campo obrigatório da DPS ausente: ' . $field . '.');
        return $value;
    }
    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function array(array $data, string $field): array
    {
        if (!isset($data[$field]) || !is_array($data[$field])) throw new InvalidArgumentException('Snapshot DPS inválido: ' . $field . '.');
        return $data[$field];
    }
    private function add(DOMDocument $dom, DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $node = $dom->createElementNS(self::NS, $name);
        if ($value !== null) $node->appendChild($dom->createTextNode($value));
        $parent->appendChild($node);
        return $node;
    }
    private function optional(DOMDocument $dom, DOMElement $parent, string $name, string $value): void
    {
        if (trim($value) !== '') $this->add($dom, $parent, $name, trim($value));
    }
    private function document(DOMDocument $dom, DOMElement $parent, string $document): void
    {
        $document = $this->digits($document);
        if (!in_array(strlen($document), [11,14], true)) throw new InvalidArgumentException('CPF/CNPJ da DPS inválido.');
        $this->add($dom, $parent, strlen($document) === 14 ? 'CNPJ' : 'CPF', $document);
    }
    private function digits(string $value): string { return preg_replace('/\D+/', '', $value) ?? ''; }
}
