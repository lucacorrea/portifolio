<?php
declare(strict_types=1);

use App\Access\Entity\User;
use App\Access\Repository\ProfileRepository;
use App\Access\Repository\UserRepository;
use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

foreach ($argv ?? [] as $argument) {
    if (stripos($argument, 'password') !== false || stripos($argument, 'senha') !== false) {
        fwrite(STDERR, 'Nao informe senha por argumento de comando.' . PHP_EOL);
        exit(1);
    }
}

try {
    $app = require dirname(__DIR__) . '/bootstrap.php';
    /** @var Database $database */
    $database = $app['database'];
    $connection = $database->connection();
} catch (Throwable $exception) {
    fwrite(STDERR, 'Nao foi possivel inicializar o banco configurado.' . PHP_EOL);
    exit(1);
}

$profiles = new ProfileRepository($connection);
$users = new UserRepository($connection);

$adminProfile = $profiles->findByName('Administrador');
if ($adminProfile === null || $adminProfile->id() === null || $adminProfile->status() !== 'ativo' || !$adminProfile->isProtected()) {
    fwrite(STDERR, 'Perfil Administrador ativo e protegido nao encontrado.' . PHP_EOL);
    exit(1);
}

if ($users->countActiveAdministrators() > 0) {
    fwrite(STDERR, 'Ja existe um Administrador ativo. Nenhum usuario foi criado.' . PHP_EOL);
    exit(1);
}

$name = promptRequired('Nome completo: ');
$username = promptRequired('Usuario: ');
$email = promptRequired('E-mail: ');
$password = promptPassword('Senha: ');
$confirmation = promptPassword('Confirme a senha: ');

if ($password !== $confirmation) {
    fwrite(STDERR, 'Confirmacao de senha diferente.' . PHP_EOL);
    exit(1);
}

if (!isStrongPassword($password)) {
    fwrite(STDERR, 'Senha fraca. Use no minimo 10 caracteres, com pelo menos uma letra e um numero.' . PHP_EOL);
    exit(1);
}

if ($users->existsByUsername($username)) {
    fwrite(STDERR, 'Usuario ja cadastrado.' . PHP_EOL);
    exit(1);
}

if ($users->existsByEmail($email)) {
    fwrite(STDERR, 'E-mail ja cadastrado.' . PHP_EOL);
    exit(1);
}

try {
    $connection->beginTransaction();

    $createdAt = new DateTimeImmutable();
    $user = new User(
        null,
        $adminProfile->id(),
        $name,
        $username,
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        null,
        'ativo',
        false,
        0,
        null,
        null,
        $createdAt->format('Y-m-d H:i:s')
    );

    $userId = $users->create($user);
    $connection->commit();

    echo 'Administrador criado com ID ' . $userId . ' e usuario ' . $user->username() . '.' . PHP_EOL;
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    fwrite(STDERR, 'Nao foi possivel criar o Administrador.' . PHP_EOL);
    exit(1);
}

function promptRequired(string $label): string
{
    $value = trim((string) readline($label));
    if ($value === '') {
        fwrite(STDERR, 'Valor obrigatorio nao informado.' . PHP_EOL);
        exit(1);
    }

    return $value;
}

function promptPassword(string $label): string
{
    if (PHP_OS_FAMILY !== 'Windows') {
        fwrite(STDOUT, $label);
        shell_exec('stty -echo');
        $password = rtrim((string) fgets(STDIN), "\r\n");
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);

        return $password;
    }

    return (string) readline($label);
}

function isStrongPassword(string $password): bool
{
    return strlen($password) >= 10
        && preg_match('/[A-Za-z]/', $password) === 1
        && preg_match('/\d/', $password) === 1;
}
