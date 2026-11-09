# Progresso — sistema de chamados de TI

Atualizado em 03/11/2026. Escopo executado: etapas 1–4, com resultados e histórico separados abaixo. A documentação original foi lida integralmente e copiada sem alteração para especificacao.md. SHA-256: 7885ee818100b1ac35445e4f39bbcaed2be533f67db9bac9930e4700fc089688.

## Oito etapas autorizáveis

| Etapa | Estado real | Próximo trabalho |
|---|---|---|
| 1. Fundação | Concluída: base, PostgreSQL, assets e captura SMTP validados; testes manuais da tela confirmados pelo usuário | Próxima etapa: autenticação e permissões |
| 2. Autenticação e permissões | Concluída para desenvolvimento/piloto; 34 testes/385 assertions aprovados | Conta inicial criada pelo operador; testes manuais da etapa 2 concluídos pelo usuário em 03/11/2026 |
| 3. Cadastros | Concluída para desenvolvimento/piloto em 03/11/2026; regressão 48 testes/572 assertions | Roteiro humano em testes-etapa3.md; etapa 4 somente após nova solicitação |
| 4. Portal do colaborador | Concluída para desenvolvimento/piloto, com 73 testes e 967 asserções na revalidação | Roteiro humano/visual em testes-etapa4.md. Etapa 5 somente após nova solicitação |
| 5. Atendimento do TI | Não iniciada | Fila, atribuição, notas internas, transições, histórico e concorrência |
| 6. Motor de SLA | Não iniciada | Calendários/versionamento, ciclos, dois relógios, pausas e testes de borda |
| 7. Alertas e dashboards | Não iniciada | Deduplicação, escalonamento, indicadores e reconciliação por filtros |
| 8. Homologação e implantação | Não iniciada | Aprovações corporativas, rede/hospedagem, HTTPS, backup/restauração, segurança e piloto |

Esta divisão detalha a sequência da seção 18 da especificação: autenticação/perfis/auditoria foram separados da fundação pelo pedido explícito. Nenhum requisito foi retirado. Etapas 2–4 foram solicitadas posteriormente; não avançar para etapa 5 sem nova solicitação.

## Histórico da implementação da fundação

- Git inicializado em pasta vazia, sem arquivos anteriores nem instruções AGENTS.md aplicáveis encontradas; sem commit, remoto, push ou implantação.
- Laravel 13.39.0, Livewire 4.4.8, Tailwind 4.3.3, Vite 7.3.7 e laravel-vite-plugin 2.1.0. composer.lock e package-lock.json presentes; estabilidade stable.
- Tela mínima em português com ação Livewire real, foco visível, layout responsivo e alternância de tema salva localmente. Não há dashboards, autenticação nem chamadas de negócio simuladas.
- PostgreSQL padrão tanto no desenvolvimento quanto nos testes; arquivos .env.example e .env.testing.example sem segredos. Chaves locais geradas nos arquivos ignorados pelo Git.
- Apenas migrações de cache e fila; migração de usuários do skeleton removida antes de qualquer aplicação. Modelo User/factory remanescentes são scaffolding, a adaptar na etapa 2.
- Armazenamento privado, sem serve automático; sessão/cache em arquivos permitem a tela sem conexão com banco. Filas database preparadas, scheduler sem tarefas e SMTP local preparado para captura.
- Modelo relacional completo proposto, decisões com fontes oficiais, rastreabilidade de todos AC01–AC20 e README com comandos PowerShell.

## Histórico das verificações da fundação

| Verificação / comando | Resultado real |
|---|---|
| PHP, extensões, Composer, Git e psql | PHP 8.4.8, Composer 2.8.9, Git 2.54.0 e cliente psql 18.4; pdo_pgsql e extensões Laravel presentes |
| Serviço PostgreSQL / pg_isready | Serviço postgresql-x64-18 Running; 127.0.0.1:5432 aceita conexões |
| composer create-project (sem scripts), instalação e composer update livewire/livewire | Concluídos; primeira tentativa restrita falhou por DNS e foi refeita com autorização. Restrição Livewire corrigida no JSON após cmd do Windows remover o caret do argumento |
| composer validate --strict | Passou |
| composer check-platform-reqs | Passou no PHP 8.4.8 instalado |
| Instalação npm | Concluída com Node 24.22.1 do runtime e npm 10.9.2; 87 pacotes instalados; auditoria reportou zero vulnerabilidades naquele instante |
| npm run build (via runtime documentado) | Passou; CSS 40,62 kB e JS 0,39 kB; manifest gerado |
| php artisan test (suítes padrão Unit/Feature) | 4 testes passaram, 15 assertions; não acessam banco |
| php artisan test --testsuite=PostgreSQL | Executado e falhou: fe_sendauth: no password supplied. Duas assertions de configuração passaram, conexão bloqueada. Não marcar integração aprovada |
| Migrações PostgreSQL | Não executadas: faltam credenciais e confirmação de banco dedicado. Nenhum banco existente foi alterado; não houve SQLite |
| php vendor/bin/pint --test | Passou após normalização de finais de linha pelo Pint |
| php artisan view:cache / view:clear | Passaram |
| php artisan route:list --except-vendor | Somente GET/HEAD /; sem registro público |
| php artisan schedule:list | Nenhuma tarefa definida, conforme escopo |
| Navegador local em http://127.0.0.1:8000 | Tela renderizou com assets; clique Livewire exibiu “Comunicação com o servidor verificada.”; tema escuro permaneceu após reload. Evidência visual salva fora do repositório no diretório de visualizações da sessão |
| Verificação visual | Desktop conferido; celular e validação completa por teclado ainda pendentes, AC17 não concluído |
| git check-ignore | .env, .env.testing e storage/logs/laravel.log ignorados; exemplos de ambiente não ignorados |
| SMTP/captura local | Configuração preparada; Mailpit não instalado/iniciado nem envio validado |

## Bloqueios e limites

1. Resolvido em 30/10/2026: usuário criou bancos/roles e preencheu credenciais localmente. Conexões chamados_dev/chamados_app e chamados_test/chamados_test confirmadas, sem superusuário; bancos estavam vazios. Migrações aplicadas em ambos; suíte PostgreSQL aprovada. Senhas não foram exibidas e os arquivos de ambiente continuam ignorados pelo Git.
2. Resolvido em nova verificação de 29/10/2026: Node do sistema 22.17.1 e npm 10.9.2 executam normalmente; npm.cmd run build passou com código de saída 0. A alternativa Node 24 permanece somente como histórico da instalação inicial.
3. Resolvido em 30/10/2026: Mailpit 1.32.0 instalado localmente e iniciado em loopback; mensagem fictícia enviada pelo Laravel e recebida na captura. Convites/recuperação serão implementados na etapa 2.
4. Git detectou ownership diferente da pasta. Inspeções foram executadas com -c safe.directory=C:/laragon/www/Sistema_de_chamado somente por comando; configuração global não alterada. Usar o mesmo prefixo nos comandos Git desta pasta se o aviso persistir.
5. PHP 8.4.8 e cliente PostgreSQL 18.4 são patches antigos de linhas suportadas. Atualização controlada/homologação antes da produção; nenhuma atualização global foi feita. Compatibilidade medida é com PHP 8.4.8 e Node 24.22.1.

Pendências corporativas da seção 19 continuam válidas e não se tornaram políticas definitivas. Não bloqueiam o desenho local da etapa 2; impedem compromissos/produção conforme a referência.

## Próximo passo

Fundação, autenticação/permissões, cadastros e portal pessoal concluídos para desenvolvimento/piloto. Próximo módulo: **atendimento do TI**, somente após nova solicitação. Roteiros em testes-etapa2.md, testes-etapa3.md e testes-etapa4.md. Não há credenciais administrativas fixas, integração Microsoft, rede/VPN, túnel ou implantação nesta entrega.


## Assistente local de configuracao PostgreSQL

Preparado scripts/configurar-postgresql-local.ps1, com entrada oculta da senha administrativa no terminal local, senhas aleatorias para usuarios dedicados e gravacao somente em .env/.env.testing. Recusa nomes de bancos/usuarios ja existentes, sem sobrescrever ou remover recursos. Nao imprime nem salva senha administrativa. A senha administrativa e solicitada somente ao operador local; nao deve ser enviada ao chat. Provisionamento e verificacoes de banco permanecem pendentes ate o usuario concluir a entrada no terminal e a execucao ser verificada. O script nao executa migracoes.

## Validação PostgreSQL concluída — 30/10/2026

- Servidor confirmado por SQL: PostgreSQL 18.6 em 127.0.0.1:5432.
- Desenvolvimento: chamados_dev, usuário chamados_app; testes: chamados_test, usuário chamados_test. Usuários sem superusuário; ambos os bancos estavam vazios antes da execução.
- php artisan config:clear executado antes de conferir as conexões. Credenciais lidas apenas pela aplicação, sem impressão de segredos.
- php artisan migrate --no-interaction e php artisan migrate --env=testing --no-interaction: aprovados. Duas migrações de infraestrutura aplicadas em cada banco (cache e filas), além da tabela de controle migrations. Nenhuma migração destrutiva, seed ou conta administrativa criada.
- php artisan migrate:status nos dois ambientes confirmou ambas as migrações como Ran.
- Ajustado timezone UTC na conexão pgsql em config/database.php; servidor tinha America/Sao_Paulo como padrão. A mudança afeta as conexões da aplicação, sem alterar globalmente o servidor. Teste de integração consulta o fuso real da sessão e exige UTC.
- php artisan test --testsuite=Unit,Feature,PostgreSQL: cinco testes passaram, 24 assertions. Teste de persistência utiliza transação com rollback.
- php vendor/bin/pint --test: passou. git check-ignore confirmou .env e .env.testing ignorados.
- Os resultados de falha de conexão na tabela anterior são históricos da execução de 29/10/2026, superados por esta validação.
- O helper scripts/configurar-postgresql-local.ps1 não foi utilizado para provisionar os bancos. Não o executar sobre os recursos já criados; ele recusa nomes existentes.
- Permanece pendente apenas a validação da captura local de e-mail nessa parte da fundação. Autenticação e permissões não foram iniciadas.

## Captura de e-mail validada e fundação concluída — 30/10/2026

- Usuário confirmou que os testes manuais orientados da tela estavam corretos (comunicação, tema persistido, teclado e janela reduzida). Não houve teste em celular físico; homologação funcional completa continua na etapa 8.
- Instalado Mailpit v1.32.0 Windows amd64 da distribuição oficial https://github.com/axllent/mailpit/releases/tag/v1.32.0, sem serviço global. Arquivo em .tools/mailpit, ignorado pelo Git.
- SHA-256 do ZIP conferido com o digest oficial da release: 16915767a6c95dca569336e8f561f91cf355e9a33c57b91deedcc10a4bba7fd4.
- Captura iniciada com flags --listen 127.0.0.1:8025 e --smtp 127.0.0.1:1025, sem relay/forwarding, com checagem automática de versão desativada. Variáveis MP_* herdadas são isoladas ao iniciar. Nenhuma alteração de firewall ou publicação.
- scripts/iniciar-mailpit.ps1 inicia a captura em segundo plano e reconhece instância própria existente. Comando testado novamente: reconheceu a captura sem duplicar processo.
- Laravel no ambiente local, usando sua configuração SMTP real, enviou mensagem fictícia de sistema@example.test para teste@example.test. Assunto único confirmado uma única vez na API GET /api/v1/messages; versão local conferida em GET /api/v1/info. Nenhuma conta corporativa ou destinatário externo utilizado.
- Evidência técnica do recebimento em .tools/mailpit/verificacao.json, não versionada; captura temporária do Mailpit não é armazenamento de negócio do sistema. O banco da aplicação permanece PostgreSQL.
- php artisan test --testsuite=Unit,Feature,PostgreSQL passou novamente: 5 testes, 24 assertions. .env, .env.testing e .tools continuam ignorados pelo Git.
- Consultas de listeners por CIM/netstat não listaram sockets LISTEN do processo nesse ambiente, embora SMTP e API HTTP locais tenham respondido e o binário tenha sido confirmado em execução. Endereços de inicialização são explícitos em loopback; não se declarou inspeção de sockets como aprovada.
- As pendências de SMTP de registros anteriores são históricas e estão resolvidas. Autenticação e permissões ainda não foram implementadas. Nenhuma regra de chamados, SLA ou alertas foi adiantada.

## Etapa 2 — autenticação e permissões concluída (30/10/2026)

Estado: **concluída para desenvolvimento/piloto**, sem implantação. Pré-requisito confirmado por execução: PostgreSQL dedicado, assets e Mailpit operacionais. Somente etapa 2 implementada; cadastros funcionais/chamados/SLA/alertas não iniciados.

### Entregas

- Laravel Fortify 1.41.0 adicionado, preservando as versões principais e pacotes existentes. Rotas de registro público ausentes no frontend/backend.
- Login/logout, confirmação de e-mail assinada, broker de recuperação, convites temporários com hash/uso único e reenvio revogando anterior.
- Sete perfis explícitos, Gates administrativos distintos, componente Livewire de usuários com autorização por ação, validação de IDs/perfis e justificativas.
- MFA TOTP obrigatório para administradores; setup restrito com senha recente, prova TOTP, códigos de recuperação criptografados e consumo serializado. Sem endpoint de desativação/reset por e-mail.
- Desativação/revogação por banco e auth_version, incluindo requisição Livewire e desafio MFA pendente. Reativação não restaura sessões.
- Último administrador protegido; auditoria transacional sem credenciais, sem exclusão operacional. Unidade/setor/equipes permanecem na etapa 3.
- Bootstrap interativo opt-in somente local/testing, sem senha fixa ou conta humana gerada automaticamente. Procedimento em README e testes-etapa2.md.
- Identidades externas separadas/reservadas por provider/organization/subject; nenhuma integração Microsoft.
- Login, recuperação, confirmação, convite, segurança e administração em português; temas, foco e responsividade básica. Home apenas confirma acesso e informa módulos ainda não implementados.
- Matriz em docs/permissoes.md, modelo/decisões/rastreabilidade/README atualizados. docs/especificacao.md preservado byte a byte.

### Comandos e resultados reais

| Comando/verificação | Resultado |
|---|---|
| composer require laravel/fortify --no-interaction | Aprovado: Fortify 1.41.0; 21 instalações, zero updates/removals de pacotes anteriores; auditoria Composer sem advisories reportados naquela execução |
| php artisan migrate --no-interaction | Aprovado em chamados_dev; somente migração incremental 2026_10_30_000001 |
| php artisan migrate --env=testing --no-interaction | Aprovado em chamados_test; migrações da fundação preservadas |
| php artisan migrate:status nos dois ambientes | Aprovado: duas migrações da fundação e migração incremental de acesso como Ran |
| npm.cmd run build | Aprovado: Vite 7.3.7/Tailwind 4.3.3, CSS 64,47 kB e JS 0,39 kB |
| php artisan test --testsuite=Unit,Feature,PostgreSQL | **Aprovado: 34 testes, 385 assertions** na verificação final |
| php vendor/bin/pint / --test | Aprovado após normalizar formatação; última verificação passou |
| php artisan view:cache / view:clear | Aprovados |
| composer validate --strict / check-platform-reqs | Aprovados no PHP 8.4.8 local |
| SMTP dos fluxos reais | Aprovado: confirmação, recuperação e convite recebidos em Mailpit para validacao-pt-etapa2@example.test; assuntos em português; fixtures de chamados_test revertidas |
| Revisão de segredos | Nenhuma correspondência de APP_KEY/DB_PASSWORD locais nos fontes/documentos não ignorados examinados, sem imprimir valores; .env/.env.testing/.tools ignorados |
| UI por navegador | Login desktop e 390x844, claro/escuro, persistência após reload e foco por Tab confirmados; largura de conteúdo 390 = viewport 390, sem overflow horizontal |
| Conta humana inicial / MFA em aparelho do operador | Não executado: criação é interativa e segredo deve ser definido pelo operador; código/fluxos validados via HTTP/Livewire/PostgreSQL |

Ensaios intermediários registrados: após instalar Fortify antes de configurar suas rotas, /register retornou 500 por view não vinculada; configuração explícita corrigiu, 404 comprovado. A primeira suíte de acesso teve 28 testes aprovados e 1 falhou por chamar ação após mount Livewire negado (snapshot inválido do teste); corrigida a sequência, sem remover autorização. Primeira inspeção SMTP usava filtro PowerShell incorreto para destinatário e revelou dois assuntos em inglês; filtro e traduções corrigidos, três recebimentos finais confirmados. Esses resultados intermediários não são apresentados como verificações aprovadas.

### Evidências funcionais

AccessControlTest cobre login inválido/válido, logout, bloqueio de inativo/sem perfil, e-mail obrigatório/assinatura, limitação, registro público, convite inválido/expirado/reutilizado/reemitido, reset expirado/reutilizado, resposta genérica de recuperação, MFA pendente/TOTP/recovery/limitação, ausência de bypass por reset, criptografia/serialização, seis perfis comuns bloqueados, ações administrativas positivas/negativas, perfil adulterado, justificativa, último administrador, desativação/sessões antigas e Livewire. AC03 e AC04 atendidos neste escopo; AC01/AC02 continuam pendentes.

### Limites e condição para a próxima etapa

Não há bloqueio técnico conhecido para iniciar a etapa 3 após nova solicitação. Antes de uso humano, executar bootstrap e o roteiro manual com dados fictícios. Recuperação de perda simultânea de autenticador/todos os códigos, provisionamento de produção, revisão por leitor de tela e validação em celular físico dependem de procedimento/homologação. Políticas corporativas da referência continuam pendentes; parâmetros de segurança adotados são técnicos para desenvolvimento/piloto.

Próxima etapa: **cadastros** (unidades, setores, equipes, categorias e serviços), somente quando solicitada. Sem commit/push, publicação, túneis ou alterações da rede corporativa.

## Etapa 3 — cadastros e organização concluída (03/11/2026)

Estado: **concluída para desenvolvimento/piloto**, com catálogo persistido e formulários administrativos HTTP/Livewire funcionais. Sem implantação. Etapa 4 não iniciada. Roteiro humano/visual e homologação corporativa permanecem pendentes, explicitados abaixo; não foram usados para declarar AC17 integralmente atendido.

### Escopo entregue

- Migração incremental de unidades/cidades, setores, equipes/membros, categorias/subcategorias, serviços e referências simples de equipamento; vínculos de organização adicionados aos usuários sem alterar migrações anteriores.
- Formulários de adicionar/editar/inativar com busca, filtro de estado, paginação, estado vazio, rótulos, temas herdados e layout responsivo. Organização dos usuários edita cadastros existentes; conta/convite e perfis continuam na administração da etapa 2.
- Gate catalogs.manage exclusivo para administrador ativo/verificado com MFA comprovado, senha recente, autorização na rota/componente/serviço, IDs Locked e whitelist de campos. Equipe/cargo/N1–N3 não conferem acesso.
- Referência/substituto da própria equipe; referência do serviço membro da equipe padrão; cobertura manual e alternativa para críticos como dados da instalação. Desenvolvimento preparado para encaminhamentos específicos, sem visibilidade global ou fluxo de chamados antecipado.
- Inativação sem exclusão, preservação de vínculos antigos, filtro de itens inativos nas novas seleções, proteção de referência de membro e FK composta categoria/subcategoria. Auditoria transacional e rejeição visível de edição desatualizada.
- Seed fiel às 11 categorias/41 subcategorias da seção 8, dez serviços nominados e seis exemplos técnicos configuráveis de infraestrutura. 5 equipes, incluindo Desenvolvimento; nenhuma pessoa/cidade/unidade real inventada. Solus sem criticidade automática e nenhum serviço define P1.
- Seed idempotente por chave estável, sem sobrescrever personalização; conflito com nome manual aborta integralmente. Repetição executada duas vezes no banco local.
- README, progresso, decisões, modelo, rastreabilidade, permissões e roteiro manual atualizados. Especificação preservada, hash original reconfirmado.

### Comandos e resultados reais

| Verificação/comando | Resultado |
|---|---|
| git -c safe.directory=C:/laragon/www/Sistema_de_chamado status --short antes das alterações | Aprovado: árvore limpa; nenhuma alteração anterior sobrescrita. Exceção por comando; sem configuração global/commit/push |
| php artisan test --testsuite=Unit,Feature,PostgreSQL antes de implementar | Aprovado: 34 testes/385 assertions, verificando autenticação/desativação/autorização existentes |
| Identidade PostgreSQL por SQL antes da migração local | Aprovado: chamados_dev/chamados_app; testes exigem chamados_test/chamados_test antes de escrever, com DB_URL vazio |
| php artisan migrate --env=testing --no-interaction | Aprovado: somente incremental 2026_11_03_000001; nenhuma limpeza destrutiva |
| php artisan migrate --no-interaction | Aprovado: mesma incremental no desenvolvimento; anteriores preservadas |
| php artisan db:seed --class=InitialCatalogSeeder --no-interaction (duas execuções em dev) | Aprovado: 5 equipes, 11 categorias, 41 subcategorias, 16 serviços confirmados por SQL após repetição; unidades/setores/membros seguem vazios |
| php artisan migrate:status e --env=testing | Aprovados: quatro migrações Ran em cada ambiente |
| php artisan test --filter=CatalogTest, primeira execução | Falhou: 9 passaram/1 falhou porque o teste esperava somente SQLSTATE 23503; PostgreSQL retornou 23001 ao impedir exclusão restritiva. As restrições permaneceram; teste passou a reconhecer os dois códigos corretos |
| Regressão intermediária após esse ajuste | Aprovada: 44 testes/537 assertions |
| CatalogTest com casos adicionais de formulários completos/seleção vazia | Falhou: 13 passaram/1 falhou; select “Não informado” enviava string vazia para query bigint no render. Corrigido normalizando IDs nas consultas de opções; troca de equipe também limpa referência |
| php artisan test --testsuite=Unit,Feature,PostgreSQL final | **Aprovado: 48 testes/572 assertions**, incluindo os 14 casos de CatalogTest e toda autenticação existente |
| npm.cmd run build | Aprovado: Vite 7.3.7; CSS 65,02 kB e JS 0,39 kB |
| php vendor/bin/pint e php vendor/bin/pint --test | Aprovados: formatação normalizada, verificação final sem problemas |
| php artisan view:cache / view:clear | Aprovados |
| Get-FileHash docs/especificacao.md -Algorithm SHA256 | Aprovado: 7885ee818100b1ac35445e4f39bbcaed2be533f67db9bac9930e4700fc089688 |
| git diff --check | Aprovado; somente avisos de normalização CRLF/LF |
| Composer/dependências | Não reinstalados/atualizados: nenhuma alteração em dependências/lockfiles; mesmas versões verificadas na etapa 2 |
| E-mail, Microsoft, rede/publicação | Nenhum envio novo ou integração/alteração/publicação. Testes de autenticação usam notificações falsas e mailer array |
| Navegador autenticado, celular físico e leitor de tela | Não executados nesta entrega: dependem do roteiro humano e conta inicial com segredo definido pelo operador; renderização/ações verificadas por HTTP/Livewire, sem alegar homologação visual |

A primeira tentativa da suíte dentro do sandbox falhou ao abrir vendor/autoload.php por leitura negada no PHP; executada novamente com acesso autorizado ao ambiente local, sem alteração de arquivos globais. Uma consulta auxiliar de contagens via Tinker falhou por escape PowerShell de variável; substituída por SELECT sem variáveis, aprovado. Essas tentativas não são apresentadas como verificações aprovadas.

### Evidências, limites e continuidade

CatalogTest verifica todos os perfis comuns por URL/componente/serviço; ação após revogação; adulteração de catálogo; proteção de roles/credenciais/estado; formulários de unidades, setores, equipes, membros, categorias/subcategorias, serviços, equipamento e organização; seed exato/repetido/personalizado/conflitante; relações, inativação/reativação, FK composta, exclusão restritiva, busca/paginação e conflito de versões. Fixtures são fictícias e revertidas em transação no banco dedicado, sem migrate:fresh/refresh/limpeza de desenvolvimento.

AC03/AC04 continuam aprovados pela regressão; AC05 é **parcial** (cadastros de serviços, ainda sem abertura de chamados). AC15 permanece pendente para concorrência de atendimento, embora cadastros já rejeitem versão desatualizada. AC17 permanece parcial: não houve nova inspeção visual autenticada. Demais AC não foram marcados concluídos.

Pendências: executar testes-etapa3.md com conta do operador, preencher organização/referências fictícias do piloto e homologar visual/dispositivos/tecnologia assistiva. Pessoas, cobertura por período e criticidade final dependem da empresa; não há bloqueio técnico conhecido para solicitar a etapa 4. Requisitos de produção da seção 19 permanecem válidos.

Condição técnica de avanço atendida: catálogo real persistido no banco local, formulários com operações reais testadas e autenticação preservada. **Próxima etapa: portal do colaborador**, somente após nova solicitação. Não foram implementados chamados, anexos, SLA, aprovações ou alertas.

## Ajuste de feedback do bootstrap — 03/11/2026

Após relato de erro genérico na criação manual da primeira conta, auth:bootstrap passou a listar os erros de validação em português sem imprimir senha/confirmacao. Mensagens específicas para senha vazia, confirmação divergente, e-mail fora de .test e e-mail já cadastrado. Regras de senha/perfis/segurança preservadas; nenhuma conta humana criada ou alterada por este ajuste. Alterações preexistentes da etapa 3 preservadas.

Verificação: php artisan test tests/Feature/AccessControlTest.php --filter=bootstrap aprovado (6 testes, 82 assertions), com casos isolados e transações revertidas em chamados_test. Pint dos dois arquivos alterados aprovado. Ensaio inicial de teste em loop falhou por acúmulo de expectativas de console; substituído por DataProvider com instâncias isoladas, mantendo os quatro cenários negativos. Não foi executada nova suíte completa por se tratar de ajuste restrito ao comando; demais verificações anteriores mantêm suas datas.

## Falha de SMTP após cadastro inicial — 03/11/2026

Operador concluiu a validação da senha e criou a primeira conta, mas a notificação falhou porque 127.0.0.1:1025 estava sem captura em execução. Verificado no banco: um administrador ativo com senha definida e e-mail ainda pendente. Nenhuma senha ou token foi exibido. Mailpit iniciado em loopback; confirmação reenviada somente para endereço fictício .test, com recebimento confirmado na captura. AUTH_BOOTSTRAP_ENABLED desativado após cadastro, sem alterar credencial da conta.

Comando agora captura especificamente falha de transporte e informa que a conta foi criada e que o operador deve iniciar Mailpit, entrar e reenviar confirmação, sem repetir cadastro. Verificações: sintaxe PHP aprovada; filtro bootstrap aprovado com 7 testes/99 assertions, incluindo preservação da conta diante de SMTP indisponível; Pint dos arquivos alterados aprovado. Dados de teste revertidos em chamados_test. Alterações preexistentes preservadas.

## Retorno dos testes manuais da etapa 2 — 03/11/2026

Usuário informou: “certo, finalizei os teste 2”. Registrada a conclusão do roteiro humano da etapa 2 pelo operador, após criação da conta, confirmação de e-mail/MFA, teste de convite em sessão separada e orientação de desativação. O erro 403 relatado durante a sessão foi declarado resolvido pelo próprio usuário; o diagnóstico de leitura confirmou administrador ativo, e-mail e MFA confirmados, sem ler/exibir segredos. Não se atribui uma causa não comprovada ao 403.

Esse retorno é evidência de conclusão informada pelo usuário, não uma nova execução automatizada nem relatório individualizado de cada cenário. Testes em aparelho físico, leitor de tela e homologação de produção continuam com seus estados anteriores. Não foram alterados dados, permissões ou código nesta atualização, nem executada nova suíte por ser registro documental. Roteiro manual da etapa 3 permanece separado; nenhum avanço de etapa foi executado nesta interação.

## Retorno dos testes manuais da etapa 3 — 03/11/2026

Usuário confirmou: “todos os teste feitos”, após executar o roteiro da etapa 3 e receber detalhamento dos testes 8 (tema, teclado e janela reduzida) e 9 (seed repetido preservando personalização). Registrada a conclusão manual informada pelo operador, sem falhas pendentes relatadas. Essa confirmação atualiza a pendência de roteiro humano dos registros anteriores; não é nova execução automatizada nem comprovação individualizada de cada cenário.

Etapa 3 permanece concluída para desenvolvimento/piloto, agora com conclusão do roteiro manual informada pelo usuário. Celular físico e leitor de tela não foram confirmados especificamente; homologação corporativa e de produção permanece pendente. Nenhum AC futuro foi marcado como atendido, nenhuma nova suíte foi necessária para este registro documental e a etapa 4 não foi iniciada. Próxima etapa somente após nova solicitação.

## Preparação do envio da etapa 3 ao GitHub — 03/11/2026

Usuário autorizou o envio desta etapa ao repositório origin, AmandaBfa/chamados_app. Fetch confirmou main sincronizada com origin/main antes do commit. Escopo inclui cadastros, documentação e conclusão manual das etapas 2/3, além das mensagens de validação e tratamento de SMTP do bootstrap corrigidos durante os testes do operador.

Regressão anterior ao envio: `php artisan test --testsuite=Unit,Feature,PostgreSQL` **aprovada: 53 testes/649 assertions**, incluindo cinco casos adicionais do bootstrap. `php vendor/bin/pint --test` e `git diff --check` aprovados. .env, .env.testing, .tools, vendor e node_modules permanecem ignorados; exemplos de ambiente sem chaves/senhas preenchidas. Não há implantação nem avanço para etapa 4. O registro histórico de ausência de autorização de push foi substituído pela solicitação explícita deste envio.

## Etapa 4 — portal pessoal e abertura concluída (03/11/2026)

Revalidação em 09/11/2026: `php artisan test --testsuite=Unit,Feature,PostgreSQL` **aprovado, 73 testes e 967 asserções** no PostgreSQL dedicado `chamados_test`. A primeira tentativa no sandbox falhou na leitura do bootstrap. Uma configuração temporária contornou o bootstrap, mas o sandbox negou a conexão local (10013). A repetição com acesso ao PostgreSQL local usou a configuração original. `php -l` e `php vendor/bin/pint --test app/Services/RequesterTickets.php tests/Feature/RequesterPortalTest.php` aprovados nos PHP alterados. Roteiro visual e por teclado não executado nesta revalidação.

A abertura agora rejeita início conhecido sem data também quando o valor recebido é o texto `0`. A consulta de subcategorias usa um identificador de categoria inteiro, inclusive quando a entrada é adulterada. O modelo de dados permanece aplicável sem nova migração.

Estado: **concluída para desenvolvimento/piloto**, com isolamento funcional comprovado por HTTP/Livewire/PostgreSQL. A homologação visual e humana está pendente (AC17). A etapa 5 aguarda solicitação. A entrega inicial foi local. O usuário autorizou posteriormente o envio do código da etapa 4 ao GitHub.

### Escopo e decisão do usuário

- Portal real com abrir chamado, lista pessoal com busca/filtro/paginação e detalhe com conversa/histórico públicos. Interface pt-BR com rótulos, foco, layout responsivo e tema salvo herdado, sem métricas ou prazos fictícios.
- Solicitante/autor/contexto obtidos da sessão e cadastro recarregado; snapshot de unidade/setor/cidade, catálogo validado, início aproximado UTC ou desconhecido e estado Novo. Tipo incidente e prioridade nula, sem motor de SLA ou escolha de P1/P2 pelo colaborador.
- Migração incremental de tickets/comments/attachments/ticket_events/temporary_upload_receipts; FKs restritivas e compostas, CHECKs, UNIQUEs de protocolo/idempotência e índices de consultas pessoais. Modelo de eventos/ciclos conferido antes das tabelas; relógios/ciclos futuros não foram simulados.
- Abertura transacional com chave por formulário, proteção contra concorrência, um evento inicial com ator/instante e retorno do mesmo protocolo em reenvio. Comentário tem chave própria. Itens históricos inativados continuam legíveis e contexto não muda com o cadastro atual.
- Upload privado, dono/validade/uso único desde o temporário, quantidade/tamanho/conteúdo/MIME/extensão revalidados ao salvar. Download autenticado verifica ticket/anexo/comentário e força arquivo sem execução inline. Rollback de metadados e limpeza das cópias privadas de envio falho comprovados. Sem scanner antivírus inventado.
- Notas/eventos/anexos internos excluídos das consultas e DTOs do portal. Administrador não recebe leitura global. Ações Livewire, buscas, envio temporário e downloads autorizados no servidor.
- Lacuna de acesso foi apresentada ao usuário: escolha explícita de permitir portal pessoal a **todos os usuários internos ativos, incluindo TI e administrador**. Teste dos sete perfis aprovado, mantendo e-mail/sessão válidos e MFA administrativo. Especificação original preservada; decisão registrada em decisões/permissões.

### Comandos e resultados reais

| Verificação / comando | Resultado |
|---|---|
| Git/status e instruções locais | Aprovado: árvore limpa no início, sem AGENTS.md aplicável localizado; alterações da etapa 3 preservadas. Sem commit/push nesta etapa |
| php artisan test --testsuite=Unit,Feature,PostgreSQL antes da implementação | Aprovado: 53 testes/649 assertions, verificando os pré-requisitos de autenticação/cadastros |
| php artisan migrate --env=testing --no-interaction | Aprovado: somente incremental 2026_11_03_000002 em chamados_test; sem fresh/refresh/limpeza de dados existentes |
| Identidade por SQL antes da migração local | Aprovado: chamados_dev/chamados_app; testes verificam chamados_test/chamados_test e DB_URL vazio antes de escrita |
| php artisan migrate --no-interaction | Aprovado: incremental do portal no desenvolvimento; dados anteriores preservados |
| migrate:status nos dois ambientes | Aprovado: cinco migrações Ran em cada banco |
| RequesterPortalTest inicial | Falhou: 8 passaram/4 falharam porque endpoint de upload de teste precisava do disco tmp-for-tests inicializado; harness corrigido, sem alterar storage de desenvolvimento |
| Reexecução após configurar disco de testes | Falhou: comparação do recibo não considerava barra inicial retornada pelo pacote; nome temporário passou a ser normalizado consistentemente, sem retirar checagem de dono/validade/uso único |
| Reexecução do portal após normalização | Falhou: 11 passaram/1 falhou por RecordNotFoundException do query builder no teste Livewire; ausência/alheio passou a usar abort(404) explícito em portal/download |
| Regressão intermediária | Aprovada: 66 testes/857 assertions, incluindo ensaio real de concorrência |
| Casos adicionais de segurança | Aprovados isoladamente: 18 casos de portal/264 assertions; teste dos sete perfis aprovado separadamente (1 caso/40 assertions) |
| Regressão durante duas execuções sobrepostas | Falhou em uma fixture de arquivo removida pelo fake do outro processo. Corrigido isolamento dos discos fictícios por PID; validação final executada sequencialmente. Nenhum arquivo de desenvolvimento afetado |
| php artisan test --testsuite=Unit,Feature,PostgreSQL final | **Aprovado: 73 testes/964 assertions**, incluindo 19 casos de portal, todas as etapas anteriores e concorrência |
| TicketConcurrencyTest | Aprovado: dois processos PHP independentes aguardam barreira e enviam a mesma chave; mesmo ID/protocolo, um chamado e um evento. Fixtures temporariamente persistidas apenas em chamados_test e removidas por seus IDs no finally |
| npm.cmd run build | Aprovado: CSS 65,31 kB e JS 0,39 kB, Vite 7.3.7 |
| php vendor/bin/pint e --test final | Aprovados após normalizar imports; uma verificação --test intermediária apontou imports do caso recém-adicionado e foi corrigida |
| php artisan view:cache / view:clear | Aprovados |
| Limites PHP CLI observados | upload_max_filesize=2G, post_max_size=2G, max_file_uploads=20; não alterados. Aplicação/Livewire limitam a cinco arquivos de 10 MB; servidor HTTP de produção precisa de conferência própria |
| Documentação oficial | Livewire uploads/segurança e PostgreSQL locks consultados; Laravel filesystem teve timeout inicial, API instalada inspecionada e consulta repetida com sucesso |
| Controle visual do Edge | Não executado: autorização da ferramenta expirou antes de controlar a janela. Nenhuma evidência de celular/teclado/leitor de tela do portal foi declarada aprovada |
| E-mail/dependências/integrações | Nenhum envio real ou dependência nova; testes usam notificações falsas. Sem Microsoft, aprovação Adobe/Teams, rede, implantação ou push |

### Limites e condição para continuidade

Condição técnica de avanço atendida: usuário abre/consulta/comenta/anexa em chamados próprios, com isolamento demonstrado em GET, Livewire, upload, download e DTO; autenticação/cadastros continuam aprovados. AC01/AC02 atendidos **no portal pessoal**, sem alegar políticas operacionais/notificações futuras; AC05/AC06/AC16 têm evidência específica. AC15 parcial pela abertura concorrente; atendimento/SLA pendentes. AC17 parcial até roteiro humano/visual.

Roteiro em testes-etapa4.md. Não há scanner antivírus/parser completo de PDF; filtros conservadores podem rejeitar PDFs complexos. Reconciliação de órfãos após queda abrupta, retenção de anexos/recibos, análise antimalware, restauração de backups e parâmetros do servidor de produção dependem de homologação. Colar/arrastar arquivos são facilidades futuras não implementadas; seleção múltipla normal está disponível.

Próxima etapa: **atendimento do TI**, somente após nova solicitação. Não foram antecipados fila, atribuição, transições, ciclos/relógios de SLA, prioridades calculadas, aprovações, alertas, exportações ou notificações. Pendências corporativas da seção 19 seguem preservadas.
