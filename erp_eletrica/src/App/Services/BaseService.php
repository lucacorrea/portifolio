<?php
namespace App\Services;

abstract class BaseService {
    protected $repository;

    public function __construct($repository = null) {
        $this->repository = $repository;
    }

    protected function validate(array $data, array $rules) {
        $errors = [];
        foreach ($rules as $field => $rule) {
            if ($rule === 'required' && empty($data[$field])) {
                $errors[] = "O campo {$field} é obrigatório.";
            }
        }
        return $errors;
    }

    protected function logAction(string $action, string $table = null, int $id = null, $old = null, $new = null) {
        $audit = new AuditLogService();
        $audit->record($action, $table, $id, $old, $new);
    }
}
