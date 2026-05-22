<?php
require __DIR__ . '/data.php';
require __DIR__ . '/../libs/SimplePdf.php';

$data = ky_data();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 301);
$orcamento = null;
foreach ($data['orcamentos'] as $item) {
  if ((int)$item['id'] === $id) { $orcamento = $item; break; }
}
if (!$orcamento) { json_response(['success'=>false, 'message'=>'Orçamento não encontrado.']); }

$token = bin2hex(random_bytes(8));
$fileName = strtolower($orcamento['numero']) . '-' . $token . '.pdf';
$dir = __DIR__ . '/../storage/orcamentos';
if (!is_dir($dir)) { mkdir($dir, 0775, true); }
$filePath = $dir . '/' . $fileName;

$lines = [
  'K.Yamaguchi Refrigeração - Orçamento Técnico',
  'Número: ' . $orcamento['numero'],
  'Cliente: ' . $orcamento['cliente'],
  'Telefone/WhatsApp: ' . $orcamento['telefone'],
  'Data de emissão: ' . date('d/m/Y'),
  'Validade: ' . $orcamento['validade'],
  'Status: ' . $orcamento['status'],
  'Responsável: ' . $orcamento['responsavel'],
  '',
  'Itens do orçamento:',
  '1. Manutenção corretiva em sistema de refrigeração - R$ 450,00',
  '2. Peças e materiais técnicos - R$ 780,00',
  '3. Mão de obra especializada - R$ 250,00',
  '',
  'Total: ' . money_br($orcamento['total']),
  '',
  'Condições:',
  '- Orçamento sujeito à disponibilidade de peças.',
  '- Valores válidos até a data informada.',
  '- Serviço executado após aprovação do cliente.',
  '',
  'Documento gerado automaticamente pelo K.Yamaguchi Service.'
];
$pdf = new SimplePdf();
$pdf->addPage($lines);
$pdf->output($filePath);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\');
$publicUrl = $scheme . '://' . $host . $basePath . '/storage/orcamentos/' . rawurlencode($fileName);

json_response(['success'=>true, 'pdf_url'=>$publicUrl, 'file'=>$fileName, 'orcamento'=>$orcamento]);
