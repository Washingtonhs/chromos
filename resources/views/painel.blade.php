<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rede Chromos — Identificação de Aprovados</title>
    <style>
        * { box-sizing: border-box; margin: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f4f4f5; color: #18181b; line-height: 1.5; }
        .container { max-width: 1100px; margin: 0 auto; padding: 2rem 1rem; }
        h1 { font-size: 1.4rem; margin-bottom: .25rem; }
        .subtitulo { color: #52525b; margin-bottom: 1.5rem; font-size: .95rem; }
        .aviso { padding: .75rem 1rem; border-radius: .5rem; margin-bottom: 1rem; white-space: pre-line; }
        .aviso-sucesso { background: #dcfce7; color: #14532d; border: 1px solid #86efac; }
        .aviso-erro { background: #fee2e2; color: #7f1d1d; border: 1px solid #fca5a5; }
        .cartoes { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .cartao { background: #fff; border: 1px solid #e4e4e7; border-radius: .75rem; padding: 1.25rem; }
        .cartao h2 { font-size: 1rem; margin-bottom: .25rem; }
        .cartao p { color: #52525b; font-size: .85rem; margin-bottom: 1rem; }
        input[type=file] { display: block; width: 100%; font-size: .85rem; margin-bottom: .75rem; }
        button { background: #18181b; color: #fff; border: 0; border-radius: .5rem; padding: .5rem 1.25rem; font-size: .9rem; cursor: pointer; }
        button:hover { background: #3f3f46; }
        .botao-secundario { display: inline-block; background: #fff; color: #18181b; border: 1px solid #d4d4d8; border-radius: .5rem; padding: .5rem 1.25rem; font-size: .9rem; text-decoration: none; }
        .botao-secundario:hover { background: #f4f4f5; }
        .resumo { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; margin-bottom: 1rem; }
        .nivel { display: inline-block; border-radius: 999px; padding: .15rem .7rem; font-size: .78rem; font-weight: 600; white-space: nowrap; }
        .nivel-ambigua { background: #fef3c7; color: #92400e; }
        .nivel-provavel { background: #dbeafe; color: #1e40af; }
        .nivel-exata { background: #dcfce7; color: #166534; }
        .nivel-sem_correspondencia { background: #e4e4e7; color: #3f3f46; }
        .tabela-envolucro { overflow-x: auto; background: #fff; border: 1px solid #e4e4e7; border-radius: .75rem; }
        table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        th, td { text-align: left; padding: .6rem .75rem; border-bottom: 1px solid #f4f4f5; vertical-align: top; }
        th { background: #fafafa; font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; color: #52525b; }
        tr:last-child td { border-bottom: 0; }
        .discreto { color: #71717a; font-size: .8rem; }
        .paginacao { display: flex; gap: .75rem; margin-top: 1rem; }
        .vazio { background: #fff; border: 1px dashed #d4d4d8; border-radius: .75rem; padding: 2rem; text-align: center; color: #71717a; }
    </style>
</head>
<body>
<div class="container">
    <h1>Rede Chromos — Identificação de Alunos Aprovados</h1>
    <p class="subtitulo">Cruza a lista de aprovados das instituições com a base de alunos e classifica cada correspondência por nível de confiança.</p>

    @if (session('sucesso'))
        <div class="aviso aviso-sucesso">{{ session('sucesso') }}</div>
    @endif
    @if (session('erro'))
        <div class="aviso aviso-erro">{{ session('erro') }}</div>
    @endif
    @if ($errors->any())
        <div class="aviso aviso-erro">{{ $errors->first() }}</div>
    @endif

    <div class="cartoes">
        <div class="cartao">
            <h2>1. Base de alunos</h2>
            <p>
                @if ($totalAlunos > 0)
                    {{ $totalAlunos }} aluno(s) na base. Reenviar atualiza os registros existentes, sem duplicar.
                @else
                    Nenhum aluno importado ainda. Envie o CSV da base da Rede (colunas: id_aluno, nome_completo, cpf, unidade, turma, ano_matricula).
                @endif
            </p>
            <form method="POST" action="{{ route('alunos.importar') }}" enctype="multipart/form-data">
                @csrf
                <input type="file" name="arquivo" accept=".csv,.txt" required>
                <button type="submit">Importar alunos</button>
            </form>
        </div>

        <div class="cartao">
            <h2>2. Lista de aprovados</h2>
            <p>
                Envie o CSV divulgado pelas instituições (colunas: nome, cpf_mascarado, instituicao, curso, modalidade).
                Cada envio inicia uma nova rodada e substitui a anterior.
            </p>
            <form method="POST" action="{{ route('aprovados.comparar') }}" enctype="multipart/form-data">
                @csrf
                <input type="file" name="arquivo" accept=".csv,.txt" required>
                <button type="submit">Comparar aprovados</button>
            </form>
        </div>
    </div>

    @if ($correspondencias->isEmpty())
        <div class="vazio">Nenhuma comparação realizada ainda. Importe a base de alunos e envie uma lista de aprovados.</div>
    @else
        <div class="resumo">
            <span class="nivel nivel-ambigua">ambígua: {{ $resumo['ambigua'] ?? 0 }}</span>
            <span class="nivel nivel-provavel">provável: {{ $resumo['provavel'] ?? 0 }}</span>
            <span class="nivel nivel-exata">exata: {{ $resumo['exata'] ?? 0 }}</span>
            <span class="nivel nivel-sem_correspondencia">sem correspondência: {{ $resumo['sem_correspondencia'] ?? 0 }}</span>
            @if ($relatorioDisponivel)
                <a class="botao-secundario" href="{{ route('relatorio.baixar') }}">Baixar relatório CSV</a>
            @endif
        </div>

        <div class="tabela-envolucro">
            <table>
                <thead>
                <tr>
                    <th>Aprovado</th>
                    <th>Aprovação</th>
                    <th>Nível</th>
                    <th>Pontuação</th>
                    <th>Aluno da Rede</th>
                    <th>Motivo</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($correspondencias as $correspondencia)
                    <tr>
                        <td>{{ $correspondencia->aprovado->nome }}</td>
                        <td>
                            {{ $correspondencia->aprovado->instituicao }}
                            <div class="discreto">{{ $correspondencia->aprovado->curso }} — {{ $correspondencia->aprovado->modalidade }}</div>
                        </td>
                        <td><span class="nivel nivel-{{ $correspondencia->nivel_confianca->value }}">{{ str_replace('_', ' ', $correspondencia->nivel_confianca->value) }}</span></td>
                        <td>{{ $correspondencia->pontuacao }}</td>
                        <td>
                            @if ($correspondencia->aluno)
                                {{ $correspondencia->aluno->nome_completo }}
                                <div class="discreto">#{{ $correspondencia->aluno->id_aluno }} — {{ $correspondencia->aluno->unidade }}, {{ $correspondencia->aluno->turma }}</div>
                            @elseif ($correspondencia->candidatos_alternativos)
                                <span class="discreto">
                                    Candidatos:
                                    @foreach ($correspondencia->candidatos_alternativos as $candidato)
                                        {{ $candidato['nome'] }} ({{ $candidato['pontuacao'] }}){{ $loop->last ? '' : '; ' }}
                                    @endforeach
                                </span>
                            @else
                                <span class="discreto">—</span>
                            @endif
                        </td>
                        <td class="discreto">{{ $correspondencia->criterios['motivo'] ?? '' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="paginacao">
            @if ($correspondencias->previousPageUrl())
                <a class="botao-secundario" href="{{ $correspondencias->previousPageUrl() }}">&larr; Anterior</a>
            @endif
            @if ($correspondencias->hasMorePages())
                <a class="botao-secundario" href="{{ $correspondencias->nextPageUrl() }}">Próxima &rarr;</a>
            @endif
        </div>
    @endif
</div>
</body>
</html>
