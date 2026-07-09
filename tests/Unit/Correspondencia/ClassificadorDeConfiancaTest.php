<?php

namespace Tests\Unit\Correspondencia;

use App\Services\Correspondencia\CandidatoPontuado;
use App\Services\Correspondencia\ClassificadorDeConfianca;
use App\Services\Correspondencia\NivelConfianca;
use PHPUnit\Framework\TestCase;

class ClassificadorDeConfiancaTest extends TestCase
{
    public function test_sem_candidatos_e_sem_correspondencia(): void
    {
        $resultado = ClassificadorDeConfianca::classificar([]);

        $this->assertSame(NivelConfianca::SemCorrespondencia, $resultado->nivelConfianca);
        $this->assertNull($resultado->alunoId);
    }

    public function test_nome_identico_sem_cpf_e_exata(): void
    {
        $candidato = new CandidatoPontuado(1012, 'Thiago Henrique Barbosa', 85.0, nomeIdentico: true, cpfConfere: false);

        $resultado = ClassificadorDeConfianca::classificar([$candidato]);

        $this->assertSame(NivelConfianca::Exata, $resultado->nivelConfianca);
        $this->assertSame(1012, $resultado->alunoId);
    }

    public function test_cpf_confirma_apesar_de_abreviacao_e_exata(): void
    {
        // Fernanda C. Lopes -> Fernanda Cristina Lopes, com CPF confirmando
        $candidato = new CandidatoPontuado(1017, 'Fernanda Cristina Lopes', 83.5, nomeIdentico: false, cpfConfere: true);

        $resultado = ClassificadorDeConfianca::classificar([$candidato]);

        $this->assertSame(NivelConfianca::Exata, $resultado->nivelConfianca);
        $this->assertSame(1017, $resultado->alunoId);
    }

    public function test_abreviacao_sem_cpf_e_provavel(): void
    {
        // Pedro H. Almeida Costa, sem CPF disponivel
        $candidato = new CandidatoPontuado(1003, 'Pedro Henrique Almeida Costa', 84.25, nomeIdentico: false, cpfConfere: false);

        $resultado = ClassificadorDeConfianca::classificar([$candidato]);

        $this->assertSame(NivelConfianca::Provavel, $resultado->nivelConfianca);
        $this->assertSame(1003, $resultado->alunoId);
    }

    public function test_primeiro_nome_divergente_com_sobrenome_identico_e_ambigua_e_nao_confirma_aluno(): void
    {
        // Juliana Beatriz Xavier Campos x Júlia Beatriz Xavier Campos
        $candidato = new CandidatoPontuado(1019, 'Júlia Beatriz Xavier Campos', 58.0, nomeIdentico: false, cpfConfere: false);

        $resultado = ClassificadorDeConfianca::classificar([$candidato]);

        $this->assertSame(NivelConfianca::Ambigua, $resultado->nivelConfianca);
        $this->assertNull($resultado->alunoId);
        $this->assertNotEmpty($resultado->candidatosAlternativos);
    }

    public function test_pontuacao_muito_baixa_e_sem_correspondencia(): void
    {
        $candidato = new CandidatoPontuado(1022, 'Lucas Gabriel Freitas Diniz', 15.0, nomeIdentico: false, cpfConfere: false);

        $resultado = ClassificadorDeConfianca::classificar([$candidato]);

        $this->assertSame(NivelConfianca::SemCorrespondencia, $resultado->nivelConfianca);
        $this->assertNull($resultado->alunoId);
    }

    public function test_cpf_desempata_homonimos_com_nome_identico(): void
    {
        // Ana Clara Silva: dois candidatos com nome 100% identico, so um bate o CPF
        $candidatoCorreto = new CandidatoPontuado(1004, 'Ana Clara Silva', 85.0, nomeIdentico: true, cpfConfere: true);
        $candidatoHomonimo = new CandidatoPontuado(1005, 'Ana Clara Silva', 85.0, nomeIdentico: true, cpfConfere: false);

        $resultado = ClassificadorDeConfianca::classificar([$candidatoHomonimo, $candidatoCorreto]);

        $this->assertSame(NivelConfianca::Exata, $resultado->nivelConfianca);
        $this->assertSame(1004, $resultado->alunoId);
    }

    public function test_cpf_batendo_com_nome_sem_relacao_nao_e_confirmado(): void
    {
        // Colisão de fragmento: CPF bate, nome sem relação — confirmar seria o falso positivo proibido
        $candidato = new CandidatoPontuado(999, 'Zeferino Prudêncio', 5.0, nomeIdentico: false, cpfConfere: true);

        $resultado = ClassificadorDeConfianca::classificar([$candidato]);

        $this->assertSame(NivelConfianca::SemCorrespondencia, $resultado->nivelConfianca);
        $this->assertNull($resultado->alunoId);
    }

    public function test_cpf_batendo_com_nome_na_zona_cinzenta_exige_revisao_humana(): void
    {
        $candidato = new CandidatoPontuado(999, 'Fulano Qualquer', 45.0, nomeIdentico: false, cpfConfere: true);

        $resultado = ClassificadorDeConfianca::classificar([$candidato]);

        $this->assertSame(NivelConfianca::Ambigua, $resultado->nivelConfianca);
        $this->assertNull($resultado->alunoId);
    }

    public function test_bonus_de_cpf_so_e_aplicado_com_nome_minimamente_compativel(): void
    {
        $abaixoDoPiso = new CandidatoPontuado(1, 'X', 49.0, nomeIdentico: false, cpfConfere: true);
        $noPiso = new CandidatoPontuado(1, 'X', 50.0, nomeIdentico: false, cpfConfere: true);

        $this->assertEqualsWithDelta(49.0, $abaixoDoPiso->pontuacaoFinal(), 0.001);
        $this->assertEqualsWithDelta(65.0, $noPiso->pontuacaoFinal(), 0.001);
        $this->assertFalse($abaixoDoPiso->cpfConfirmaComSeguranca());
        $this->assertTrue($noPiso->cpfConfirmaComSeguranca());
    }

    public function test_dois_candidatos_proximos_sem_cpf_para_desempatar_e_ambigua(): void
    {
        $candidatoA = new CandidatoPontuado(2001, 'Fulano de Tal', 70.0, nomeIdentico: false, cpfConfere: false);
        $candidatoB = new CandidatoPontuado(2002, 'Fulano de Tal Junior', 65.0, nomeIdentico: false, cpfConfere: false);

        $resultado = ClassificadorDeConfianca::classificar([$candidatoA, $candidatoB]);

        $this->assertSame(NivelConfianca::Ambigua, $resultado->nivelConfianca);
        $this->assertNull($resultado->alunoId);
        $this->assertCount(2, $resultado->candidatosAlternativos);
    }
}
