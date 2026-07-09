<?php

namespace Tests\Unit\Correspondencia;

use App\Services\Correspondencia\ComparadorDeNome;
use App\Services\Correspondencia\NormalizadorDeNome;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ComparadorDeNomeTest extends TestCase
{
    #[DataProvider('casosReaisDoEnunciado')]
    public function test_pontuacao_dos_casos_reais(
        string $nomeAprovado,
        string $nomeAluno,
        float $pontuacaoEsperada,
        bool $nomeIdenticoEsperado,
    ): void {
        $resultado = ComparadorDeNome::pontuar(
            NormalizadorDeNome::tokens($nomeAprovado),
            NormalizadorDeNome::tokens($nomeAluno),
        );

        $this->assertEqualsWithDelta($pontuacaoEsperada, $resultado['pontuacao'], 0.01);
        $this->assertSame($nomeIdenticoEsperado, $resultado['nome_identico']);
    }

    public static function casosReaisDoEnunciado(): array
    {
        return [
            'nome completo identico (Thiago), sem CPF, deve ser tratado como perfeito' => [
                'Thiago Henrique Barbosa', 'Thiago Henrique Barbosa', 85.0, true,
            ],
            'particula omitida (Gabriel) normaliza para identico' => [
                'Gabriel Reis Fonseca', 'Gabriel dos Reis Fonseca', 85.0, true,
            ],
            'abreviacao no nome do meio (Pedro H.) nao conta como identico' => [
                'Pedro H. Almeida Costa', 'Pedro Henrique Almeida Costa', 84.25, false,
            ],
            'erro de digitacao de uma letra (Guilerme/Guilherme)' => [
                'Guilerme Rodrigues Fontes', 'Guilherme Rodrigues Fontes', 81.11, false,
            ],
            'nome do meio inteiro ausente (Rafael Nunes)' => [
                'Rafael Nunes', 'Rafael Augusto Nunes', 77.5, false,
            ],
            'primeiro nome diferente com sobrenome identico (Juliana x Julia)' => [
                'Juliana Beatriz Xavier Campos', 'Júlia Beatriz Xavier Campos', 58.0, false,
            ],
            'primeiro nome diferente com sobrenome identico (Marcela x Marina)' => [
                'Marcela Fagundes Correia', 'Marina Fagundes Correia', 58.0, false,
            ],
        ];
    }

    public function test_teto_impede_que_sobrenome_identico_disfarce_primeiro_nome_diferente(): void
    {
        $juliaXJuliana = ComparadorDeNome::pontuar(
            NormalizadorDeNome::tokens('Juliana Beatriz Xavier Campos'),
            NormalizadorDeNome::tokens('Júlia Beatriz Xavier Campos'),
        );

        // Mesmo com sobrenome e nomes do meio 100% iguais, a pontuacao final
        // fica na faixa "ambigua" (< 65), nunca em "provavel" ou "exata".
        $this->assertLessThan(65, $juliaXJuliana['pontuacao']);
    }

    public function test_similaridade_de_primeiro_nome_e_exposta_para_o_filtro_de_plausibilidade(): void
    {
        $resultado = ComparadorDeNome::pontuar(
            NormalizadorDeNome::tokens('Lucas Oliveira Souza'),
            NormalizadorDeNome::tokens('Camila Aparecida de Souza'),
        );

        // Sobrenome coincidente ("Souza") sozinho não sustenta correspondência
        $this->assertSame(0.0, $resultado['similaridade_primeiro_nome']);
    }

    public function test_abreviacao_de_uma_letra_e_reconhecida_como_compativel(): void
    {
        $this->assertEqualsWithDelta(0.9, ComparadorDeNome::similaridadeToken('h', 'henrique'), 0.001);
        $this->assertEqualsWithDelta(0.9, ComparadorDeNome::similaridadeToken('henrique', 'h'), 0.001);
    }
}
