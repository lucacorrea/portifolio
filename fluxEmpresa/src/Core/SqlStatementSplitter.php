<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

final class SqlStatementSplitter
{
    /** @return string[] */
    public static function split(string $sql): array
    {
        if (str_contains($sql, "\0")) {
            throw new InvalidArgumentException('Arquivo de migration inválido.');
        }

        $statements = [];
        $buffer = '';
        $quote = null;
        $lineComment = false;
        $blockComment = false;
        $length = strlen($sql);

        for ($index = 0; $index < $length; ++$index) {
            $character = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : '';

            if ($lineComment) {
                $buffer .= $character;
                if ($character === "\n") {
                    $lineComment = false;
                }
                continue;
            }
            if ($blockComment) {
                $buffer .= $character;
                if ($character === '*' && $next === '/') {
                    $buffer .= '/';
                    ++$index;
                    $blockComment = false;
                }
                continue;
            }
            if ($quote !== null) {
                $buffer .= $character;
                if ($character === '\\' && $next !== '') {
                    $buffer .= $next;
                    ++$index;
                    continue;
                }
                if ($character === $quote) {
                    if ($next === $quote) {
                        $buffer .= $next;
                        ++$index;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($character === '-' && $next === '-' && ($index + 2 >= $length || ctype_space($sql[$index + 2]))) {
                $buffer .= '--';
                ++$index;
                $lineComment = true;
                continue;
            }
            if ($character === '#') {
                $buffer .= $character;
                $lineComment = true;
                continue;
            }
            if ($character === '/' && $next === '*') {
                $buffer .= '/*';
                ++$index;
                $blockComment = true;
                continue;
            }
            if ($character === "'" || $character === '"' || $character === '`') {
                $quote = $character;
                $buffer .= $character;
                continue;
            }
            if ($character === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }
            $buffer .= $character;
        }

        if ($quote !== null || $blockComment) {
            throw new InvalidArgumentException('Arquivo de migration com SQL incompleto.');
        }
        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }
        return $statements;
    }
}
