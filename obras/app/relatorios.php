<?php
/**
 * OBRAS360 | Relatórios gerenciais, impressão, CSV e XLSX.
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
        // Compatível com a tela: registros sem data permanecem quando se filtra por período.
        if ($reference !== '' && $query['inicio'] !== '' && $reference < $query['inicio']) continue;
        if ($reference !== '' && $query['fim'] !== '' && $reference > $query['fim']) continue;
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
function rpt_csv_injection_safe(string $value): string {
    // Protege a abertura de CSV em planilhas (inclusive prefixos após espaços).
    return preg_match('/^[\s\x00-\x1F]*[=+\-@]/u',$value) ? "'".$value : $value;
}
function rpt_download_csv(array $dataset): never {
    $columns=rpt_columns($dataset['module'],true);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.rpt_filename($dataset['module'],'csv').'"');
    header('X-Content-Type-Options: nosniff');
    echo "\xEF\xBB\xBF";
    $out=fopen('php://output','wb');
    fputcsv($out, array_column($columns,1), ';', '"', '');
    foreach ($dataset['rows'] as $row) {
        $cells=[];
        foreach ($columns as [$key]) $cells[]=rpt_csv_injection_safe(rpt_display_value((string)$key,$row[$key]??null));
        fputcsv($out,$cells,';','"','');
    }
    fclose($out);exit;
}
function rpt_xml(string $value): string {
    // Caracteres válidos XML 1.0; não permitir elementos ou fórmulas executáveis.
    $value=preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u','',$value) ?? '';
    return htmlspecialchars($value,ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE,'UTF-8');
}
/**
 * XLSX Office Open XML: duas abas institucionais, tipos numéricos e estilos.
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
    $font=[
        '<font><sz val="11"/><name val="Aptos"/><color rgb="FF243B4A"/></font>',
        '<font><b/><sz val="17"/><name val="Aptos Display"/><color rgb="FFFFFFFF"/></font>',
        '<font><b/><sz val="13"/><name val="Aptos"/><color rgb="FF183849"/></font>',
        '<font><b/><sz val="10"/><name val="Aptos"/><color rgb="FFFFFFFF"/></font>',
        '<font><sz val="10"/><name val="Aptos"/><color rgb="FF667D86"/></font>',
        '<font><b/><sz val="10"/><name val="Aptos"/><color rgb="FF183849"/></font>',
    ];
    $fills=['<fill><patternFill patternType="none"/></fill>',
        '<fill><patternFill patternType="gray125"/></fill>'];
    foreach(['FF183E4D','FFE1EEEA','FFF3F7F8','FFFFFFFF'] as $color){
        $fills[]='<fill><patternFill patternType="solid"><fgColor rgb="'.$color.'"/><bgColor indexed="64"/></patternFill></fill>';
    }
    $border='<border><left/><right/><top/><bottom/><diagonal/></border>';
    $border2='<border><left/><right/><top/><bottom style="hair"><color rgb="FFD9E4E7"/></bottom><diagonal/></border>';
    $styles=[
        [0,0,0,0], // 0 normal
        [1,2,0,0], // 1 faixa institucional
        [2,5,0,0], // 2 subtítulo
        [4,0,0,0], // 3 metadados
        [3,2,0,0], // 4 seção / cabeçalho
        [3,2,0,0], // 5 cabeçalho de tabela
        [0,5,1,0], // 6 linha branca
        [0,4,1,0], // 7 zebra
        [0,5,1,2], // 8 numérico, 2 casas
        [0,4,1,2], // 9 numérico zebra
        [0,5,1,164], // 10 moeda
        [0,4,1,164], // 11 moeda zebra
        [0,5,1,165], // 12 percentual
        [0,4,1,165], // 13 percentual zebra
        [4,3,0,0], // 14 label KPI
        [2,3,0,0], // 15 valor KPI
        [4,0,0,0], // 16 observação
        [5,0,0,0], // 17 negrito simples
        [2,0,0,0], // 18 título fora do banner
        [2,3,0,164], // 19 KPI moeda
        [2,3,0,165], // 20 KPI %
    ];
    $xfs='';foreach($styles as [$fontId,$fillId,$borderId,$formatId]){
        $xfs.='<xf numFmtId="'.$formatId.'" fontId="'.$fontId.'" fillId="'.$fillId.'" borderId="'.$borderId.'" xfId="0" applyFont="1" applyFill="1" applyBorder="1"'.($formatId?' applyNumberFormat="1"':'').'><alignment vertical="center" wrapText="1"/></xf>';
    }
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<numFmts count="2"><numFmt numFmtId="164" formatCode="&quot;R$&quot; #,##0.00"/><numFmt numFmtId="165" formatCode="0%"/></numFmts>'
        .'<fonts count="'.count($font).'">'.implode('',$font).'</fonts>'
        .'<fills count="'.count($fills).'">'.implode('',$fills).'</fills>'
        .'<borders count="2">'.$border.$border2.'</borders>'
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
function rpt_xlsx_sheet(array $rows,array $widths,array $merges=[],string $filter='',int $freeze=0): string {
    $lastRow=max(1,(int)max(array_keys($rows)));
    $lastCol=rpt_xlsx_col(count($widths)-1);
    $xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<dimension ref="A1:'.$lastCol.$lastRow.'"/>'
        .'<sheetViews><sheetView showGridLines="0" workbookViewId="0">';
    if ($freeze>0) $xml.='<pane ySplit="'.$freeze.'" topLeftCell="A'.($freeze+1).'" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A'.($freeze+1).'" sqref="A'.($freeze+1).'"/>';
    $xml.='</sheetView></sheetViews><sheetFormatPr defaultRowHeight="19"/><cols>';
    foreach($widths as $index=>$width)$xml.='<col min="'.($index+1).'" max="'.($index+1).'" width="'.(float)$width.'" customWidth="1"/>';
    $xml.='</cols><sheetData>';
    ksort($rows);
    foreach($rows as $r=>$cells){
        $height=$r===1?31:($r===2?27:($r===6||$r===8?24:22));
        $xml.='<row r="'.$r.'" ht="'.$height.'" customHeight="1">';
        ksort($cells);
        foreach($cells as $col=>$item)$xml.=rpt_xlsx_cell((int)$col,(int)$r,$item);
        $xml.='</row>';
    }
    $xml.='</sheetData>';
    // Ordem oficial: autoFilter antes de mergeCells. O Excel removia as células ao reparar a planilha anterior.
    if ($filter!=='')$xml.='<autoFilter ref="'.rpt_xml($filter).'"/>';
    if ($merges){$xml.='<mergeCells count="'.count($merges).'">';foreach($merges as $range)$xml.='<mergeCell ref="'.rpt_xml($range).'"/>';$xml.='</mergeCells>';}
    $xml.='<printOptions gridLines="0"/><pageMargins left="0.3" right="0.3" top="0.55" bottom="0.5" header="0.2" footer="0.2"/>'
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
    $columns=rpt_columns($dataset['module'],true);
    $count=max(1,count($columns));
    // A aba Resumo é sempre visível, mesmo quando nenhum registro corresponde aos filtros.
    $summary=[];$summaryMerges=['A1:H1','A2:H2','A4:H4','A5:H5','A6:H6','A8:H8','A13:H13'];
    $set=static function(array &$rows,int $r,int $c,mixed $v,int $style=6,bool $numeric=false): void {
        $rows[$r][$c]=rpt_xlsx_item($v,$style,$numeric);
    };
    $set($summary,1,0,'OBRAS360   /   PREFEITURA MUNICIPAL DE COARI',1);
    $set($summary,2,0,rpt_module_title($dataset['module']),18);
    $set($summary,4,0,'EMISSÃO  '.date('d/m/Y H:i').'     •     ORIGEM  OBRAS360 / MYSQL',3);
    $set($summary,5,0,'RESPONSÁVEL  '.($dataset['user']['name']??$dataset['user']['username']??'Usuário'),3);
    $set($summary,6,0,'FILTROS  '.rpt_filters_text($dataset['filters']),3);
    $set($summary,8,0,'INDICADORES DA SELEÇÃO',4);
    foreach([[0,'REGISTROS'],[3,'SITUAÇÕES'],[6,'DEMONSTRATIVOS']] as [$c,$label])$set($summary,9,$c,$label,14);
    foreach([[0,(int)$dataset['total']],[3,count($dataset['status'])],[6,(int)$dataset['demo']]] as [$c,$value])$set($summary,10,$c,$value,15,true);
    foreach([[0,'ORÇAMENTO ESTIMADO'],[3,'EXECUÇÃO MÉDIA'],[6,'VALOR MEDIDO']] as [$c,$label])$set($summary,11,$c,$label,14);
    $set($summary,12,0,(float)$dataset['budget'],19,true);
    $set($summary,12,3,$dataset['progressCount'] ? (float)$dataset['progress']/$dataset['progressCount']/100 : 0,20,true);
    $set($summary,12,6,(float)$dataset['measured'],19,true);
    $set($summary,13,0,'DISTRIBUIÇÃO POR SITUAÇÃO',4);
    $set($summary,14,0,'SITUAÇÃO',5);$set($summary,14,3,'QUANTIDADE',5);$set($summary,14,6,'PARTICIPAÇÃO',5);
    $i=15;
    foreach($dataset['status'] as $status=>$quantity){
        $style=$i%2?6:7;
        $set($summary,$i,0,$status,$style);
        $set($summary,$i,3,(int)$quantity,$style,true);
        $set($summary,$i,6,$dataset['total'] ? (float)$quantity/$dataset['total'] : 0,$i%2?12:13,true);
        $i++;
    }
    if (!$dataset['status']){$set($summary,15,0,'Sem classificação de situação na seleção.',16);$i=16;}
    $noteRow=$i+2;
    $set($summary,$noteRow,0,'OBSERVAÇÕES',4);$summaryMerges[]='A'.$noteRow.':H'.$noteRow;
    $set($summary,$noteRow+1,0,'Os dados são extraídos do MySQL no momento da emissão. Consulte a aba Registros para ver a relação completa.',16);
    $summaryMerges[]='A'.($noteRow+1).':H'.($noteRow+1);
    if ($dataset['demo']>0){$set($summary,$noteRow+2,0,'ATENÇÃO: há registros demonstrativos nesta seleção. Validar antes de utilizar oficialmente.',16);$summaryMerges[]='A'.($noteRow+2).':H'.($noteRow+2);}
    $summaryWidth=[31,19,4,31,19,4,31,19];

    // Na aba Registros, a tabela começa em A6 e cada linha corresponde a um registro do banco.
    $data=[];$lastCol=rpt_xlsx_col($count-1);
    $set($data,1,0,'OBRAS360   /   SECRETARIA MUNICIPAL DE OBRAS E PLANEJAMENTO',1);
    $set($data,2,0,rpt_module_title($dataset['module']).' — relação de registros',18);
    $set($data,3,0,'EMISSÃO: '.date('d/m/Y H:i').'    |    RESPONSÁVEL: '.($dataset['user']['name']??'Usuário').'    |    REGISTROS: '.$dataset['total'],3);
    $set($data,4,0,'FILTROS: '.rpt_filters_text($dataset['filters']),3);
    foreach($columns as $j=>$column)$set($data,6,$j,(string)$column[1],5);
    $r=7;
    foreach($dataset['rows'] as $record){
        foreach($columns as $j=>[$key]){
            $value=$record[$key]??'';
            $kind=rpt_xlsx_numeric_kind((string)$key,$value);
            $odd=$r%2!==0;
            if ($kind==='money')$set($data,$r,$j,(float)$value,$odd?10:11,true);
            elseif ($kind==='progress')$set($data,$r,$j,((float)$value)/100,$odd?12:13,true);
            elseif ($kind==='number')$set($data,$r,$j,(float)$value,$odd?8:9,true);
            else $set($data,$r,$j,$value===''||$value===null?'—':rpt_display_value((string)$key,$value),$odd?6:7);
        }
        $r++;
    }
    if ($dataset['total']===0)$set($data,8,0,'Nenhum registro encontrado para os filtros selecionados.',16);
    $widths=[];
    foreach($columns as [$key,$label]){
        $widths[]=match((string)$key){
            'id','code','username','tipo','status','role'=>22,
            'title','name','email','local','descricao','observacao'=>42,
            'responsavel','work','contrato'=>31,
            'inicio','fim','prazo','date','data','created_at','last_login_at'=>23,
            default=>max(18,min(33,strlen((string)$label)+9))
        };
    }
    $sheetOne=rpt_xlsx_sheet($summary,$summaryWidth,$summaryMerges,'',0);
    $sheetTwo=rpt_xlsx_sheet($data,$widths,['A1:'.$lastCol.'1','A2:'.$lastCol.'2','A3:'.$lastCol.'3','A4:'.$lastCol.'4'],
        $dataset['total']>0?'A6:'.$lastCol.($r-1):'',6);
    $types='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        .'<Default Extension="xml" ContentType="application/xml"/>'
        .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        .'<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        .'</Types>';
    $files=[
        '[Content_Types].xml'=>$types,
        '_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets><sheet name="Resumo" sheetId="1" r:id="rId1"/><sheet name="Registros" sheetId="2" r:id="rId2"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml'=>rpt_xlsx_styles(),
        'xl/worksheets/sheet1.xml'=>$sheetOne,
        'xl/worksheets/sheet2.xml'=>$sheetTwo,
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
    .'<title>'.rpt_html($title).' | OBRAS360</title><link rel="stylesheet" href="assets/relatorios.css?v=3"></head><body>'
    .'<div class="print-toolbar"><div class="toolbar-brand"><span class="toolbar-logo">◈</span><div><b>OBRAS<span>360</span></b><small>Relatório gerencial</small></div></div><div class="toolbar-actions"><a class="toolbar-link" href="'.rpt_html($back).'">← Voltar</a><a class="toolbar-btn outlined" href="'.rpt_html(rpt_url($dataset,'csv')).'">Baixar CSV</a><a class="toolbar-btn outlined" href="'.rpt_html(rpt_url($dataset,'xlsx')).'">Baixar Excel</a><button class="toolbar-btn primary" type="button" id="print-now">Imprimir / Salvar PDF</button></div></div>'
    .'<div class="document"><header class="doc-header"><div class="header-mark"><div class="emblem">◈</div><div><span class="gov-small">PREFEITURA MUNICIPAL DE COARI</span><strong>Secretaria Municipal de Obras e Planejamento</strong><small>Coari · Amazonas</small></div></div><div class="edition-mark"><strong>OBRAS<span>360</span></strong><small>ACOMPANHAMENTO GERENCIAL</small></div></header>'
    .'<div class="hero-band"><div class="hero-kicker">RELATÓRIO DE GESTÃO <span> / </span> '.rpt_html(date('Y')).'</div><h1>'.rpt_html($title).'</h1><p>Síntese das informações cadastradas para acompanhamento gerencial.</p></div>'
    .'<section class="document-meta"><div><small>EMISSÃO</small><b>'.rpt_html(date('d/m/Y H:i')).' (AM)</b></div><div><small>RESPONSÁVEL PELA EMISSÃO</small><b>'.rpt_html($dataset['user']['name']??$dataset['user']['username']??'Usuário').'</b></div><div><small>ORIGEM</small><b>OBRAS360 · Registros cadastrados</b></div></section>'
    .'<section class="filters-strip"><div class="filter-label">RECORTE ANALISADO</div><div class="filter-values">'.$meta.'</div></section>'
    .$notice.$executive.$singleDetail.(!$isExec?'<section class="section stats-section"><div class="section-heading"><span>01</span><div><h2>Indicadores do recorte</h2><p>Resultados da consulta ao banco, atualizados na geração deste documento.</p></div></div>'.$summary.'</section>':'')
    .(!empty($counts)?'<section class="section situation-section"><div class="section-heading"><span>02</span><div><h2>Distribuição por situação</h2><p>Visão comparativa dos registros filtrados.</p></div></div><div class="status-grid">'.$countsHtml.'</div></section>':'')
    .$content
    .'<footer class="doc-footer"><div class="footer-note"><span class="footer-dot"></span><div><strong>Observação de integridade</strong><p>Documento gerado a partir dos dados disponíveis no OBRAS360. Validar os cadastros e eventuais dados de demonstração antes de apresentação oficial. Este relatório não substitui documentos assinados ou parecer técnico.</p></div></div><div class="footer-bottom"><strong>OBRAS360</strong><span>Secretaria Municipal de Obras e Planejamento · Coari/AM</span><span>Emitido em '.rpt_html(date('d/m/Y')).'</span></div></footer></div>'
    .'<script src="assets/relatorios.js?v=1" defer></script></body></html>';
}
