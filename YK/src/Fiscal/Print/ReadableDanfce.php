<?php

declare(strict_types=1);

namespace App\Fiscal\Print;

use Com\Tecnick\Barcode\Barcode;
use NFePHP\DA\Legacy\Pdf;
use NFePHP\DA\NFe\Danfce;

/**
 * DANFCE com melhor legibilidade para impressão/PDF.
 *
 * Objetivos:
 * - não alterar arquivos em vendor/;
 * - aumentar as fontes mais críticas;
 * - reduzir o QR Code de 50 mm para 34 mm;
 * - recalcular alturas para evitar sobreposição;
 * - preservar o leiaute fiscal e os dados vindos do XML autorizado.
 */
final class ReadableDanfce extends Danfce
{
    private const QR_SIZE_MM = 30.0;

    private const FONT_HEADER = 9;
    private const FONT_NORMAL = 8;
    private const FONT_PRODUCT_CODE = 9;
    private const FONT_SMALL = 7;
    private const FONT_TOTAL = 10;

    public function __construct(string $xml)
    {
        parent::__construct($xml);

        /*
         * Arial tende a ficar mais legível do que Times em impressão
         * térmica e visualização reduzida do PDF.
         */
        $this->setFont('arial');

        /*
         * Largura padrão de bobina/PDF NFC-e.
         */
        $this->setPaperWidth(80);

        /*
         * Nova proporção da tabela:
         * mais espaço para o código do produto.
         */
        $this->descPercent = 0.34;

        /*
         * Margem de segurança para impressoras com área não imprimível.
         *
         * 5 mm evita que o DANFC-e fique colado principalmente
         * na borda superior e esquerda, reduzindo risco de corte
         * em impressoras como Epson L5590.
         */
        $this->setMargins(5);

        /*
         * Ajustes de altura para acomodar as fontes maiores.
         */
        $this->bloco1H = 24.0;
        $this->bloco2H = 15.0;
        $this->bloco4H = 17.0;
        $this->bloco6H = 12.0;
        $this->bloco7H = 29.0;

        /*
         * QR 30 mm + respiro.
         * O original usa bloco de 50 mm.
         */
        $this->bloco8H = 32.0;

        $this->bloco10H = 7.0;
    }

    /**
     * Sobrescreve a limitação do Danfce original.
     *
     * A biblioteca NFePHP limita setMargins() a no máximo 4 mm.
     * Para impressoras jato de tinta/laser com área física não imprimível
     * precisamos de 5 mm para evitar corte no topo e na esquerda.
     *
     * Não altera vendor/. A propriedade $margem é protected na classe pai.
     *
     * @param int|float $width
     */
    public function setMargins($width = 2)
    {
        $width = (float) $width;

        if ($width < 0 || $width > 8) {
            throw new \InvalidArgumentException(
                'As margens do DANFC-e devem estar entre 0 e 8 mm.'
            );
        }

        $this->margem = $width;
    }

    /**
     * Cabeçalho do emitente.
     */
    protected function blocoI()
    {
        $y = $this->margem;

        $emitRazao = $this->getTagValue(
            $this->emit,
            'xNome'
        );

        $emitCnpj = $this->getTagValue(
            $this->emit,
            'CNPJ'
        );

        $emitIE = $this->getTagValue(
            $this->emit,
            'IE'
        );

        $emitCnpj = $this->formatField(
            $emitCnpj,
            '###.###.###/####-##'
        );

        $emitLgr = $this->getTagValue(
            $this->enderEmit,
            'xLgr'
        );

        $emitNro = $this->getTagValue(
            $this->enderEmit,
            'nro'
        );

        $emitBairro = $this->getTagValue(
            $this->enderEmit,
            'xBairro'
        );

        $emitMun = $this->getTagValue(
            $this->enderEmit,
            'xMun'
        );

        $emitUF = $this->getTagValue(
            $this->enderEmit,
            'UF'
        );

        $emitFone = $this->getTagValue(
            $this->enderEmit,
            'fone'
        );

        if ($emitFone !== '') {
            if (strlen($emitFone) === 11) {
                $emitFone = $this->formatField(
                    $emitFone,
                    '(##) #####-####'
                );
            } else {
                $emitFone = $this->formatField(
                    $emitFone,
                    '(##) ####-####'
                );
            }
        }

        $maxHimg = $this->bloco1H - 4;

        if (!empty($this->logomarca)) {
            $xImg = $this->margem;
            $yImg = $this->margem + 1;

            $logoInfo = getimagesize(
                $this->logomarca
            );

            $logoWmm = ($logoInfo[0] / 72) * 25.4;
            $logoHmm = ($logoInfo[1] / 72) * 25.4;

            $nImgW = $this->wPrint / 4;
            $nImgH = round(
                $logoHmm
                * ($nImgW / $logoWmm),
                0
            );

            if ($nImgH > $maxHimg) {
                $nImgH = $maxHimg;

                $nImgW = round(
                    $logoWmm
                    * ($nImgH / $logoHmm),
                    0
                );
            }

            $xRs =
                $nImgW
                + $this->margem;

            $wRs =
                $this->wPrint
                - $nImgW;

            $alignH = 'L';

            $this->pdf->image(
                $this->logomarca,
                $xImg,
                $yImg,
                $nImgW,
                $nImgH,
                'jpeg'
            );
        } else {
            $xRs =
                $this->margem;

            $wRs =
                $this->wPrint;

            $alignH =
                'C';
        }

        /*
         * Razão social maior e em negrito.
         */
        $fontTitle = [
            'font' => $this->fontePadrao,
            'size' => self::FONT_HEADER,
            'style' => 'B',
        ];

        $y += $this->pdf->textBox(
            $xRs + 1,
            $this->margem,
            $wRs - 1,
            5,
            $emitRazao,
            $fontTitle,
            'T',
            $alignH,
            false,
            '',
            true
        );

        $font = [
            'font' => $this->fontePadrao,
            'size' => self::FONT_NORMAL,
            'style' => '',
        ];

        $lines = [
            'CNPJ: ' . $emitCnpj . '  IE: ' . $emitIE,
            trim($emitLgr . ', ' . $emitNro),
            $emitBairro,
            trim(
                $emitMun
                . '-'
                . $emitUF
                . (
                    $emitFone !== ''
                        ? '  Fone: ' . $emitFone
                        : ''
                )
            ),
        ];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $y += $this->pdf->textBox(
                $xRs + 1,
                $y,
                $wRs - 1,
                3.4,
                $line,
                $font,
                'T',
                $alignH,
                false,
                '',
                true
            );
        }

        $this->pdf->dashedHLine(
            $this->margem,
            $this->bloco1H,
            $this->wPrint,
            0.1,
            30
        );

        return $this->bloco1H;
    }

    /**
     * Título "Documento Auxiliar...".
     */
    protected function blocoII($y)
    {
        $font = [
            'font' => $this->fontePadrao,
            'size' => self::FONT_NORMAL,
            'style' => '',
        ];

        if ($this->tpEmis == 9) {
            $texto =
                'Documento Auxiliar da Nota Fiscal de Consumidor Eletronica';

            $y1 = $this->pdf->textBox(
                $this->margem,
                $y,
                $this->wPrint,
                4,
                $texto,
                $font,
                'T',
                'C',
                false,
                '',
                true
            );

            $texto =
                'Nao permite aproveitamento de credito de ICMS';

            $y1 += $this->pdf->textBox(
                $this->margem,
                $y + $y1,
                $this->wPrint,
                3,
                $texto,
                $font,
                'T',
                'C',
                false,
                '',
                true
            );

            $alertFont = [
                'font' => $this->fontePadrao,
                'size' => 10,
                'style' => 'B',
            ];

            $y2 = $this->pdf->textBox(
                $this->margem,
                $y + $y1,
                $this->wPrint,
                4,
                'EMITIDA EM CONTINGENCIA',
                $alertFont,
                'B',
                'C',
                false,
                '',
                true
            );

            if (!$this->infProt) {
                $pendingFont = [
                    'font' => $this->fontePadrao,
                    'size' => self::FONT_NORMAL,
                    'style' => 'I',
                ];

                $this->pdf->textBox(
                    $this->margem,
                    $y + $y1 + $y2,
                    $this->wPrint,
                    3,
                    'Pendente de autorizacao',
                    $pendingFont,
                    'B',
                    'C',
                    false,
                    '',
                    true
                );
            }
        } else {
            $texto =
                "Documento Auxiliar da Nota Fiscal de Consumidor Eletronica\n"
                . 'Nao permite aproveitamento de credito de ICMS';

            $this->pdf->textBox(
                $this->margem,
                $this->bloco1H - 1,
                $this->wPrint,
                $this->bloco2H,
                $texto,
                $font,
                'C',
                'C',
                false,
                '',
                true
            );
        }

        $this->pdf->dashedHLine(
            $this->margem,
            $this->bloco2H + $y,
            $this->wPrint,
            0.1,
            30
        );

        return $this->bloco2H + $y;
    }

    /**
     * Itens da NFC-e.
     *
     * O original usa 7 pt em papel 80 mm.
     * Aqui usamos 8 pt e recalculamos a altura das linhas.
     */
    protected function blocoIII($y)
    {
        if ($this->flagResume) {
            return $y;
        }

        $matrix = [
            0.16,
            0.34,
            0.08,
            0.09,
            0.165,
            0.165,
        ];

        $fontSize =
            $this->paperwidth < 70
                ? 6
                : self::FONT_NORMAL;

        $font = [
            'font' => $this->fontePadrao,
            'size' => $fontSize,
            'style' => '',
        ];

        $headerFont = [
            'font' => $this->fontePadrao,
            'size' => self::FONT_NORMAL,
            'style' => 'B',
        ];

        /*
         * Código do produto mais destacado.
         * Ex.: PRD-000075
         */
        $productCodeFont = [
            'font' => $this->fontePadrao,
            'size' => self::FONT_PRODUCT_CODE,
            'style' => 'B',
        ];

        $x = $this->margem;

        $x1 =
            $x
            + ($this->wPrint * $matrix[0]);

        $x2 =
            $x1
            + ($this->wPrint * $matrix[1]);

        $x3 =
            $x2
            + ($this->wPrint * $matrix[2]);

        $x4 =
            $x3
            + ($this->wPrint * $matrix[3]);

        $x5 =
            $x4
            + ($this->wPrint * $matrix[4]);

        $headers = [
            [$x,  $matrix[0], 'Codigo',    'L'],
            [$x1, $matrix[1], 'Descricao', 'L'],
            [$x2, $matrix[2], 'Qtde',      'C'],
            [$x3, $matrix[3], 'UN',        'C'],
            [$x4, $matrix[4], 'Vl Unit',   'C'],
            [$x5, $matrix[5], 'Vl Total',  'R'],
        ];

        $headerHeight = 3.6;

        foreach ($headers as $header) {
            $this->pdf->textBox(
                $header[0],
                $y,
                $this->wPrint * $header[1],
                $headerHeight,
                $header[2],
                $headerFont,
                'T',
                $header[3],
                false,
                '',
                true
            );
        }

        $y2 =
            $y
            + $headerHeight;

        foreach ($this->itens as $item) {
            $it = (object) $item;

            $this->pdf->textBox(
                $x,
                $y2,
                $this->wPrint * $matrix[0],
                $it->height,
                $it->codigo,
                $productCodeFont,
                'T',
                'L',
                false,
                '',
                true
            );

            $this->pdf->textBox(
                $x1,
                $y2,
                $this->wPrint * $matrix[1],
                $it->height,
                $it->desc,
                $font,
                'T',
                'L',
                false,
                '',
                false
            );

            $this->pdf->textBox(
                $x2,
                $y2,
                $this->wPrint * $matrix[2],
                $it->height,
                $it->qtd,
                $font,
                'T',
                'R',
                false,
                '',
                true
            );

            $this->pdf->textBox(
                $x3,
                $y2,
                $this->wPrint * $matrix[3],
                $it->height,
                $it->un,
                $font,
                'T',
                'C',
                false,
                '',
                true
            );

            $this->pdf->textBox(
                $x4,
                $y2,
                $this->wPrint * $matrix[4],
                $it->height,
                $it->vunit,
                $font,
                'T',
                'R',
                false,
                '',
                true
            );

            $this->pdf->textBox(
                $x5,
                $y2,
                $this->wPrint * $matrix[5],
                $it->height,
                $it->valor,
                $font,
                'T',
                'R',
                false,
                '',
                true
            );

            $y2 += $it->height;
        }

        $this->pdf->dashedHLine(
            $this->margem,
            $this->bloco3H + $y,
            $this->wPrint,
            0.1,
            30
        );

        return $this->bloco3H + $y;
    }

    protected function calculateHeightItens(
        $descriptionWidth
    ) {
        if ($this->flagResume) {
            return 0;
        }

        $fontSize =
            $this->paperwidth < 70
                ? 6
                : self::FONT_NORMAL;

        $totalHeight = 0;

        foreach ($this->det as $item) {
            $prod =
                $item
                    ->getElementsByTagName('prod')
                    ->item(0);

            $codigo =
                $this->getTagValue(
                    $prod,
                    'cProd'
                );

            $descricaoOriginal =
                $this->getTagValue(
                    $prod,
                    'xProd'
                );

            $descricao =
                function_exists('mb_substr')
                    ? mb_substr(
                        $descricaoOriginal,
                        0,
                        55,
                        'UTF-8'
                    )
                    : substr(
                        $descricaoOriginal,
                        0,
                        55
                    );

            $qtd =
                (float) $this->getTagValue(
                    $prod,
                    'qCom'
                );

            $un =
                $this->getTagValue(
                    $prod,
                    'uCom'
                );

            $vUnit =
                number_format(
                    (float) $this->getTagValue(
                        $prod,
                        'vUnCom'
                    ),
                    2,
                    ',',
                    '.'
                );

            $valor =
                number_format(
                    (float) $this->getTagValue(
                        $prod,
                        'vProd'
                    ),
                    2,
                    ',',
                    '.'
                );

            $tempPdf =
                new Pdf();

            $tempPdf->setFont(
                $this->fontePadrao,
                '',
                $fontSize
            );

            $lines =
                max(
                    1,
                    $tempPdf->wordWrap(
                        $descricao,
                        $descriptionWidth
                    )
                );

            /*
             * Limita a descrição a até 3 linhas,
             * mas sem reduzir a fonte.
             */
            $limit = 55;

            while (
                $lines > 3
                && $limit > 20
            ) {
                $limit--;

                $descricao =
                    function_exists('mb_substr')
                        ? mb_substr(
                            $descricaoOriginal,
                            0,
                            $limit,
                            'UTF-8'
                        )
                        : substr(
                            $descricaoOriginal,
                            0,
                            $limit
                        );

                $candidate =
                    $descricao;

                $lines =
                    $tempPdf->wordWrap(
                        $candidate,
                        $descriptionWidth
                    );
            }

            /*
             * Aproximadamente 3 mm por linha em 8 pt.
             */
            $height =
                max(
                    3.6,
                    ($lines * 3.1) + 0.5
                );

            $this->itens[] = [
                'codigo' => $codigo,
                'desc' => $descricao,
                'qtd' => $qtd,
                'un' => $un,
                'vunit' => $vUnit,
                'valor' => $valor,
                'height' => $height,
            ];

            $totalHeight += $height;
        }

        return
            $totalHeight
            + 4.0;
    }

    /**
     * Formas de pagamento.
     */
    protected function blocoV($y)
    {
        $this->bloco5H =
            $this->calculateHeightPag();

        $payments = [];

        if ($this->pag->length > 0) {
            foreach ($this->pag as $payment) {
                $payments[] = [
                    'tipo' => $this->pagType(
                        (int) $this->getTagValue(
                            $payment,
                            'tPag'
                        )
                    ),

                    'valor' => number_format(
                        (float) $this->getTagValue(
                            $payment,
                            'vPag'
                        ),
                        2,
                        ',',
                        '.'
                    ),
                ];
            }
        } else {
            $payments[] = [
                'tipo' => $this->pagType(
                    (int) $this->getTagValue(
                        $this->pag,
                        'tPag'
                    )
                ),

                'valor' => number_format(
                    (float) $this->getTagValue(
                        $this->pag,
                        'vPag'
                    ),
                    2,
                    ',',
                    '.'
                ),
            ];
        }

        $headerFont = [
            'font' => $this->fontePadrao,
            'size' => self::FONT_NORMAL,
            'style' => 'B',
        ];

        $lineFont = [
            'font' => $this->fontePadrao,
            'size' => self::FONT_NORMAL,
            'style' => '',
        ];

        $this->pdf->textBox(
            $this->margem,
            $y,
            $this->wPrint,
            4,
            'FORMA PAGAMENTO',
            $headerFont,
            'T',
            'L',
            false,
            '',
            false
        );

        $headerH = $this->pdf->textBox(
            $this->margem,
            $y,
            $this->wPrint,
            4,
            'VALOR PAGO R$',
            $headerFont,
            'T',
            'R',
            false,
            '',
            false
        );

        $z =
            $y
            + $headerH;

        foreach ($payments as $payment) {
            $this->pdf->textBox(
                $this->margem,
                $z,
                $this->wPrint,
                3.5,
                $payment['tipo'],
                $lineFont,
                'T',
                'L',
                false,
                '',
                false
            );

            $lineH = $this->pdf->textBox(
                $this->margem,
                $z,
                $this->wPrint,
                3.5,
                $payment['valor'],
                $lineFont,
                'T',
                'R',
                false,
                '',
                false
            );

            $z +=
                max(
                    3.5,
                    $lineH
                );
        }

        $this->pdf->textBox(
            $this->margem,
            $z,
            $this->wPrint,
            3.5,
            'Troco R$',
            $lineFont,
            'T',
            'L',
            false,
            '',
            false
        );

        $troco =
            !empty($this->vTroco)
                ? number_format(
                    (float) $this->vTroco,
                    2,
                    ',',
                    '.'
                )
                : '0,00';

        $this->pdf->textBox(
            $this->margem,
            $z,
            $this->wPrint,
            3.5,
            $troco,
            $lineFont,
            'T',
            'R',
            false,
            '',
            false
        );

        $this->pdf->dashedHLine(
            $this->margem,
            $this->bloco5H + $y,
            $this->wPrint,
            0.1,
            30
        );

        return
            $this->bloco5H
            + $y;
    }

    protected function calculateHeightPag()
    {
        $count =
            $this->pag->length > 0
                ? $this->pag->length
                : 1;

        return
            4.5
            + (3.5 * $count)
            + 4.0;
    }

    protected function pagType($type)
    {
        $list = [
            1 => 'Dinheiro',
            2 => 'Cheque',
            3 => 'Cartao de Credito',
            4 => 'Cartao de Debito',
            5 => 'Credito Loja',
            10 => 'Vale Alimentacao',
            11 => 'Vale Refeicao',
            12 => 'Vale Presente',
            13 => 'Vale Combustivel',
            15 => 'Boleto Bancario',
            16 => 'Deposito Bancario',
            17 => 'Pagamento Instantaneo (PIX)',
            18 => 'Transferencia bancaria / Carteira Digital',
            19 => 'Programa fidelidade / Cashback',
            90 => 'Sem pagamento',
            99 => 'Outros',
        ];

        $label =
            $list[$type]
            ?? 'Outros';

        return function_exists('mb_strtoupper')
            ? mb_strtoupper(
                $label,
                'UTF-8'
            )
            : strtoupper($label);
    }

    /**
     * Chave de acesso.
     */
    protected function blocoVI($y)
    {
        $titleFont = [
            'font' => $this->fontePadrao,
            'size' => 9,
            'style' => 'B',
        ];

        $font = [
            'font' => $this->fontePadrao,
            'size' => self::FONT_NORMAL,
            'style' => '',
        ];

        $y1 = $this->pdf->textBox(
            $this->margem,
            $y,
            $this->wPrint,
            3.5,
            'Consulte pela Chave de Acesso em:',
            $titleFont,
            'T',
            'C',
            false,
            '',
            false
        );

        $y2 = $this->pdf->textBox(
            $this->margem,
            $y + $y1,
            $this->wPrint,
            3,
            $this->urlChave,
            $font,
            'T',
            'C',
            false,
            '',
            true
        );

        $chave =
            str_replace(
                'NFe',
                '',
                $this->infNFe->getAttribute('Id')
            );

        $formatted =
            $this->formatField(
                $chave,
                $this->formatoChave
            );

        $this->pdf->textBox(
            $this->margem,
            $y + $y1 + $y2 + 0.5,
            $this->wPrint,
            3,
            $formatted,
            $font,
            'T',
            'C',
            false,
            '',
            true
        );

        $this->pdf->dashedHLine(
            $this->margem,
            $this->bloco6H + $y,
            $this->wPrint,
            0.1,
            30
        );

        return
            $this->bloco6H
            + $y;
    }

    /**
     * Consumidor, número da NFC-e e protocolo.
     */
    protected function blocoVII($y)
    {
        $nome =
            $this->getTagValue(
                $this->dest,
                'xNome'
            );

        $cnpj =
            $this->getTagValue(
                $this->dest,
                'CNPJ'
            );

        $cpf =
            $this->getTagValue(
                $this->dest,
                'CPF'
            );

        $rua =
            $this->getTagValue(
                $this->enderDest,
                'xLgr'
            );

        $numero =
            $this->getTagValue(
                $this->enderDest,
                'nro'
            );

        $complemento =
            $this->getTagValue(
                $this->enderDest,
                'xCpl'
            );

        $bairro =
            $this->getTagValue(
                $this->enderDest,
                'xBairro'
            );

        $mun =
            $this->getTagValue(
                $this->enderDest,
                'xMun'
            );

        $uf =
            $this->getTagValue(
                $this->enderDest,
                'UF'
            );

        if ($cnpj !== '') {
            $texto =
                'CONSUMIDOR - CNPJ '
                . $this->formatField(
                    $cnpj,
                    '##.###.###/####-##'
                )
                . ' - '
                . $nome;
        } elseif ($cpf !== '') {
            $texto =
                'CONSUMIDOR - CPF '
                . $this->formatField(
                    $cpf,
                    '###.###.###-##'
                )
                . ' - '
                . $nome;
        } else {
            $texto =
                'CONSUMIDOR NAO IDENTIFICADO';
        }

        if ($rua !== '') {
            $texto .=
                "\n"
                . trim(
                    $rua
                    . ', '
                    . $numero
                    . ' '
                    . $complemento
                    . ' '
                    . $bairro
                    . ' '
                    . $mun
                    . '-'
                    . $uf
                );
        }

        $xMsg =
            $this->getTagValue(
                $this->nfeProc,
                'xMsg'
            );

        if ($xMsg !== '') {
            $texto .=
                "\n"
                . $xMsg;

            $this->bloco7H +=
                4;
        }

        $font = [
            'font' => $this->fontePadrao,
            'size' => self::FONT_NORMAL,
            'style' => '',
        ];

        $y1 = $this->pdf->textBox(
            $this->margem,
            $y + 1,
            $this->wPrint,
            8,
            $texto,
            $font,
            'T',
            'C',
            false,
            '',
            false
        );

        $num =
            str_pad(
                $this->getTagValue(
                    $this->ide,
                    'nNF'
                ),
                9,
                '0',
                STR_PAD_LEFT
            );

        $serie =
            str_pad(
                $this->getTagValue(
                    $this->ide,
                    'serie'
                ),
                3,
                '0',
                STR_PAD_LEFT
            );

        $data =
            (
                new \DateTime(
                    $this->getTagValue(
                        $this->ide,
                        'dhEmi'
                    )
                )
            )->format(
                'd/m/Y H:i:s'
            );

        $numberFont = [
            'font' => $this->fontePadrao,
            'size' => 9,
            'style' => 'B',
        ];

        $y2 = $this->pdf->textBox(
            $this->margem,
            $y + 1 + $y1,
            $this->wPrint,
            4,
            'NFC-e n. '
                . $num
                . ' Serie '
                . $serie
                . ' '
                . $data,
            $numberFont,
            'T',
            'C',
            false,
            '',
            true
        );

        $protocolo = '';
        $dhRecbto = '';

        if (!empty($this->infProt)) {
            $protocolo =
                $this->formatField(
                    $this->getTagValue(
                        $this->infProt,
                        'nProt'
                    ),
                    '### ########## ##'
                );

            $received =
                $this->getTagValue(
                    $this->infProt,
                    'dhRecbto'
                );

            if ($received !== '') {
                $dhRecbto =
                    (
                        new \DateTime(
                            $received
                        )
                    )->format(
                        'd/m/Y H:i:s'
                    );
            }
        }

        /*
         * Mantém os avisos de contingência.
         */
        if ($this->tpEmis == 9) {
            $viaH = $this->pdf->textBox(
                $this->margem,
                $y + 1 + $y1 + $y2,
                $this->wPrint,
                4,
                $this->via,
                $numberFont,
                'T',
                'C',
                false,
                '',
                true
            );

            $alertFont = [
                'font' => $this->fontePadrao,
                'size' => 10,
                'style' => 'B',
            ];

            $alertH = $this->pdf->textBox(
                $this->margem,
                $y + 1 + $y1 + $y2 + $viaH,
                $this->wPrint,
                4,
                'EMITIDA EM CONTINGENCIA',
                $alertFont,
                'B',
                'C',
                false,
                '',
                true
            );

            if ($protocolo === '') {
                $pendingFont = [
                    'font' => $this->fontePadrao,
                    'size' => self::FONT_NORMAL,
                    'style' => 'I',
                ];

                $this->pdf->textBox(
                    $this->margem,
                    $y
                        + 1
                        + $y1
                        + $y2
                        + $viaH
                        + $alertH,
                    $this->wPrint,
                    4,
                    'Pendente de autorizacao',
                    $pendingFont,
                    'T',
                    'C',
                    false,
                    '',
                    true
                );
            } else {
                $this->blocoVIIProt(
                    $y
                        + 1
                        + $y1
                        + $y2
                        + $viaH
                        + $alertH,
                    0,
                    $protocolo,
                    $dhRecbto
                );
            }
        } else {
            $this->blocoVIIProt(
                $y + 1 + $y1 + $y2,
                0,
                $protocolo,
                $dhRecbto
            );
        }

        $this->pdf->dashedHLine(
            $this->margem,
            $this->bloco7H + $y,
            $this->wPrint,
            0.1,
            30
        );

        return
            $this->bloco7H
            + $y;
    }

    protected function blocoVIIProt(
        $y,
        $subSize,
        $protocolo,
        $dhRecbto
    ) {
        if ($protocolo === '') {
            return 0;
        }

        $font = [
            'font' => $this->fontePadrao,
            'size' => self::FONT_NORMAL,
            'style' => '',
        ];

        $y1 = $this->pdf->textBox(
            $this->margem,
            $y,
            $this->wPrint,
            4,
            'Protocolo de Autorizacao: '
                . $protocolo,
            $font,
            'T',
            'C',
            false,
            '',
            true
        );

        return $this->pdf->textBox(
            $this->margem,
            $y + $y1,
            $this->wPrint,
            4,
            'Data de Autorizacao: '
                . $dhRecbto,
            $font,
            'T',
            'C',
            false,
            '',
            true
        );
    }

    /**
     * QR Code reduzido.
     *
     * Original: 50 x 50 mm.
     * Ajustado: 34 x 34 mm.
     */
    protected function blocoVIII($y)
    {
        $y += 1;

        $barcode =
            new Barcode();

        $barcodeObject =
            $barcode->getBarcodeObj(
                'QRCODE,M',
                $this->qrCode,
                -4,
                -4,
                'black',
                [-2, -2, -2, -2]
            )->setBackgroundColor(
                'white'
            );

        $qrCode =
            $barcodeObject
                ->getPngData();

        $qrSize =
            self::QR_SIZE_MM;

        /*
         * Centraliza considerando margem e largura útil.
         */
        $xQr =
            $this->margem
            + (
                (
                    $this->wPrint
                    - $qrSize
                )
                / 2
            );

        $pic =
            'data://text/plain;base64,'
            . base64_encode(
                $qrCode
            );

        $this->pdf->image(
            $pic,
            $xQr,
            $y,
            $qrSize,
            $qrSize,
            'PNG'
        );

        return
            $this->bloco8H
            + $y;
    }

    /**
     * Tributos e informações adicionais.
     */
    protected function blocoIX($y)
    {
        $font = [
            'font' => $this->fontePadrao,
            'size' => self::FONT_NORMAL,
            'style' => '',
        ];

        $valor =
            $this->getTagValue(
                $this->ICMSTot,
                'vTotTrib'
            );

        $tributos =
            $valor !== ''
                ? number_format(
                    (float) $valor,
                    2,
                    ',',
                    '.'
                )
                : '-----';

        $this->pdf->textBox(
            $this->margem,
            $y,
            $this->wPrint,
            4,
            'Tributos totais Incidentes (Lei Federal 12.741/2012): R$ '
                . $tributos,
            $font,
            'T',
            'C',
            false,
            '',
            true
        );

        $this->pdf->textBox(
            $this->margem,
            $y + 4,
            $this->wPrint,
            max(
                4,
                $this->bloco9H - 4
            ),
            str_replace(
                ';',
                "\n",
                (string) $this->infCpl
            ),
            $font,
            'T',
            'L',
            false,
            '',
            false
        );

        return
            $y
            + $this->bloco9H;
    }

    protected function calculateHeighBlokIX()
    {
        $paper = [
            $this->paperwidth,
            100,
        ];

        $wPrint =
            $this->paperwidth
            - (2 * $this->margem);

        $pdf =
            new Pdf(
                'P',
                'mm',
                $paper
            );

        $font = [
            'font' => $this->fontePadrao,
            'size' => self::FONT_NORMAL,
            'style' => '',
        ];

        $lines =
            str_replace(
                ';',
                "\n",
                (string) $this->infCpl
            );

        $numLines =
            $pdf->getNumLines(
                $lines,
                $wPrint,
                $font
            );

        if (!empty($this->textoExtra)) {
            $numLines +=
                $pdf->getNumLines(
                    str_replace(
                        ';',
                        "\n",
                        (string) $this->textoExtra
                    ),
                    $wPrint,
                    $font
                );
        }

        /*
         * Cabeçalho de tributos + linhas adicionais.
         */
        return
            max(
                7,
                (int) ceil(
                    4
                    + ($numLines * 3.2)
                )
            );
    }

    /**
     * Créditos/texto extra.
     */
    protected function blocoX($y)
    {
        $font = [
            'font' => $this->fontePadrao,
            'size' => self::FONT_SMALL,
            'style' => 'I',
        ];

        if (!empty($this->creditos)) {
            $y += 3;

            $this->pdf->textBox(
                $this->margem,
                $y,
                $this->wPrint,
                4,
                $this->creditos,
                $font,
                'T',
                'R',
                false,
                '',
                true
            );
        }

        if (!empty($this->textoExtra)) {
            $y += 3;

            $this->pdf->textBox(
                $this->margem,
                $y,
                $this->wPrint,
                4,
                $this->textoExtra,
                $font,
                'T',
                'L',
                false,
                '',
                true
            );
        }

        return
            $this->bloco10H
            + $y;
    }
}
