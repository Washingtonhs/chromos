<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Aprovado;
use App\Models\Correspondencia;
use App\Services\Correspondencia\NivelConfianca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompararAprovadosCommandTest extends TestCase
{
    use RefreshDatabase;

    private const CAMINHO_ALUNOS = __DIR__.'/../Fixtures/base_alunos.csv';

    private const CAMINHO_APROVADOS = __DIR__.'/../Fixtures/aprovados_instituicoes.csv';

    protected function tearDown(): void
    {
        @unlink(storage_path('app/relatorios/teste_relatorio.csv'));

        parent::tearDown();
    }

    /**
     * Os números (17/4/2/1) vêm da conferência manual dos 24 aprovados
     * contra a base de 30 alunos — é o gabarito de regressão do projeto.
     */
    public function test_relatorio_completo_bate_com_a_conferencia_manual(): void
    {
        $this->artisan('alunos:importar', ['caminho' => self::CAMINHO_ALUNOS])
            ->assertSuccessful();

        $this->assertSame(30, Aluno::count());

        $this->artisan('aprovados:comparar', [
            'caminho' => self::CAMINHO_APROVADOS,
            '--saida' => 'teste_relatorio.csv',
        ])->assertSuccessful();

        $this->assertSame(24, Aprovado::count());
        $this->assertSame(24, Correspondencia::count());

        $this->assertSame(17, Correspondencia::where('nivel_confianca', NivelConfianca::Exata)->count());
        $this->assertSame(4, Correspondencia::where('nivel_confianca', NivelConfianca::Provavel)->count());
        $this->assertSame(2, Correspondencia::where('nivel_confianca', NivelConfianca::Ambigua)->count());
        $this->assertSame(1, Correspondencia::where('nivel_confianca', NivelConfianca::SemCorrespondencia)->count());

        $this->assertTrue(is_file(storage_path('app/relatorios/teste_relatorio.csv')));
    }

    public function test_homonimo_e_resolvido_pelo_fragmento_de_cpf_e_nao_pelo_nome(): void
    {
        $this->importarFixtures();

        $anaCorreta = Aluno::where('id_aluno', 1004)->sole();
        $anaHomonima = Aluno::where('id_aluno', 1005)->sole();
        $this->assertSame('Ana Clara Silva', $anaCorreta->nome_completo);
        $this->assertSame('Ana Clara Silva', $anaHomonima->nome_completo);

        $correspondencia = Correspondencia::whereHas('aprovado', fn ($q) => $q->where('nome', 'Ana Clara Silva'))->sole();

        $this->assertSame(NivelConfianca::Exata, $correspondencia->nivel_confianca);
        $this->assertSame($anaCorreta->id, $correspondencia->aluno_id);
        $this->assertNotSame($anaHomonima->id, $correspondencia->aluno_id);
    }

    public function test_nomes_parecidos_mas_diferentes_nunca_sao_confirmados_automaticamente(): void
    {
        $this->importarFixtures();

        foreach (['Juliana Beatriz Xavier Campos', 'Marcela Fagundes Correia'] as $nome) {
            $correspondencia = Correspondencia::whereHas('aprovado', fn ($q) => $q->where('nome', $nome))->sole();

            $this->assertSame(NivelConfianca::Ambigua, $correspondencia->nivel_confianca);
            $this->assertNull($correspondencia->aluno_id);
        }
    }

    public function test_aprovado_sem_candidato_plausivel_fica_sem_correspondencia(): void
    {
        $this->importarFixtures();

        $correspondencia = Correspondencia::whereHas('aprovado', fn ($q) => $q->where('nome', 'Lucas Oliveira Souza'))->sole();

        $this->assertSame(NivelConfianca::SemCorrespondencia, $correspondencia->nivel_confianca);
        $this->assertNull($correspondencia->aluno_id);
    }

    public function test_mesmo_aluno_aprovado_em_duas_instituicoes_gera_duas_correspondencias_validas(): void
    {
        $this->importarFixtures();

        $pedro = Aluno::where('id_aluno', 1003)->sole();

        $correspondencias = Correspondencia::where('aluno_id', $pedro->id)->get();

        $this->assertCount(2, $correspondencias);
        $this->assertTrue($correspondencias->every(fn (Correspondencia $c) => $c->nivel_confianca === NivelConfianca::Provavel));
    }

    private function importarFixtures(): void
    {
        $this->artisan('alunos:importar', ['caminho' => self::CAMINHO_ALUNOS]);
        $this->artisan('aprovados:comparar', [
            'caminho' => self::CAMINHO_APROVADOS,
            '--saida' => 'teste_relatorio.csv', // não sobrescreve o relatorio.csv versionado
        ]);
    }
}
