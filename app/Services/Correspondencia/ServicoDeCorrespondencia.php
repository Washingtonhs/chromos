<?php

namespace App\Services\Correspondencia;

use App\Models\Aluno;
use App\Models\Aprovado;
use Illuminate\Database\Eloquent\Collection;

class ServicoDeCorrespondencia
{
    /**
     * Sem CPF, primeiro nome sem relação não entra na disputa mesmo com
     * sobrenome idêntico — "Souza" sozinho não é evidência de identidade.
     */
    private const SIMILARIDADE_MINIMA_PRIMEIRO_NOME = 0.5;

    public function corresponder(Aprovado $aprovado): ResultadoCorrespondencia
    {
        $tokensAprovado = NormalizadorDeNome::tokens($aprovado->nome);

        if ($tokensAprovado === []) {
            return new ResultadoCorrespondencia(
                nivelConfianca: NivelConfianca::SemCorrespondencia,
                pontuacao: 0,
                alunoId: null,
                criterios: ['motivo' => 'nome do aprovado vazio ou inválido'],
            );
        }

        $fragmentoCpfAprovado = Cpf::fragmentoDoMeio($aprovado->cpf_mascarado);

        $candidatos = $this->pontuarCandidatos(
            $tokensAprovado,
            $this->buscarCandidatos(end($tokensAprovado), $fragmentoCpfAprovado),
            $fragmentoCpfAprovado,
        );

        return ClassificadorDeConfianca::classificar($candidatos);
    }

    /**
     * Blocking por igualdade indexada (sobrenome final OU fragmento de
     * CPF) — evita comparar cada aprovado contra a base inteira.
     */
    private function buscarCandidatos(string $sobrenomeNormalizado, ?string $fragmentoCpf): Collection
    {
        return Aluno::query()
            ->where(function ($query) use ($sobrenomeNormalizado, $fragmentoCpf) {
                $query->where('sobrenome_normalizado', $sobrenomeNormalizado);

                if ($fragmentoCpf !== null) {
                    $query->orWhere('cpf_meio', $fragmentoCpf);
                }
            })
            ->get();
    }

    /**
     * @param  string[]  $tokensAprovado
     * @return CandidatoPontuado[]
     */
    private function pontuarCandidatos(array $tokensAprovado, Collection $alunos, ?string $fragmentoCpfAprovado): array
    {
        $candidatos = [];

        foreach ($alunos as $aluno) {
            // A forma normalizada vem pronta da importação; não normalizar de novo
            $tokensAluno = $aluno->nome_normalizado === ''
                ? []
                : explode(' ', $aluno->nome_normalizado);

            $comparacao = ComparadorDeNome::pontuar($tokensAprovado, $tokensAluno);
            $cpfConfere = $fragmentoCpfAprovado !== null && $fragmentoCpfAprovado === $aluno->cpf_meio;

            if (! $cpfConfere && $comparacao['similaridade_primeiro_nome'] < self::SIMILARIDADE_MINIMA_PRIMEIRO_NOME) {
                continue;
            }

            $candidatos[] = new CandidatoPontuado(
                alunoId: $aluno->id,
                nomeCompleto: $aluno->nome_completo,
                pontuacaoNome: $comparacao['pontuacao'],
                nomeIdentico: $comparacao['nome_identico'],
                cpfConfere: $cpfConfere,
            );
        }

        return $candidatos;
    }
}
