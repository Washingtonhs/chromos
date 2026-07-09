<?php

namespace Tests\Unit\Correspondencia;

use App\Services\Correspondencia\Cpf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CpfTest extends TestCase
{
    #[DataProvider('cpfsCompletos')]
    public function test_extrai_fragmento_do_meio_de_cpf_completo(string $cpfCompleto, string $fragmentoEsperado): void
    {
        $this->assertSame($fragmentoEsperado, Cpf::fragmentoDoMeio($cpfCompleto));
    }

    public static function cpfsCompletos(): array
    {
        return [
            'José Antônio Carvalho (1001)' => ['123.456.789-01', '456789'],
            'Ana Clara Silva (1004)' => ['456.789.012-34', '789012'],
            'Ana Clara Silva homônima (1005)' => ['567.890.123-45', '890123'],
            'CPF começando com zero (1010)' => ['012.345.678-90', '345678'],
        ];
    }

    public function test_extrai_fragmento_de_cpf_ja_mascarado(): void
    {
        $this->assertSame('456789', Cpf::fragmentoDoMeio('***.456.789-**'));
    }

    public function test_fragmento_indisponivel_quando_cpf_mascarado_vazio(): void
    {
        $this->assertNull(Cpf::fragmentoDoMeio(''));
        $this->assertNull(Cpf::fragmentoDoMeio(null));
    }

    public function test_fragmento_indisponivel_para_cpf_malformado(): void
    {
        $this->assertNull(Cpf::fragmentoDoMeio('123.45'));
    }

    public function test_a_homonimia_de_ana_clara_silva_e_desfeita_pelo_fragmento(): void
    {
        $fragmentoDoAprovado = Cpf::fragmentoDoMeio('***.789.012-**');

        $this->assertSame($fragmentoDoAprovado, Cpf::fragmentoDoMeio('456.789.012-34'));
        $this->assertNotSame($fragmentoDoAprovado, Cpf::fragmentoDoMeio('567.890.123-45'));
    }
}
