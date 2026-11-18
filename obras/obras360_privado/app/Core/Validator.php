<?php
declare(strict_types=1);
namespace App\Core;
use DateTimeImmutable;use InvalidArgumentException;
final class Validator {
 public static function record(string $module,array $input,bool $updating=false): array {
  $config=Modules::get($module);$data=[];
  foreach ($config['fields'] as $key=>$spec) {
   if (!empty($spec['virtual']) || ($updating&&!empty($spec['create_only']))) continue;
   $value=$input[$key]??null;
   if (is_array($value)) throw new InvalidArgumentException('Campo inválido: '.$spec['label']);
   $value=trim((string)($value??''));
   $limits=['title'=>200,'local'=>200,'responsible'=>150,'requester'=>150,
    'contract_ref'=>90,'version'=>30,'unit'=>80,'origin_department'=>150,
    'destination_department'=>150,'type'=>80,'status'=>60,'priority'=>24,'period'=>40];
   $maxLength=$limits[$key]??($spec['type']==='textarea'?6000:400);
   $chars=function_exists('mb_strlen')?mb_strlen($value,'UTF-8'):(function_exists('iconv_strlen')?iconv_strlen($value,'UTF-8'):strlen($value));
   if ($chars>$maxLength) throw new InvalidArgumentException('Campo muito longo: '.$spec['label'].'. Máximo de '.$maxLength.' caracteres.');
   if (!empty($spec['required']) && $value==='') throw new InvalidArgumentException('Preencha o campo: '.$spec['label']);
   if ($value==='') {$data[$key]=(($module==='materiais' && $key==='quantity')||($module==='obras' && $key==='budget'))?0:null;continue;}
   switch ($spec['type']) {
    case 'select': if (!in_array($value,$spec['options'],true)) throw new InvalidArgumentException('Opção inválida: '.$spec['label']);break;
    case 'date':
     $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
     if (!$dt||$dt->format('Y-m-d')!==$value) throw new InvalidArgumentException('Data inválida: '.$spec['label']); break;
    case 'month': if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$value)) throw new InvalidArgumentException('Período inválido: '.$spec['label']);break;
    case 'work':
     if (!ctype_digit($value)) throw new InvalidArgumentException('Obra inválida.');
     $q=Database::pdo()->prepare('SELECT COUNT(*) FROM works WHERE id=? AND deleted_at IS NULL');$q->execute([(int)$value]);
     if (!$q->fetchColumn()) throw new InvalidArgumentException('A obra vinculada não existe.');
     $value=(int)$value;break;
    case 'number':
     if (!is_numeric($value) || !is_finite((float)$value)) throw new InvalidArgumentException('Valor numérico inválido: '.$spec['label']);
     $value=(float)$value;
     if (isset($spec['min']) && $value<$spec['min'])throw new InvalidArgumentException('Valor abaixo do permitido: '.$spec['label']);
     if (isset($spec['max']) && $value>$spec['max'])throw new InvalidArgumentException('Valor acima do permitido: '.$spec['label']);
     break;
    default: if (strlen($value)>($spec['type']==='textarea'?6000:400))throw new InvalidArgumentException('Texto muito longo: '.$spec['label']);
   }
   $data[$key]=$value;
  }
  if (in_array($module,['obras','patrimonio'],true)) {
   if (($data['latitude']===null)!==($data['longitude']===null)) throw new InvalidArgumentException('Preencha latitude e longitude juntas.');
  }
  if ($module==='obras' && $data['start_date'] && $data['end_date'] && $data['start_date']>$data['end_date']) throw new InvalidArgumentException('A data final não pode anteceder o início.');
  return $data;
 }
}
