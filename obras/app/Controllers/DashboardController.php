<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Repository,Database};
final class DashboardController {
 public static function index(): void {
  Auth::requireLogin();$totals=Repository::totals();$works=Repository::list('obras',[],8);$statuses=Repository::statusCounts('obras');
  $orders=Repository::list('ordens',[],5);$alerts=Database::pdo()->query('SELECT * FROM materials WHERE deleted_at IS NULL AND quantity<minimum ORDER BY quantity LIMIT 6')->fetchAll();
  $milestones=Repository::list('marcos',[],6);\render('modules/dashboard',compact('totals','works','statuses','orders','alerts','milestones')+['pageTitle'=>'Painel geral']);
 }
 public static function planning(): void {
  Auth::requireLogin();$works=Repository::list('obras',[],100);$milestones=Repository::list('marcos',[],100);
  \render('modules/planning',compact('works','milestones')+['pageTitle'=>'Planejamento']);
 }
}
