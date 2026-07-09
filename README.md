# Rede Chromos — Identificação de Alunos Aprovados

Serviço que cruza a lista pública de aprovados (SISU, CEFET-MG, COLTEC, IFMG etc.) com a
base interna de alunos da Rede, e produz um relatório dizendo, para cada aprovado, se ele é
aluno da rede, qual o registro correspondente e o grau de confiança dessa correspondência.

O problema central não é ler CSV — é decidir quando dois nomes escritos de formas diferentes
se referem à mesma pessoa, sem nunca confirmar automaticamente alguém que não é aluno. Falso
positivo é o erro mais caro aqui (gera divulgação pública incorreta), então a solução inteira
foi desenhada em torno disso.

## Entregáveis do teste

| Pedido no enunciado | Onde está |
|---|---|
| Código-fonte completo | Este repositório |
| Instruções para executar localmente | Seção "Instalação e execução" abaixo |
| Decisões de arquitetura e de comparação de nomes | "Estratégia de comparação de nomes", "Estrutura", "Por que zero dependências novas" |
| Trade-offs e limitações conhecidas | "Trade-offs considerados", "Limitações conhecidas" |
| Registro de uso de IA | "Uso de IA" |
| Relatório gerado a partir dos arquivos de exemplo | `storage/app/relatorios/relatorio.csv` (versionado) |
| Relatório em tela/interface simples (opcional no enunciado) | Atendido — é a forma recomendada de uso, ver passo 3 de "Instalação e execução" |

## Instalação e execução

Pré-requisitos: **Git**, **PHP 8.3+** (com as extensões padrão do Laravel — `mbstring`,
`pdo_sqlite` e/ou `pdo_mysql`) e **Composer**. Nenhuma dependência além do Laravel é
instalada e não há build de front-end — Node/npm não são necessários.

### 1. Clonar e instalar

```bash
git clone <url-do-repositorio> chromos
cd chromos
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Banco de dados

**Opção A — SQLite (zero configuração; já é o padrão do `.env.example`):**

```bash
touch database/database.sqlite
php artisan migrate
```

**Opção B — MySQL (espelha a stack da Rede):** crie um banco vazio, ajuste o `.env` e migre:

```bash
mysql -u root -p -e "CREATE DATABASE chromos CHARACTER SET utf8mb4"
```

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=chromos
DB_USERNAME=root
DB_PASSWORD=sua_senha
```

```bash
php artisan migrate
```

### 3. Usar a aplicação — pela tela (forma recomendada)

```bash
php artisan serve
```

Abra `http://localhost:8000` — é a própria página inicial (rota `/`), sem login nem passo
extra. O fluxo do enunciado fica em dois formulários visíveis na tela:

1. **Base de alunos** — envia `storage/app/entrada/base_alunos.csv` (ou o arquivo real da
   Rede); mostra quantos alunos existem e, em reenvios, atualiza em vez de duplicar.
2. **Lista de aprovados** — envia `storage/app/entrada/aprovados_instituicoes.csv`; a tela
   bloqueia com aviso se a base de alunos ainda estiver vazia.

O resultado aparece na própria página: resumo por nível de confiança, tabela paginada com
os casos **ambíguos primeiro** (os que exigem revisão humana), candidatos alternativos
listados nas linhas ambíguas, e um botão para baixar o relatório CSV. Erros de arquivo
(cabeçalho faltando, delimitador errado, extensão inválida, acima de 20 MB) aparecem como
aviso na própria tela.

Decisões técnicas: o `PainelController` chama os **mesmos comandos Artisan** via
`Artisan::call` — nenhuma regra duplicada, a tela é só apresentação e os comandos continuam
sendo o único ponto de entrada da lógica (inclusive as mensagens de erro são as mesmas dos
dois canais). Blade puro com CSS embutido, sem Vite/npm — rodar a aplicação continua sem
exigir build de front-end. O processamento roda dentro do request, o que é instantâneo para
os volumes do teste; entradas problemáticas (cabeçalho faltando, delimitador errado, BOM do
Excel, encoding ISO-8859-1) são tratadas com mensagem clara em vez de descarte silencioso.

> Os mesmos dois passos também rodam direto via Artisan (`php artisan alunos:importar
> caminho.csv` e `php artisan aprovados:comparar caminho.csv`, com `--saida=arquivo.csv`
> para nomear o relatório) — é o que a tela chama por baixo, útil para automação/scripts ou
> para arquivos grandes o bastante para não caber no tempo de um request HTTP. Gera o mesmo
> `storage/app/relatorios/relatorio.csv`, já commitado com o resultado dos arquivos de
> exemplo para conferência sem rodar nada.

### 4. Testes

```bash
php artisan test          # 65 testes, rodam em SQLite em memória (não tocam seu banco)
./vendor/bin/pint --test  # estilo de código
```

Cobrem o núcleo do algoritmo, a leitura de CSV, os comandos de ponta a ponta contra os
arquivos de exemplo e a tela — incluindo casos adversariais de colisão de CPF.

## Estratégia de comparação de nomes

### Por que não dá pra comparar a string inteira

As duas armadilhas do arquivo de exemplo — "Juliana Beatriz Xavier Campos" x "Júlia Beatriz
Xavier Campos" e "Marcela Fagundes Correia" x "Marina Fagundes Correia" — têm sobrenome
idêntico e primeiro nome diferente. Comparar a string inteira de uma vez pontuaria essas
duplas como muito parecidas, porque a maior parte dos caracteres bate. Por isso o nome é
tokenizado (primeiro nome / nomes do meio / sobrenome final) e cada parte é pontuada
separadamente, com pesos:

| Componente | Peso | Como pontua |
|---|---|---|
| Primeiro nome | 35 | Levenshtein normalizado, com teto de 8 pontos se a similaridade ficar abaixo de 0,82 (impede que um sobrenome idêntico "carregue" um primeiro nome diferente) |
| Sobrenome final | 35 | Mesma regra do primeiro nome |
| Nomes do meio | 15 | Cada token do aprovado busca o melhor equivalente nos tokens do aluno; se o aprovado não informa nome do meio nenhum, vale metade do peso (nem confirma, nem contradiz) |
| Bônus de CPF | +15 | Só se o fragmento de CPF confere **e** a pontuação de nome já é razoável (≥ 50) — CPF nunca confirma sozinho |

Normalização (antes de tokenizar): minúsculo, sem acento (`Str::ascii`), espaços duplicados
colapsados, e partículas (de/da/do/das/dos) removidas. Partícula é tratada como ruído
gramatical — "Silva" e "da Silva" são a mesma pessoa — por isso a maioria das
correspondências "exatas" no relatório vem de nomes que batem 100% só depois dessa
normalização, sem precisar de CPF nenhum.

Abreviação de uma letra ("Pedro **H.** Almeida Costa") é reconhecida como compatível com a
palavra completa, mas pontua 0,9 em vez de 1,0 — o suficiente para não travar em "ambíguo",
mas insuficiente para fechar como "exata" sozinha (ver tabela de classificação abaixo).

### Por que o CPF mascarado é usado como sinal, não como chave

O CPF que as instituições divulgam (`***.456.789-**`) deixa visíveis os 6 dígitos do meio.
Isso é o suficiente para desfazer o único homônimo real do arquivo de exemplo — dois "Ana
Clara Silva" na base, cada um com um CPF diferente — porque o fragmento do aprovado bate com
o CPF de um e não do outro.

O CPF **nunca decide sozinho**, por dois motivos:

1. Ele só tem 6 dígitos livres (1 milhão de combinações). Em bases de dezenas de milhares de
   registros, o paradoxo do aniversário torna colisão de fragmento não-desprezível — em
   ~50 mil CPFs aleatórios, o número esperado de pares com o mesmo fragmento de 6 dígitos já
   não é zero.
2. Um fragmento batendo com um nome completamente diferente é mais provável ser colisão ou
   erro de digitação do que uma confirmação real.

Por isso a regra "CPF confirma com segurança" (fragmento confere **e** pontuação de nome
≥ 50) existe num único lugar (`CandidatoPontuado::cpfConfirmaComSeguranca()`) e é usada
tanto pelo bônus de pontuação quanto pela classificação como exata — um CPF batendo com um
nome sem relação nenhuma cai em "sem correspondência", nunca em confirmação automática
(há teste adversarial cobrindo exatamente esse cenário de colisão).

### Classificação de confiança

```
Existe 2º colocado com pontuação a menos de 10 pontos do 1º?
  → AMBÍGUA (não decide sozinho; lista os candidatos concorrentes)

Senão, olhando só o melhor candidato:
  nome idêntico após normalização OU CPF confirma
  com segurança (fragmento confere E nome ≥ 50)    → EXATA
  pontuação final ≥ 65                             → PROVÁVEL
  pontuação final ≥ 40                             → AMBÍGUA (zona cinzenta, um só candidato mas confiança insuficiente)
  caso contrário                                   → SEM CORRESPONDÊNCIA
```

A margem de desempate (10) foi calibrada para ser menor que o bônus de CPF (15): uma
confirmação por CPF sempre resolve um empate de nome, mas duas pontuações de nome apenas
parecidas nunca decidem sozinhas — sempre viram revisão humana.

Os números dos pesos e limiares (35/35/15, 0,82, 65/40) não são "verdade matemática" — foram
calibrados contra os 24 casos do arquivo de exemplo (ver `tests/Unit/Correspondencia`, cada
caso do enunciado virou um teste) e ficam centralizados como constantes de classe em
`ComparadorDeNome` e `ClassificadorDeConfianca`, fáceis de ajustar se uma base real pedir
outro ponto de corte.

### Busca de candidatos (blocking)

Comparar cada aprovado contra a base inteira não escala para dezenas de milhares de
registros. Os candidatos de um aprovado vêm de uma busca indexada por igualdade (não por
`LIKE`, que não usa índice com curinga à esquerda):

- todo aluno cujo **sobrenome final normalizado** bate exatamente (coluna
  `sobrenome_normalizado`, indexada) — calculada uma vez na importação, não a cada comparação;
- todo aluno cujo **fragmento de CPF** bate exatamente (coluna `cpf_meio`, indexada).

Um candidato sem confirmação de CPF e com primeiro nome sem nenhuma relação plausível
(similaridade < 0,5) é descartado antes mesmo de chegar ao classificador — é o que impede
"Lucas Oliveira Souza" de virar candidato de "Camila Aparecida de Souza" só porque os dois
têm sobrenome "Souza" (sobrenome comum, não é evidência de identidade sozinho).

## Por que zero dependências novas

Tudo usa PHP core ou o que o Laravel já traz de fábrica:

| Necessidade | Recurso usado |
|---|---|
| Ler CSV grande em memória constante | `SplFileObject` + `Illuminate\Support\LazyCollection` |
| Corrigir encoding inconsistente | `mb_check_encoding` / `mb_convert_encoding` |
| Remover acento | `Illuminate\Support\Str::ascii()` |
| Distância entre nomes | `levenshtein()` |
| `nivel_confianca` com valores fixos | enum nativo do PHP (`NivelConfianca`), com cast nativo do Eloquent |
| Escrever o relatório | `fputcsv()` |

Nenhum pacote de fuzzy matching (Jaro-Winkler, etc.) ou de algoritmo fonético (Soundex,
Metaphone) foi usado: esses algoritmos foram calibrados para fonética do inglês e não têm
comportamento garantido em nomes portugueses, além de comprimirem o nome numa forma
opaca — o contrário do que se quer aqui, que é uma pontuação explicável linha a linha para
alguém do time de resultados conseguir entender por que uma correspondência foi aceita ou
não.

## Estrutura

```
app/Services/Correspondencia/
  Cpf.php                     — extração de dígitos e fragmento do meio
  NormalizadorDeNome.php      — normalização e tokenização
  ComparadorDeNome.php        — pontuação ponderada de dois nomes (0-85)
  CandidatoPontuado.php       — DTO: candidato + pontuação de nome + CPF confere
  ClassificadorDeConfianca.php — árvore de decisão (exata/provável/ambígua/sem correspondência)
  ResultadoCorrespondencia.php — DTO de saída
  ServicoDeCorrespondencia.php — orquestrador (único ponto que toca o banco)
app/Services/LeitorCsv.php    — leitura de CSV em streaming
app/Console/Commands/
  ImportarAlunosCommand.php
  CompararAprovadosCommand.php
app/Http/Controllers/PainelController.php — interface principal (upload/visualização), delega aos comandos
resources/views/painel.blade.php — página única, sem build de front-end
app/Models/ (Aluno, Aprovado, Correspondencia)
database/migrations/
tests/Unit/Correspondencia/   — núcleo do algoritmo, sem banco
tests/Feature/                — comando de ponta a ponta contra os arquivos de exemplo
```

Persisto os dados em três tabelas (`alunos`, `aprovados`, `correspondencias`) em vez de só
gerar um CSV solto, porque o enunciado fala em indicadores internos de resultado — algo que
precisa ser consultável depois, não só um arquivo descartável. `criterios` e
`candidatos_alternativos` ficam em JSON na tabela, guardando o motivo da classificação para
quem for auditar sem precisar reler o código.

## Limitações conhecidas

- **Blocking assume o sobrenome final sem erro de digitação.** Um typo na última palavra do
  sobrenome faria o candidato nunca ser encontrado (a menos que o CPF resolva). Não há
  evidência desse padrão no arquivo de exemplo (o único typo encontrado, "Guilerme", está no
  primeiro nome), então não foi resolvido — resolveria com uma segunda chave de blocking mais
  tolerante (ex.: primeiros N caracteres do sobrenome), a um custo de mais candidatos por
  aprovado.
- **Linhas individuais com CPF, nome ou id_aluno malformados são apenas contadas** na
  importação (o comando avisa quantas descartou), sem dizer quais linhas nem por quê. Erros
  estruturais — coluna obrigatória faltando, delimitador errado, arquivo vazio — abortam
  imediatamente com mensagem clara antes de processar qualquer linha. Em produção eu
  adicionaria um log ou uma tabela de rejeitados para as linhas individuais.
- **Hífen em nome composto não é tratado como separador** (ex.: "Ana-Maria" viraria um token
  só). Não ocorre nos dados de exemplo.
- **Nenhuma correspondência é confirmada sem revisão para os níveis "provável" e "ambígua"**
  — isso é intencional, não uma limitação a esconder: é a consequência direta de "falso
  positivo é o erro mais grave".

## Trade-offs considerados

- **Processamento síncrono via comando Artisan, sem fila.** Com blocking indexado, dezenas de
  milhares de linhas rodam em segundos a poucos minutos num processo só. Fila (Horizon,
  retries, monitoramento) seria complexidade desproporcional ao volume e ao prazo do teste. O
  serviço de comparação não depende do ciclo de request, então dá pra colocar atrás de uma
  fila depois sem reescrever a lógica.
- **Escrita em lotes de 500 dentro de transação** nos dois comandos: a importação de alunos
  usa upsert em lote, e a comparação insere as correspondências de cada lote num único
  `INSERT` dentro de `DB::transaction()` — evita um fsync por linha, que é o que mataria a
  performance em dezenas de milhares de registros. A validação de cabeçalho roda **antes** do
  truncate da rodada anterior, para que um arquivo inválido não apague o último resultado bom.
- **`aprovados` e `correspondencias` são truncados a cada execução do comando de comparação**,
  tratando cada rodada como um novo lote (mesma leitura do enunciado: "todo ano, quando saem
  as listas..."). A base de `alunos` é cumulativa e importada separadamente via upsert.
- **Pontuação arredondada para inteiro (0-100)** no relatório final — mais legível para quem
  vai usar isso numa campanha de divulgação do que uma casa decimal sem significado prático.

## Relatório gerado

`storage/app/relatorios/relatorio.csv`, gerado a partir dos arquivos de exemplo fornecidos:
17 correspondências exatas, 4 prováveis, 2 ambíguas e 1 sem correspondência, batendo com a
conferência manual documentada nos testes de `tests/Feature/CompararAprovadosCommandTest.php`.

CSV como formato de saída porque o consumidor final é o time de resultados/marketing, que
abre direto no Excel/Sheets e filtra por nível de confiança sem precisar de ferramenta
nenhuma. O "porquê" de cada decisão fica em JSON no banco (`correspondencias.criterios`)
para auditoria, e a tela web cobre a visualização rápida — os três formatos que o enunciado
menciona (CSV, JSON, tela), cada um no papel que faz sentido.

## Uso de IA

Todo o código deste repositório foi implementado com apoio do Claude Code (Anthropic), em
sessões de pair-programming guiadas. O processo, resumido:

1. **Análise do enunciado**: pedi para o modelo ler o PDF do teste e extrair objetivos,
   requisitos e ambiguidades antes de qualquer código — e travar num plano por escrito antes
   de implementar.
2. **Discussão de abordagem antes de aceitar**: questionei explicitamente duas decisões que o
   modelo propôs — (a) por que Levenshtein + comparação por token em vez de biblioteca de
   fuzzy matching ou algoritmo fonético (Soundex/Metaphone), e (b) se dava pra eliminar toda
   dependência externa via Composer e usar só recursos nativos do PHP/Laravel. A resposta (b)
   fez o plano mudar de fato: a ideia inicial incluía considerar `League\Csv`, que foi
   descartada em favor de `SplFileObject` nativo.
3. **Conferência manual antes de codificar**: antes de escrever qualquer classe, pedi (e o
   modelo fez) o cálculo manual, caso a caso, dos 24 aprovados contra os 30 alunos do arquivo
   de exemplo — isso é o que revelou que o CPF mascarado desfaz o homônimo "Ana Clara Silva" e
   que "Juliana"/"Júlia" e "Marcela"/"Marina" são as duas armadilhas propositais do enunciado.
   Esse gabarito manual virou os testes em `tests/Unit/Correspondencia` e
   `tests/Feature/CompararAprovadosCommandTest.php` — não aceitei os números de peso/limiar
   "de olho", eles foram validados um a um contra esse gabarito antes de eu considerar a
   implementação correta.
4. **O que rejeitei/ajustei**: a primeira formulação da regra de "nome idêntico = exata"
   usava só a pontuação numérica (≥ 90), o que teria classificado errado o caso "Pedro
   Henrique Costa" (falta o nome "Almeida") como exata — pedi a correção e o modelo trocou
   para exigir igualdade estrutural exata das strings normalizadas como condição, não só um
   valor de pontuação, especificamente para não deixar essa brecha.
5. **O que aceitei sem alteração**: a estrutura geral de camadas (comparador de nome puro →
   classificador de decisão → orquestrador que toca banco), o uso de enum nativo do PHP para
   `nivel_confianca`, e o formato do relatório em CSV.
6. **Revisão adversarial pós-implementação**: pedi ao modelo uma revisão crítica do próprio
   código, com instrução explícita de ser rigoroso. Ela encontrou um bug real que os 24 casos
   de exemplo não exercitavam: a regra "CPF só confirma com nome minimamente compatível"
   estava implementada na pontuação, mas a classificação final usava a flag crua de CPF —
   um CPF colidindo com um nome sem relação seria confirmado como "exata" (reproduzido via
   tinker antes de corrigir). A correção unificou a regra num único método e foi travada com
   testes adversariais. A mesma revisão apontou a falta de batching no comando de comparação
   e a ausência de validação de cabeçalho nos CSVs, ambos corrigidos em seguida. Depois das
   correções, o relatório dos arquivos de exemplo foi regerado e conferido campo a campo
   contra o anterior: idêntico, como esperado (os bugs estavam em caminhos que os dados de
   exemplo não atingem).

Todos os testes (`php artisan test`) e o Laravel Pint (`./vendor/bin/pint --test`) passam
limpo. O relatório final foi gerado rodando os comandos de verdade contra os arquivos de
exemplo, não copiado à mão.
