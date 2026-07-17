<?php

declare(strict_types=1);

namespace App\CRM\Import;

use InvalidArgumentException;
use Smalot\PdfParser\Parser;
use Throwable;

final class ClientPdfParser
{
    private const MAX_PAGES = 200;
    private const MAX_CLIENTS = 5000;

    public function __construct(private readonly A7ClientReportMapper $mapper)
    {
    }

    /** @return array{pages:int,rows:array<int,array<string,mixed>>} */
    public function parse(string $filePath): array
    {
        if (!class_exists(Parser::class)) {
            throw new InvalidArgumentException('O leitor de PDF não está instalado. Execute composer install no servidor.');
        }
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException('O arquivo PDF enviado não pôde ser lido.');
        }

        try {
            $document = (new Parser())->parseFile($filePath);
            $pages = $document->getPages();
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('O PDF está corrompido, protegido ou não pôde ser interpretado.', 0, $exception);
        }

        $pageCount = count($pages);
        if ($pageCount === 0 || $pageCount > self::MAX_PAGES) {
            throw new InvalidArgumentException('O PDF deve ter entre 1 e ' . self::MAX_PAGES . ' páginas.');
        }

        $rows = [];
        $seenCodes = [];
        foreach ($pages as $index => $page) {
            try {
                $pageRows = $this->mapper->mapPage($page->getDataTm(), $index + 1);
            } catch (Throwable $exception) {
                throw new InvalidArgumentException('Não foi possível interpretar a página ' . ($index + 1) . ' do PDF.', 0, $exception);
            }

            foreach ($pageRows as $row) {
                $sourceCode = (string) $row['source_code'];
                if (isset($seenCodes[$sourceCode])) {
                    throw new InvalidArgumentException('O PDF possui o código de cliente ' . $sourceCode . ' repetido.');
                }
                $seenCodes[$sourceCode] = true;
                $rows[] = $row;
                if (count($rows) > self::MAX_CLIENTS) {
                    throw new InvalidArgumentException('O PDF excede o limite de ' . self::MAX_CLIENTS . ' clientes.');
                }
            }
        }

        if ($rows === []) {
            throw new InvalidArgumentException('Nenhum cliente foi encontrado. Use o relatório "RELATÓRIO DE CLIENTES 2" do A7.');
        }

        return ['pages' => $pageCount, 'rows' => $rows];
    }
}
