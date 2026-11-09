<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HabilidadeBncc extends Model
{
    protected $table = 'habilidades_bncc';
    protected $fillable = ['codigo', 'nome', 'descricao', 'disciplina_id', 'ano_escolar'];

    public function disciplina()
    {
        return $this->belongsTo(Disciplina::class);
    }
}
