<?php
declare(strict_types=1);
namespace App\Admin\Repository;
use PDO;
use RuntimeException;
final class AdminCompanyRepository
{
    private array $columns = [];
    public function __construct(private readonly PDO $connection) {}
    public function counts(int $userId): array
    {
        $result = ['total'=>0,'ativo'=>0,'pendente'=>0,'inativo'=>0,'bloqueado'=>0,'so'=>0,'mine'=>0];
        if (!$this->has('empresas', 'id')) return $result;
        $status = $this->column('empresas', ['status']); $creator = $this->column('empresas', ['criado_por','aprovado_por']);
        $sql = 'SELECT COUNT(*) total' . ($status ? ", SUM($status = 'ativo') ativo, SUM($status = 'pendente') pendente, SUM($status = 'inativo') inativo, SUM($status = 'bloqueado') bloqueado" : '') . ' FROM empresas';
        $row = $this->connection->query($sql)->fetch() ?: [];
        foreach ($result as $key => $_) if (isset($row[$key])) $result[$key] = (int) $row[$key];
        if ($creator) { $s=$this->connection->prepare("SELECT COUNT(*) FROM empresas WHERE $creator = :id"); $s->execute(['id'=>$userId]); $result['mine']=(int)$s->fetchColumn(); }
        if ($this->has('empresa_integracoes','empresa_id')) { $result['so']=(int)$this->connection->query("SELECT COUNT(DISTINCT empresa_id) FROM empresa_integracoes WHERE sistema = 'SO' AND entidade = 'fornecedor'")->fetchColumn(); }
        return $result;
    }
    public function paginate(array $filters, int $page, int $perPage, ?int $ownerId = null): array
    {
        if (!$this->has('empresas','id')) return ['items'=>[],'total'=>0];
        $fields = $this->selectFields(); $where=[]; $params=[]; $status=$this->column('empresas',['status']); $name=$this->column('empresas',['nome_fantasia','razao_social']); $creator=$this->column('empresas',['criado_por','aprovado_por']);
        if (($filters['search'] ?? '') !== '' && $name) { $where[]="$name LIKE :search"; $params['search']='%'.trim((string)$filters['search']).'%'; }
        if (($filters['status'] ?? '') !== '' && $status) { $where[]="$status = :status"; $params['status']=$filters['status']; }
        if ($ownerId !== null && $creator) { $where[]="$creator = :owner"; $params['owner']=$ownerId; }
        $condition=$where === [] ? '' : ' WHERE '.implode(' AND ',$where); $count=$this->connection->prepare('SELECT COUNT(*) FROM empresas'.$condition); $count->execute($params);
        $sql='SELECT '.implode(', ', $fields).' FROM empresas'.$condition.' ORDER BY id DESC LIMIT :limit OFFSET :offset'; $s=$this->connection->prepare($sql); foreach($params as $k=>$v)$s->bindValue(':'.$k,$v); $s->bindValue(':limit',$perPage,PDO::PARAM_INT);$s->bindValue(':offset',max(0,($page-1)*$perPage),PDO::PARAM_INT);$s->execute(); return ['items'=>$s->fetchAll(),'total'=>(int)$count->fetchColumn()];
    }
    public function find(int $id): ?array { if (!$this->has('empresas','id')) return null; $s=$this->connection->prepare('SELECT '.implode(', ',$this->selectFields()).' FROM empresas WHERE id=:id');$s->execute(['id'=>$id]);$row=$s->fetch();return is_array($row)?$row:null; }
    public function findByDocument(string $document): ?array { $column=$this->column('empresas',['documento','cnpj','cpf']); if(!$column)return null;$s=$this->connection->prepare("SELECT ".implode(', ',$this->selectFields())." FROM empresas WHERE $column=:documento LIMIT 1");$s->execute(['documento'=>$document]);$row=$s->fetch();return is_array($row)?$row:null; }
    public function integrationBySupplier(int $supplierId): ?array { if(!$this->has('empresa_integracoes','identificador_externo'))return null;$s=$this->connection->prepare("SELECT empresa_id FROM empresa_integracoes WHERE sistema='SO' AND entidade='fornecedor' AND identificador_externo=:id LIMIT 1");$s->execute(['id'=>(string)$supplierId]);$r=$s->fetch();return is_array($r)?$r:null; }
    public function create(array $data, int $approvedBy, ?int $supplierId): int
    {
        $this->connection->beginTransaction(); try { $values=[]; $params=[]; $map=['razao_social'=>'razao_social','nome_fantasia'=>'nome_fantasia','documento'=>'documento','tipo_pessoa'=>'tipo_pessoa','segmento'=>'segmento','contato_responsavel'=>'contato_responsavel','telefone'=>'telefone','email'=>'email','status'=>'status']; foreach($map as $key=>$column)if($this->has('empresas',$column)&&isset($data[$key])){$values[]=$column;$params[$column]=$data[$key];} $creator=$this->column('empresas',['criado_por','aprovado_por']);if($creator){$values[]=$creator;$params[$creator]=$approvedBy;} if($values===[])throw new RuntimeException('Estrutura administrativa indisponível.');$s=$this->connection->prepare('INSERT INTO empresas ('.implode(',',$values).') VALUES (:'.implode(',:',array_keys($params)).')');$s->execute($params);$id=(int)$this->connection->lastInsertId();if($supplierId&&$this->has('empresa_integracoes','empresa_id')){$i=$this->connection->prepare("INSERT INTO empresa_integracoes (empresa_id,sistema,entidade,identificador_externo) VALUES (:empresa_id,'SO','fornecedor',:external)");$i->execute(['empresa_id'=>$id,'external'=>(string)$supplierId]);}$this->connection->commit();return $id;}catch(\Throwable $e){if($this->connection->inTransaction())$this->connection->rollBack();throw $e;}
    }
    public function updateStatus(int $id,string $status): void { $col=$this->column('empresas',['status']);if(!$col)throw new RuntimeException('Estrutura administrativa indisponível.');$s=$this->connection->prepare("UPDATE empresas SET $col=:status WHERE id=:id");$s->execute(['status'=>$status,'id'=>$id]); }
    public function columns(string $table): array { if(isset($this->columns[$table]))return $this->columns[$table];try{$rows=$this->connection->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll();return $this->columns[$table]=array_column($rows,'Field');}catch(\Throwable){return $this->columns[$table]=[];} }
    private function has(string $table,string $column): bool{return in_array($column,$this->columns($table),true);}
    private function column(string $table,array $candidates): ?string {foreach($candidates as $c)if($this->has($table,$c))return $c;return null;}
    private function selectFields(): array { $allowed=['id','uuid','razao_social','nome_fantasia','documento','tipo_pessoa','segmento','contato_responsavel','telefone','email','status','criado_por','aprovado_por','criado_em','atualizado_em'];return array_values(array_filter($allowed,fn($c)=>$this->has('empresas',$c))); }
}
