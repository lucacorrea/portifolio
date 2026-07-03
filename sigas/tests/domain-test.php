<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

App\Core\Autoloader::register();

use App\Domain\AccessLevelSlug;
use App\Domain\AuthorizationScope;
use App\Domain\UserStatus;
use App\Domain\UserStatusTransition;

$failures = 0;

function assert_true(bool $condition, string $message): void
{
    global $failures;

    if (!$condition) {
        $failures++;
        echo "FAIL: {$message}" . PHP_EOL;
    }
}

assert_true(UserStatus::ACTIVE->value === 'ativo', 'UserStatus ativo');
assert_true(AccessLevelSlug::SUPPORT->value === 'suporte', 'AccessLevelSlug suporte');
assert_true(AuthorizationScope::SECTOR->value === 'setorial', 'AuthorizationScope setorial');
assert_true(UserStatusTransition::canTransition(UserStatus::PENDING, UserStatus::ACTIVE), 'pendente para ativo');
assert_true(!UserStatusTransition::canTransition(UserStatus::REJECTED, UserStatus::PENDING), 'rejeitado sem reabertura');
assert_true(UserStatusTransition::canTransition(UserStatus::REJECTED, UserStatus::PENDING, true), 'rejeitado com reabertura');

echo $failures === 0 ? 'PASS domain-test' . PHP_EOL : "FAILURES: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
