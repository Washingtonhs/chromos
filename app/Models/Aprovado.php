<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['nome', 'cpf_mascarado', 'instituicao', 'curso', 'modalidade'])]
class Aprovado extends Model
{
    public function correspondencia(): HasOne
    {
        return $this->hasOne(Correspondencia::class);
    }
}
