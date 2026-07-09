<?php

namespace Tests\Feature;

use App\Models\Aluno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportarAlunosCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] */
    private array $arquivosTemporarios = [];

    protected function tearDown(): void
    {
        foreach ($this->arquivosTemporarios as $arquivo) {
            @unlink($arquivo);
        }

        parent::tearDown();
    }

    public function test_cabecalho_invalido_aborta_sem_importar_nada(): void
    {
        $caminho = $this->arquivo("coluna_errada;outra\n1;2\n");

        $this->artisan('alunos:importar', ['caminho' => $caminho])->assertFailed();

        $this->assertSame(0, Aluno::count());
    }

    public function test_linhas_invalidas_sao_descartadas_e_as_validas_importadas(): void
    {
        $caminho = $this->arquivo(
            "id_aluno;nome_completo;cpf;unidade;turma;ano_matricula\n"
            ."1;Fulano de Tal;123.456.789-01;U;T;2025\n"
            ."abc;Nome Bom;234.567.890-12;U;T;2025\n"    // id não numérico
            ."2;;345.678.901-23;U;T;2025\n"               // nome vazio
            ."3;Ciclano Souza;123;U;T;2025\n"             // CPF malformado
            ."4;Beltrano Silva;456.789.012-34;U;T;2025\n"
        );

        $this->artisan('alunos:importar', ['caminho' => $caminho])->assertSuccessful();

        $this->assertSame(2, Aluno::count());
        $this->assertSame([1, 4], Aluno::orderBy('id_aluno')->pluck('id_aluno')->all());
    }

    public function test_reimportar_atualiza_em_vez_de_duplicar(): void
    {
        $cabecalho = "id_aluno;nome_completo;cpf;unidade;turma;ano_matricula\n";

        $this->artisan('alunos:importar', [
            'caminho' => $this->arquivo($cabecalho."1;Fulano de Tal;123.456.789-01;Antiga;T;2025\n"),
        ])->assertSuccessful();

        $this->artisan('alunos:importar', [
            'caminho' => $this->arquivo($cabecalho."1;Fulano de Tal;123.456.789-01;Nova;T;2025\n"),
        ])->assertSuccessful();

        $this->assertSame(1, Aluno::count());
        $this->assertSame('Nova', Aluno::sole()->unidade);
    }

    private function arquivo(string $conteudo): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'importar_alunos_teste_');
        file_put_contents($caminho, $conteudo);
        $this->arquivosTemporarios[] = $caminho;

        return $caminho;
    }
}
