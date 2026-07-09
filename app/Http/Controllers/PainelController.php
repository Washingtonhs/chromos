<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\Correspondencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Apresentação pura: importar e comparar rodam via Artisan::call —
 * os comandos são o único ponto de entrada da lógica.
 */
class PainelController extends Controller
{
    /** Mesma ordem do relatório CSV: revisão humana primeiro. */
    private const ORDENACAO_POR_NIVEL = "CASE nivel_confianca WHEN 'ambigua' THEN 0 WHEN 'provavel' THEN 1 WHEN 'exata' THEN 2 ELSE 3 END";

    public function exibir(): View
    {
        return view('painel', [
            'totalAlunos' => Aluno::count(),
            'resumo' => Correspondencia::selectRaw('nivel_confianca, count(*) as total')
                ->groupBy('nivel_confianca')
                ->pluck('total', 'nivel_confianca'),
            'correspondencias' => Correspondencia::with(['aprovado', 'aluno'])
                ->orderByRaw(self::ORDENACAO_POR_NIVEL)
                ->orderBy('id')
                ->simplePaginate(50),
            'relatorioDisponivel' => is_file(storage_path('app/relatorios/relatorio.csv')),
        ]);
    }

    public function importarAlunos(Request $request): RedirectResponse
    {
        $this->validarArquivo($request);

        $codigo = Artisan::call('alunos:importar', [
            'caminho' => $request->file('arquivo')->getRealPath(),
        ]);

        return $this->responderComSaidaDoComando($codigo);
    }

    public function compararAprovados(Request $request): RedirectResponse
    {
        $this->validarArquivo($request);

        if (Aluno::count() === 0) {
            return back()->with('erro', 'Importe a base de alunos antes de comparar os aprovados.');
        }

        $codigo = Artisan::call('aprovados:comparar', [
            'caminho' => $request->file('arquivo')->getRealPath(),
        ]);

        return $this->responderComSaidaDoComando($codigo);
    }

    public function baixarRelatorio(): BinaryFileResponse|RedirectResponse
    {
        $caminho = storage_path('app/relatorios/relatorio.csv');

        if (! is_file($caminho)) {
            return redirect()->route('painel')->with('erro', 'Nenhum relatório foi gerado ainda.');
        }

        return response()->download($caminho, 'relatorio_correspondencias.csv');
    }

    private function validarArquivo(Request $request): void
    {
        $request->validate(
            ['arquivo' => ['required', 'file', 'extensions:csv,txt', 'max:20480']],
            [
                'arquivo.required' => 'Selecione um arquivo CSV.',
                'arquivo.file' => 'Envio inválido, tente novamente.',
                'arquivo.extensions' => 'O arquivo deve ser um CSV (.csv ou .txt).',
                'arquivo.max' => 'O arquivo excede o limite de 20 MB.',
            ],
        );
    }

    /** As mensagens dos comandos já são texto de usuário final — só repassa. */
    private function responderComSaidaDoComando(int $codigo): RedirectResponse
    {
        $saida = trim(Artisan::output());

        return $codigo === 0
            ? back()->with('sucesso', $saida)
            : back()->with('erro', $saida);
    }
}
