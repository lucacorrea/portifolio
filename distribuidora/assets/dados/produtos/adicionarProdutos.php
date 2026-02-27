<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';

function images_dir_abs(): string {
  $dir = __DIR__ . '/./images';
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  return $dir;
}

function delete_image_if_any(?string $path): void {
  $path = trim((string)$path);
  if ($path === '') return;

  // só apaga se for dentro de images/
  if (!str_starts_with($path, 'images/')) return;

  $abs = __DIR__ . '/./' . $path;
  if (is_file($abs)) {
    @unlink($abs);
  }
}

function save_uploaded_image(string $fieldName = 'imagem'): ?string {
  if (empty($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) return null;

  $f = $_FILES[$fieldName];

if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
  if (($f['error'] ?? 0) !== UPLOAD_ERR_OK) {
    throw new RuntimeException("Falha no upload da imagem (código: " . ($f['error'] ?? -1) . ").");
  }

  $tmp = (string)($f['tmp_name'] ?? '');
  if (!is_file($tmp)) {
    throw new RuntimeException("Arquivo temporário da imagem inválido.");
  }

  // limite 5MB
  $size = (int)($f['size'] ?? 0);
  if ($size > 5 * 1024 * 1024) {
    throw new RuntimeException("Imagem muito grande. Máximo 5MB.");
  }

  // validar se é imagem de verdade
  $info = @getimagesize($tmp);
  if (!$info || empty($info['mime'])) {
    throw new RuntimeException("Arquivo enviado não é uma imagem válida.");
  }

  $mime = strtolower($info['mime']);
  $ext = match ($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    default      => ''
  };

  if ($ext === '') {
    throw new RuntimeException("Formato de imagem não suportado: {$mime}");
  }

  $dir = images_dir_abs();
  $name = 'prod_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
  $destAbs = $dir . '/' . $name;

  if (!move_uploaded_file($tmp, $destAbs)) {
    throw new RuntimeException("Não foi possível salvar a imagem no diretório images/.");
  }

  // retorna caminho relativo para salvar no banco
  return 'images/' . $name;
}

try {
  csrf_check($_POST['csrf_token'] ?? null);

  $id          = (int)($_POST['id'] ?? 0);
  $codigo      = trim((string)($_POST['codigo'] ?? ''));
  $nome        = trim((string)($_POST['nome'] ?? ''));
  $status      = only_status((string)($_POST['status'] ?? 'ATIVO'));
  $categoriaId = (int)($_POST['categoria_id'] ?? 0);
  $fornecedorId= (int)($_POST['fornecedor_id'] ?? 0);
  $unidade     = trim((string)($_POST['unidade'] ?? ''));
  $precoDec    = parse_money_to_decimal_string((string)($_POST['preco'] ?? '0'));
  $estoque     = max(0, (int)($_POST['estoque'] ?? 0));
  $minimo      = max(0, (int)($_POST['minimo'] ?? 0));
  $obs         = trim((string)($_POST['obs'] ?? ''));

  $imgRemove   = (int)($_POST['img_remove'] ?? 0) === 1;

  if ($codigo === '' || $nome === '' || $categoriaId <= 0 || $fornecedorId <= 0 || $unidade === '') {
    flash_set('danger', 'Preencha os campos obrigatórios (Código, Produto, Categoria, Fornecedor, Unidade).');
    redirect_produtos();
  }

  $pdo = pdo();

  // valida código único
  if ($id > 0) {
    $chk = $pdo->prepare("SELECT id FROM produtos WHERE codigo=? AND id<>? LIMIT 1");
    $chk->execute([$codigo, $id]);
    if ($chk->fetchColumn()) {
      flash_set('danger', 'Já existe outro produto com esse código.');
      redirect_produtos();
    }
  } else {
    $chk = $pdo->prepare("SELECT id FROM produtos WHERE codigo=? LIMIT 1");
    $chk->execute([$codigo]);
    if ($chk->fetchColumn()) {
      flash_set('danger', 'Já existe um produto com esse código.');
      redirect_produtos();
    }
  }

  // Busca imagem antiga (apenas no edit)
  $oldImg = null;
  if ($id > 0) {
    $stOld = $pdo->prepare("SELECT imagem FROM produtos WHERE id=? LIMIT 1");
    $stOld->execute([$id]);
    $oldImg = (string)($stOld->fetchColumn() ?? '');
    if ($oldImg === '') $oldImg = null;
  }

  // verifica se veio nova imagem
  $newImgPath = null;
  $hasNewImage = !empty($_FILES['imagem']) && is_array($_FILES['imagem']) && (int)($_FILES['imagem']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

  if ($hasNewImage) {
    // salva nova
    $newImgPath = save_uploaded_image('imagem');
    // apaga antiga
    delete_image_if_any($oldImg);
    // se salvou nova, ignora img_remove
    $imgRemove = false;
  } elseif ($imgRemove) {
    // remove imagem
    delete_image_if_any($oldImg);
    $newImgPath = null; // vai setar NULL
  }

  if ($id > 0) {
    // UPDATE (com ou sem mexer na imagem)
    if ($hasNewImage) {
      $st = $pdo->prepare("UPDATE produtos
        SET codigo=?, nome=?, status=?, categoria_id=?, fornecedor_id=?, unidade=?, preco=?, estoque=?, minimo=?, obs=?, imagem=?
        WHERE id=?");
      $st->execute([$codigo,$nome,$status,$categoriaId,$fornecedorId,$unidade,$precoDec,$estoque,$minimo,$obs,$newImgPath,$id]);
    } elseif ($imgRemove) {
      $st = $pdo->prepare("UPDATE produtos
        SET codigo=?, nome=?, status=?, categoria_id=?, fornecedor_id=?, unidade=?, preco=?, estoque=?, minimo=?, obs=?, imagem=NULL
        WHERE id=?");
      $st->execute([$codigo,$nome,$status,$categoriaId,$fornecedorId,$unidade,$precoDec,$estoque,$minimo,$obs,$id]);
    } else {
      // não mexe na imagem
      $st = $pdo->prepare("UPDATE produtos
        SET codigo=?, nome=?, status=?, categoria_id=?, fornecedor_id=?, unidade=?, preco=?, estoque=?, minimo=?, obs=?
        WHERE id=?");
      $st->execute([$codigo,$nome,$status,$categoriaId,$fornecedorId,$unidade,$precoDec,$estoque,$minimo,$obs,$id]);
    }

    flash_set('success', 'Produto atualizado com sucesso!');
    redirect_produtos();
  }

  // INSERT
  if ($hasNewImage) {
    // já salvou em $newImgPath
  } else {
    $newImgPath = null;
  }

  $st = $pdo->prepare("INSERT INTO produtos
    (codigo,nome,status,categoria_id,fornecedor_id,unidade,preco,estoque,minimo,obs,imagem)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)");
  $st->execute([$codigo,$nome,$status,$categoriaId,$fornecedorId,$unidade,$precoDec,$estoque,$minimo,$obs,$newImgPath]);

  flash_set('success', 'Produto cadastrado com sucesso!');
  redirect_produtos();

} catch (Throwable $e) {
  fail_page($e->getMessage());
}

?>