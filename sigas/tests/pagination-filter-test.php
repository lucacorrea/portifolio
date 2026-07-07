<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

App\Core\Autoloader::register();

use App\Domain\UserStatus;
use App\DTO\PaginatedResult;
use App\DTO\Pagination;
use App\DTO\UserFilter;

$failures = 0;

function assert_true(bool $condition, string $message): void
{
    global $failures;

    if (!$condition) {
        $failures++;
        echo "FAIL: {$message}" . PHP_EOL;
    }
}

$pagination = new Pagination(0, 500);
assert_true($pagination->getPage() === 1, 'pagina minima');
assert_true($pagination->getPerPage() === 100, 'limite maximo por pagina');
assert_true($pagination->getOffset() === 0, 'offset inicial');

$filter = new UserFilter(status: UserStatus::ACTIVE, page: 2, perPage: 10, sortBy: 'invalido', direction: 'DESC');
assert_true($filter->sortBy === 'nome', 'ordenacao invalida usa padrao');
assert_true($filter->direction === 'desc', 'direcao normalizada');

$result = new PaginatedResult([1, 2], 25, 2, 10);
assert_true($result->getTotalPages() === 3, 'total de paginas');
assert_true($result->hasPreviousPage(), 'tem pagina anterior');
assert_true($result->hasNextPage(), 'tem proxima pagina');

echo $failures === 0 ? 'PASS pagination-filter-test' . PHP_EOL : "FAILURES: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
