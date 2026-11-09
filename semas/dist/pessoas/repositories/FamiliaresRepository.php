<?php

declare(strict_types=1);

class FamiliaresRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listarPorPessoa($solicitanteId)
    {
        try {
            $stmt = $this->pdo->prepare('SELECT nome, data_nascimento, parentesco, escolaridade, obs FROM familiares WHERE solicitante_id = :id ORDER BY id');
            $stmt->execute(array(':id' => (int)$solicitanteId));
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
        } catch (Throwable $e) {
            return array();
        }
    }
}
