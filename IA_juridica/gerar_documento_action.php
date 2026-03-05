<?php
/**
 * Main Controller for Document Generation
 */

require_once __DIR__ . '/api/openai_client.php';
require_once __DIR__ . '/api/Documento.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo_documento'] ?? '';
    $destinatario = $_POST['destinatario'] ?? '';
    $assunto = $_POST['assunto'] ?? '';
    $solicitacao = $_POST['solicitacao'] ?? '';
    $responsavel = $_POST['responsavel'] ?? '';
    $cargo = $_POST['cargo'] ?? '';
    $cidade = $_POST['cidade'] ?? 'Brasília';
    $data_doc = $_POST['data'] ?? date('Y-m-d');

    if (empty($tipo) || empty($destinatario) || empty($assunto)) {
        die("Erro: Campos obrigatórios ausentes.");
    }

    try {
        $docManager = new Documento();
        $aiClient = new OpenAIClient();

        // 1. Get next number
        $proximoNumero = $docManager->getNextNumber($tipo);
        $ano = date('Y', strtotime($data_doc));
        $numeroFormatado = strtoupper($tipo) . " Nº $proximoNumero/$ano";

        // 2. Load template
        $templatePath = __DIR__ . "/modelos/" . strtolower($tipo) . ".txt";
        if (!file_exists($templatePath)) {
            $template = "Tipo de documento não reconhecido. Use formato padrão.";
        } else {
            $template = file_get_contents($templatePath);
        }

        // 3. Prepare AI Prompt Data
        $dataAI = [
            'numero' => $proximoNumero,
            'ano' => $ano,
            'destinatario' => $destinatario,
            'assunto' => $assunto,
            'solicitacao' => $solicitacao,
            'responsavel' => $responsavel,
            'cargo' => $cargo,
            'cidade' => $cidade,
            'data' => date('d/m/Y', strtotime($data_doc))
        ];

        // 4. Generate with IA
        $conteudoGerado = $aiClient->generateDocument($tipo, $template, $dataAI);

        // 5. Save to Database
        $id = $docManager->save([
            'tipo_documento' => $tipo,
            'numero_documento' => "$proximoNumero/$ano",
            'destinatario' => $destinatario,
            'assunto' => $assunto,
            'conteudo' => $conteudoGerado,
            'responsavel' => $responsavel,
            'cargo' => $cargo,
            'cidade' => $cidade,
            'data_documento' => $data_doc
        ]);

        // 6. Redirect to view
        header("Location: visualizar_documento.php?id=$id&success=1");
        exit;

    } catch (Exception $e) {
        die("Erro: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
    exit;
}
