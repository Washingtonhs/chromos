<?php

namespace App\Services\Correspondencia;

readonly class CandidatoPontuado
{
    /**
     * O fragmento tem só 6 dígitos livres: em bases grandes, CPF batendo
     * com nome sem relação é mais provável colisão do que a mesma pessoa.
     */
    private const PONTUACAO_MINIMA_DE_NOME_PARA_CPF = 50;

    private const BONUS_CPF = 15;

    public function __construct(
        public int $alunoId,
        public string $nomeCompleto,
        public float $pontuacaoNome,
        public bool $nomeIdentico,
        public bool $cpfConfere,
    ) {}

    /**
     * Única definição de "o CPF nunca confirma sozinho" — pontuação e
     * classificação passam por aqui.
     */
    public function cpfConfirmaComSeguranca(): bool
    {
        return $this->cpfConfere && $this->pontuacaoNome >= self::PONTUACAO_MINIMA_DE_NOME_PARA_CPF;
    }

    public function pontuacaoFinal(): float
    {
        $bonusCpf = $this->cpfConfirmaComSeguranca() ? self::BONUS_CPF : 0;

        return min(100.0, $this->pontuacaoNome + $bonusCpf);
    }
}
