<?php
declare(strict_types=1);
namespace App\Admin\DTO;
final class CompanyImportData { public function __construct(private readonly int $supplierId,private readonly CompanyAdminFormData $form){}public function supplierId():int{return $this->supplierId;}public function form():CompanyAdminFormData{return $this->form;}public static function fromArray(array $data):self{$id=filter_var($data['fornecedor_so_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!is_int($id))throw new \InvalidArgumentException('Fornecedor inválido.');return new self($id,CompanyAdminFormData::fromArray($data));} }
