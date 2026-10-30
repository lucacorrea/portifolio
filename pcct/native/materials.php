<?php
declare(strict_types=1);

function teacher_id(): int
{
    $user = need_role('professor');
    $st = db()->prepare('SELECT id FROM professores WHERE user_id = ?');
    $st->execute([$user['id']]);
    $id = (int)$st->fetchColumn();
    if (!$id) abort_page(403, 'Perfil de professor não encontrado. Solicite ajuste ao administrador.');
    return $id;
}

function teacher_material(int $id): array
{
    $st = db()->prepare('SELECT * FROM objetos_aprendizagem WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $material = $st->fetch();
    if (!$material) abort_page(404);
    if ((int)$material['professor_id'] !== teacher_id()) abort_page(403);
    return $material;
}

function materials_index(): void
{
    $teacher = teacher_id();
    $st = db()->prepare('SELECT o.*,d.nome AS disciplina_nome FROM objetos_aprendizagem o
                          LEFT JOIN disciplinas d ON d.id=o.disciplina_id
                          WHERE o.professor_id=? ORDER BY o.id DESC LIMIT 100');
    $st->execute([$teacher]);
    view('materials', ['materiais' => $st->fetchAll()]);
}

function materials_form(int $id = 0): void
{
    $teacher = teacher_id();
    $material = $id ? teacher_material($id) : null;
    $disc = db()->query('SELECT id,nome FROM disciplinas ORDER BY nome')->fetchAll();
    $skills = db()->query('SELECT id,codigo,nome,disciplina_id FROM habilidades_bncc ORDER BY codigo')->fetchAll();
    $selected = [];
    if ($id) {
        $st = db()->prepare('SELECT habilidade_id FROM objeto_habilidade WHERE objeto_id=?');
        $st->execute([$id]);
        $selected = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
    view('material-form', compact('material','disc','skills','selected'));
}

function save_upload(string $key, string $kind): ?string
{
    $file = $_FILES[$key] ?? null;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Falha no envio de '.$key.'. Confira o limite de tamanho no PHP.');
    }
    $max = $kind === 'capas' ? 2*1024*1024 : 10*1024*1024;
    if ($file['size'] > $max || $file['size'] < 1) {
        throw new InvalidArgumentException('Arquivo inválido ou maior que o limite permitido.');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = $kind === 'capas'
        ? ['jpg','jpeg','png','webp']
        : ['pdf','doc','docx','ppt','pptx','mp3','mp4','webm'];
    if (!in_array($ext, $allowed, true)) {
        throw new InvalidArgumentException('Formato não permitido.');
    }
    if ($kind === 'capas' && @getimagesize($file['tmp_name']) === false) {
        throw new InvalidArgumentException('Imagem inválida.');
    }
    $relative = 'pcct/'.$kind.'/'.bin2hex(random_bytes(16)).'.'.$ext;
    $dir = PCCT_ROOT . '/storage/private/pcct/' . $kind;
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível criar pasta de arquivos.');
    }
    if (!move_uploaded_file($file['tmp_name'], PCCT_ROOT . '/storage/private/' . $relative)) {
        throw new RuntimeException('Não foi possível salvar o arquivo.');
    }
    return $relative;
}

function remove_upload(?string $relative): void
{
    if (!$relative || !preg_match('~^pcct/(capas|arquivos)/[a-f0-9]{32}\.[a-z0-9]{2,5}$~', $relative)) return;
    $file = PCCT_ROOT . '/storage/private/' . $relative;
    if (is_file($file)) @unlink($file);
}

function materials_save(int $id = 0): never
{
    $teacher = teacher_id();
    $existing = $id ? teacher_material($id) : null;
    $title = input('titulo');
    $description = input('descricao');
    $topic = input('tematica');
    $disc = (int)input('disciplina_id');
    $year = input('ano_escolar');
    $type = input('tipo_objeto');
    $status = input('status');
    $tags = input('tags');
    $link = input('link');
    $types = ['video','slide','documento_pdf','podcast_audio','quiz_avaliacao','mapa_mental','outro'];
    $returnTo = $id ? '/professor/materiais/'.$id.'/editar' : '/professor/materiais/novo';
    if ($title === '' || field_len($title) > 255 || $description === '' || field_len($description) > 10000
        || $topic === '' || field_len($topic) > 255 || !in_array($year, ['1','2','3'], true)
        || !in_array($type, $types, true) || !in_array($status, ['rascunho','publicado'], true)
        || field_len($tags) > 255 || field_len($link) > 255
        || ($link !== '' && (!filter_var($link, FILTER_VALIDATE_URL)
            || !in_array(strtolower((string)parse_url($link,PHP_URL_SCHEME)), ['http','https'], true)))) {
        fail('Confira título, conteúdo, série, formato e link.', $returnTo);
    }
    $st = db()->prepare('SELECT id,nome FROM disciplinas WHERE id=?');
    $st->execute([$disc]);
    $subject = $st->fetch();
    if (!$subject) fail('Selecione uma disciplina válida.', $returnTo);

    $skills = array_values(array_unique(array_map('intval', input_array('habilidades'))));
    $skills = array_values(array_filter($skills, fn($x)=>$x > 0));
    if ($skills) {
        $marks = implode(',', array_fill(0, count($skills), '?'));
        $st = db()->prepare("SELECT id,codigo FROM habilidades_bncc
            WHERE disciplina_id = ? AND id IN ($marks) ORDER BY id");
        $st->execute(array_merge([$disc],$skills));
        $valid = $st->fetchAll();
        if (count($valid) !== count($skills)) fail('Habilidades BNCC não pertencem à disciplina.', $returnTo);
        $bncc = $valid[0]['codigo'];
    } else $bncc = null;

    if (!$link && empty($_FILES['arquivo']['name']) && empty($existing['arquivo'])) {
        fail('Informe um link ou envie um arquivo.', $returnTo);
    }

    $created = [];
    try {
        $image = save_upload('imagem', 'capas');
        $file = save_upload('arquivo', 'arquivos');
        if ($image) $created[] = $image;
        if ($file) $created[] = $file;
        db()->beginTransaction();
        if ($id) {
            $st = db()->prepare('UPDATE objetos_aprendizagem SET curso=?,tematica=?,nome_disciplina=?,
              titulo=?,descricao=?,bncc=?,tipo_objeto=?,imagem=?,tags=?,link=?,disciplina_id=?,
              ano_escolar=?,status=?,arquivo=?,updated_at=NOW() WHERE id=? AND professor_id=?');
            $st->execute(['Ensino Médio',$topic,$subject['nome'],$title,$description,$bncc,$type,
                $image ?: $existing['imagem'],$tags ?: null,$link,$disc,$year,$status,
                $file ?: $existing['arquivo'],$id,$teacher]);
            $materialId = $id;
        } else {
            $st = db()->prepare('INSERT INTO objetos_aprendizagem(curso,tematica,nome_disciplina,
               titulo,descricao,bncc,tipo_objeto,imagem,tags,link,professor_id,disciplina_id,
               ano_escolar,status,arquivo,created_at,updated_at)
               VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
            $st->execute(['Ensino Médio',$topic,$subject['nome'],$title,$description,$bncc,$type,
                $image,$tags ?: null,$link,$teacher,$disc,$year,$status,$file]);
            $materialId = (int)db()->lastInsertId();
        }
        $st = db()->prepare('DELETE FROM objeto_habilidade WHERE objeto_id=?');
        $st->execute([$materialId]);
        $st = db()->prepare('INSERT INTO objeto_habilidade(objeto_id,habilidade_id,created_at,updated_at)
                              VALUES(?,?,NOW(),NOW())');
        foreach ($skills as $skill) $st->execute([$materialId,$skill]);
        db()->commit();
        if ($image && $existing) remove_upload($existing['imagem']);
        if ($file && $existing) remove_upload($existing['arquivo']);
    } catch (InvalidArgumentException $e) {
        foreach ($created as $rel) remove_upload($rel);
        if (db()->inTransaction()) db()->rollBack();
        fail($e->getMessage(), $returnTo);
    } catch (Throwable $e) {
        foreach ($created as $rel) remove_upload($rel);
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
    flash($id ? 'Material atualizado.' : 'Material cadastrado.');
    go('/professor/materiais');
}

function materials_delete(int $id): never
{
    teacher_id();
    $material = teacher_material($id);
    $st = db()->prepare('DELETE FROM objetos_aprendizagem WHERE id=? AND professor_id=?');
    $st->execute([$id,$material['professor_id']]);
    remove_upload($material['imagem']);
    remove_upload($material['arquivo']);
    flash('Material excluído.');
    go('/professor/materiais');
}
