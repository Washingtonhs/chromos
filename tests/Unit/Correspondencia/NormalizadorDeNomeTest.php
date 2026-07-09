<?php

namespace Tests\Unit\Correspondencia;

use App\Services\Correspondencia\NormalizadorDeNome;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NormalizadorDeNomeTest extends TestCase
{
    #[DataProvider('paresEquivalentes')]
    public function test_duas_grafias_diferentes_normalizam_para_a_mesma_forma(string $nomeA, string $nomeB): void
    {
        $this->assertSame(
            NormalizadorDeNome::normalizar($nomeA),
            NormalizadorDeNome::normalizar($nomeB),
        );
    }

    public static function paresEquivalentes(): array
    {
        return [
            'acento e caixa' => ['LARISSA GONCALVES MOREIRA', 'Larissa Gonçalves Moreira'],
            'espaco duplo' => ['Bruna  Ferreira Lima', 'Bruna Ferreira Lima'],
            'particula omitida' => ['Camila Aparecida Souza', 'Camila Aparecida de Souza'],
            'particula e acento' => ['Andre Luis Teixeira Ramos', 'André Luís Teixeira Ramos'],
            'multiplas particulas' => ['Amanda Vieira Santos Silva', 'Amanda Vieira dos Santos Silva'],
        ];
    }

    public function test_normalizacao_nao_esconde_diferenca_real_de_primeiro_nome(): void
    {
        $this->assertNotSame(
            NormalizadorDeNome::normalizar('Juliana Beatriz Xavier Campos'),
            NormalizadorDeNome::normalizar('Júlia Beatriz Xavier Campos'),
        );
    }

    public function test_normalizacao_nao_esconde_erro_de_digitacao(): void
    {
        $this->assertNotSame(
            NormalizadorDeNome::normalizar('Guilerme Rodrigues Fontes'),
            NormalizadorDeNome::normalizar('Guilherme Rodrigues Fontes'),
        );
    }

    public function test_tokens_removem_pontuacao_de_abreviacao(): void
    {
        $this->assertSame(['pedro', 'h', 'almeida', 'costa'], NormalizadorDeNome::tokens('Pedro H. Almeida Costa'));
    }

    public function test_tokens_de_nome_vazio(): void
    {
        $this->assertSame([], NormalizadorDeNome::tokens('   '));
    }
}
