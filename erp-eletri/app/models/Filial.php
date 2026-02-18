<?php

namespace App\Models;

use App\Core\Model;

class Filial extends Model
{
    protected $table = 'filiais';

    public function getAll($limit = 100)
    {
        return parent::getAll($limit);
    }
}
