<?php
declare(strict_types=1);

namespace Sigesp\Demo;

final class PeopleDemoData
{
    private static ?array $cache = null;

    public static function datasets(): array
    {
        if (self::$cache !== null) return self::$cache;
        $responsibleNames = ['Marina Souza', 'Carlos Lima', 'Renata Rocha', 'Paulo Alves', 'Luciana Martins', 'Eduardo Nunes', 'Simone Costa', 'Ricardo Barros'];
        $responsibles = [];
        foreach ($responsibleNames as $index => $name) {
            $id = $index + 1;
            $responsibles[] = [
                'id' => $id, 'nome' => $name, 'parentesco' => ['Mãe', 'Pai', 'Avó', 'Responsável'][$index % 4],
                'atletas' => 1 + ($index % 3), 'telefone' => sprintf('(97) 98888-%04d', 2000 + $id),
                'email' => sprintf('responsavel%02d@demonstracao.local', $id), 'status' => $index === 6 ? 'Inativo' : 'Ativo',
            ];
        }
        $documentTypes = ['RG', 'CPF', 'Comprovante de residência', 'Atestado médico', 'Autorização', 'Foto 3x4'];
        $documentStatuses = ['Pendente', 'Aprovado', 'Aprovado', 'Vencido', 'Rejeitado'];
        $documents = [];
        foreach (range(1, 15) as $id) {
            $documents[] = [
                'id' => $id, 'atleta' => AtletasDemoData::all()[($id - 1) % 24]['nome'],
                'tipo' => $documentTypes[($id - 1) % count($documentTypes)],
                'validade' => sprintf('%d-%02d-%02d', 2026 + ($id % 2), (($id + 2) % 12) + 1, (($id + 8) % 25) + 1),
                'enviado_em' => sprintf('2026-%02d-%02d', (($id + 4) % 8) + 1, (($id + 5) % 25) + 1),
                'status' => $documentStatuses[($id - 1) % count($documentStatuses)],
            ];
        }
        return self::$cache = ['responsaveis' => $responsibles, 'documentos' => $documents];
    }
}
