<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ObjetoAprendizagem extends Model
{
    use HasFactory;

    protected $filable=[
        'curso',
        'tematica',
        'nome_disciplina',
        'titulo',
        'descricao',
        'bncc',
        'tipo_objeto',
        'imagem',
        'tags',
        'link'
    ];

}
