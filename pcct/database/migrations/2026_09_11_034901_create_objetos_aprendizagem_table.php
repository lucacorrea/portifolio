<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('objetos_aprendizagem', function (Blueprint $table) {
            $table->id();

            $table->string('curso');
            $table->string('tematica');
            $table->string('nome_disciplina');
            $table->string('titulo');
            $table->text('descricao');
            $table->string('bncc')->nullable();
            $table->string('tipo_objeto');
            $table->string('imagem')->nullable();
            $table->string('tags')->nullable();
            $table->string('link');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('objetos_aprendizagem');
    }
};
