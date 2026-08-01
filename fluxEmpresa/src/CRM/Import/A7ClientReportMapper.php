<?php

declare(strict_types=1);

namespace App\CRM\Import;

final class A7ClientReportMapper
{
    /** @return array<int, array<string, mixed>> */
    public function mapPage(array $positionedText, int $pageNumber): array
    {
        $items = [];
        foreach ($positionedText as $entry) {
            $matrix = is_array($entry[0] ?? null) ? $entry[0] : [];
            $text = $this->normalizeText($this->flattenText($entry[1] ?? ''));
            if ($text === '') {
                continue;
            }

            $items[] = [
                'x' => (float) ($matrix[4] ?? 0),
                'y' => (float) ($matrix[5] ?? 0),
                'text' => $text,
            ];
        }

        $rows = [];
        foreach ($items as $anchor) {
            if (!$this->isClientCodeAnchor($anchor)) {
                continue;
            }

            $rowY = $anchor['y'] + 1.5;
            $rowItems = array_values(array_filter(
                $items,
                static fn(array $item): bool => $item['x'] > 55
                    && abs($item['y'] - $rowY) <= 2.6
            ));
            usort($rowItems, static fn(array $left, array $right): int => $left['x'] <=> $right['x']);

            $name = $this->columnText($rowItems, 55, 215);
            if ($name === '') {
                continue;
            }

            $sourceCode = $anchor['text'];
            $fantasy = $this->columnText($rowItems, 215, 325);
            $rawAddress = $this->columnText($rowItems, 325, 570);
            $city = $this->nullable($this->columnText($rowItems, 570, 650));
            $rawState = strtoupper($this->columnText($rowItems, 650, 675));
            $state = preg_match('/^[A-Z]{2}$/', $rawState) === 1 ? $rawState : null;
            $areaCode = preg_replace('/\D+/', '', $this->columnText($rowItems, 675, 698)) ?? '';
            $rawPhone = $this->columnText($rowItems, 698, 755);
            $rawSecondPhone = $this->columnText($rowItems, 755, 850);
            $phone = $this->formatPhone($rawPhone, $areaCode);
            $secondPhone = $this->formatPhone($rawSecondPhone, $areaCode);
            if ($phone === null && $secondPhone !== null) {
                $phone = $secondPhone;
                $secondPhone = null;
            }

            $address = $this->splitAddress($rawAddress);
            $notes = ['Importado do relatório de clientes A7. Código original: ' . $sourceCode . '.'];
            if ($rawState !== '' && $state === null) {
                $notes[] = 'UF anterior não reconhecida: ' . $rawState . '.';
            }
            if ($rawPhone !== '' && $this->formatPhone($rawPhone, $areaCode) === null) {
                $notes[] = 'Telefone anterior pendente de revisão: ' . $rawPhone . '.';
            }
            if ($rawSecondPhone !== '' && $this->formatPhone($rawSecondPhone, $areaCode) === null) {
                $notes[] = 'Telefone 2 anterior pendente de revisão: ' . $rawSecondPhone . '.';
            }
            if ($fantasy !== '') {
                $notes[] = 'Nome fantasia: ' . $fantasy . '.';
            }
            if ($secondPhone !== null) {
                $notes[] = 'Telefone 2 no sistema anterior: ' . $secondPhone . '.';
            }

            $rows[] = [
                'source_code' => $sourceCode,
                'code' => 'A7-' . $sourceCode,
                'page' => $pageNumber,
                'person_type' => 'fisica',
                'name' => $name,
                'document' => null,
                'phone' => $phone,
                'whatsapp' => null,
                'email' => null,
                'address' => $address['address'],
                'number' => $address['number'],
                'complement' => null,
                'district' => $address['district'],
                'city' => $city,
                'state' => $state,
                'zip_code' => null,
                'notes' => implode(' ', $notes),
                'status' => 'ativo',
            ];
        }

        return $rows;
    }

    private function isClientCodeAnchor(array $item): bool
    {
        return $item['x'] >= 20
            && $item['x'] <= 55
            && $item['y'] >= 55
            && $item['y'] < 510
            && preg_match('/^\d{1,10}$/', $item['text']) === 1;
    }

    private function columnText(array $items, float $minimumX, float $maximumX): string
    {
        $parts = [];
        foreach ($items as $item) {
            if ($item['x'] >= $minimumX && $item['x'] < $maximumX) {
                $parts[] = $item['text'];
            }
        }

        return $this->normalizeText(implode(' ', $parts));
    }

    /** @return array{address:?string,number:?string,district:?string} */
    private function splitAddress(string $value): array
    {
        $value = $this->normalizeText($value);
        if ($value === '' || preg_match('/^[,;\s.\-]+$/u', $value) === 1) {
            return ['address' => null, 'number' => null, 'district' => null];
        }

        $address = $value;
        $number = null;
        $district = null;
        if (preg_match('/^(.*),\s*(.*?)\s+-\s*(.*)$/u', $value, $matches) === 1) {
            $address = $matches[1];
            $number = $matches[2];
            $district = $matches[3];
        } elseif (preg_match('/^(.*?)\s+-\s+(.+)$/u', $value, $matches) === 1) {
            $address = $matches[1];
            $district = $matches[2];
        }

        $address = $this->nullablePlaceholder($address);
        $number = $this->nullablePlaceholder($number);
        $district = $this->nullablePlaceholder($district);
        if ($address === null && $district === null && $number !== null && preg_match('/\d/u', $number) !== 1) {
            $district = $number;
            $number = null;
        }

        return [
            'address' => $this->limit($address, 150),
            'number' => $this->limit($number, 30),
            'district' => $this->limit($district, 100),
        ];
    }

    private function formatPhone(string $value, string $areaCode): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            return null;
        }

        if (($length = strlen($digits)) >= 8 && $length <= 9 && strlen($areaCode) === 2) {
            $digits = $areaCode . $digits;
        }

        if (strlen($digits) === 11) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7, 4));
        }
        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6, 4));
        }
        if (strlen($digits) === 9) {
            return substr($digits, 0, 5) . '-' . substr($digits, 5, 4);
        }
        if (strlen($digits) === 8) {
            return substr($digits, 0, 4) . '-' . substr($digits, 4, 4);
        }

        return null;
    }

    private function flattenText(mixed $value): string
    {
        if (!is_array($value)) {
            return (string) $value;
        }

        $text = '';
        foreach ($value as $part) {
            $text .= is_array($part) && array_key_exists('c', $part)
                ? $this->flattenText($part['c'])
                : $this->flattenText($part);
        }
        return $text;
    }

    private function normalizeText(string $value): string
    {
        $value = str_replace("\0", '', $value);
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private function nullable(?string $value): ?string
    {
        $value = $this->normalizeText((string) $value);
        return $value === '' ? null : $value;
    }

    private function nullablePlaceholder(?string $value): ?string
    {
        $value = $this->nullable($value);
        if ($value === null || preg_match('/^(?:-|--|---|s\/?n|n\/?a|nº?)$/iu', $value) === 1) {
            return null;
        }
        return $value;
    }

    private function limit(?string $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }
        return mb_substr($value, 0, $length);
    }
}
