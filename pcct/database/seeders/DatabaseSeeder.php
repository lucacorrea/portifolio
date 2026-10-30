<?php

namespace Database\Seeders;

use App\Models\Disciplina;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $disciplinas = [
            'Linguagens' => ['Língua Portuguesa', 'Língua Inglesa', 'Arte', 'Educação Física'],
            'Matemática' => ['Matemática'],
            'Ciências da Natureza' => ['Biologia', 'Física', 'Química'],
            'Ciências Humanas' => ['História', 'Geografia', 'Filosofia', 'Sociologia'],
            'Formação Técnica' => ['Informática Básica'],
        ];

        foreach ($disciplinas as $area => $nomes) {
            foreach ($nomes as $nome) {
                Disciplina::firstOrCreate(
                    ['nome' => $nome],
                    ['area_conhecimento' => $area]
                );
            }
        }

        // Habilidades BNCC devem ser inseridas a partir dos codigos oficiais.
        // Nenhuma conta administrativa com senha predefinida e criada aqui.
    }
}
