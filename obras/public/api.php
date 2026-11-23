<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/data.php';
require_once dirname(__DIR__) . '/app/auth.php';

try {
    $u = require_login(true);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $method === 'POST' ? post_string('action', 90) : (string)($_GET['action'] ?? '');
    if ($method === 'POST') verify_csrf();
    if ($method !== 'GET' && $method !== 'POST') throw new HttpError('Método não permitido.', 405);
    $pdo = db();
    switch ($action) {
        case 'data.reload':
            if ($method !== 'GET') throw new HttpError('Método não permitido.',405);
            json_reply(['ok'=>true, 'records'=>collect_data(), 'user'=>public_user($u)]);
        case 'record.save':
            require_post($method); require_write($u);
            $module = post_string('module', 45); ensure_module($module);
            $code = post_string('code', 64);
            $old = $code !== '' ? record_row($module, $code) : null;
            if ($code !== '' && !$old) throw new HttpError('Registro não encontrado.',404);
            $prior = $old ? record_decode($old) : [];
            $data = validated_payload($module, $prior);
            if ($module === 'materiais' && $old && abs((float)$data['quantidade'] - (float)($prior['quantidade']??0)) > 0.00001) {
                throw new HttpError('Para alterar o saldo de um material existente, utilize Movimentação de estoque.');
            }
            $pdo->beginTransaction();
            try {
                if ($code === '') {
                    $code = next_code($module);
                    $data['id'] = $code;
                    $sql = 'INSERT INTO records (module, code, payload, created_by, updated_by) VALUES (?,?,?,?,?)';
                    $pdo->prepare($sql)->execute([$module, $code, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR), $u['id'], $u['id']]);
                } else {
                    $data['id'] = $code;
                    $pdo->prepare('UPDATE records SET payload=?,updated_by=? WHERE module=? AND code=? AND deleted_at IS NULL')->execute([json_encode($data, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$u['id'],$module,$code]);
                }
                upload_attachment($module, $code, (int)$u['id']);
                audit_log($old ? 'record.updated' : 'record.created', $module, $code, ['title'=>$data['title'] ?? '']);
                $pdo->commit();
                json_reply(['ok'=>true,'message'=>$old?'Registro atualizado.':'Registro cadastrado.','record'=>$data]);
            } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        case 'record.delete':
            require_post($method); require_write($u);
            $module = post_string('module',45); $code = post_string('code',64);
            $pdo->beginTransaction();
            try {
                $row = record_row($module,$code,true);
                if (!$row) throw new HttpError('Registro não encontrado.',404);
                $pdo->prepare('UPDATE records SET deleted_at=NOW(), updated_by=? WHERE module=? AND code=?')->execute([$u['id'],$module,$code]);
                audit_log('record.deleted',$module,$code);
                $pdo->commit(); json_reply(['ok'=>true,'message'=>'Registro excluído da listagem.']);
            } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        case 'stock.move':
            require_post($method); require_write($u);
            $code = post_string('material',60);
            $type = post_string('tipo',30);
            $quantity = post_string('quantidade',30);
            $note = post_string('observacao',1000);
            if (!in_array($type,['Entrada','Saída'],true) || !is_numeric($quantity) || (float)$quantity <= 0 || (float)$quantity > 1000000000) throw new HttpError('Tipo ou quantidade inválida.');
            $n = round((float)$quantity,3);
            if ($n <= 0) throw new HttpError('Quantidade muito pequena.');
            $pdo->beginTransaction();
            try {
                $row=record_row('materiais',$code,true);
                if (!$row) throw new HttpError('Material não encontrado.',404);
                $data=record_decode($row);
                $before=(float)($data['quantidade'] ?? 0);
                if ($type==='Saída' && $n>$before) throw new HttpError('A saída excede o saldo disponível.');
                $after=round($before+($type==='Entrada'?$n:-$n),3);
                $data['quantidade']=$after;
                $data['status']=$after < (float)($data['minimo']??0) ? 'Estoque baixo':'Normal';
                $pdo->prepare('UPDATE records SET payload=?,updated_by=? WHERE module=? AND code=?')->execute([json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$u['id'],'materiais',$code]);
                $pdo->prepare('INSERT INTO stock_movements(material_code,material_name,type,quantity,balance_before,balance_after,note,created_by) VALUES (?,?,?,?,?,?,?,?)')->execute([$code,$data['title'],$type,$n,$before,$after,$note,$u['id']]);
                audit_log('stock.moved','materiais',$code,['type'=>$type,'quantity'=>$n]);
                $pdo->commit(); json_reply(['ok'=>true,'message'=>'Movimentação registrada com sucesso.']);
            } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
        case 'milestone.save':
            require_post($method); require_write($u);
            $title=post_string('title',250); $work=post_string('work',250);
            $date=post_string('date',12); $status=post_string('status',35);
            if (!$title || !$work || !valid_date($date) || !in_array($status,['Programado','A confirmar','Concluído'],true)) throw new HttpError('Preencha corretamente os dados do marco.');
            $id=post_string('id',40);
            if ($id !== '') {
                if (!preg_match('/^MR-\d+$/',$id)) throw new HttpError('Identificador inválido.');
                $st=$pdo->prepare('UPDATE milestones SET title=?,work=?,due_date=?,status=?,updated_by=? WHERE id=?');
                $st->execute([$title,$work,$date,$status,$u['id'],substr($id,3)]);
                if (!$st->rowCount()) throw new HttpError('Marco não encontrado ou sem alterações.',404);
            } else {
                $st=$pdo->prepare('INSERT INTO milestones(title,work,due_date,status,created_by,updated_by) VALUES (?,?,?,?,?,?)');
                $st->execute([$title,$work,$date,$status,$u['id'],$u['id']]); $id='MR-'.$pdo->lastInsertId();
            }
            audit_log('milestone.saved','planejamento',$id);
            json_reply(['ok'=>true,'message'=>'Marco salvo com sucesso.']);
        case 'milestone.delete':
            require_post($method);require_write($u);
            $id=post_string('id',40);
            if(!preg_match('/^MR-\d+$/',$id))throw new HttpError('Marco inválido.');
            $pdo->prepare('DELETE FROM milestones WHERE id=?')->execute([substr($id,3)]);
            audit_log('milestone.deleted','planejamento',$id);
            json_reply(['ok'=>true,'message'=>'Marco removido.']);
        case 'protocol.forward':
            require_post($method); require_write($u);
            $id=post_string('code',60); $destination=post_string('destino',160); $dispatch=post_string('despacho',5000);
            if($destination==='')throw new HttpError('Informe o setor de destino.');
            $pdo->beginTransaction();
            try {
                $row=record_row('protocolos',$id,true);
                if(!$row)throw new HttpError('Protocolo não encontrado.',404);
                $data=record_decode($row);
                $from=(string)($data['destino']??'');
                $data['destino']=$destination;$data['status']='Em tramitação';
                $pdo->prepare('UPDATE records SET payload=?,updated_by=? WHERE module=? AND code=?')->execute([json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$u['id'],'protocolos',$id]);
                $pdo->prepare('INSERT INTO protocol_forwards(protocol_code,previous_sector,new_sector,dispatch,created_by) VALUES (?,?,?,?,?)')->execute([$id,$from,$destination,$dispatch,$u['id']]);
                audit_log('protocol.forwarded','protocolos',$id,['from'=>$from,'to'=>$destination]);
                $pdo->commit();json_reply(['ok'=>true,'message'=>'Protocolo encaminhado e histórico registrado.']);
            } catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
        case 'protocol.history':
            require_get($method);
            $id=(string)($_GET['code']??'');
            if(!record_row('protocolos',$id))throw new HttpError('Protocolo não encontrado.',404);
            $st=$pdo->prepare('SELECT pf.previous_sector,pf.new_sector,pf.dispatch,pf.created_at,u.name AS actor FROM protocol_forwards pf LEFT JOIN users u ON u.id=pf.created_by WHERE pf.protocol_code=? ORDER BY pf.id DESC LIMIT 100');
            $st->execute([$id]);json_reply(['ok'=>true,'items'=>$st->fetchAll()]);
        case 'attachments.list':
            require_get($method);
            $module=(string)($_GET['module']??'');$code=(string)($_GET['code']??'');
            if(!record_row($module,$code))throw new HttpError('Registro não encontrado.',404);
            $st=$pdo->prepare('SELECT id,original_name,mime,size_bytes,uploaded_at FROM attachments WHERE module=? AND record_code=? AND deleted_at IS NULL ORDER BY id DESC');
            $st->execute([$module,$code]);json_reply(['ok'=>true,'items'=>$st->fetchAll()]);
        case 'attachments.delete':
            require_post($method);require_write($u);
            $id=filter_var($_POST['id']??'',FILTER_VALIDATE_INT);
            if(!$id)throw new HttpError('Anexo inválido.');
            $st=$pdo->prepare('SELECT module,record_code FROM attachments WHERE id=? AND deleted_at IS NULL');$st->execute([$id]);$row=$st->fetch();
            if(!$row || !record_row($row['module'],$row['record_code']))throw new HttpError('Anexo não encontrado.',404);
            $pdo->prepare('UPDATE attachments SET deleted_at=NOW() WHERE id=?')->execute([$id]);
            audit_log('attachment.deleted',$row['module'],$row['record_code'],['attachment_id'=>$id]);
            json_reply(['ok'=>true,'message'=>'Anexo removido.']);
        case 'users.list':
            require_get($method);require_admin($u);
            $rows=$pdo->query('SELECT id, username,email,name,role,active,must_change_password,last_login_at,created_at FROM users ORDER BY id DESC')->fetchAll();
            json_reply(['ok'=>true,'items'=>$rows]);
        case 'users.save':
            require_post($method);require_admin($u);
            $id=(int)post_string('id',15);$name=post_string('name',150);$username=strtolower(post_string('username',50));$email=strtolower(post_string('email',190));$role=post_string('role',30);$password=post_string('password',256);
            if($name===''||!preg_match('/^[a-z0-9._-]{3,50}$/',$username)||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new HttpError('Nome, usuário ou e-mail inválido.');
            if(!in_array($role,['suporte','administrador','gestor','operador','consulta'],true))throw new HttpError('Perfil inválido.');
            if($u['role']!=='suporte'&&$role==='suporte')throw new HttpError('Somente o suporte pode conceder acesso de suporte.',403);
            $pdo->beginTransaction();
            try {
                if($id>0){
                    $st=$pdo->prepare('SELECT id,role FROM users WHERE id=? FOR UPDATE');$st->execute([$id]);$old=$st->fetch();
                    if(!$old)throw new HttpError('Usuário não encontrado.',404);
                    if($u['role']!=='suporte' && $old['role']==='suporte')throw new HttpError('Não é permitido modificar o suporte.',403);
                    if($id===(int)$u['id'] && $role!==$u['role'])throw new HttpError('Não altere seu próprio perfil.',403);
                    if($old['role']==='suporte' && $role!=='suporte') {
                        $cnt=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='suporte' AND active=1")->fetchColumn();
                        if($cnt <= 1)throw new HttpError('O último suporte ativo não pode perder seu perfil.');
                    }
                    $pdo->prepare('UPDATE users SET username=?,email=?,name=?,role=? WHERE id=?')->execute([$username,$email,$name,$role,$id]);
                    if($password!==''){
                        if(!password_secure($password))throw new HttpError('Senha: mínimo 8 caracteres, com maiúscula, minúscula, número e símbolo.');
                        if($id===(int)$u['id'])throw new HttpError('Para alterar sua senha use Meu perfil.');
                        $hash=password_hash($password,PASSWORD_ARGON2ID,['memory_cost'=>65536,'time_cost'=>3,'threads'=>2]);
                        $pdo->prepare('UPDATE users SET password_hash=?,must_change_password=1,failed_attempts=0,locked_until=NULL WHERE id=?')->execute([$hash,$id]);
                    }
                } else {
                    if(!password_secure($password))throw new HttpError('Senha: mínimo 8 caracteres, com maiúscula, minúscula, número e símbolo.');
                    $hash=password_hash($password,PASSWORD_ARGON2ID,['memory_cost'=>65536,'time_cost'=>3,'threads'=>2]);
                    $pdo->prepare('INSERT INTO users(username,email,name,role,password_hash,must_change_password,created_by) VALUES (?,?,?,?,?,1,?)')->execute([$username,$email,$name,$role,$hash,$u['id']]);
                    $id=(int)$pdo->lastInsertId();
                }
                audit_log('user.saved','usuarios',(string)$id,['role'=>$role]);
                $pdo->commit();json_reply(['ok'=>true,'message'=>'Usuário salvo com sucesso.']);
            }catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();if($e->getCode()==='23000')throw new HttpError('Usuário ou e-mail já está cadastrado.',409);throw $e;}
            catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        case 'users.toggle':
            require_post($method);require_admin($u);
            $id=(int)post_string('id',15);$active=post_string('active',2);
            if($id===(int)$u['id'])throw new HttpError('Você não pode desativar sua própria conta.');
            if(!in_array($active,['0','1'],true))throw new HttpError('Situação inválida.');
            $st=$pdo->prepare('SELECT role FROM users WHERE id=?');$st->execute([$id]);$old=$st->fetchColumn();
            if($old===false)throw new HttpError('Usuário inexistente.',404);
            if($old==='suporte'&&$u['role']!=='suporte')throw new HttpError('Não é permitido desativar o suporte.',403);
            if($old==='suporte' && $active==='0') {
                $cnt=$pdo->query("SELECT COUNT(*) FROM users WHERE role='suporte' AND active=1")->fetchColumn();
                if((int)$cnt<=1)throw new HttpError('O último usuário de suporte não pode ser desativado.');
            }
            $pdo->prepare('UPDATE users SET active=? WHERE id=?')->execute([(int)$active,$id]);
            audit_log('user.status','usuarios',(string)$id,['active'=>$active]);
            json_reply(['ok'=>true,'message'=>'Situação de acesso atualizada.']);
        case 'audit.list':
            require_get($method);require_admin($u);
            $rows=$pdo->query('SELECT a.id,a.event,a.module,a.record_code,a.created_at,u.name AS actor FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_id ORDER BY a.id DESC LIMIT 200')->fetchAll();
            json_reply(['ok'=>true,'items'=>$rows]);
        default:
            throw new HttpError('Operação não encontrada.',404);
    }
} catch (HttpError $e) { json_reply(['ok'=>false,'error'=>$e->getMessage()],$e->httpCode); }
catch (Throwable $e) { error_log('[OBRAS360 API] '.$e->getMessage()); json_reply(['ok'=>false,'error'=>'Falha interna. Confira o log do servidor e a configuração do banco.'],500); }
function require_post(string $m): void { if($m!=='POST')throw new HttpError('Método não permitido.',405); }
function require_get(string $m): void { if($m!=='GET')throw new HttpError('Método não permitido.',405); }
function valid_date(string $s): bool {
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/',$s) && checkdate((int)substr($s,5,2),(int)substr($s,8,2),(int)substr($s,0,4));
}
