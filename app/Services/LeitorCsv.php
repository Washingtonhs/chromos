<?php

namespace App\Services;

use Illuminate\Support\LazyCollection;
use RuntimeException;
use SplFileObject;

class LeitorCsv
{
    /**
     * Lê um CSV em streaming (memória constante), primeira linha como
     * cabeçalho. Delimitador ';' — o usual em CSV brasileiro, já que ','
     * é separador decimal. O cabeçalho é validado antes de entregar
     * qualquer linha: coluna faltando ou delimitador errado falham com
     * mensagem clara em vez de descartar tudo em silêncio.
     *
     * @param  string[]  $colunasObrigatorias
     * @return LazyCollection<int, array<string, string>>
     */
    public static function ler(string $caminho, array $colunasObrigatorias = [], string $delimitador = ';'): LazyCollection
    {
        return LazyCollection::make(function () use ($caminho, $colunasObrigatorias, $delimitador) {
            $arquivo = new SplFileObject($caminho);
            $arquivo->setFlags(
                SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE
            );
            // escape '' desativa o escape proprietário do PHP; fica só o RFC 4180
            $arquivo->setCsvControl($delimitador, '"', '');

            $cabecalho = null;

            foreach ($arquivo as $linha) {
                if ($linha === null || $linha === [null] || $linha === false) {
                    continue;
                }

                $linha = array_map(self::garantirUtf8(...), $linha);

                if ($cabecalho === null) {
                    $linha[0] = self::removerBom($linha[0]);
                    $cabecalho = array_map(trim(...), $linha);
                    self::validarCabecalho($cabecalho, $colunasObrigatorias, $delimitador);

                    continue;
                }

                if (count($linha) !== count($cabecalho)) {
                    continue;
                }

                yield array_combine($cabecalho, array_map(trim(...), $linha));
            }

            if ($cabecalho === null && $colunasObrigatorias !== []) {
                throw new RuntimeException("Arquivo CSV vazio, sem cabeçalho: {$caminho}");
            }
        });
    }

    /**
     * @param  string[]  $cabecalho
     * @param  string[]  $colunasObrigatorias
     */
    private static function validarCabecalho(array $cabecalho, array $colunasObrigatorias, string $delimitador): void
    {
        $faltantes = array_diff($colunasObrigatorias, $cabecalho);

        if ($faltantes !== []) {
            throw new RuntimeException(sprintf(
                'Cabeçalho do CSV não contém a(s) coluna(s) obrigatória(s): %s. Colunas encontradas: %s. Verifique o arquivo e o delimitador (esperado "%s").',
                implode(', ', $faltantes),
                implode(', ', $cabecalho),
                $delimitador,
            ));
        }
    }

    /**
     * O Excel salva "CSV UTF-8" com BOM; sem removê-lo, a validação de
     * cabeçalho falharia apontando colunas que parecem idênticas.
     */
    private static function removerBom(string $valor): string
    {
        return str_starts_with($valor, "\xEF\xBB\xBF") ? substr($valor, 3) : $valor;
    }

    private static function garantirUtf8(string $valor): string
    {
        return mb_check_encoding($valor, 'UTF-8')
            ? $valor
            : mb_convert_encoding($valor, 'UTF-8', 'ISO-8859-1');
    }
}
