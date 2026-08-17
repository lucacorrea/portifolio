<?php

declare(strict_types=1);

namespace App\Nfse\Service;

use DOMDocument;
use InvalidArgumentException;

final class DpsSchemaValidator
{
    public function validate(string $xml, string $schemaPath): void
    {
        $real = realpath($schemaPath);
        if ($real === false || !is_file($real) || strtolower(pathinfo($real, PATHINFO_EXTENSION)) !== 'xsd') {
            throw new InvalidArgumentException('XSD NFS-e local não configurado.');
        }
        $dom = new DOMDocument();
        if (!@$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            throw new InvalidArgumentException('XML DPS inválido.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            if (!@$dom->schemaValidate($real)) {
                $errors = libxml_get_errors();
                $message = isset($errors[0]) ? trim($errors[0]->message) : 'estrutura incompatível';
                throw new InvalidArgumentException('A DPS não passou no XSD local: ' . $message);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
