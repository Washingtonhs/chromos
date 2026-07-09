<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Correspondencia;
use App\Services\Correspondencia\NivelConfianca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PainelTest extends TestCase
{
    use RefreshDatabase;

    private string $caminhoRelatorio;

    private ?string $relatorioOriginal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->caminhoRelatorio = storage_path('app/relatorios/relatorio.csv');
        $this->relatorioOriginal = is_file($this->caminhoRelatorio)
            ? file_get_contents($this->caminhoRelatorio)
            : null;
    }

    protected function tearDown(): void
    {
        // O fluxo da tela regrava o relatorio.csv oficial; restaura o
        // arquivo versionado para os testes não sujarem o entregável.
        if ($this->relatorioOriginal !== null) {
            file_put_contents($this->caminhoRelatorio, $this->relatorioOriginal);
        } elseif (is_file($this->caminhoRelatorio)) {
            unlink($this->caminhoRelatorio);
        }

        parent::tearDown();
    }

    public function test_pagina_inicial_carrega_sem_dados(): void
    {
        $this->get(route('painel'))
            ->assertOk()
            ->assertSee('Base de alunos')
            ->assertSee('Nenhuma comparação realizada ainda');
    }

    public function test_importa_alunos_via_upload(): void
    {
        $resposta = $this->post(route('alunos.importar'), ['arquivo' => $this->upload('base_alunos.csv')]);

        $resposta->assertRedirect();
        $resposta->assertSessionHas('sucesso');
        $this->assertSame(30, Aluno::count());
    }

    public function test_comparar_sem_base_importada_e_bloqueado(): void
    {
        $resposta = $this->post(route('aprovados.comparar'), ['arquivo' => $this->upload('aprovados_instituicoes.csv')]);

        $resposta->assertSessionHas('erro');
        $this->assertSame(0, Correspondencia::count());
    }

    public function test_fluxo_completo_importa_compara_e_exibe_resultado(): void
    {
        $this->post(route('alunos.importar'), ['arquivo' => $this->upload('base_alunos.csv')]);

        $this->post(route('aprovados.comparar'), ['arquivo' => $this->upload('aprovados_instituicoes.csv')])
            ->assertSessionHas('sucesso');

        $this->assertSame(24, Correspondencia::count());
        $this->assertSame(2, Correspondencia::where('nivel_confianca', NivelConfianca::Ambigua)->count());

        // Ambíguas aparecem primeiro na tabela (mesma ordem do relatório)
        $this->get(route('painel'))
            ->assertOk()
            ->assertSee('Juliana Beatriz Xavier Campos')
            ->assertSee('Baixar relatório CSV');
    }

    public function test_arquivo_com_cabecalho_invalido_mostra_o_erro_do_comando(): void
    {
        $arquivo = UploadedFile::fake()->createWithContent('invalido.csv', "coluna_errada;outra\n1;2\n");

        $resposta = $this->post(route('alunos.importar'), ['arquivo' => $arquivo]);

        $resposta->assertSessionHas('erro');
        $this->assertSame(0, Aluno::count());
    }

    public function test_extensao_nao_permitida_e_rejeitada(): void
    {
        $arquivo = UploadedFile::fake()->create('planilha.xlsx', 10);

        $this->post(route('aprovados.comparar'), ['arquivo' => $arquivo])
            ->assertSessionHasErrors('arquivo');
    }

    public function test_download_do_relatorio_apos_comparacao(): void
    {
        $this->post(route('alunos.importar'), ['arquivo' => $this->upload('base_alunos.csv')]);
        $this->post(route('aprovados.comparar'), ['arquivo' => $this->upload('aprovados_instituicoes.csv')]);

        $this->get(route('relatorio.baixar'))
            ->assertOk()
            ->assertDownload('relatorio_correspondencias.csv');
    }

    public function test_download_sem_relatorio_gerado_redireciona_com_erro(): void
    {
        if (is_file($this->caminhoRelatorio)) {
            unlink($this->caminhoRelatorio);
        }

        $this->get(route('relatorio.baixar'))
            ->assertRedirect(route('painel'))
            ->assertSessionHas('erro');
    }

    private function upload(string $fixture): UploadedFile
    {
        return new UploadedFile(base_path("tests/Fixtures/{$fixture}"), $fixture, 'text/csv', null, true);
    }
}
