<?php

namespace App\Services\Correspondencia;

class ComparadorDeNome
{
    private const PESO_PRIMEIRO_NOME = 35;

    private const PESO_SOBRENOME = 35;

    private const PESO_NOMES_DO_MEIO = 15;

    /**
     * Acima disso, dois tokens são tratados como a mesma palavra para
     * fins de pontuação cheia (typos leves, abreviação, etc.).
     */
    private const LIMIAR_TOKEN_COMPATIVEL = 0.82;

    /**
     * Pontuação máxima que um primeiro nome ou sobrenome pode contribuir
     * quando fica abaixo do limiar de compatibilidade. Existe para
     * impedir que um sobrenome idêntico "carregue" um primeiro nome
     * claramente diferente (ex.: "Juliana" x "Júlia", "Marcela" x "Marina").
     */
    private const TETO_TOKEN_DIVERGENTE = 8;

    /**
     * Pontuação de similaridade só de nome (0 a 85); o CPF é sinal do
     * orquestrador, não do nome, e fica fora daqui.
     *
     * @param  string[]  $tokensAprovado
     * @param  string[]  $tokensAluno
     * @return array{pontuacao: float, nome_identico: bool, similaridade_primeiro_nome: float}
     */
    public static function pontuar(array $tokensAprovado, array $tokensAluno): array
    {
        if ($tokensAprovado === [] || $tokensAluno === []) {
            return ['pontuacao' => 0.0, 'nome_identico' => false, 'similaridade_primeiro_nome' => 0.0];
        }

        $similaridadePrimeiroNome = self::similaridadeToken($tokensAprovado[0], $tokensAluno[0]);

        $pontuacao = self::pontuarTokenPrincipal($similaridadePrimeiroNome, self::PESO_PRIMEIRO_NOME)
            + self::pontuarTokenPrincipal(
                self::similaridadeToken(end($tokensAprovado), end($tokensAluno)),
                self::PESO_SOBRENOME
            )
            + self::pontuarNomesDoMeio(
                array_slice($tokensAprovado, 1, -1),
                array_slice($tokensAluno, 1, -1)
            );

        return [
            'pontuacao' => round($pontuacao, 2),
            'nome_identico' => $tokensAprovado === $tokensAluno,
            'similaridade_primeiro_nome' => $similaridadePrimeiroNome,
        ];
    }

    private static function pontuarTokenPrincipal(float $similaridade, int $peso): float
    {
        if ($similaridade >= self::LIMIAR_TOKEN_COMPATIVEL) {
            return $similaridade * $peso;
        }

        return min($similaridade * $peso, self::TETO_TOKEN_DIVERGENTE);
    }

    /**
     * Aprovado sem nome do meio não confirma nem contradiz — vale meio
     * peso; ambos sem nome do meio, nenhuma discrepância — peso integral.
     *
     * @param  string[]  $tokensAprovado
     * @param  string[]  $tokensAluno
     */
    private static function pontuarNomesDoMeio(array $tokensAprovado, array $tokensAluno): float
    {
        if ($tokensAprovado === [] && $tokensAluno === []) {
            return self::PESO_NOMES_DO_MEIO;
        }

        if ($tokensAprovado === []) {
            return self::PESO_NOMES_DO_MEIO * 0.5;
        }

        $somaMelhores = 0.0;

        foreach ($tokensAprovado as $token) {
            $melhor = 0.0;

            foreach ($tokensAluno as $tokenAluno) {
                $melhor = max($melhor, self::similaridadeToken($token, $tokenAluno));
            }

            $somaMelhores += $melhor;
        }

        return self::PESO_NOMES_DO_MEIO * ($somaMelhores / count($tokensAprovado));
    }

    public static function similaridadeToken(string $tokenA, string $tokenB): float
    {
        if ($tokenA === $tokenB) {
            return 1.0;
        }

        if (self::ehAbreviacaoDe($tokenA, $tokenB) || self::ehAbreviacaoDe($tokenB, $tokenA)) {
            return 0.9;
        }

        $maiorTamanho = max(strlen($tokenA), strlen($tokenB));

        if ($maiorTamanho === 0) {
            return 0.0;
        }

        return 1 - (levenshtein($tokenA, $tokenB) / $maiorTamanho);
    }

    private static function ehAbreviacaoDe(string $inicial, string $completo): bool
    {
        return strlen($inicial) === 1 && strlen($completo) > 1 && $inicial[0] === $completo[0];
    }
}
