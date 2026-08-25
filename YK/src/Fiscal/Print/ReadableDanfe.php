<?php

declare(strict_types=1);

namespace App\Fiscal\Print;

use NFePHP\DA\NFe\Danfe;

/**
 * DANFE legível para NF-e modelo 55.
 *
 * Separado do DANFC-e:
 * - papel A4;
 * - retrato;
 * - fonte Arial/Helvetica;
 * - margem superior 8 mm;
 * - margem esquerda 8 mm;
 * - sem alterações em vendor/.
 */
final class ReadableDanfe extends Danfe
{
    private const TOP_MARGIN_MM = 8;
    private const LEFT_MARGIN_MM = 8;

    public function __construct(string $xml)
    {
        parent::__construct($xml);

        $this->setDefaultFont('arial');

        $this->printParameters(
            'P',
            'A4',
            self::TOP_MARGIN_MM,
            self::LEFT_MARGIN_MM
        );
    }
}
