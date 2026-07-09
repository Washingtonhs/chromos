<?php

namespace App\Services\Correspondencia;

class Cpf
{
    public static function apenasDigitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor) ?? '';
    }

    /**
     * Os 6 dígitos do meio — o trecho visível na máscara ***.XXX.XXX-**
     * das listas oficiais; funciona para CPF completo ou já mascarado.
     */
    public static function fragmentoDoMeio(?string $valor): ?string
    {
        $digitos = self::apenasDigitos($valor);

        return match (strlen($digitos)) {
            11 => substr($digitos, 3, 6),
            6 => $digitos,
            default => null,
        };
    }
}
