<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Aprovado;
use App\Services\Correspondencia\Cpf;
use App\Services\Correspondencia\NivelConfianca;
use App\Services\Correspondencia\NormalizadorDeNome;
use App\Services\Correspondencia\ServicoDeCorrespondencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicoDeCorrespondenciaTest extends TestCase
{
    use RefreshDatabase;

    private ServicoDeCorrespondencia $servico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servico = new ServicoDeCorrespondencia;
    }

    public function test_colisao_de_fragmento_de_cpf_com_nome_sem_relacao_nao_confirma(): void
    {
        // CPF bate por coincidência, nome sem relação — não pode confirmar
        $this->criarAluno(2001, 'Zeferino Prudêncio Sobrinho', '111.456.789-11');

        $resultado = $this->servico->corresponder($this->aprovado('MARIA JOSE DA SILVA', '***.456.789-**'));

        $this->assertNotSame(NivelConfianca::Exata, $resultado->nivelConfianca);
        $this->assertNull($resultado->alunoId);
    }

    public function test_erro_de_digitacao_no_sobrenome_ainda_e_encontrado_pelo_indice_de_cpf(): void
    {
        // O blocking por sobrenome falharia ("perira"); o índice de CPF resgata o candidato
        $aluno = $this->criarAluno(2002, 'Carlos Pereira', '222.333.444-55');

        $resultado = $this->servico->corresponder($this->aprovado('Carlos Perira', '***.333.444-**'));

        $this->assertSame(NivelConfianca::Exata, $resultado->nivelConfianca);
        $this->assertSame($aluno->id, $resultado->alunoId);
    }

    public function test_sobrenome_comum_compartilhado_nao_vira_candidato_sem_primeiro_nome_plausivel(): void
    {
        $this->criarAluno(2003, 'Camila Aparecida de Souza', '333.444.555-66');

        $resultado = $this->servico->corresponder($this->aprovado('Lucas Oliveira Souza', null));

        $this->assertSame(NivelConfianca::SemCorrespondencia, $resultado->nivelConfianca);
        $this->assertNull($resultado->alunoId);
    }

    public function test_homonimos_sao_desempatados_pelo_fragmento_de_cpf(): void
    {
        $correta = $this->criarAluno(2004, 'Ana Clara Silva', '456.789.012-34');
        $this->criarAluno(2005, 'Ana Clara Silva', '567.890.123-45');

        $resultado = $this->servico->corresponder($this->aprovado('Ana Clara Silva', '***.789.012-**'));

        $this->assertSame(NivelConfianca::Exata, $resultado->nivelConfianca);
        $this->assertSame($correta->id, $resultado->alunoId);
    }

    private function criarAluno(int $idAluno, string $nome, string $cpf): Aluno
    {
        $tokens = NormalizadorDeNome::tokens($nome);

        return Aluno::create([
            'id_aluno' => $idAluno,
            'nome_completo' => $nome,
            'nome_normalizado' => implode(' ', $tokens),
            'sobrenome_normalizado' => end($tokens) ?: '',
            'cpf' => Cpf::apenasDigitos($cpf),
            'cpf_meio' => Cpf::fragmentoDoMeio($cpf),
            'unidade' => 'Unidade Teste',
            'turma' => 'Turma Teste',
            'ano_matricula' => 2025,
        ]);
    }

    private function aprovado(string $nome, ?string $cpfMascarado): Aprovado
    {
        return new Aprovado([
            'nome' => $nome,
            'cpf_mascarado' => $cpfMascarado,
            'instituicao' => 'UFMG',
            'curso' => 'Curso Teste',
            'modalidade' => 'Ampla Concorrência',
        ]);
    }
}
