<?php

namespace App\Services\Correspondencia;

readonly class ResultadoCorrespondencia
{
    public function __construct(
        public NivelConfianca $nivelConfianca,
        public int $pontuacao,
        public ?int $alunoId,
        public array $criterios,
        public ?array $candidatosAlternativos = null,
    ) {}
}
