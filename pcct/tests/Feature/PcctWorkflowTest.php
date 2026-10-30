<?php

namespace Tests\Feature;

use App\Models\Disciplina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PcctWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_register_but_cannot_publish_material(): void
    {
        $this->post('/cadastro', [
            'name' => 'Estudante Teste',
            'email' => 'estudante@example.test',
            'password' => 'SenhaTeste123!',
            'password_confirmation' => 'SenhaTeste123!',
        ])->assertRedirect(route('painel'));

        $this->assertDatabaseHas('users', [
            'email' => 'estudante@example.test', 'role' => 'estudante',
        ]);
        $this->get('/professor/materiais/novo')->assertForbidden();
    }

    public function test_professor_can_publish_high_school_material(): void
    {
        $user = User::factory()->create(['role' => 'professor']);
        DB::table('professores')->insert([
            'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $disciplina = Disciplina::create(['nome' => 'Matemática', 'area_conhecimento' => 'Matemática']);

        $this->actingAs($user)->post(route('professor.materiais.store'), [
            'titulo' => 'Introdução às funções',
            'descricao' => 'Conceitos e exemplos de funções do primeiro grau.',
            'tematica' => 'Álgebra', 'disciplina_id' => $disciplina->id,
            'ano_escolar' => '1', 'tipo_objeto' => 'video',
            'status' => 'publicado', 'link' => 'https://example.org/aula',
        ])->assertRedirect(route('professor.materiais.index'));

        $this->assertDatabaseHas('objetos_aprendizagem', [
            'titulo' => 'Introdução às funções', 'curso' => 'Ensino Médio',
            'ano_escolar' => '1', 'status' => 'publicado',
        ]);
        $this->get('/')->assertSee('Introdução às funções');
    }

    public function test_draft_is_hidden_from_visitors(): void
    {
        $user = User::factory()->create(['role' => 'professor']);
        $professorId = DB::table('professores')->insertGetId([
            'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $disciplina = Disciplina::create(['nome' => 'História', 'area_conhecimento' => 'Ciências Humanas']);
        $id = DB::table('objetos_aprendizagem')->insertGetId([
            'curso' => 'Ensino Médio', 'tematica' => 'História do Brasil',
            'nome_disciplina' => 'História', 'titulo' => 'Material em construção',
            'descricao' => 'Rascunho de aula de história.', 'tipo_objeto' => 'documento_pdf',
            'link' => 'https://example.org/aula', 'status' => 'rascunho',
            'ano_escolar' => '2', 'professor_id' => $professorId,
            'disciplina_id' => $disciplina->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get('/materiais/'.$id)->assertNotFound();
        $this->get('/materiais/'.$id.'/midia/arquivo')->assertNotFound();
        $this->actingAs($user)->get('/materiais/'.$id)->assertOk();
    }
}
