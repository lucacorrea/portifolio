<?php
declare(strict_types=1);
namespace App\Integration\SO;
use App\Sales\DTO\BudgetActorData;
use App\Sales\Service\BudgetManagementService;
use App\ServiceOrder\Entity\ServiceOrder;
use App\ServiceOrder\Service\ServiceOrderManagementService;
final class BudgetApprovalIntegrationService {
    public function __construct(private readonly SoConfiguration $config, private readonly BudgetSoIntegrationRepository $integrations, private readonly SoApiClient $client, private readonly BudgetManagementService $budgets, private readonly ServiceOrderManagementService $orders, private readonly \Closure $company) {}
    public function approve(int $budgetId, BudgetActorData $actor): ServiceOrder {
        if (!$this->config->enabled()) return $this->orders->approveBudgetAndCreateOrder($budgetId, $actor);
        $reserved = $this->integrations->reserve($budgetId);
        if (($reserved['processing'] ?? false) === true) throw new \InvalidArgumentException('A integração deste orçamento já está em processamento.');
        $budget = $this->budgets->getBudget($budgetId);
        if (($reserved['already_synced'] ?? false) === true) return $this->orders->approveBudgetAndCreateOrder($budgetId, $actor);
        try {
            $response = $this->client->createAcquisition((string) $reserved['event_uuid'], $this->payload($budgetId, $actor, (string) $reserved['event_uuid']));
            $this->integrations->synchronized($budgetId, $response);
            return $this->orders->approveBudgetAndCreateOrder($budgetId, $actor);
        } catch (\Throwable $e) {
            $this->integrations->failed($budgetId, 'Falha na comunicação com o SO.');
            throw new \InvalidArgumentException('O orçamento foi preservado, mas a aquisição não pôde ser criada no SO. A integração será tentada novamente.');
        }
    }
    private function payload(int $budgetId, BudgetActorData $actor, string $event): array {
        $budget=$this->budgets->getBudget($budgetId); $company=($this->company)();
        return ['event_id'=>$event,'event_type'=>'budget.approved','source'=>['system'=>'fluxempresa','environment'=>'production'],'company'=>['legal_name'=>(string)($company['razao_social']??''),'trade_name'=>(string)($company['nome_fantasia']??''),'document'=>preg_replace('/\D/','',(string)($company['documento']??'')),'email'=>(string)($company['email']??''),'phone'=>(string)($company['telefone']??'')],'budget'=>['id'=>$budget->id(),'number'=>$budget->displayNumber(),'issue_date'=>$budget->issueDate(),'valid_until'=>$budget->validUntil(),'notes'=>$budget->notes(),'services_subtotal'=>$budget->servicesSubtotal(),'products_subtotal'=>$budget->productsSubtotal(),'others_subtotal'=>$budget->othersSubtotal(),'discount'=>$budget->discount(),'increase'=>$budget->increase(),'total'=>$budget->total()],'customer'=>['id'=>$budget->clientId(),'code'=>$budget->clientCode(),'name'=>$budget->clientName(),'document'=>preg_replace('/\D/','',$budget->clientDocument()??'')],'creator'=>['name'=>$budget->creatorName(),'profile_name'=>$budget->creatorProfileName(),'is_support'=>$budget->createdBySupport()],'approver'=>['user_id'=>$actor->userId(),'name'=>$actor->name(),'profile_code'=>$actor->profileCode(),'profile_name'=>$actor->profileName()],'items'=>array_map(static fn($i)=>['id'=>$i->id(),'type'=>$i->type(),'reference_id'=>$i->referenceId(),'description'=>$i->description(),'unit'=>$i->unit(),'quantity'=>$i->quantity(),'unit_price'=>$i->unitPrice(),'discount'=>$i->discount(),'subtotal'=>$i->subtotal()],$this->budgets->getBudgetItems($budgetId)),'requested_acquisition_status'=>'ESPERANDO_OFICIO'];
    }
}
