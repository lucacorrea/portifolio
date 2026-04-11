<?php
namespace App\Services;

use DOMDocument;
use Exception;

class SefazXmlService extends BaseService {
    
    private $ns = 'http://www.portalfiscal.inf.br/nfe';

    private function createEl($dom, $name, $value = null) {
        if ($value !== null) {
            return $dom->createElementNS($this->ns, $name, $value);
        }
        return $dom->createElementNS($this->ns, $name);
    }

    public function generateNFCe(array $sale, array $fiscal) {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $nfe = $dom->createElementNS($this->ns, 'NFe');
        $dom->appendChild($nfe);

        $infNFe = $this->createEl($dom, 'infNFe');
        
        // ID: NFe + cUF(2) + AAMM(4) + CNPJ(14) + mod(2) + serie(3) + nNF(9) + tpEmis(1) + cNF(8) + cDV(1)
        $cUF = substr(preg_replace('/\D/', '', $fiscal['codigo_uf'] ?? '35'), 0, 2);
        $cUF = str_pad($cUF, 2, '0', STR_PAD_LEFT);
        $tpAmb = ($fiscal['ambiente'] == 1) ? '1' : '2';
        $mod = "65"; // NFC-e
        $serie = str_pad($fiscal['serie_nfce'] ?? '1', 3, '0', STR_PAD_LEFT);
        $nNF = str_pad($sale['id'], 9, '0', STR_PAD_LEFT);
        $tpEmis = "1"; // Normal
        $cNF = str_pad(rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        $dhEmi = date('Y-m-d\TH:i:sP');
        
        $cnpj = preg_replace('/[^0-9]/', '', $fiscal['cnpj']);
        $aamm = date('ym');
        
        $chaveSemDV = $cUF . $aamm . $cnpj . $mod . $serie . $nNF . $tpEmis . $cNF;
        $cDV = $this->calculateDV($chaveSemDV);
        $chave = $chaveSemDV . $cDV;
        
        $infNFe->setAttribute('Id', 'NFe' . $chave);
        $infNFe->setAttribute('versao', '4.00');
        $nfe->appendChild($infNFe);

        // 1. ide
        $ide = $this->createEl($dom, 'ide');
        $ide->appendChild($this->createEl($dom, 'cUF', $cUF));
        $ide->appendChild($this->createEl($dom, 'cNF', $cNF));
        $ide->appendChild($this->createEl($dom, 'natOp', 'VENDA'));
        $ide->appendChild($this->createEl($dom, 'mod', $mod));
        $ide->appendChild($this->createEl($dom, 'serie', (int)$serie));
        $ide->appendChild($this->createEl($dom, 'nNF', (int)$nNF));
        $ide->appendChild($this->createEl($dom, 'dhEmi', $dhEmi));
        $ide->appendChild($this->createEl($dom, 'tpNF', '1')); // Saída
        $ide->appendChild($this->createEl($dom, 'idDest', '1')); // Interna
        $ide->appendChild($this->createEl($dom, 'cMunFG', $fiscal['codigo_municipio'] ?? '3550308'));
        $ide->appendChild($this->createEl($dom, 'tpImp', '4')); // DANFE NFC-e
        $ide->appendChild($this->createEl($dom, 'tpEmis', $tpEmis));
        $ide->appendChild($this->createEl($dom, 'cDV', $cDV));
        $ide->appendChild($this->createEl($dom, 'tpAmb', $tpAmb));
        $ide->appendChild($this->createEl($dom, 'finNFe', '1')); // Normal
        $ide->appendChild($this->createEl($dom, 'indFinal', '1')); // Consumidor Final
        $ide->appendChild($this->createEl($dom, 'indPres', '1')); // Presencial
        $ide->appendChild($this->createEl($dom, 'procEmi', '0')); // Aplicativo do Contribuinte
        $ide->appendChild($this->createEl($dom, 'verProc', 'ERP_ELET_V1'));
        $infNFe->appendChild($ide);

        // 2. emit
        $emit = $this->createEl($dom, 'emit');
        $emit->appendChild($this->createEl($dom, 'CNPJ', $cnpj));
        $emit->appendChild($this->createEl($dom, 'xNome', $this->clearText($fiscal['razao_social'] ?? $fiscal['nome'])));
        $enderEmit = $this->createEl($dom, 'enderEmit');
        $enderEmit->appendChild($this->createEl($dom, 'xLgr', $this->clearText($fiscal['logradouro'] ?? 'Logradouro')));
        $enderEmit->appendChild($this->createEl($dom, 'nro', $fiscal['numero'] ?? 'S/N'));
        $enderEmit->appendChild($this->createEl($dom, 'xBairro', $this->clearText($fiscal['bairro'] ?? 'Bairro')));
        $enderEmit->appendChild($this->createEl($dom, 'cMun', $fiscal['codigo_municipio'] ?? '3550308'));
        $enderEmit->appendChild($this->createEl($dom, 'xMun', $this->clearText($fiscal['municipio'] ?? 'SAO PAULO')));
        $enderEmit->appendChild($this->createEl($dom, 'UF', $fiscal['uf'] ?? 'SP'));
        $enderEmit->appendChild($this->createEl($dom, 'CEP', preg_replace('/\D/', '', $fiscal['cep'] ?? '01001000')));
        $enderEmit->appendChild($this->createEl($dom, 'cPais', '1058'));
        $enderEmit->appendChild($this->createEl($dom, 'xPais', 'BRASIL'));
        $emit->appendChild($enderEmit);
        $emit->appendChild($this->createEl($dom, 'IE', preg_replace('/[^0-9]/', '', $fiscal['inscricao_estadual'] ?? '')));
        $emit->appendChild($this->createEl($dom, 'CRT', $fiscal['crt'] ?? '1'));
        $infNFe->appendChild($emit);

        // 3. dest (Customer)
        if (!empty($sale['cliente_id']) || !empty($sale['nome_cliente_avulso'])) {
            $dest = $this->createEl($dom, 'dest');
            
            $doc = preg_replace('/\D/', '', $sale['cpf_cnpj'] ?? $sale['cpf_cliente'] ?? '');
            if (strlen($doc) === 11) {
                $dest->appendChild($this->createEl($dom, 'CPF', $doc));
            } elseif (strlen($doc) === 14) {
                $dest->appendChild($this->createEl($dom, 'CNPJ', $doc));
            }
            
            $nome = $this->clearText($sale['cliente_nome'] ?? $sale['nome_cliente_avulso'] ?? 'Consumidor Final');
            $dest->appendChild($this->createEl($dom, 'xNome', substr($nome, 0, 60)));
            $dest->appendChild($this->createEl($dom, 'indIEDest', '9')); // Não contribuinte
            
            $infNFe->appendChild($dest);
        }

        // 4. det (Items)
        $nItem = 1;
        foreach ($sale['items'] as $item) {
            $det = $this->createEl($dom, 'det');
            $det->setAttribute('nItem', (string)$nItem++);
            
            $prod = $this->createEl($dom, 'prod');
            $prod->appendChild($this->createEl($dom, 'cProd', $item['produto_id']));
            $prod->appendChild($this->createEl($dom, 'cEAN', 'SEM GTIN'));
            $prod->appendChild($this->createEl($dom, 'xProd', $this->clearText($item['nome'])));
            $prod->appendChild($this->createEl($dom, 'NCM', $item['ncm'] ?: '21069090'));
            $prod->appendChild($this->createEl($dom, 'CFOP', $item['cfop_interno'] ?: '5102'));
            $prod->appendChild($this->createEl($dom, 'uCom', $item['unidade'] ?: 'UN'));
            $prod->appendChild($this->createEl($dom, 'qCom', number_format((float)$item['quantidade'], 4, '.', '')));
            $prod->appendChild($this->createEl($dom, 'vUnCom', number_format((float)$item['preco_unitario'], 10, '.', '')));
            $prod->appendChild($this->createEl($dom, 'vProd', number_format((float)$item['quantidade'] * (float)$item['preco_unitario'], 2, '.', '')));
            $prod->appendChild($this->createEl($dom, 'cEANTrib', 'SEM GTIN'));
            $prod->appendChild($this->createEl($dom, 'uTrib', $item['unidade'] ?: 'UN'));
            $prod->appendChild($this->createEl($dom, 'qTrib', number_format((float)$item['quantidade'], 4, '.', '')));
            $prod->appendChild($this->createEl($dom, 'vUnTrib', number_format((float)$item['preco_unitario'], 10, '.', '')));
            $prod->appendChild($this->createEl($dom, 'indTot', '1'));
            $det->appendChild($prod);
            
            $imposto = $this->createEl($dom, 'imposto');
            $icms = $this->createEl($dom, 'ICMS');
            $icmsSn = $this->createEl($dom, 'ICMSSN102');
            $icmsSn->appendChild($this->createEl($dom, 'orig', (string)($item['origem'] ?? '0')));
            $icmsSn->appendChild($this->createEl($dom, 'CSOSN', '102'));
            $icms->appendChild($icmsSn);
            $imposto->appendChild($icms);
            
            $pis = $this->createEl($dom, 'PIS');
            $pisNT = $this->createEl($dom, 'PISNT');
            $pisNT->appendChild($this->createEl($dom, 'CST', '07'));
            $pis->appendChild($pisNT);
            $imposto->appendChild($pis);

            $cofins = $this->createEl($dom, 'COFINS');
            $cofinsNT = $this->createEl($dom, 'COFINSNT');
            $cofinsNT->appendChild($this->createEl($dom, 'CST', '07'));
            $cofins->appendChild($cofinsNT);
            $imposto->appendChild($cofins);

            $det->appendChild($imposto);
            $infNFe->appendChild($det);
        }

        // 5. total
        $total = $this->createEl($dom, 'total');
        $icmsTot = $this->createEl($dom, 'ICMSTot');
        $icmsTot->appendChild($this->createEl($dom, 'vBC', '0.00'));
        $icmsTot->appendChild($this->createEl($dom, 'vICMS', '0.00'));
        $icmsTot->appendChild($this->createEl($dom, 'vICMSDeson', '0.00'));
        $icmsTot->appendChild($this->createEl($dom, 'vFCP', '0.00'));
        $icmsTot->appendChild($this->createEl($dom, 'vBCST', '0.00'));
        $icmsTot->appendChild($this->createEl($dom, 'vST', '0.00'));
        $icmsTot->appendChild($this->createEl($dom, 'vFCPST', '0.00'));
        $icmsTot->appendChild($this->createEl($dom, 'vFCPSTRet', '0.00'));
        $icmsTot->appendChild($this->createEl($dom, 'vProd', number_format((float)$sale['valor_total'] + (float)($sale['desconto_total'] ?? 0), 2, '.', '')));
        $icmsTot->appendChild($this->createEl($dom, 'vFrete', '0.00'));
        $icmsTot->appendChild($this->createEl($dom, 'vSeg', '0.00'));
        $icmsTot->appendChild($this->createEl($dom, 'vDesc', number_format((float)($sale['desconto_total'] ?? 0), 2, '.', '')));
        $icmsTot->appendChild($this->createEl($dom, 'vII', '0.00'));
        $icmsTot->appendChild($this->createEl($dom, 'vIPI', '0.00'));
        $icmsTot->appendChild($this->createEl($dom, 'vIPIDevol', '0.00'));
        $icmsTot->appendChild($this->createEl($dom, 'vPIS', '0.00'));
        $icmsTot->appendChild($this->createEl($dom, 'vCOFINS', '0.00'));
        $icmsTot->appendChild($this->createEl($dom, 'vOutro', '0.00'));
        $icmsTot->appendChild($this->createEl($dom, 'vNF', number_format((float)$sale['valor_total'], 2, '.', '')));
        $total->appendChild($icmsTot);
        $infNFe->appendChild($total);

        // 6. transp
        $transp = $this->createEl($dom, 'transp');
        $transp->appendChild($this->createEl($dom, 'modFrete', '9')); // Sem frete
        $infNFe->appendChild($transp);

        // 7. pag
        $pag = $this->createEl($dom, 'pag');
        $detPag = $this->createEl($dom, 'detPag');
        $detPag->appendChild($this->createEl($dom, 'tPag', $this->mapPaymentMethod($sale['forma_pagamento'])));
        $detPag->appendChild($this->createEl($dom, 'vPag', number_format((float)$sale['valor_total'], 2, '.', '')));
        $pag->appendChild($detPag);
        $infNFe->appendChild($pag);

        return [
            'xml' => $dom->saveXML(),
            'chave' => $chave
        ];
    }

    private function calculateDV($key) {
        $factors = [2, 3, 4, 5, 6, 7, 8, 9];
        $sum = 0;
        $i = strlen($key) - 1;
        $f = 0;
        while ($i >= 0) {
            $sum += (int)$key[$i] * $factors[$f];
            $i--;
            $f++;
            if ($f > 7) $f = 0;
        }
        $remainder = $sum % 11;
        if ($remainder == 0 || $remainder == 1) return 0;
        return 11 - $remainder;
    }

    private function clearText($text) {
        $from = ['á','à','ã','â','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','õ','ô','ö','ú','ù','û','ü','ñ','ç','Á','À','Ã','Â','Ä','É','È','Ê','Ë','Í','Ì','Î','Ï','Ó','Ò','Õ','Ô','Ö','Ú','Ù','Û','Ü','Ñ','Ç'];
        $to   = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','n','c','A','A','A','A','A','E','E','E','E','I','I','I','I','O','O','O','O','O','U','U','U','U','N','C'];
        $text = str_replace($from, $to, (string)$text);
        $text = preg_replace('/[^\x20-\x7E]/', '', $text); // Keep only printable ASCII
        return substr(trim($text), 0, 120);
    }

    private function mapPaymentMethod($method) {
        $method = trim(mb_strtolower((string)$method, 'UTF-8'));
        
        // Mapa de textos → códigos
        $map = [
            'dinheiro'                 => '01',
            'cheque'                   => '02',
            'credito'                  => '03',
            'cartao de credito'        => '03',
            'cartao credito'           => '03',
            'debito'                   => '04',
            'cartao de debito'         => '04',
            'cartao debito'            => '04',
            'crediario'                => '05',
            'credito loja'             => '05',
            'vale alimentacao'         => '10',
            'vale refeicao'            => '11',
            'vale presente'            => '12',
            'vale combustivel'         => '13',
            'boleto'                   => '15',
            'deposito'                 => '16',
            'pix'                      => '17',
            'transferencia'            => '18',
            'carteira digital'         => '18',
            'programa de fidelidade'   => '19',
            'devolucao'                => '99',
            'cartao_voucher'           => '99',
            'sem pagamento'            => '90',
            'fiado'                    => '99',
            'outros'                   => '99'
        ];

        if (isset($map[$method])) return $map[$method];

        // Heurísticas
        if (str_contains($method, 'pix')) return '17';
        if (str_contains($method, 'debito')) return '04';
        if (str_contains($method, 'credito')) return '03';
        if (str_contains($method, 'boleto')) return '15';
        if (str_contains($method, 'transfer')) return '18';

        return '01'; // Default to Dinheiro if unknown
    }
}
