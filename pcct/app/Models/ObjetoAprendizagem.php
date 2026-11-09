<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ObjetoAprendizagem extends Model
{
    use HasFactory;

    protected $table = 'objetos_aprendizagem';

    protected $fillable = [
        'curso', 'tematica', 'nome_disciplina', 'titulo', 'descricao',
        'bncc', 'tipo_objeto', 'imagem', 'tags', 'link', 'professor_id',
        'disciplina_id', 'ano_escolar', 'status', 'arquivo',
    ];

    protected $casts = [
        'professor_id' => 'integer',
        'disciplina_id' => 'integer',
    ];

    public function professor()
    {
        return $this->belongsTo(Professor::class);
    }

    public function disciplina()
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function habilidades()
    {
        return $this->belongsToMany(HabilidadeBncc::class, 'objeto_habilidade', 'objeto_id', 'habilidade_id')
            ->withTimestamps();
    }
}
