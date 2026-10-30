<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Professor extends Model
{
    protected $fillable = ['user_id', 'instituicao_id', 'formacao'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function instituicao()
    {
        return $this->belongsTo(Instituicao::class);
    }
}
