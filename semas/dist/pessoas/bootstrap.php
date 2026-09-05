<?php

declare(strict_types=1);

/*
 * Usa exatamente a mesma camada de armazenamento do editarSolicitante.php.
 * As fotos/documentos podem estar fora do public_html e são servidos por
 * dist/arquivo.php após autenticação.
 */
require_once dirname(__DIR__, 2) . '/config/storage.php';

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/repositories/PessoasRepository.php';
require_once __DIR__ . '/repositories/SolicitacoesRepository.php';
require_once __DIR__ . '/repositories/FamiliaresRepository.php';
require_once __DIR__ . '/repositories/DocumentosRepository.php';
require_once __DIR__ . '/integrations/SigasConnection.php';
require_once __DIR__ . '/repositories/BeneficiosSigasRepository.php';
require_once __DIR__ . '/services/BeneficiosSigasService.php';
require_once __DIR__ . '/services/DetalhesPessoaService.php';
require_once __DIR__ . '/services/PessoasService.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException('Conexão principal não disponível.');
}

$pessoasRepository = new PessoasRepository($pdo);
$solicitacoesRepository = new SolicitacoesRepository($pdo);
$familiaresRepository = new FamiliaresRepository($pdo);
$documentosRepository = new DocumentosRepository($pdo);
$sigasRepository = new BeneficiosSigasRepository(SigasConnection::connection());
$beneficiosSigasService = new BeneficiosSigasService($sigasRepository);
$pessoasService = new PessoasService($pessoasRepository, $beneficiosSigasService);
$detalhesPessoaService = new DetalhesPessoaService($pessoasRepository, $solicitacoesRepository, $familiaresRepository, $documentosRepository, $beneficiosSigasService);
