<?php

namespace App\Services\Correspondencia;

use Illuminate\Support\Str;

class NormalizadorDeNome
{
    /**
     * Partículas tratadas como ruído gramatical, não como parte da
     * identidade do nome: "Silva" e "da Silva" são a mesma pessoa.
     */
    private const PARTICULAS = ['de', 'da', 'do', 'das', 'dos'];

    /**
     * Duas grafias que produzem a mesma string aqui são tratadas como o
     * mesmo nome pelo comparador.
     */
    public static function normalizar(string $nome): string
    {
        return implode(' ', self::tokens($nome));
    }

    /**
     * @return string[]
     */
    public static function tokens(string $nome): array
    {
        $minusculoSemAcento = mb_strtolower(Str::ascii($nome));
        $brutos = preg_split('/\s+/', trim($minusculoSemAcento), -1, PREG_SPLIT_NO_EMPTY);

        $tokens = [];

        foreach ($brutos as $token) {
            $apenasLetras = preg_replace('/[^a-z]/', '', $token);

            if ($apenasLetras === '' || in_array($apenasLetras, self::PARTICULAS, true)) {
                continue;
            }

            $tokens[] = $apenasLetras;
        }

        return $tokens;
    }
}
