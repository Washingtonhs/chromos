<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id_aluno', 'nome_completo', 'nome_normalizado', 'sobrenome_normalizado', 'cpf', 'cpf_meio', 'unidade', 'turma', 'ano_matricula'])]
class Aluno extends Model
{
    public function correspondencias(): HasMany
    {
        return $this->hasMany(Correspondencia::class);
    }
}
