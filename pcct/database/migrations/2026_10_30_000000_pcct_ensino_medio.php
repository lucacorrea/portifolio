<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Funciona tanto com as migrations originais quanto com o SQL manual
        // pcct/database/PCCT_estrutura_banco.sql ja importado.
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'professor', 'estudante'])->nullable();
            });
        }

        if (! Schema::hasTable('instituicoes')) {
            Schema::create('instituicoes', function (Blueprint $table) {
                $table->id();
                $table->string('nome');
                $table->text('descricao')->nullable();
                $table->string('localidade')->nullable();
                $table->string('url', 2048)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('administradores')) {
            Schema::create('administradores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('professores')) {
            Schema::create('professores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->foreignId('instituicao_id')->nullable()->constrained('instituicoes')->nullOnDelete();
                $table->string('formacao', 150)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('estudantes')) {
            Schema::create('estudantes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->foreignId('instituicao_id')->nullable()->constrained('instituicoes')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('disciplinas')) {
            Schema::create('disciplinas', function (Blueprint $table) {
                $table->id();
                $table->string('nome');
                $table->text('descricao')->nullable();
                $table->string('area_conhecimento', 100)->nullable();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('disciplinas', 'area_conhecimento')) {
            Schema::table('disciplinas', function (Blueprint $table) {
                $table->string('area_conhecimento', 100)->nullable();
            });
        }

        if (! Schema::hasTable('habilidades_bncc')) {
            Schema::create('habilidades_bncc', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 40)->unique();
                $table->string('nome');
                $table->text('descricao')->nullable();
                $table->foreignId('disciplina_id')->nullable()->constrained('disciplinas')->nullOnDelete();
                $table->string('ano_escolar', 80)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('objetos_aprendizagem', 'professor_id')) {
            Schema::table('objetos_aprendizagem', function (Blueprint $table) {
                $table->foreignId('professor_id')->nullable()->constrained('professores')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('objetos_aprendizagem', 'disciplina_id')) {
            Schema::table('objetos_aprendizagem', function (Blueprint $table) {
                $table->foreignId('disciplina_id')->nullable()->constrained('disciplinas')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('objetos_aprendizagem', 'ano_escolar')) {
            Schema::table('objetos_aprendizagem', function (Blueprint $table) {
                $table->string('ano_escolar', 10)->nullable();
            });
        }
        if (! Schema::hasColumn('objetos_aprendizagem', 'status')) {
            Schema::table('objetos_aprendizagem', function (Blueprint $table) {
                $table->enum('status', ['rascunho', 'publicado'])->default('rascunho');
            });
        }
        if (! Schema::hasColumn('objetos_aprendizagem', 'arquivo')) {
            Schema::table('objetos_aprendizagem', function (Blueprint $table) {
                $table->string('arquivo')->nullable();
            });
        }

        if (! Schema::hasTable('objeto_habilidade')) {
            Schema::create('objeto_habilidade', function (Blueprint $table) {
                $table->foreignId('objeto_id')->constrained('objetos_aprendizagem')->cascadeOnDelete();
                $table->foreignId('habilidade_id')->constrained('habilidades_bncc')->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['objeto_id', 'habilidade_id']);
            });
        }
    }

    public function down(): void
    {
        // Nao removemos tabelas ou colunas automaticamente no rollback:
        // elas podem ter sido criadas pelo SQL manual e conter dados reais.
    }
};
