<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

class DashboardController
{
    public function index()
    {
        $pageTitle = 'Dashboard Admin';

        $contentView = __DIR__ . '/../../../../resources/views/admin/dashboard.php';

        include __DIR__ . '/../../../../resources/views/layouts/admin.php';
    }
}