<?php

namespace App\Console\Commands;

use App\Models\Aprovado;
use App\Models\Correspondencia;
use App\Services\Correspondencia\ServicoDeCorrespondencia;
use App\Services\LeitorCsv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class CompararAprovadosCommand extends Command
{
    protected $signature = 'aprovados:comparar
        {caminho : Caminho do arquivo aprovados_instituicoes.csv}
        {--saida=relatorio.csv : Nome do arquivo de saída, salvo em storage/app/relatorios}';

    protected $description = 'Importa a lista de aprovados, cruza com a base de alunos e gera o relatório de correspondências';

    /** Quem precisa de revisão humana aparece primeiro no relatório. */
    private const ORDEM_NIVEL = [
        'ambigua' => 0,
        'provavel' => 1,
        'exata' => 2,
        'sem_correspondencia' => 3,
    ];

    private const COLUNAS_OBRIGATORIAS = ['nome', 'cpf_mascarado', 'instituicao', 'curso', 'modalidade'];

    private const TAMANHO_DO_LOTE = 500;

    public function handle(ServicoDeCorrespondencia $servico): int
    {
        $caminho = $this->argument('caminho');

        if (! is_file($caminho)) {
            $this->error("Arquivo não encontrado: {$caminho}");

            return self::FAILURE;
        }

        $linhas = LeitorCsv::ler($caminho, self::COLUNAS_OBRIGATORIAS)
            ->filter(fn (array $linha) => trim($linha['nome'] ?? '') !== '');

        try {
            // Valida o cabeçalho ANTES do truncate: arquivo inválido não
            // pode apagar o resultado da rodada anterior.
            $linhas->first();
        } catch (RuntimeException $excecao) {
            $this->error($excecao->getMessage());

            return self::FAILURE;
        }

        // Nova rodada a cada execução; a base de alunos permanece intacta
        Schema::disableForeignKeyConstraints();
        DB::table('correspondencias')->truncate();
        DB::table('aprovados')->truncate();
        Schema::enableForeignKeyConstraints();

        // Via Artisan::call (tela web) a saída é bufferizada; a barra viraria ruído
        $barra = $this->output->isDecorated() ? $this->output->createProgressBar() : null;
        $barra?->start();
        $total = 0;

        // Se um lote falhar, os anteriores permanecem; reexecutar o
        // comando recomeça a rodada do zero (truncate acima)
        $linhas->chunk(self::TAMANHO_DO_LOTE)->each(function ($lote) use ($servico, $barra, &$total) {
            DB::transaction(function () use ($lote, $servico, $barra, &$total) {
                $agora = now();
                $registros = [];

                foreach ($lote as $linha) {
                    $aprovado = $this->criarAprovado($linha);
                    $resultado = $servico->corresponder($aprovado);

                    $registros[] = [
                        'aprovado_id' => $aprovado->id,
                        'aluno_id' => $resultado->alunoId,
                        'nivel_confianca' => $resultado->nivelConfianca->value,
                        'pontuacao' => $resultado->pontuacao,
                        'criterios' => json_encode($resultado->criterios, JSON_UNESCAPED_UNICODE),
                        'candidatos_alternativos' => $resultado->candidatosAlternativos === null
                            ? null
                            : json_encode($resultado->candidatosAlternativos, JSON_UNESCAPED_UNICODE),
                        'created_at' => $agora,
                        'updated_at' => $agora,
                    ];

                    $total++;
                    $barra?->advance();
                }

                DB::table('correspondencias')->insert($registros);
            });
        });

        $barra?->finish();
        $this->newLine(2);

        $caminhoSaida = $this->exportarRelatorio($this->option('saida'));

        $this->info("Processados {$total} aprovados.");
        $this->info("Relatório gerado em: {$caminhoSaida}");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $linha
     */
    private function criarAprovado(array $linha): Aprovado
    {
        $nome = trim($linha['nome']);
        $cpfMascarado = trim($linha['cpf_mascarado'] ?? '') ?: null;

        return Aprovado::create([
            'nome' => $nome,
            'cpf_mascarado' => $cpfMascarado,
            'instituicao' => trim($linha['instituicao'] ?? ''),
            'curso' => trim($linha['curso'] ?? ''),
            'modalidade' => trim($linha['modalidade'] ?? ''),
        ]);
    }

    private function exportarRelatorio(string $nomeArquivo): string
    {
        $diretorio = storage_path('app/relatorios');

        if (! is_dir($diretorio)) {
            mkdir($diretorio, recursive: true);
        }

        $caminhoSaida = $diretorio.DIRECTORY_SEPARATOR.$nomeArquivo;
        $arquivo = fopen($caminhoSaida, 'w');

        fputcsv($arquivo, [
            'aprovado_nome', 'instituicao', 'curso', 'modalidade',
            'nivel_confianca', 'pontuacao', 'aluno_id', 'aluno_nome', 'unidade', 'turma',
            'motivo', 'candidatos_alternativos',
        ], ';');

        // CASE funciona em MySQL e SQLite (FIELD() é só MySQL); lazy() mantém memória constante
        $ordenacaoPorNivel = 'CASE nivel_confianca '.collect(self::ORDEM_NIVEL)
            ->map(fn (int $ordem, string $nivel) => "WHEN '{$nivel}' THEN {$ordem}")
            ->implode(' ').' END';

        Correspondencia::with(['aprovado', 'aluno'])
            ->orderByRaw($ordenacaoPorNivel)
            ->orderBy('id')
            ->lazy()
            ->each(function (Correspondencia $c) use ($arquivo) {
                fputcsv($arquivo, [
                    $c->aprovado->nome,
                    $c->aprovado->instituicao,
                    $c->aprovado->curso,
                    $c->aprovado->modalidade,
                    $c->nivel_confianca->value,
                    $c->pontuacao,
                    $c->aluno?->id_aluno,
                    $c->aluno?->nome_completo,
                    $c->aluno?->unidade,
                    $c->aluno?->turma,
                    $c->criterios['motivo'] ?? '',
                    $c->candidatos_alternativos ? json_encode($c->candidatos_alternativos, JSON_UNESCAPED_UNICODE) : '',
                ], ';');
            });

        fclose($arquivo);

        return $caminhoSaida;
    }
}
