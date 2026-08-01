<?php
declare(strict_types=1);
namespace App\Admin\Service;
use App\Admin\Repository\AdminDashboardRepository;
final class AdminDashboardService { public function __construct(private readonly AdminDashboardRepository $repository,private readonly AdminCompanyService $companies){} public function data(int $userId):array{return ['counts'=>$this->companies->counts($userId),'segments'=>$this->repository->segments(),'months'=>$this->repository->months()];} }
