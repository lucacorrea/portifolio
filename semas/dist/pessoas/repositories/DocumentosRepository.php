<?php

declare(strict_types=1);

class DocumentosRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listarPorPessoa($solicitanteId)
    {
        try {
            $solicitacaoField = pc_table_has_column($this->pdo, 'solicitante_documentos', 'solicitacao_id')
                ? 'solicitacao_id'
                : 'NULL AS solicitacao_id';
            $sizeField = pc_table_has_column($this->pdo, 'solicitante_documentos', 'size_bytes')
                ? 'size_bytes'
                : 'NULL AS size_bytes';

            $sql = 'SELECT id, ' . $solicitacaoField . ', arquivo_path, original_name, mime_type, ' . $sizeField . ', created_at
                    FROM solicitante_documentos
                    WHERE solicitante_id = :id
                    ORDER BY created_at DESC, id DESC';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array(':id' => (int)$solicitanteId));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();

            foreach ($rows as &$row) {
                $row['arquivo_url'] = $this->resolverUrl(isset($row['arquivo_path']) ? $row['arquivo_path'] : '');
            }
            unset($row);

            return $rows;
        } catch (Throwable $e) {
            return array();
        }
    }

    private function resolverUrl($path)
    {
        $path = trim((string)$path);
        if ($path === '') {
            return '';
        }

        /* Mesma camada segura usada pelas fotos e pelo editarSolicitante.php. */
        if (
            function_exists('semas_storage_absolute_path')
            && function_exists('semas_storage_public_url')
            && semas_storage_absolute_path($path) !== ''
        ) {
            return (string)semas_storage_public_url($path);
        }

        return '';
    }
}
