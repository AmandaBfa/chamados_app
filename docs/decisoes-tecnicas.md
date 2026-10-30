# Decisões técnicas — etapa 1

Registro de 29/10/2026. Não altera decisões da especificação. Fontes oficiais consultadas por navegação; versões exatas resolvidas nos lockfiles, sem pré-lançamentos.

## Ambiente e versões

- Windows / PowerShell; pasta inicialmente vazia, sem Git ou AGENTS.md aplicável encontrado nos ancestrais. Git 2.54.0 inicializado localmente, sem commit, remoto ou push.
- PHP 8.4.8 existente do Laragon; linha 8.4 suportada e compatível com Laravel 13 (mínimo PHP 8.3). Patch instalado antigo: atualizar de forma controlada antes de produção; não houve atualização global. Composer 2.8.9 existente.
- Laravel 13.39.0 e Livewire 4.4.8 (MIT), instalados pelo Composer. Restrições ^13.0 e ^4.0, minimum-stability stable e composer.lock versionado. Instalação inicial com --no-scripts impediu criação/migração automática de SQLite.
- Node do sistema em C:\\Program Files\\nodejs não executou (acesso negado, inclusive com elevação). Alternativa disponível: Node 24.22.1 LTS do runtime do aplicativo; npm 10.9.2 do sistema executado por esse Node. Não foram alteradas instalações globais.
- PostgreSQL cliente 18.4; serviço postgresql-x64-18 em execução e porta 5432 aceitando conexões. Versão do servidor ainda não confirmada por SQL: autenticação exige senha. Linha 18 suportada; fonte oficial recomenda patch atual (página consultada lista 18.6); avaliar atualização separadamente.
- Tailwind 4, Vite 7 e laravel-vite-plugin 2: linha fornecida pelo skeleton oficial, mantida sem upgrade indiscriminado. Versões exatas e resultado do build em progresso.md após verificação.
- Extensões presentes: ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, PDO, pdo_pgsql, session, tokenizer, xml. intl e bcmath também presentes. SQLite existe no PHP, mas não é utilizado pela aplicação nem pelos testes.

## Escolhas reversíveis

1. Monólito Laravel. Componentes agrupados por contexto conforme módulos surgirem (ex.: App/Livewire); sem pastas vazias ou abstrações de módulos futuros. Sem API separada.
2. Sem starter kit de autenticação: mantém etapa 2 isolada e evita registro público acidental e dependência paga. Scaffolding User/factory não é autenticação implementada. Seeder não cria contas.
3. Apenas migrações de cache/fila na fundação. Migração original de usuários/sessões removida do skeleton antes de qualquer aplicação; identidades e credenciais serão desenhadas antes da etapa 2.
4. PostgreSQL nativo existente: dispensa Docker. Bancos chamados_dev e chamados_test e usuários próprios precisam ser provisionados pelo operador local. Não usar usuário postgres como usuário da aplicação. Nenhum banco existente alterado.
5. Sessão e cache em arquivos para verificar interface independentemente do banco. Fila database configurada; nenhum job de negócio. Scheduler sem tarefas; não representa SLA/alertas implementados.
6. Disco local em storage/app/private, visibility private, serve=false e throw=true. Não executar storage:link para anexos. Download autorizado somente nas etapas 4/5.
7. Captura SMTP local em 127.0.0.1:1025 preparada para Mailpit, sem instalação automática e sem envio corporativo. Testes usam mailer array. Não usar log de e-mail contendo tokens como captura padrão.
8. UTC no framework e PostgreSQL; fuso de apresentação configurado por INSTALLATION_TIMEZONE. Nenhum cálculo de SLA nesta etapa.
9. Tailwind compilado localmente, fonte do sistema sem CDN. Tema inicial claro e preferência localStorage; área TI escura virá na etapa 5. Não usar identidade corporativa sem solicitação.
10. Removidos scripts do skeleton que faziam migrate --force, criavam SQLite ou iniciavam Pail (dependente de pcntl ausente em Windows). Comandos explícitos no README; não executar migrações destrutivas automaticamente.

## Fontes oficiais

- Laravel: https://laravel.com/framework/docs/releases (Laravel 13, suporte e PHP mínimo).
- PHP: https://www.php.net/supported-versions.php (suporte por linha, não certifica patch local).
- Livewire: https://livewire.laravel.com/docs/4.x/installation e https://github.com/livewire/livewire (instalação e compatibilidade confirmada pelo Composer).
- Tailwind: https://tailwindcss.com/docs/installation/using-vite (plugin Vite).
- Vite: https://vite.dev/guide/ (requisitos Node 20.19+/22.12+; Node 24 atende).
- Node: https://nodejs.org/en/about/previous-releases (Node 24 LTS).
- PostgreSQL: https://www.postgresql.org/support/versioning/ (linha 18 suportada; patches recomendados).

A verificação de compatibilidade executada não substitui atualização de segurança/homologação de produção. Não foi configurada hospedagem, VPN, túnel ou Microsoft.

Versões finais dos assets: Tailwind 4.3.3, Vite 7.3.7 e laravel-vite-plugin 2.1.0, registradas no package-lock.json; build passou. A compatibilidade do lockfile foi medida em PHP 8.4.8, não em PHP 8.3. Git exige exceção safe.directory por comando neste ambiente, sem alteração global.


## Confirmação da conexão PostgreSQL — 30/10/2026

Servidor 18.6 confirmado por SQL. Credenciais próprias configuradas localmente pelo usuário para chamados_dev e chamados_test; nenhuma senha foi exibida ou versionada. Bancos verificados vazios antes das migrações não destrutivas de infraestrutura. Conexão pgsql configurada explicitamente com timezone UTC, evitando herdar America/Sao_Paulo do servidor; consulta real do fuso incluída no teste de integração. Cinco testes passaram (24 assertions). Falta de credenciais registrada na execução inicial foi resolvida; captura SMTP continua pendente.

## Captura SMTP local — 30/10/2026

Escolhido Mailpit v1.32.0 estável, ferramenta auxiliar MIT sem serviço global, para captura SMTP independente de destinatários reais. Fonte oficial https://github.com/axllent/mailpit/releases/tag/v1.32.0; SHA-256 do ZIP Windows amd64 16915767a6c95dca569336e8f561f91cf355e9a33c57b91deedcc10a4bba7fd4 verificado antes de executar. Binário em .tools/mailpit ignorado pelo Git, inicialização via scripts/iniciar-mailpit.ps1. HTTP/SMTP vinculados explicitamente a 127.0.0.1; sem encaminhamento externo, variáveis MP_* herdadas removidas apenas do processo filho, sem serviço ou firewall. Dados temporários da captura não substituem PostgreSQL nem definem retenção de dados do produto. Envio fictício pelo Laravel confirmado pela API local. Documentação de flags consultada em https://mailpit.axllent.org/docs/configuration/runtime-options/.

## Etapa 2 — autenticação e permissões (30/10/2026)

### Biblioteca e organização

Adicionado Laravel Fortify 1.41.0 (^1.41), MIT, como backend mantido para login/logout, confirmação de senha/e-mail, recuperação e TOTP. Composer instalou 21 pacotes novos, sem atualizar/remover dependências existentes. Dependências principais novas: Google2FA 9.1.0 e BaconQrCode 3.1.1; pacote passkeys transitivo não habilitado nem exposto. Laravel/Livewire/Tailwind/PostgreSQL e arquitetura de instalação isolada preservados.

Optou-se por rotas explícitas e Fortify::ignoreRoutes(), usando os controllers/actions oficiais onde pertinentes. Evita registrar cadastro público, atualização irrestrita de perfil, endpoints de remoção de MFA e passkeys fora de escopo. Cadastro administrativo ocorre por convite autorizado, não por senha fixa. Não há botão Microsoft, cliente Entra ou vinculação por coincidência de e-mail.

Fontes oficiais consultadas: https://github.com/laravel/docs/blob/13.x/fortify.md e https://livewire.laravel.com/docs/4.x/security. APIs também conferidas diretamente nos pacotes instalados antes do uso. Fortify default após instalação registrava rotas não configuradas: uma verificação intermediária de /register falhou; corrigida pela configuração explícita/ignoreRoutes, com GET/POST /register agora testados como 404.

### Identidade, papéis e autorização

Usuário interno tem ID estável; credencial local (hash) permanece em users.password para interoperar com guard, broker e Fortify sem provider próprio. Proposta anterior de local_credentials separada foi substituída por esta escolha reversível; não altera a exigência de identidades externas separadas. login_identities reserva provider + organization_id + subject UNIQUE e FK para usuário; sem fluxo de cadastro/login externo nesta etapa.

Sete roles são catálogo incremental no banco; role_user permite atribuições múltiplas. Abilities explícitas em Gates (users.view, users.create, users.update, roles.assign), sem tabela genérica de permissões editáveis ou pacote adicional prematuro. Não há superpermissão Gate::before. Cargo, classificação e futuras equipes independem de roles. Matriz completa em permissoes.md; permissões de chamados sensíveis não foram antecipadas.

Mudanças de acesso e convites usam AdminUserService, validação/Gates no servidor e justificativa de 8–500 caracteres. Componente Livewire repete autorização no render; serviço autoriza cada operação e exige senha recente. IDs e arrays de perfis enviados pelo cliente nunca são considerados autorização.

### Parâmetros técnicos iniciais (desenvolvimento/piloto)

- Senha mínima 12 caracteres com maiúsculas, minúsculas e números, usando Rules\Password e hash do framework; sem API externa de verificação de senha.
- Sessões database; arquivo .env local mantém SESSION_ENCRYPT=true e SESSION_LIFETIME=120 minutos. HttpOnly/SameSite=Lax; produção exige HTTPS e secure cookie, a homologar na etapa 8.
- Confirmação de senha válida por 900 segundos. Login 5/min por email+IP e 30/min por IP; MFA 5/min por conta em desafio; operações sensíveis/recuperação 5/min por usuário/IP; verificação de e-mail 6/min.
- Convite 72 horas (INVITATION_HOURS), token aleatório via Str::random, somente SHA-256 persistido, consumo com bloqueio transacional e expiração. Reenvio revoga anteriores. Aceitar convite comprova posse do e-mail autorizado e o confirma; ID/e-mail/perfis não são escolhidos pelo convidado.
- Broker oficial de recuperação, validade 60 minutos e throttle 60 segundos; consumo único; respostas genéricas para contas inexistentes/inativas. Conta sem credencial aceita não pode usar reset para contornar convite expirado. Reset não autentica nem remove MFA.
- SMTP síncrono local. Convite enviado após commit; em falha de envio a conta pode permanecer pendente e há reenvio autenticado. Não foi criado módulo genérico de entrega/alertas, reservado para etapa 7.
- Estes parâmetros são escolhas de implementação de segurança delegadas pela especificação e pelo pedido da etapa, não políticas corporativas definitivas.

### MFA e inicialização

Administradores precisam de fator confirmado no cadastro E comprovado na sessão. Fluxo de configuração fica disponível com senha/e-mail confirmados, sem exigir fator inexistente; administração e início bloqueados até concluir. TOTP validado pelo Fortify; semente/códigos criptografados, ocultados na serialização, páginas no-store. O segredo não é gravado na auditoria.

Login MFA aceita TOTP ou recovery code; consumo de recovery codes serializado por lockForUpdate do usuário. Regeneração exige MFA e senha recente e invalida códigos anteriores. Não há remoção de MFA pela interface nem reset por e-mail. Perda de todos os fatores requer procedimento de recuperação validado antes da produção, sem backdoor automática.

auth:bootstrap é opt-in (AUTH_BOOTSTRAP_ENABLED=false por padrão), apenas local/testing, recusa administrador já existente e serializa criação pela linha do role. Nome/e-mail fictícios .test e senha oculta fornecida interativamente, nunca senha fixa ou argumento público. Conta só ganha acesso após verificar e-mail/MFA. Não foi criada conta humana automaticamente. Provisionamento de produção depende de procedimento autorizado da etapa 8; o comando local permanece bloqueado em production, inclusive se a flag for ativada.

### Desativação, histórico e concorrência

auth_version no usuário e no estado de sessão impede reviver sessões depois de reativar. Desativar/mudar perfis remove sessões do banco, troca remember_token, revoga reset tokens/convites e incrementa versão. Middleware web (incluindo requests Livewire) recarrega estado; middlewares persistentes também protegem componente/página. Desativar durante desafio MFA invalida login.id/login.version.

Para retirar/desativar o último administrador ativo, exigir outro ativo com senha definida e e-mail confirmado. Convites não aceitos não contam. A linha do role administrador serializa todas as concessões/remoções. Auditoria em mesma transação da alteração contém ator, alvo, instante UTC, motivo e antes/depois permitidos; sem edição/exclusão operacional e sem promessa de inviolabilidade contra DBA.

Incremental 2026_10_30_000001_create_identity_and_access_tables aplicada em chamados_dev e chamados_test. Migrações anteriores preservadas. Usuários não têm endpoint de exclusão; FKs restritivas preservam autoria. Chamados atribuídos serão revistos na etapa 5.

### Validação e limites

Testes HTTP/Livewire usam PostgreSQL dedicado com transações revertidas, sem limpeza destrutiva; identidade real current_database/current_user verificada antes de escrita e DB_URL forçado vazio. Cobrem perfis, MFA, tokens, desativação, sessões e auditoria. SMTP dos três fluxos exercitado no Mailpit com usuário fictício de teste em transação/rollback; primeira inspeção revelou dois assuntos do framework em inglês, corrigidos e reconfirmados em português. Nenhum link/token foi exposto no relatório.

UI de login conferida no navegador em desktop e 390x844, foco de Tab, tema claro/escuro e persistência. Fluxos autenticados completos foram testados por HTTP/Livewire; a conta humana inicial e confirmação com aplicativo pessoal continuam ações interativas do operador. Teste em dispositivo físico e leitor de tela completo ficam para homologação. AC01/AC02 não concluídos; AC03/AC04 têm evidências em AccessControlTest.
