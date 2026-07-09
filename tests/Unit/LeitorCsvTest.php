<?php

namespace Tests\Unit;

use App\Services\LeitorCsv;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LeitorCsvTest extends TestCase
{
    /** @var string[] */
    private array $arquivosTemporarios = [];

    protected function tearDown(): void
    {
        foreach ($this->arquivosTemporarios as $arquivo) {
            @unlink($arquivo);
        }

        parent::tearDown();
    }

    public function test_le_linhas_como_arrays_associativos_pelo_cabecalho(): void
    {
        $caminho = $this->arquivo("nome;cpf\nFulano;123\nCiclana;456\n");

        $this->assertSame([
            ['nome' => 'Fulano', 'cpf' => '123'],
            ['nome' => 'Ciclana', 'cpf' => '456'],
        ], LeitorCsv::ler($caminho)->all());
    }

    public function test_falha_com_mensagem_clara_quando_coluna_obrigatoria_falta(): void
    {
        $caminho = $this->arquivo("nome;cpf\nFulano;123\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('id_aluno');

        LeitorCsv::ler($caminho, ['id_aluno', 'nome'])->all();
    }

    public function test_delimitador_errado_e_detectado_pela_validacao_de_cabecalho(): void
    {
        // Com delimitador errado o cabeçalho vira uma coluna só e a validação acusa na hora
        $caminho = $this->arquivo("nome,cpf\nFulano,123\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('delimitador');

        LeitorCsv::ler($caminho, ['nome', 'cpf'])->all();
    }

    public function test_bom_utf8_do_excel_e_removido_do_cabecalho(): void
    {
        $caminho = $this->arquivo("\xEF\xBB\xBFnome;cpf\nFulano;123\n");

        $this->assertSame(
            [['nome' => 'Fulano', 'cpf' => '123']],
            LeitorCsv::ler($caminho, ['nome', 'cpf'])->all(),
        );
    }

    public function test_converte_iso_8859_1_para_utf8(): void
    {
        $conteudo = "nome\n".mb_convert_encoding('José Antônio', 'ISO-8859-1', 'UTF-8')."\n";
        $caminho = $this->arquivo($conteudo);

        $this->assertSame('José Antônio', LeitorCsv::ler($caminho)->first()['nome']);
    }

    public function test_linha_com_numero_de_colunas_diferente_do_cabecalho_e_descartada(): void
    {
        $caminho = $this->arquivo("a;b\n1;2\nlinha-solta\n3;4\n");

        $this->assertCount(2, LeitorCsv::ler($caminho)->all());
    }

    public function test_arquivo_vazio_falha_quando_ha_colunas_obrigatorias(): void
    {
        $caminho = $this->arquivo('');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('vazio');

        LeitorCsv::ler($caminho, ['nome'])->all();
    }

    private function arquivo(string $conteudo): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'leitor_csv_teste_');
        file_put_contents($caminho, $conteudo);
        $this->arquivosTemporarios[] = $caminho;

        return $caminho;
    }
}
