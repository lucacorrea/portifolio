<?php
namespace App\Services;

use DOMDocument;
use Exception;

class SefazXmlService extends BaseService {
    
    public function generateNFCe(array $sale, array $fiscal) {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $nfe = $dom->createElementNS('http://www.portalfiscal.inf.br/nfe', 'NFe');
        $dom->appendChild($nfe);

        $infNFe = $dom->createElement('infNFe');
        
        // ID: NFe + cUF(2) + AAMM(4) + CNPJ(14) + mod(2) + serie(3) + nNF(9) + tpEmis(1) + cNF(8) + cDV(1)
        $cUF = "35"; // São Paulo
        $tpAmb = ($fiscal['ambiente'] == 1) ? '1' : '2';
        $mod = "65"; // NFC-e
        $serie = "001";
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
        $ide = $dom->createElement('ide');
        $ide->appendChild($dom->createElement('cUF', $cUF));
        $ide->appendChild($dom->createElement('cNF', $cNF));
        $ide->appendChild($dom->createElement('natOp', 'VENDA'));
        $ide->appendChild($dom->createElement('mod', $mod));
        $ide->appendChild($dom->createElement('serie', (int)$serie));
        $ide->appendChild($dom->createElement('nNF', (int)$nNF));
        $ide->appendChild($dom->createElement('dhEmi', $dhEmi));
        $ide->appendChild($dom->createElement('tpNF', '1')); // Saída
        $ide->appendChild($dom->createElement('idDest', '1')); // Interna
        $ide->appendChild($dom->createElement('cMunFG', '3550308')); // São Paulo
        $ide->appendChild($dom->createElement('tpImp', '4')); // DANFE NFC-e
        $ide->appendChild($dom->createElement('tpEmis', $tpEmis));
        $ide->appendChild($dom->createElement('cDV', $cDV));
        $ide->appendChild($dom->createElement('tpAmb', $tpAmb));
        $ide->appendChild($dom->createElement('finNFe', '1')); // Normal
        $ide->appendChild($dom->createElement('indFinal', '1')); // Consumidor Final
        $ide->appendChild($dom->createElement('indPres', '1')); // Presencial
        $ide->appendChild($dom->createElement('procEmi', '0')); // Aplicativo do Contribuinte
        $ide->appendChild($dom->createElement('verProc', 'ERP_ELET_V1'));
        $infNFe->appendChild($ide);

        // 2. emit
        $emit = $dom->createElement('emit');
        $emit->appendChild($dom->createElement('CNPJ', $cnpj));
        $emit->appendChild($dom->createElement('xNome', $this->clearText($fiscal['nome'])));
        $enderEmit = $dom->createElement('enderEmit');
        $enderEmit->appendChild($dom->createElement('xLgr', 'Logradouro'));
        $enderEmit->appendChild($dom->createElement('nro', '123'));
        $enderEmit->appendChild($dom->createElement('xBairro', 'Bairro'));
        $enderEmit->appendChild($dom->createElement('cMun', '3550308'));
        $enderEmit->appendChild($dom->createElement('xMun', 'SAO PAULO'));
        $enderEmit->appendChild($dom->createElement('UF', 'SP'));
        $enderEmit->appendChild($dom->createElement('CEP', '01001000'));
        $enderEmit->appendChild($dom->createElement('cPais', '1058'));
        $enderEmit->appendChild($dom->createElement('xPais', 'BRASIL'));
        $emit->appendChild($enderEmit);
        $emit->appendChild($dom->createElement('IE', preg_replace('/[^0-9]/', '', $fiscal['inscricao_estadual'] ?? '')));
        $emit->appendChild($dom->createElement('CRT', '1')); // Simples Nacional
        $infNFe->appendChild($emit);

        // 3. det (Items)
        $nItem = 1;
        $totalVBC = 0;
        $totalVICMS = 0;
        
        foreach ($sale['items'] as $item) {
            $det = $dom->createElement('det');
            $det->setAttribute('nItem', $nItem++);
            
            $prod = $dom->createElement('prod');
            $prod->appendChild($dom->createElement('cProd', $item['produto_id']));
            $prod->appendChild($dom->createElement('cEAN', 'SEM GTIN'));
            $prod->appendChild($dom->createElement('xProd', $this->clearText($item['nome'])));
            $prod->appendChild($dom->createElement('NCM', $item['ncm'] ?: '00000000'));
            $prod->appendChild($dom->createElement('CFOP', $item['cfop_interno'] ?: '5102'));
            $prod->appendChild($dom->createElement('uCom', $item['unidade'] ?: 'UN'));
            $prod->appendChild($dom->createElement('qCom', number_format($item['quantidade'], 4, '.', '')));
            $prod->appendChild($dom->createElement('vUnCom', number_format($item['preco_unitario'], 10, '.', '')));
            $prod->appendChild($dom->createElement('vProd', number_format($item['quantidade'] * $item['preco_unitario'], 2, '.', '')));
            $prod->appendChild($dom->createElement('cEANTrib', 'SEM GTIN'));
            $prod->appendChild($dom->createElement('uTrib', $item['unidade'] ?: 'UN'));
            $prod->appendChild($dom->createElement('qTrib', number_format($item['quantidade'], 4, '.', '')));
            $prod->appendChild($dom->createElement('vUnTrib', number_format($item['preco_unitario'], 10, '.', '')));
            $prod->appendChild($dom->createElement('indTot', '1'));
            $det->appendChild($prod);
            
            $imposto = $dom->createElement('imposto');
            $icms = $dom->createElement('ICMS');
            $icmsSn = $dom->createElement('ICMSSN102'); // Simples Nacional - Sem crédito
            $icmsSn->appendChild($dom->createElement('orig', $item['origem'] ?? '0'));
            $icmsSn->appendChild($dom->createElement('CSOSN', '102'));
            $icms->appendChild($icmsSn);
            $imposto->appendChild($icms);
            
            // PIS/COFINS (Standard for Simples Nacional)
            $pis = $dom->createElement('PIS');
            $pisOutr = $dom->createElement('PISAliq');
            $pisOutr->appendChild($dom->createElement('CST', '01'));
            $pisOutr->appendChild($dom->createElement('vBC', '0.00'));
            $pisOutr->appendChild($dom->createElement('pPIS', '0.00'));
            $pisOutr->appendChild($dom->createElement('vPIS', '0.00'));
            $pis->appendChild($pisOutr);
            $imposto->appendChild($pis);

            $cofins = $dom->createElement('COFINS');
            $cofinsAliq = $dom->createElement('COFINSAliq');
            $cofinsAliq->appendChild($dom->createElement('CST', '01'));
            $cofinsAliq->appendChild($dom->createElement('vBC', '0.00'));
            $cofinsAliq->appendChild($dom->createElement('pCOFINS', '0.00'));
            $cofinsAliq->appendChild($dom->createElement('vCOFINS', '0.00'));
            $cofins->appendChild($cofinsAliq);
            $imposto->appendChild($cofins);

            $det->appendChild($imposto);
            $infNFe->appendChild($det);
        }

        // 4. total
        $total = $dom->createElement('total');
        $icmsTot = $dom->createElement('ICMSTot');
        $icmsTot->appendChild($dom->createElement('vBC', '0.00'));
        $icmsTot->appendChild($dom->createElement('vICMS', '0.00'));
        $icmsTot->appendChild($dom->createElement('vICMSDeson', '0.00'));
        $icmsTot->appendChild($dom->createElement('vFCP', '0.00'));
        $icmsTot->appendChild($dom->createElement('vBCST', '0.00'));
        $icmsTot->appendChild($dom->createElement('vST', '0.00'));
        $icmsTot->appendChild($dom->createElement('vFCPST', '0.00'));
        $icmsTot->appendChild($dom->createElement('vFCPSTRet', '0.00'));
        $icmsTot->appendChild($dom->createElement('vProd', number_format($sale['valor_total'] + ($sale['desconto_total'] ?? 0), 2, '.', '')));
        $icmsTot->appendChild($dom->createElement('vFrete', '0.00'));
        $icmsTot->appendChild($dom->createElement('vSeg', '0.00'));
        $icmsTot->appendChild($dom->createElement('vDesc', number_format($sale['desconto_total'] ?? 0, 2, '.', '')));
        $icmsTot->appendChild($dom->createElement('vII', '0.00'));
        $icmsTot->appendChild($dom->createElement('vIPI', '0.00'));
        $icmsTot->appendChild($dom->createElement('vIPIDevol', '0.00'));
        $icmsTot->appendChild($dom->createElement('vPIS', '0.00'));
        $icmsTot->appendChild($dom->createElement('vCOFINS', '0.00'));
        $icmsTot->appendChild($dom->createElement('vOutro', '0.00'));
        $icmsTot->appendChild($dom->createElement('vNF', number_format($sale['valor_total'], 2, '.', '')));
        $total->appendChild($icmsTot);
        $infNFe->appendChild($total);

        // 5. transp
        $transp = $dom->createElement('transp');
        $transp->appendChild($dom->createElement('modFrete', '9')); // Sem frete
        $infNFe->appendChild($transp);

        // 6. pag
        $pag = $dom->createElement('pag');
        $detPag = $dom->createElement('detPag');
        $detPag->appendChild($dom->createElement('tPag', $this->mapPaymentMethod($sale['forma_pagamento'])));
        $detPag->appendChild($dom->createElement('vPag', number_format($sale['valor_total'], 2, '.', '')));
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
        return preg_replace('/[^a-zA-Z0-9 ]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $text));
    }

    private function mapPaymentMethod($method) {
        $map = [
            'dinheiro' => '01',
            'credito' => '03',
            'debito' => '04',
            'pix' => '17',
            'fiado' => '99'
        ];
        return $map[strtolower($method)] ?? '99';
    }
}
