<?php
declare(strict_types=1);

namespace Sigesp\Demo;

final class SystemDemoData
{
    private static ?array $cache = null;

    public static function datasets(): array
    {
        if (self::$cache !== null) return self::$cache;
        $usuarios = [];
        foreach (range(1, 10) as $id) {
            $usuarios[] = ['id' => $id, 'nome' => ['Marcos Oliveira', 'Helena Costa', 'Rafael Lima', 'Patrícia Alves', 'João Nunes', 'Camila Rocha', 'André Martins', 'Bianca Reis', 'Diego Barros', 'Sofia Melo'][$id - 1], 'perfil' => ['Administrador', 'Gestor esportivo', 'Analista documental', 'Operador'][$id % 4], 'email' => sprintf('usuario%02d@demonstracao.local', $id), 'ultimo_acesso' => sprintf('2026-08-%02d %02d:15', (($id - 1) % 3) + 1, 8 + $id), 'status' => $id === 9 ? 'Inativo' : 'Ativo'];
        }
        $auditoria = [];
        foreach (range(1, 20) as $id) {
            $auditoria[] = ['id' => $id, 'usuario' => $usuarios[($id - 1) % 10]['nome'], 'acao' => ['Visualizou cadastro', 'Atualizou registro', 'Aprovou documento', 'Exportou relatório'][$id % 4], 'modulo' => ['Atletas', 'Documentos', 'Equipes', 'Relatórios'][$id % 4], 'data' => sprintf('2026-08-%02d %02d:%02d', (($id - 1) % 3) + 1, 8 + ($id % 10), ($id * 7) % 60), 'ip' => sprintf('192.0.2.%d', 20 + $id), 'antes' => 'Estado demonstrativo anterior', 'depois' => 'Estado demonstrativo atualizado', 'status' => 'Registrado'];
        }
        $permissoes = [
            ['id' => 1, 'perfil' => 'Administrador', 'modulo' => 'Todos', 'permissoes' => 'Visualizar, cadastrar, editar, excluir', 'status' => 'Ativo'],
            ['id' => 2, 'perfil' => 'Gestor esportivo', 'modulo' => 'Gestão esportiva', 'permissoes' => 'Visualizar, cadastrar, editar', 'status' => 'Ativo'],
            ['id' => 3, 'perfil' => 'Analista documental', 'modulo' => 'Documentos', 'permissoes' => 'Visualizar, analisar', 'status' => 'Ativo'],
            ['id' => 4, 'perfil' => 'Operador', 'modulo' => 'Cadastros', 'permissoes' => 'Visualizar, cadastrar', 'status' => 'Ativo'],
        ];
        $relatorios = [];
        foreach (range(1, 10) as $id) {
            $relatorios[] = ['id' => $id, 'nome' => ['Atletas ativos', 'Frequência mensal', 'Situação documental', 'Equipes por modalidade', 'Participação em eventos'][$id % 5], 'periodo' => sprintf('2026-%02d', ($id % 8) + 1), 'registros' => 80 + $id * 37, 'formato' => ['Tabela', 'Gráfico', 'Resumo'][$id % 3], 'status' => 'Disponível'];
        }
        $configuracoes = [
            ['id' => 1, 'secao' => 'Secretaria', 'configuracao' => 'Dados institucionais', 'valor' => 'Secretaria Municipal de Esporte', 'status' => 'Configurado'],
            ['id' => 2, 'secao' => 'Identidade visual', 'configuracao' => 'Marca SIGESP', 'valor' => 'Padrão demonstrativo', 'status' => 'Configurado'],
            ['id' => 3, 'secao' => 'Notificações', 'configuracao' => 'Avisos documentais', 'valor' => 'Ativo', 'status' => 'Configurado'],
            ['id' => 4, 'secao' => 'Segurança', 'configuracao' => 'Modo demonstração', 'valor' => 'Ativo', 'status' => 'Configurado'],
        ];
        $carteiras = array_map(static fn (array $athlete): array => ['id' => $athlete['id'], 'codigo' => 'CAR-' . substr($athlete['codigo'], -4), 'atleta' => $athlete['nome'], 'modalidade' => $athlete['modalidade'], 'validade' => '31/12/2026', 'status' => $athlete['status'] === 'Ativo' ? 'Válida' : 'Pendente'], AtletasDemoData::all());
        return self::$cache = compact('usuarios', 'auditoria', 'permissoes', 'relatorios', 'configuracoes', 'carteiras');
    }
}
