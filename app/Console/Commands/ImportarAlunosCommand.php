<?php

namespace App\Console\Commands;

use App\Services\Correspondencia\Cpf;
use App\Services\Correspondencia\NormalizadorDeNome;
use App\Services\LeitorCsv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportarAlunosCommand extends Command
{
    protected $signature = 'alunos:importar {caminho : Caminho do arquivo base_alunos.csv}';

    protected $description = 'Importa (upsert) a base de alunos da Rede a partir de um CSV';

    private const COLUNAS_OBRIGATORIAS = ['id_aluno', 'nome_completo', 'cpf', 'unidade', 'turma', 'ano_matricula'];

    public function handle(): int
    {
        $caminho = $this->argument('caminho');

        if (! is_file($caminho)) {
            $this->error("Arquivo não encontrado: {$caminho}");

            return self::FAILURE;
        }

        $total = 0;
        $descartadas = 0;

        try {
            LeitorCsv::ler($caminho, self::COLUNAS_OBRIGATORIAS)
                ->chunk(500)
                ->each(function ($linhas) use (&$total, &$descartadas) {
                    $lote = $linhas->map(fn (array $linha) => $this->mapearLinha($linha));

                    $descartadas += $lote->filter(fn ($linha) => $linha === null)->count();
                    $lote = $lote->filter()->values()->all();

                    if ($lote !== []) {
                        DB::table('alunos')->upsert(
                            $lote,
                            ['id_aluno'],
                            ['nome_completo', 'nome_normalizado', 'sobrenome_normalizado', 'cpf', 'cpf_meio', 'unidade', 'turma', 'ano_matricula', 'updated_at']
                        );
                        $total += count($lote);
                    }
                });
        } catch (RuntimeException $excecao) {
            $this->error($excecao->getMessage());

            return self::FAILURE;
        }

        $this->info("Importados/atualizados {$total} alunos.");

        if ($descartadas > 0) {
            $this->warn("{$descartadas} linha(s) descartada(s) por dados inválidos (nome, id_aluno ou CPF ausente/malformado).");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $linha
     */
    private function mapearLinha(array $linha): ?array
    {
        $nomeCompleto = trim($linha['nome_completo'] ?? '');
        $idAluno = $linha['id_aluno'] ?? null;
        $cpf = Cpf::apenasDigitos($linha['cpf'] ?? null);

        if ($nomeCompleto === '' || ! is_numeric($idAluno) || strlen($cpf) !== 11) {
            return null;
        }

        $tokens = NormalizadorDeNome::tokens($nomeCompleto);
        $agora = now();

        return [
            'id_aluno' => (int) $idAluno,
            'nome_completo' => $nomeCompleto,
            'nome_normalizado' => implode(' ', $tokens),
            'sobrenome_normalizado' => end($tokens) ?: '',
            'cpf' => $cpf,
            'cpf_meio' => Cpf::fragmentoDoMeio($cpf),
            'unidade' => trim($linha['unidade'] ?? ''),
            'turma' => trim($linha['turma'] ?? ''),
            'ano_matricula' => (int) ($linha['ano_matricula'] ?? 0),
            'created_at' => $agora,
            'updated_at' => $agora,
        ];
    }
}
