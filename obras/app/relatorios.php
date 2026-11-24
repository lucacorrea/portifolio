<?php
/**
 * OBRAS360 | Relatórios gerenciais, impressão e XLSX.
 * Fonte: banco MySQL autenticado, nunca dados fictícios do JavaScript.
 */
declare(strict_types=1);
require_once __DIR__ . '/data.php';

function rpt_lower(string $s): string { return function_exists('mb_strtolower') ? mb_strtolower($s,'UTF-8') : strtolower($s); }
function rpt_cut(string $s, int $max): string { return function_exists('mb_substr') ? mb_substr($s,0,$max,'UTF-8') : substr($s,0,$max); }
function rpt_contains(string $s,string $part): bool {
    if ($part==='') return true;
    return function_exists('mb_strpos') ? mb_strpos($s,$part,0,'UTF-8') !== false : strpos($s,$part)!==false;
}
function rpt_text(mixed $value): string {
    if (is_bool($value)) return $value ? 'Sim' : 'Não';
    if ($value === null || $value === '') return '—';
    if (is_array($value) || is_object($value)) return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '—';
    return (string)$value;
}
function rpt_html(mixed $value): string { return htmlspecialchars(rpt_text($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function rpt_currency(mixed $value): string { return 'R$ ' . number_format((float)$value, 2, ',', '.'); }
function rpt_num(mixed $value, int $dec = 0): string { return number_format((float)$value, $dec, ',', '.'); }
function rpt_date(string $date): string {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $m)) return $date ?: '—';
    return $m[3] . '/' . $m[2] . '/' . $m[1];
}
function rpt_query_string(string $key, int $max=150): string {
    $v = $_GET[$key] ?? '';
    if (!is_string($v)) throw new HttpError('Filtro inválido.', 400);
    return rpt_cut(trim($v), $max);
}
function rpt_filter_date(string $key): string {
    $s = rpt_query_string($key, 10);
    if ($s === '') return '';
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        throw new HttpError('Data inválida no relatório.', 400);
    }
    return $s;
}
function rpt_extra_columns(string $module): array {
    return match ($module) {
        'planejamento' => [['id','Código'],['title','Marco / atividade'],['work','Obra relacionada'],['date','Data prevista'],['status','Situação']],
        'movimentacoes' => [['id','Lançamento'],['material','Material'],['tipo','Movimentação'],['quantidade','Quantidade'],['data','Data'],['observacao','Observação']],
        'mapa' => [['id','Código'],['module_name','Módulo'],['title','Registro'],['local','Localização'],['status','Situação'],['lat','Latitude'],['lng','Longitude']],
        'usuarios' => [['username','Usuário'],['name','Nome'],['email','E-mail'],['role','Perfil'],['active_label','Situação'],['last_login_at','Último acesso']],
        'auditoria' => [['id','ID'],['actor','Responsável'],['event','Evento'],['module','Módulo'],['record_code','Registro'],['created_at','Data / hora']],
        default => [],
    };
}
function rpt_module_title(string $module): string {
    return match ($module) {
        'executivo' => 'Panorama executivo da Secretaria',
        'planejamento' => 'Planejamento e cronogramas',
        'movimentacoes' => 'Histórico de movimentações do estoque',
        'mapa' => 'Relação territorial de registros georreferenciados',
        'usuarios' => 'Usuários e permissões',
        'auditoria' => 'Histórico de auditoria',
        default => modules()[$module]['title'] ?? 'Relatório gerencial',
    };
}
function rpt_allowed(string $module, array $who): void {
    $allowed = array_merge(array_keys(modules()), ['executivo','planejamento','movimentacoes','mapa','usuarios','auditoria']);
    if (!in_array($module, $allowed, true)) throw new HttpError('Relatório não encontrado.', 404);
    if (in_array($module, ['usuarios','auditoria'], true)) require_admin($who);
}
function rpt_columns(string $module, bool $extended): array {
    if (isset(modules()[$module])) {
        $def = modules()[$module];
        $list = $def['columns'];
        if ($extended) {
            $known = array_column($list, 0);
            foreach ($def['fields'] as $fld) {
                if (!in_array($fld[0], $known, true)) {
                    $list[] = [$fld[0], $fld[1]];
                    $known[] = $fld[0];
                }
            }
            if (in_array($module, ['obras','ordens','fiscalizacao','patrimonio'], true)) {
                foreach ([['lat','Latitude'],['lng','Longitude']] as $column) if (!in_array($column[0], $known, true)) $list[] = $column;
            }
        }
        return $list;
    }
    if ($module === 'executivo') return rpt_columns('obras', $extended);
    return rpt_extra_columns($module);
}
function rpt_rows(string $module, array $all, array $user): array {
    if ($module === 'executivo') return $all['obras'] ?? [];
    if ($module === 'planejamento') return $all['__milestones'] ?? [];
    if ($module === 'movimentacoes') return db()->query("SELECT CONCAT('MOV-',id) AS id, material_name AS material, type AS tipo, quantity AS quantidade, note AS observacao, created_at AS data FROM stock_movements ORDER BY id DESC")->fetchAll();
    if ($module === 'mapa') {
        $rows=[];
        foreach (['obras','ordens','fiscalizacao','patrimonio'] as $k) {
            foreach ($all[$k] ?? [] as $row) {
                if (!isset($row['lat'],$row['lng']) || !is_numeric($row['lat']) || !is_numeric($row['lng'])) continue;
                $row['module_name'] = rpt_module_title($k);
                $rows[] = $row;
            }
        }
        return $rows;
    }
    if ($module === 'usuarios') {
        $stmt=db()->query('SELECT username,name,email,role,active,last_login_at FROM users ORDER BY name ASC');
        return array_map(static function($row){$row['active_label'] = (int)$row['active']===1?'Ativo':'Desativado';return $row;},$stmt->fetchAll());
    }
    if ($module === 'auditoria') {
        return db()->query('SELECT a.id,COALESCE(u.name,\'Sistema\') AS actor,a.event,a.module,a.record_code,a.created_at FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_id ORDER BY a.id DESC')->fetchAll();
    }
    return $all[$module] ?? [];
}
function rpt_apply_filters(array $rows, array $query): array {
    $result=[];
    $needle=rpt_lower($query['busca']);
    foreach ($rows as $row) {
        if ($query['situacao'] !== 'all' && (string)($row['status'] ?? '') !== $query['situacao']) continue;
        if ($needle !== '') {
            $hit=false;
            foreach ($row as $value) {
                if (is_scalar($value) && rpt_contains(rpt_lower((string)$value), $needle)) {$hit=true;break;}
            }
            if (!$hit) continue;
        }
        $reference = substr((string)($row['data'] ?? $row['inicio'] ?? $row['prazo'] ?? $row['fim'] ?? $row['date'] ?? ''),0,10);
        // Uma seleção de período não deve incluir registros sem data de referência.
        if (($query['inicio'] !== '' || $query['fim'] !== '') && $reference === '') continue;
        if ($query['inicio'] !== '' && $reference < $query['inicio']) continue;
        if ($query['fim'] !== '' && $reference > $query['fim']) continue;
        $result[]=$row;
    }
    $sort=$query['ordem'];
    if ($sort !== '') {
        $direction=$query['direcao']==='desc'?-1:1;
        usort($result, static function ($a,$b) use ($sort,$direction) {
            $v1=$a[$sort] ?? ''; $v2=$b[$sort] ?? '';
            if (is_numeric($v1) && is_numeric($v2)) return ((float)$v1 <=> (float)$v2) * $direction;
            return strcmp(rpt_lower((string)$v1), rpt_lower((string)$v2)) * $direction;
        });
    }
    return $result;
}
function rpt_dataset(string $module, array $user, array $filters): array {
    rpt_allowed($module,$user);
    $all = collect_data();
    $raw = rpt_rows($module,$all,$user);
    if (($filters['registro'] ?? '') !== '') {
        $raw = array_values(array_filter($raw,static fn($row)=>(string)($row['id'] ?? '') === $filters['registro']));
    }
    $rows = rpt_apply_filters($raw,$filters);
    $status=[];
    foreach ($rows as $row) {
        $state=(string)($row['status']??'');
        if ($state !== '') $status[$state]=($status[$state]??0)+1;
    }
    arsort($status);
    $demo=0;
    foreach ($rows as $row) if (!empty($row['demo'])) $demo++;
    $budget=0; $measured=0; $progress=0; $progressCount=0;
    foreach ($rows as $row) {
        $budget+=(float)($row['orcamento']??0);
        $measured+=(float)($row['valor']??0);
        if (isset($row['progresso'])) {$progress+=(float)$row['progresso'];$progressCount++;}
    }
    return compact('module','user','filters','all','rows','status','demo','budget','measured','progress','progressCount') + ['total' => count($rows)];
}
function rpt_query(): array {
    $status=rpt_query_string('situacao', 100);
    $start=rpt_filter_date('inicio');
    $end=rpt_filter_date('fim');
    if ($start!=='' && $end!=='' && $start>$end) throw new HttpError('O período informado tem data inicial posterior à data final.',400);
    return [
        'situacao' => $status !== '' ? $status : 'all',
        'busca' => rpt_query_string('busca', 160),
        'registro' => rpt_query_string('registro', 64),
        'inicio' => $start,
        'fim' => $end,
        'ordem' => rpt_query_string('ordem', 30),
        'direcao' => rpt_query_string('direcao', 5) === 'desc' ? 'desc':'asc',
    ];
}
function rpt_display_value(string $key,mixed $value): string {
    if ($value === '' || $value === null) return '—';
    if (in_array($key, ['valor','orcamento'],true)) return rpt_currency($value);
    if (in_array($key,['progresso'],true)) return rpt_num($value).' %';
    if (in_array($key,['quantidade','minimo','area'],true)) return rpt_num($value,is_numeric($value)&&(float)$value !== (float)(int)$value ? 2:0);
    if (in_array($key,['inicio','fim','prazo','data','date','due_date'],true)) return rpt_date((string)$value);
    if ($key==='last_login_at' || $key==='created_at') return rpt_date((string)$value) . (strlen((string)$value)>10 ? substr((string)$value,10,6):'');
    return rpt_text($value);
}
function rpt_filename(string $module,string $extension): string {
    return 'OBRAS360_'.preg_replace('/[^a-z0-9_-]/','',strtolower($module)).'_'.date('Ymd_His').'.'.$extension;
}
function rpt_xml(string $value): string {
    // Caracteres válidos XML 1.0; não permitir elementos ou fórmulas executáveis.
    $value=preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u','',$value) ?? '';
    return htmlspecialchars($value,ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE,'UTF-8');
}
/**
 * XLSX Office Open XML: planilha principal legível, indicadores e todos os campos.
 * O XML segue a ordem exigida pelo Excel (autoFilter ANTES de mergeCells).
 * Não depende de Composer, ZipArchive ou bibliotecas de servidor.
 */
function rpt_xlsx_col(int $number): string {
    $s='';for($i=$number+1;$i>0;$i=intdiv($i-1,26))$s=chr(65+($i-1)%26).$s;
    return $s;
}
/** ZIP básico padrão (STORE) para ambientes PHP que não possuem ZipArchive. */
function rpt_zip(array $files): string {
    if (class_exists('ZipArchive')) {
        $tmp=tempnam(sys_get_temp_dir(),'obr_xlsx_');
        if($tmp===false)throw new RuntimeException('Não foi possível preparar a planilha.');
        $zip=new ZipArchive();
        if($zip->open($tmp,ZipArchive::OVERWRITE)!==true){@unlink($tmp);throw new RuntimeException('Não foi possível preparar o arquivo Excel.');}
        foreach($files as $name=>$content)$zip->addFromString($name,$content);
        $zip->close();$res=file_get_contents($tmp);@unlink($tmp);
        if($res===false)throw new RuntimeException('Não foi possível concluir a planilha.');
        return $res;
    }
    $out='';$central='';$offset=0;$count=0;
    foreach($files as $name=>$value){
        $length=strlen($value);$nameLength=strlen($name);$crc=(int)sprintf('%u',crc32($value));
        $local=pack('VvvvvvVVVvv',0x04034b50,20,0,0,0,0,$crc,$length,$length,$nameLength,0).$name;
        $out.=$local.$value;
        $central.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0,0,0,0,$crc,$length,$length,$nameLength,0,0,0,0,0,$offset).$name;
        $offset+=strlen($local)+$length;$count++;
    }
    $out.=$central.pack('VvvvvVVv',0x06054b50,0,0,$count,$count,strlen($central),$offset,0);
    return $out;
}
function rpt_xlsx_styles(): string {
    // Visual discreto, elevado contraste e fontes amplas; sem blocos escuros enormes.
    // Tipografia compacta exclusiva do Excel; a impressão A4 mantém fonte maior.
    $fonts=[
        '<font><sz val="10"/><name val="Aptos"/><color rgb="FF243C46"/></font>',
        '<font><b/><sz val="10"/><name val="Aptos"/><color rgb="FF126E66"/></font>',
        '<font><b/><sz val="16"/><name val="Aptos Display"/><color rgb="FF183947"/></font>',
        '<font><sz val="9"/><name val="Aptos"/><color rgb="FF526875"/></font>',
        '<font><b/><sz val="10"/><name val="Aptos"/><color rgb="FF183947"/></font>',
        '<font><b/><sz val="10"/><name val="Aptos"/><color rgb="FF244754"/></font>',
        '<font><b/><sz val="15"/><name val="Aptos Display"/><color rgb="FF183947"/></font>',
        '<font><b/><sz val="10"/><name val="Aptos"/><color rgb="FF206C52"/></font>',
        '<font><b/><sz val="10"/><name val="Aptos"/><color rgb="FF9B5B24"/></font>',
        '<font><sz val="9"/><name val="Aptos"/><color rgb="FF516A75"/></font>',
    ];
    $fills=[
        '<fill><patternFill patternType="none"/></fill>',
        '<fill><patternFill patternType="gray125"/></fill>',
    ];
    foreach(['FFECF6F2','FFECF1F3','FFFFFFFF','FFF7FAFA','FFFFF6E9','FFEAF5EF','FFFFF3E8'] as $fill){
        $fills[]='<fill><patternFill patternType="solid"><fgColor rgb="'.$fill.'"/><bgColor indexed="64"/></patternFill></fill>';
    }
    $borders=[
        '<border><left/><right/><top/><bottom/><diagonal/></border>',
        '<border><left/><right/><top style="hair"><color rgb="FFDCE6E9"/></top><bottom style="hair"><color rgb="FFDCE6E9"/></bottom><diagonal/></border>',
        '<border><left/><right/><top/><bottom style="thin"><color rgb="FFB9CCD2"/></bottom><diagonal/></border>',
        // Grade sutil e completa, usada em todas as células da tabela de dados.
        '<border><left style="thin"><color rgb="FFD5E0E4"/></left><right style="thin"><color rgb="FFD5E0E4"/></right><top style="thin"><color rgb="FFD5E0E4"/></top><bottom style="thin"><color rgb="FFD5E0E4"/></bottom><diagonal/></border>',
        '<border><left style="thin"><color rgb="FFB5C8CF"/></left><right style="thin"><color rgb="FFB5C8CF"/></right><top style="thin"><color rgb="FFB5C8CF"/></top><bottom style="thin"><color rgb="FFB5C8CF"/></bottom><diagonal/></border>',
    ];
    // Font, preenchimento, borda, número. Índices públicos usados pelo gerador.
    $styles=[
        [0,0,0,0],     // 0 padrão
        [1,2,4,0],     // 1 instituição: borda completa e alinhamento central
        [2,4,4,0],     // 2 título do relatório, com borda
        [3,4,3,0],     // 3 metadados, grade discreta
        [4,2,4,0],     // 4 seção, com grade completa
        [5,3,4,0],     // 5 título de coluna: contorno completo
        [0,4,3,0],     // 6 linha branca: bordas em todos os lados
        [0,5,3,0],     // 7 linha alternada
        [0,4,3,2],     // 8 decimal branco
        [0,5,3,2],     // 9 decimal alternado
        [0,4,3,164],   // 10 moeda
        [0,5,3,164],   // 11 moeda alternada
        [0,4,3,165],   // 12 percentual
        [0,5,3,165],   // 13 percentual alternado
        [9,3,0,0],     // 14 legenda indicador
        [6,3,0,0],     // 15 número indicador
        [3,4,3,0],     // 16 nota com borda sutil
        [4,4,0,0],     // 17 negrito simples
        [7,7,3,0],     // 18 situação positiva
        [8,8,3,0],     // 19 situação atenção
        [4,5,3,0],     // 20 situação neutra
        [5,4,4,0],     // 21 título secundário com borda
        [0,4,3,1],     // 22 inteiro
        [0,5,3,1],     // 23 inteiro alternado
        [8,6,3,0],     // 24 alerta informativo com borda
    ];
    $xfs='';
    foreach($styles as [$fontId,$fillId,$borderId,$formatId]){
        // Todas as abas: centralização horizontal e vertical em cabeçalhos e dados.
        // Borda aplica-se a todas as células das tabelas e aos cabeçalhos institucionais.
        $xfs.='<xf numFmtId="'.$formatId.'" fontId="'.$fontId.'" fillId="'.$fillId.'" borderId="'.$borderId.'" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"'.($formatId?' applyNumberFormat="1"':'').'><alignment horizontal="center" vertical="center" wrapText="1"/></xf>';
    }
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
      .'<numFmts count="3"><numFmt numFmtId="164" formatCode="&quot;R$&quot; #,##0.00"/><numFmt numFmtId="165" formatCode="0%"/><numFmt numFmtId="166" formatCode="0.0000"/></numFmts>'
      .'<fonts count="'.count($fonts).'">'.implode('',$fonts).'</fonts>'
      .'<fills count="'.count($fills).'">'.implode('',$fills).'</fills>'
      .'<borders count="'.count($borders).'">'.implode('',$borders).'</borders>'
      .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
      .'<cellXfs count="'.count($styles).'">'.$xfs.'</cellXfs>'
      .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
      .'</styleSheet>';
}
/** @return array{v:mixed,s:int,n?:bool} */
function rpt_xlsx_item(mixed $value,int $style=6,bool $numeric=false): array {
    return ['v'=>$value,'s'=>$style,'n'=>$numeric];
}
function rpt_xlsx_cell(int $col,int $row,array $item): string {
    $ref=rpt_xlsx_col($col).$row;
    $style=(int)($item['s']??0);
    $value=$item['v']??'';
    if (!empty($item['n']) && is_numeric($value) && is_finite((float)$value)) {
        return '<c r="'.$ref.'" s="'.$style.'" t="n"><v>'.rpt_xml((string)(0+$value)).'</v></c>';
    }
    // Sempre texto para conteúdo do usuário: Excel não executa como fórmula.
    return '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.rpt_xml((string)$value).'</t></is></c>';
}
/**
 * @param array<int,array<int,array>> $rows linhas 1-based com células 0-based
 * @param array<int,int|float> $widths larguras 1-based
 */
function rpt_xlsx_sheet(array $rows,array $widths,array $merges=[],string $filter='',int $freeze=0,array $rowHeights=[]): string {
    $lastRow=max(1,(int)max(array_keys($rows)));
    $lastCol=rpt_xlsx_col(count($widths)-1);
    $xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<dimension ref="A1:'.$lastCol.$lastRow.'"/>'
        .'<sheetViews><sheetView showGridLines="0" workbookViewId="0">';
    if ($freeze>0) $xml.='<pane ySplit="'.$freeze.'" topLeftCell="A'.($freeze+1).'" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A'.($freeze+1).'" sqref="A'.($freeze+1).'"/>';
    $xml.='</sheetView></sheetViews><sheetFormatPr defaultRowHeight="25"/><cols>';
    foreach($widths as $index=>$width)$xml.='<col min="'.($index+1).'" max="'.($index+1).'" width="'.(float)$width.'" customWidth="1"/>';
    $xml.='</cols><sheetData>';
    ksort($rows);
    foreach($rows as $r=>$cells){
        $height=$rowHeights[$r]??($r===1?28:($r===2?34:($r===7?34:26)));
        $xml.='<row r="'.$r.'" ht="'.(float)$height.'" customHeight="1">';
        ksort($cells);
        foreach($cells as $col=>$item)$xml.=rpt_xlsx_cell((int)$col,(int)$r,$item);
        $xml.='</row>';
    }
    $xml.='</sheetData>';
    // Ordem exigida pelo Excel: autoFilter antes de mergeCells.
    if ($filter!=='')$xml.='<autoFilter ref="'.rpt_xml($filter).'"/>';
    if ($merges){$xml.='<mergeCells count="'.count($merges).'">';foreach($merges as $range)$xml.='<mergeCell ref="'.rpt_xml($range).'"/>';$xml.='</mergeCells>';}
    $xml.='<printOptions gridLines="0"/><pageMargins left="0.4" right="0.4" top="0.6" bottom="0.6" header="0.25" footer="0.25"/>'
        .'<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/>'
        .'</worksheet>';
    return $xml;
}
function rpt_xlsx_numeric_kind(string $field,mixed $value): string {
    if (!is_numeric($value) || $value==='' || $value===null) return '';
    if (in_array($field,['valor','orcamento'],true)) return 'money';
    if ($field==='progresso')return 'progress';
    if (in_array($field,['quantidade','minimo','area','lat','lng'],true))return 'number';
    return '';
}
function rpt_xlsx_bytes(array $dataset): string {
    $short = rpt_columns($dataset['module'],false);
    $extended = rpt_columns($dataset['module'],true);
    $set=static function(array &$rows,int $r,int $c,mixed $v,int $style=6,bool $numeric=false): void {
        $rows[$r][$c]=rpt_xlsx_item($v,$style,$numeric);
    };
    $user=$dataset['user']['name']??$dataset['user']['username']??'Usuário';
    $date=date('d/m/Y H:i');
    $filter=rpt_filters_text($dataset['filters']);
    $title=rpt_module_title($dataset['module']);
    $brand='PREFEITURA MUNICIPAL DE COARI  /  OBRAS360';
    $metadata='Emitido em '.$date.'   •   Responsável: '.$user.'   •   Total: '.$dataset['total'].' registro(s)';
    $baseNote='Fonte: OBRAS360 / MySQL    •    Consulte a aba Indicadores para o resumo da seleção.';
    if($dataset['demo']>0) $baseNote.='    •    '.$dataset['demo'].' registros demonstrativos: validar antes de divulgar.';
    // A primeira aba abre diretamente a tabela principal, sem espaços enormes.
    $buildData=static function(array $columns,bool $isExtended) use ($dataset,$set,$brand,$metadata,$title,$filter,$baseNote): string {
        if(!$columns)$columns=[['id','Código']];
        $data=[];$widths=[];$rowHeights=[];
        $end=rpt_xlsx_col(count($columns)-1);
        // Mesmo contorno em toda a largura, inclusive células dentro de mesclas.
        // O título ocupa no máximo as seis primeiras colunas (visível sem rolagem lateral).
        $headSpan=min(6,count($columns));
        $headerRows=[
            1=>[$brand,1],
            2=>[$title.($isExtended?' — todos os campos':''),2],
            3=>[$metadata,3],
            4=>['Filtros: '.$filter,3],
            5=>[$baseNote,16],
        ];
        foreach($headerRows as $hr=>[$label,$style]){
            for($hc=0;$hc<count($columns);$hc++)$set($data,$hr,$hc,$hc===0?$label:'',$style);
        }
        $rowHeights=[1=>32,2=>40,3=>29,4=>29,5=>34,7=>37];
        foreach($columns as $j=>$column){
            [$key,$label]=$column;
            $set($data,7,$j,(string)$label,5);
            $widths[]=match((string)$key){
                'id','code','username'=>21,
                'title'=>47,
                'name','descricao','observacao'=>46,
                'local','obra','setor','destino','responsavel','work'=>33,
                'email'=>38,
                'status','situacao','role','prioridade','tipo','condicao'=>25,
                'contrato'=>30,
                'inicio','fim','prazo','date','data','created_at','last_login_at'=>23,
                'progresso','valor','orcamento','quantidade','lat','lng'=>20,
                default=>max(19,min(36,strlen((string)$label)+9))
            };
        }
        $r=8;
        foreach($dataset['rows'] as $record){
            $maxLength=0;
            foreach($columns as $j=>[$key]){
                $value=$record[$key]??'';
                $kind=rpt_xlsx_numeric_kind((string)$key,$value);
                $white=$r%2===0;
                if($kind==='money')$set($data,$r,$j,(float)$value,$white?10:11,true);
                elseif($kind==='progress')$set($data,$r,$j,(float)$value/100,$white?12:13,true);
                elseif($kind==='number')$set($data,$r,$j,(float)$value,$white?8:9,true);
                else {
                    $txt=$value===''||$value===null?'—':rpt_display_value((string)$key,$value);
                    $style=$white?6:7;
                    if(in_array((string)$key,['status','active_label'],true)){
                        $lower=rpt_lower((string)$txt);
                        if(rpt_contains($lower,'conclu') || rpt_contains($lower,'finaliz') || $lower==='ativo' || $lower==='normal')$style=18;
                        elseif(rpt_contains($lower,'abert') || rpt_contains($lower,'atras') || rpt_contains($lower,'penden'))$style=19;
                        else $style=20;
                    }
                    $set($data,$r,$j,$txt,$style);
                    $maxLength=max($maxLength,strlen((string)$txt));
                }
            }
            $rowHeights[$r]=$maxLength>100?48:($maxLength>54?36:27);
            $r++;
        }
        if($dataset['total']===0){$set($data,9,0,'Nenhum registro encontrado para os filtros selecionados.',16);$rowHeights[9]=35;}
        $headEnd=rpt_xlsx_col($headSpan-1);
        $merges=$headSpan>1?['A1:'.$headEnd.'1','A2:'.$headEnd.'2','A3:'.$headEnd.'3','A4:'.$headEnd.'4','A5:'.$headEnd.'5']:[];
        $filterRange=$dataset['total']>0?'A7:'.$end.($r-1):'';
        return rpt_xlsx_sheet($data,$widths,$merges,$filterRange,7,$rowHeights);
    };
    $sheetOne=$buildData($short,false);
    // Segunda aba (Indicadores): desenho tabular limpo, sem mesclas dentro das tabelas.
    // Três colunas fixas tornam a leitura mais simples e os contornos uniformes.
    $summary=[];
    $summaryMerges=[];
    $summaryWidths=[40,24,40];
    $section=static function(int $row,string $text,int $style=4) use (&$summary,&$summaryMerges,$set): void {
        for($i=0;$i<3;$i++)$set($summary,$row,$i,$i===0?$text:'',$style);
        $summaryMerges[]='A'.$row.':C'.$row;
    };
    $section(1,$brand,1);
    $section(2,'Resumo gerencial — '.$title,2);
    $section(3,$metadata,3);
    $section(4,'Filtros: '.$filter,3);
    $section(6,'INDICADORES DA SELEÇÃO',4);
    foreach(['INDICADOR','VALOR','REFERÊNCIA'] as $i=>$text)$set($summary,7,$i,$text,5);
    $avg=$dataset['progressCount']?(float)$dataset['progress']/$dataset['progressCount']/100:0;
    $kpis=[
        ['Registros selecionados',(int)$dataset['total'],'Total conforme filtros','int'],
        ['Situações identificadas',count($dataset['status']),'Categorias diferentes','int'],
        ['Registros demonstrativos',(int)$dataset['demo'],'Validar antes de divulgação','int'],
        ['Orçamento estimado',(float)$dataset['budget'],'Valores cadastrados','money'],
        ['Execução média',$avg,'Média dos percentuais disponíveis','percent'],
        ['Valor medido',(float)$dataset['measured'],'Valores cadastrados','money'],
    ];
    $summaryHeights=[1=>32,2=>40,3=>30,4=>30,6=>30,7=>35];
    $line=8;
    foreach($kpis as [$label,$value,$context,$kind]){
        $shade=($line%2===0);
        $set($summary,$line,0,$label,$shade?6:7);
        $numberStyle=match($kind){'money'=>$shade?10:11,'percent'=>$shade?12:13,default=>$shade?22:23};
        $set($summary,$line,1,$value,$numberStyle,true);
        $set($summary,$line,2,$context,$shade?6:7);
        $summaryHeights[$line]=32;
        $line++;
    }
    $section(15,'DISTRIBUIÇÃO POR SITUAÇÃO',4);
    foreach(['SITUAÇÃO','QUANTIDADE','PARTICIPAÇÃO'] as $i=>$text)$set($summary,16,$i,$text,5);
    $summaryHeights[15]=30;$summaryHeights[16]=35;
    $line=17;
    foreach($dataset['status'] as $state=>$qty){
        $shade=($line%2===0);
        $set($summary,$line,0,(string)$state,$shade?6:7);
        $set($summary,$line,1,(int)$qty,$shade?22:23,true);
        $set($summary,$line,2,$dataset['total']?(float)$qty/$dataset['total']:0,$shade?12:13,true);
        $summaryHeights[$line]=31;
        $line++;
    }
    if(!$dataset['status']){
        $section($line,'Nenhuma situação informada nos registros selecionados.',16);
        $summaryHeights[$line]=34;
        $line++;
    }
    $noteRow=$line+2;
    $section($noteRow,'NOTAS DE LEITURA',4);
    $section($noteRow+1,'Dados extraídos do MySQL na emissão. Consulte a aba Registros.',16);
    if($dataset['demo']>0) $section($noteRow+2,'ATENÇÃO: há registros demonstrativos nesta seleção.',24);
    $summaryHeights[$noteRow]=30;$summaryHeights[$noteRow+1]=31;
    if($dataset['demo']>0)$summaryHeights[$noteRow+2]=32;
    $sheetTwo=rpt_xlsx_sheet($summary,$summaryWidths,$summaryMerges,'',7,$summaryHeights);
    // Terceira aba conserva todos os campos sem sobrecarregar a leitura inicial.
    $sheetThree=$buildData($extended,true);
    $types='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
      .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
      .'<Default Extension="xml" ContentType="application/xml"/>'
      .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
      .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
      .'<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
      .'<Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
      .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
      .'</Types>';
    $files=[
        '[Content_Types].xml'=>$types,
        '_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets><sheet name="Registros" sheetId="1" r:id="rId1"/><sheet name="Indicadores" sheetId="2" r:id="rId2"/><sheet name="Dados completos" sheetId="3" r:id="rId3"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/><Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml'=>rpt_xlsx_styles(),
        'xl/worksheets/sheet1.xml'=>$sheetOne,
        'xl/worksheets/sheet2.xml'=>$sheetTwo,
        'xl/worksheets/sheet3.xml'=>$sheetThree,
    ];
    return rpt_zip($files);
}
function rpt_download_xlsx(array $dataset): never {
    $content=rpt_xlsx_bytes($dataset);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.rpt_filename($dataset['module'],'xlsx').'"');
    header('Content-Length: '.strlen($content));
    header('X-Content-Type-Options: nosniff');
    echo $content;exit;
}
function rpt_filters_text(array $filters): string {
    $parts=[];
    if ($filters['situacao'] !== 'all')$parts[]='Situação: '.$filters['situacao'];
    if ($filters['busca'] !== '')$parts[]='Pesquisa: '.$filters['busca'];
    if (($filters['registro'] ?? '') !== '')$parts[]='Registro: '.$filters['registro'];
    if ($filters['inicio'] !== '')$parts[]='De: '.rpt_date($filters['inicio']);
    if ($filters['fim'] !== '')$parts[]='Até: '.rpt_date($filters['fim']);
    return $parts ? implode(' | ', $parts) : 'Todos os registros disponíveis';
}
function rpt_url(array $dataset, string $mode): string {
    $query=['modulo'=>$dataset['module'],'formato'=>$mode];
    foreach (['situacao','busca','registro','inicio','fim','ordem','direcao'] as $key) {
        if (($dataset['filters'][$key] ?? '') !== '') $query[$key]=$dataset['filters'][$key];
    }
    return 'relatorio-documento.php?'.http_build_query($query,'','&',PHP_QUERY_RFC3986);
}
function rpt_status_class(string $status): string {
    if(preg_match('/conclu|aprov|normal|regular|arquivad|publicad/i',$status))return 'positive';
    if(preg_match('/paralisa|atenç|alta|pendente|atras|alerta|crític|baixa/i',$status))return 'attention';
    return 'other';
}
function rpt_print_cell(string $key,mixed $value): string {
    $text=rpt_display_value($key,$value);
    if ($key==='status')return '<span class="status-pill '.rpt_status_class($text).'">'.rpt_html($text).'</span>';
    if ($key==='progresso') {
        $number=max(0,min(100,(float)$value));
        return '<div class="percent-cell"><strong>'.rpt_html(rpt_num($number)).'%</strong><span class="pbar"><i style="width:'.rpt_html((string)$number).'%"></i></span></div>';
    }
    if (in_array($key,['valor','orcamento','quantidade'],true))return '<span class="numeric">'.rpt_html($text).'</span>';
    return rpt_html($text);
}
function rpt_summary_card(string $title, string $value,string $sub=''): string {
    return '<div class="summary-card"><small>'.rpt_html($title).'</small><strong>'.rpt_html($value).'</strong>'.($sub!==''?'<span>'.rpt_html($sub).'</span>':'').'</div>';
}
function rpt_exec_section(array $dataset): string {
    $all=$dataset['all'];
    $works=$all['obras']??[]; $orders=$all['ordens']??[]; $stock=$all['materiais']??[];
    $active=count(array_filter($works,static fn($r)=>in_array($r['status']??'',['Em andamento','Em atenção'],true)));
    $attention=count(array_filter($works,static fn($r)=>preg_match('/atenç|paralisad/i',(string)($r['status']??''))));
    $done=count(array_filter($orders,static fn($r)=>str_contains(rpt_lower((string)($r['status']??'')),'conclu')));
    $low=count(array_filter($stock,static fn($r)=>(float)($r['quantidade']??0)<(float)($r['minimo']??0)));
    $budget=array_sum(array_map(static fn($r)=>(float)($r['orcamento']??0),$works));
    $mean=count($works)?array_sum(array_map(static fn($r)=>(float)($r['progresso']??0),$works))/count($works):0;
    $html='<section class="section executive-section"><div class="section-heading"><span>01</span><div><h2>Visão executiva</h2><p>Indicadores calculados exclusivamente com os cadastros existentes no sistema.</p></div></div><div class="summary-grid">';
    $html.=rpt_summary_card('Obras cadastradas',(string)count($works),'Carteira total');
    $html.=rpt_summary_card('Obras em execução',(string)$active,'Frentes em andamento');
    $html.=rpt_summary_card('Obras que exigem atenção',(string)$attention,'Atenção / paralisadas');
    $html.=rpt_summary_card('Execução física média',rpt_num($mean).'%','Média não ponderada');
    $html.=rpt_summary_card('Orçamento estimado',rpt_currency($budget),'Soma dos valores informados');
    $html.=rpt_summary_card('Ordens concluídas',(string)$done,'Atendimentos cadastrados');
    $html.=rpt_summary_card('Materiais abaixo do mínimo',(string)$low,'Reposição recomendada');
    $html.=rpt_summary_card('Protocolos',(string)count($all['protocolos']??[]),'Processos registrados');
    $html.='</div>';
    $priority=array_values(array_filter($works,static fn($r)=>preg_match('/atenç|paralisad|atras/i',(string)($r['status']??''))));
    $html.='<div class="priority"><div class="priority-head"><span class="priority-icon">!</span><div><strong>Pontos prioritários de acompanhamento</strong><p>Registros sinalizados por situação de atenção, paralisação ou atraso.</p></div></div>';
    if (!$priority) $html.='<p class="no-alert">Nenhuma obra classificada como situação crítica entre os registros cadastrados.</p>';
    else {
        $html.='<div class="priority-list">';
        foreach(array_slice($priority,0,8) as $row) {
            $html.='<div><span>'.rpt_html($row['id']??'—').'</span><strong>'.rpt_html($row['title']??'—').'</strong><em>'.rpt_html($row['status']??'—').'</em></div>';
        }
        $html.='</div>';
    }
    return $html.'</div></section>';
}
function rpt_print_document(array $dataset): string {
    $module=$dataset['module'];$title=rpt_module_title($module);
    $allCols=rpt_columns($module,false);
    $counts=$dataset['status'];
    $results=$dataset['rows'];
    $isExec=$module==='executivo';
    $countsHtml='';
    $total=$dataset['total'];
    foreach(array_slice($counts,0,6,true) as $status=>$count){
        $countsHtml.='<div class="status-row"><div><span class="status-dot '.rpt_status_class((string)$status).'"></span>'.rpt_html($status).'</div><b>'.$count.'</b><span class="status-track"><i style="width:'.($total?min(100,round($count/$total*100)):0).'%"></i></span></div>';
    }
    $meta='';
    if($dataset['filters']['situacao']!=='all')$meta.='<span>Situação: <b>'.rpt_html($dataset['filters']['situacao']).'</b></span>';
    if($dataset['filters']['inicio']!=='')$meta.='<span>Início: <b>'.rpt_html(rpt_date($dataset['filters']['inicio'])).'</b></span>';
    if($dataset['filters']['fim']!=='')$meta.='<span>Fim: <b>'.rpt_html(rpt_date($dataset['filters']['fim'])).'</b></span>';
    if($dataset['filters']['busca']!=='')$meta.='<span>Busca: <b>'.rpt_html($dataset['filters']['busca']).'</b></span>';
    if(($dataset['filters']['registro']??'')!=='')$meta.='<span>Registro: <b>'.rpt_html($dataset['filters']['registro']).'</b></span>';
    if(!$meta)$meta='<span><b>Todos os registros disponíveis</b></span>';
    $summary='<div class="summary-grid">'.rpt_summary_card('Registros no relatório',(string)$total,'Após aplicar os filtros')
        .rpt_summary_card('Situações distintas',(string)count($counts),'Classificações informadas');
    if ($dataset['progressCount']>0)$summary.=rpt_summary_card('Execução média',rpt_num($dataset['progress']/$dataset['progressCount']).'%','Média não ponderada');
    if($dataset['budget']>0)$summary.=rpt_summary_card('Orçamento estimado',rpt_currency($dataset['budget']),'Soma dos valores filtrados');
    if($dataset['measured']>0)$summary.=rpt_summary_card('Valor de medições',rpt_currency($dataset['measured']),'Soma dos registros filtrados');
    $summary.='</div>';
    $table='<div class="table-scroller"><table class="report-table"><thead><tr>';
    foreach($allCols as $col)$table.='<th>'.rpt_html($col[1]).'</th>';
    $table.='</tr></thead><tbody>';
    if (!$results) $table.='<tr><td colspan="'.count($allCols).'" class="empty">Nenhum registro encontrado para os filtros selecionados.</td></tr>';
    foreach($results as $index=>$row){
        $table.='<tr>';
        foreach($allCols as [$key])$table.='<td>'.rpt_print_cell((string)$key,$row[$key]??null).'</td>';
        $table.='</tr>';
    }
    $table.='</tbody></table></div>';
    $executive=$isExec ? rpt_exec_section($dataset):'';
    $singleDetail='';
    if (($dataset['filters']['registro'] ?? '') !== '' && count($results)===1 && isset(modules()[$module])) {
        $record=$results[0];
        $detailCards='';
        foreach (rpt_columns($module,true) as [$key,$label]) {
            if ($key==='descricao') continue;
            $detailCards.='<div><small>'.rpt_html($label).'</small><strong>'.rpt_html(rpt_display_value((string)$key,$record[$key]??null)).'</strong></div>';
        }
        $singleDetail='<section class="section single-detail"><div class="section-heading"><span>01</span><div><h2>Ficha individual — '.rpt_html($record['title']??$dataset['filters']['registro']).'</h2><p>Informações completas do registro selecionado.</p></div></div><div class="detail-cards">'.$detailCards.'</div>';
        if (!empty($record['descricao'])) $singleDetail.='<div class="detail-description"><strong>Descrição e observações</strong><p>'.rpt_html($record['descricao']).'</p></div>';
        $singleDetail.='</section>';
    }

    $notice=$dataset['demo']>0 ? '<div class="warning-banner"><strong>Registros demonstrativos identificados:</strong> '.(int)$dataset['demo'].' registro(s) desta seleção possuem marcação de demonstração. Eles não devem ser tratados como informação oficial.</div>':'';
    $back=match($module){'executivo'=>'index.php','planejamento'=>'planejamento.php','mapa'=>'mapa-interativo.php','movimentacoes'=>'materiais-e-estoque.php','usuarios'=>'usuarios.php','auditoria'=>'auditoria.php',default=>match($module){'obras'=>'obras.php','ordens'=>'ordens-de-servico.php','fiscalizacao'=>'fiscalizacao.php','projetos'=>'projetos-e-documentos.php','orcamentos'=>'orcamentos-e-medicoes.php','materiais'=>'materiais-e-estoque.php','patrimonio'=>'predios-publicos.php','protocolos'=>'protocolos.php',default=>'relatorios.php'}};
    $content='<section class="section table-section"><div class="section-heading"><span>03</span><div><h2>'.($isExec?'Carteira de obras':'Detalhamento dos registros').'</h2><p>Todos os registros da seleção, sem limite de pré-visualização.</p></div><strong class="table-count">'.rpt_html($total).' registros</strong></div>'.$table.'</section>';
    return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    .'<title>'.rpt_html($title).' | OBRAS360</title><link rel="stylesheet" href="assets/relatorios.css?v=4"></head><body>'
    .'<div class="print-toolbar"><div class="toolbar-brand"><span class="toolbar-logo">◈</span><div><b>OBRAS<span>360</span></b><small>Relatório gerencial</small></div></div><div class="toolbar-actions"><a class="toolbar-link" href="'.rpt_html($back).'">← Voltar</a><a class="toolbar-btn outlined" href="'.rpt_html(rpt_url($dataset,'xlsx')).'">Baixar Excel</a><button class="toolbar-btn primary" type="button" id="print-now">Imprimir / Salvar PDF</button></div></div>'
    .'<div class="document"><header class="doc-header"><div class="header-mark"><div class="emblem">◈</div><div><span class="gov-small">PREFEITURA MUNICIPAL DE COARI</span><strong>Secretaria Municipal de Obras e Planejamento</strong><small>Coari · Amazonas</small></div></div><div class="edition-mark"><strong>OBRAS<span>360</span></strong><small>ACOMPANHAMENTO GERENCIAL</small></div></header>'
    .'<div class="hero-band"><div class="hero-kicker">RELATÓRIO DE GESTÃO <span> / </span> '.rpt_html(date('Y')).'</div><h1>'.rpt_html($title).'</h1><p>Síntese das informações cadastradas para acompanhamento gerencial.</p></div>'
    .'<section class="document-meta"><div><small>EMISSÃO</small><b>'.rpt_html(date('d/m/Y H:i')).' (AM)</b></div><div><small>RESPONSÁVEL PELA EMISSÃO</small><b>'.rpt_html($dataset['user']['name']??$dataset['user']['username']??'Usuário').'</b></div><div><small>ORIGEM</small><b>OBRAS360 · Registros cadastrados</b></div></section>'
    .'<section class="filters-strip"><div class="filter-label">RECORTE ANALISADO</div><div class="filter-values">'.$meta.'</div></section>'
    .$notice.$executive.$singleDetail.(!$isExec?'<section class="section stats-section"><div class="section-heading"><span>01</span><div><h2>Indicadores do recorte</h2><p>Resultados da consulta ao banco, atualizados na geração deste documento.</p></div></div>'.$summary.'</section>':'')
    .(!empty($counts)?'<section class="section situation-section"><div class="section-heading"><span>02</span><div><h2>Distribuição por situação</h2><p>Visão comparativa dos registros filtrados.</p></div></div><div class="status-grid">'.$countsHtml.'</div></section>':'')
    .$content
    .'<footer class="doc-footer"><div class="footer-note"><span class="footer-dot"></span><div><strong>Observação de integridade</strong><p>Documento gerado a partir dos dados disponíveis no OBRAS360. Validar os cadastros e eventuais dados de demonstração antes de apresentação oficial. Este relatório não substitui documentos assinados ou parecer técnico.</p></div></div><div class="footer-bottom"><strong>OBRAS360</strong><span>Secretaria Municipal de Obras e Planejamento · Coari/AM</span><span>Emitido em '.rpt_html(date('d/m/Y')).'</span></div></footer></div>'
    .'<script src="assets/relatorios.js?v=2" defer></script></body></html>';
}
