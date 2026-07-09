<?php

namespace App\Services\Correspondencia;

class ClassificadorDeConfianca
{
    /**
     * Menor que o bônus de CPF (15) de propósito: CPF confirmado sempre
     * desempata; nomes apenas parecidos nunca decidem sozinhos.
     */
    private const MARGEM_DESEMPATE = 10;

    private const PISO_PROVAVEL = 65;

    private const PISO_AMBIGUA = 40;

    /**
     * @param  CandidatoPontuado[]  $candidatos  já filtrados como plausíveis para um aprovado
     */
    public static function classificar(array $candidatos): ResultadoCorrespondencia
    {
        if ($candidatos === []) {
            return new ResultadoCorrespondencia(
                nivelConfianca: NivelConfianca::SemCorrespondencia,
                pontuacao: 0,
                alunoId: null,
                criterios: ['motivo' => 'nenhum candidato plausível encontrado na base'],
            );
        }

        usort($candidatos, fn (CandidatoPontuado $a, CandidatoPontuado $b) => $b->pontuacaoFinal() <=> $a->pontuacaoFinal());

        $melhor = $candidatos[0];
        $segundo = $candidatos[1] ?? null;

        if ($segundo && ($melhor->pontuacaoFinal() - $segundo->pontuacaoFinal()) < self::MARGEM_DESEMPATE) {
            return new ResultadoCorrespondencia(
                nivelConfianca: NivelConfianca::Ambigua,
                pontuacao: (int) round($melhor->pontuacaoFinal()),
                alunoId: null,
                criterios: ['motivo' => 'mais de um candidato com pontuação próxima entre si, exige revisão humana'],
                candidatosAlternativos: self::listarAlternativos($candidatos),
            );
        }

        return match (true) {
            $melhor->nomeIdentico || $melhor->cpfConfirmaComSeguranca() => new ResultadoCorrespondencia(
                nivelConfianca: NivelConfianca::Exata,
                pontuacao: (int) round($melhor->pontuacaoFinal()),
                alunoId: $melhor->alunoId,
                criterios: self::criterios($melhor, 'nome idêntico após normalização e/ou CPF confirma'),
            ),
            $melhor->pontuacaoFinal() >= self::PISO_PROVAVEL => new ResultadoCorrespondencia(
                nivelConfianca: NivelConfianca::Provavel,
                pontuacao: (int) round($melhor->pontuacaoFinal()),
                alunoId: $melhor->alunoId,
                criterios: self::criterios($melhor, 'nome muito similar (abreviação, partícula ou nome do meio ausente), sem CPF para confirmar com certeza'),
            ),
            $melhor->pontuacaoFinal() >= self::PISO_AMBIGUA => new ResultadoCorrespondencia(
                nivelConfianca: NivelConfianca::Ambigua,
                pontuacao: (int) round($melhor->pontuacaoFinal()),
                alunoId: null,
                criterios: self::criterios($melhor, 'similaridade insuficiente para confirmar com segurança, exige revisão humana'),
                candidatosAlternativos: self::listarAlternativos($candidatos),
            ),
            default => new ResultadoCorrespondencia(
                nivelConfianca: NivelConfianca::SemCorrespondencia,
                pontuacao: (int) round($melhor->pontuacaoFinal()),
                alunoId: null,
                criterios: ['motivo' => 'nenhum candidato atingiu similaridade mínima'],
            ),
        };
    }

    private static function criterios(CandidatoPontuado $candidato, string $motivo): array
    {
        return [
            'motivo' => $motivo,
            'pontuacao_nome' => $candidato->pontuacaoNome,
            'nome_identico' => $candidato->nomeIdentico,
            'cpf_confere' => $candidato->cpfConfere,
        ];
    }

    /**
     * @param  CandidatoPontuado[]  $candidatos
     */
    private static function listarAlternativos(array $candidatos): array
    {
        return array_map(fn (CandidatoPontuado $c) => [
            'aluno_id' => $c->alunoId,
            'nome' => $c->nomeCompleto,
            'pontuacao' => (int) round($c->pontuacaoFinal()),
        ], $candidatos);
    }
}
