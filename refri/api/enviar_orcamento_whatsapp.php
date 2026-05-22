<?php
require __DIR__ . '/data.php';

$data = ky_data();
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 301);
$pdfUrl = $_POST['pdf_url'] ?? $_GET['pdf_url'] ?? '';
$orcamento = null;
foreach ($data['orcamentos'] as $item) { if ((int)$item['id'] === $id) { $orcamento = $item; break; } }
if (!$orcamento || !$pdfUrl) { json_response(['success'=>false,'message'=>'Dados insuficientes para envio.']); }

$message = "Olá, {$orcamento['cliente']}! Tudo bem?\n\nSegue o orçamento {$orcamento['numero']} da K.Yamaguchi Refrigeração.\nValor total: " . money_br($orcamento['total']) . "\nValidade: {$orcamento['validade']}\n\nAcesse o PDF pelo link:\n{$pdfUrl}\n\nQualquer dúvida, estamos à disposição.";
$phone = preg_replace('/\D+/', '', $orcamento['telefone']);
$whatsappUrl = 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);

$cloudToken = getenv('WHATSAPP_CLOUD_TOKEN');
$phoneNumberId = getenv('WHATSAPP_PHONE_NUMBER_ID');
$cloudResult = null;

if ($cloudToken && $phoneNumberId) {
  $payload = [
    'messaging_product' => 'whatsapp',
    'to' => $phone,
    'type' => 'document',
    'document' => [
      'link' => $pdfUrl,
      'caption' => $message,
      'filename' => $orcamento['numero'] . '.pdf'
    ]
  ];
  $ch = curl_init('https://graph.facebook.com/v19.0/' . $phoneNumberId . '/messages');
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $cloudToken, 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT => 20,
  ]);
  $response = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  curl_close($ch);
  $cloudResult = ['http_status'=>$status, 'response'=>$response, 'error'=>$error];
}

json_response([
  'success'=>true,
  'mode'=> ($cloudToken && $phoneNumberId) ? 'business_api' : 'wa_me_link',
  'whatsapp_url'=>$whatsappUrl,
  'message'=>$message,
  'cloud_result'=>$cloudResult
]);
