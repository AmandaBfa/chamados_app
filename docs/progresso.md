# Progresso — sistema de chamados de TI

Atualizado em 30/10/2026. Escopo executado: etapas 1 e 2, com resultados e histórico separados abaixo. A documentação original foi lida integralmente e copiada sem alteração para especificacao.md. SHA-256: 7885ee818100b1ac35445e4f39bbcaed2be533f67db9bac9930e4700fc089688.

## Oito etapas autorizáveis

| Etapa | Estado real | Próximo trabalho |
|---|---|---|
| 1. Fundação | Concluída: base, PostgreSQL, assets e captura SMTP validados; testes manuais da tela confirmados pelo usuário | Próxima etapa: autenticação e permissões |
| 2. Autenticação e permissões | Concluída para desenvolvimento/piloto; 34 testes/385 assertions aprovados | Operador inicializar conta com segredo próprio e seguir roteiro manual; próxima etapa 3 somente sob solicitação |
| 3. Cadastros | Não iniciada | Unidades/setores, equipes, catálogo integral e configurações por instalação |
| 4. Portal do colaborador | Não iniciada | Abertura idempotente, próprios chamados, conversa pública e anexos autorizados |
| 5. Atendimento do TI | Não iniciada | Fila, atribuição, notas internas, transições, histórico e concorrência |
| 6. Motor de SLA | Não iniciada | Calendários/versionamento, ciclos, dois relógios, pausas e testes de borda |
| 7. Alertas e dashboards | Não iniciada | Deduplicação, escalonamento, indicadores e reconciliação por filtros |
| 8. Homologação e implantação | Não iniciada | Aprovações corporativas, rede/hospedagem, HTTPS, backup/restauração, segurança e piloto |

Esta divisão detalha a sequência da seção 18 da especificação: autenticação/perfis/auditoria foram separados da fundação pelo pedido explícito. Nenhum requisito foi retirado. Não avançar para etapa 2 sem nova solicitação.

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

Fundação e autenticação/permissões concluídas para desenvolvimento/piloto. Próximo módulo: **cadastros**, somente após nova solicitação. Inicialização humana da conta e roteiro manual em testes-etapa2.md. Não há credenciais administrativas fixas, integração Microsoft, rede/VPN, túnel ou implantação nesta entrega.


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
