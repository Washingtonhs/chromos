<?php

namespace App\Models;

use App\Services\Correspondencia\NivelConfianca;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['aprovado_id', 'aluno_id', 'nivel_confianca', 'pontuacao', 'criterios', 'candidatos_alternativos'])]
class Correspondencia extends Model
{
    protected function casts(): array
    {
        return [
            'nivel_confianca' => NivelConfianca::class,
            'criterios' => 'array',
            'candidatos_alternativos' => 'array',
        ];
    }

    public function aprovado(): BelongsTo
    {
        return $this->belongsTo(Aprovado::class);
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }
}
