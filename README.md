# Sistema de chamados de TI

Etapas 1 e 2: fundação, autenticação local e administração mínima. Laravel 13.39.0, Livewire 4.4.8, Tailwind 4.3.3, Vite 7.3.7, Fortify 1.41.0 e PostgreSQL. Não há cadastro público, integração Microsoft ou módulos de chamados/SLA implementados.

## Ambiente

Windows/PowerShell, PHP 8.4.8 e Composer 2.8.9 verificados; Node do sistema 22.17.1/npm 10.9.2 funcionam. PostgreSQL 18.6 confirmado por SQL. Dependências fixadas em composer.lock e package-lock.json. Atualizações/homologação de produção permanecem separadas. Extensões: ctype, curl, date, dom, fileinfo, filter, hash, iconv, json, mbstring, openssl, pcre, PDO/pdo_pgsql, phar, session, tokenizer, xml e xmlwriter.

## Instalar uma cópia nova

~~~powershell
Set-Location C:\laragon\www\Sistema_de_chamado
composer install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
npm.cmd ci
npm.cmd run build
~~~

Gerar APP_KEY somente em instalação nova; não regenerar a chave desta instalação em uso, pois ela protege sessões e segredos MFA. Cada empresa terá chave, banco, credenciais, arquivos e backups próprios. .env/.env.testing nunca devem ser versionados nem enviados pelo chat.

## PostgreSQL

Nesta instalação, chamados_dev/chamados_app e chamados_test/chamados_test já foram criados e migrados. Não repetir provisionamento ou helpers sobre os recursos existentes.

Para uma instalação nova, um operador autorizado pode conectar pelo psql e criar recursos dedicados, somente se os nomes não existirem:

~~~powershell
psql -h 127.0.0.1 -U postgres -d postgres -W
~~~

~~~sql
CREATE ROLE chamados_app LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE;
\password chamados_app
CREATE DATABASE chamados_dev OWNER chamados_app;
CREATE ROLE chamados_test LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE;
\password chamados_test
CREATE DATABASE chamados_test OWNER chamados_test;
~~~

Preencher DB_* de .env com chamados_dev/chamados_app e de .env.testing com chamados_test/chamados_test. Não usar postgres na aplicação. Nunca substituir PostgreSQL por SQLite.

~~~powershell
if (-not (Test-Path .env.testing)) { Copy-Item .env.testing.example .env.testing }
php artisan key:generate --env=testing
php artisan config:clear
php artisan migrate
php artisan migrate --env=testing
~~~

Gerar a chave de testes somente na configuração inicial. Migrações incrementais preservam as já aplicadas. Não executar migrate:fresh, migrate:refresh ou rollback em banco existente.

## Captura local de e-mail

Mailpit 1.32.0 já está instalado em .tools/mailpit (ignorado pelo Git). Iniciar:

~~~powershell
& '.\scripts\iniciar-mailpit.ps1'
~~~

Caixa em http://127.0.0.1:8025, SMTP em 127.0.0.1:1025. Processo em segundo plano, sem serviço global, sem encaminhamento externo. Mensagens temporárias; nunca usar dados corporativos. Se a ferramenta não estiver instalada em uma cópia nova, baixar o ZIP oficial e conferir o hash:

~~~powershell
New-Item -ItemType Directory -Path .tools/mailpit -Force | Out-Null
Invoke-WebRequest -Uri 'https://github.com/axllent/mailpit/releases/download/v1.32.0/mailpit-windows-amd64.zip' -OutFile '.tools/mailpit/mailpit-windows-amd64.zip'
$mailpitHash = (Get-FileHash '.tools/mailpit/mailpit-windows-amd64.zip' -Algorithm SHA256).Hash.ToLowerInvariant()
if ($mailpitHash -ne '16915767a6c95dca569336e8f561f91cf355e9a33c57b91deedcc10a4bba7fd4') { throw 'Hash diferente do oficial.' }
Expand-Archive '.tools/mailpit/mailpit-windows-amd64.zip' -DestinationPath '.tools/mailpit'
& '.\scripts\iniciar-mailpit.ps1'
~~~

Fonte: https://mailpit.axllent.org/docs/install/. Para encerrar, conferir no Gerenciador de Tarefas o mailpit.exe desta pasta e finalizar. O armazenamento temporário da ferramenta não é o banco de negócio da aplicação.

## Primeiro administrador

Nenhuma conta humana ou senha administrativa fixa é criada por seed/migração.

1. Iniciar Mailpit.
2. Em .env, ativar temporariamente AUTH_BOOTSTRAP_ENABLED=true.
3. Executar:

~~~powershell
php artisan config:clear
php artisan auth:bootstrap
~~~

O comando pede nome/e-mail fictícios (domínio .test) e senha oculta, duas vezes. Só roda em local/testing e recusa instalação que já tenha administrador. Não colocar senha em argumentos, código ou chat. Depois, voltar AUTH_BOOTSTRAP_ENABLED=false e executar php artisan config:clear.

Entrar em http://127.0.0.1:8000/login, confirmar e-mail pelo Mailpit, confirmar senha e configurar MFA pelo aplicativo autenticador. Administração fica bloqueada até confirmar o fator. Guardar os códigos de recuperação; recuperação por e-mail não remove MFA.

Produção não possui bootstrap público/CLI habilitado por este comando: provisionamento operacional depende de autorização e procedimento na etapa 8.

## Executar

~~~powershell
php artisan serve --host=127.0.0.1 --port=8000
~~~

Deixar terminal aberto. Raiz redireciona para login/início; /verificacao mantém a checagem técnica apenas em local/testing. Para editar assets, npm.cmd run dev em outro terminal. No Laragon, document root deve ser public/, nunca a raiz do projeto.

## Segurança e parâmetros de piloto

- Perfis e escopos: docs/permissoes.md; somente Administrador recebe Gates administrativos, sem privilégios por cargo/N1–N3.
- Sessões database, criptografadas no ambiente local, 120 minutos de inatividade; cookies HttpOnly/SameSite=Lax. Em produção HTTPS e SESSION_SECURE_COOKIE=true são obrigatórios.
- Senha: ao menos 12 caracteres, maiúsculas/minúsculas/números. Confirmação recente: AUTH_PASSWORD_TIMEOUT=900 segundos.
- Login: 5 tentativas/minuto por e-mail+IP e 30/minuto por IP; MFA: 5/minuto por conta em desafio; ações de segurança/recuperação: 5/minuto por usuário/IP. Confirmação e reenvio de e-mail: 6/minuto.
- Convites: INVITATION_HOURS=72 horas, hash do token, uso único e consumo transacional; reenvio invalida anterior. Posse do convite confirma o endereço autorizado, sem trocar usuário/e-mail pelo formulário.
- Recuperação: broker oficial, expiração 60 minutos, intervalo mínimo de 60 segundos entre mensagens para a mesma conta, uso único e revogação de sessões.
- Desativação/mudança de perfil: auth_version e revogação de sessões/links, sem apagar autoria. Reativação exige novo login.
- MFA Fortify: TOTP, semente/códigos criptografados, confirmação obrigatória, recuperação de uso único. Não há endpoint de desativação nem reset de fator por e-mail.
- Auditoria de concessões/estado, aceitação de convite, senha/MFA e login/logout sem segredos; não editável pela interface. Não promete inviolabilidade contra DBA.
- Armazenamento padrão storage/app/private, sem entrega pública automática; anexos/autorização de chamados serão implementados posteriormente.
- Sem lembrar sessão persistente na interface. Todas as novas requisições web e Livewire verificam estado e versão.

Esses valores são decisões técnicas iniciais de desenvolvimento/piloto, não políticas corporativas de produção. Retenção, recuperação de perda total de MFA e operação de contas precisam de homologação.

## Filas e agenda

~~~powershell
php artisan queue:work --tries=3 --timeout=60
php artisan schedule:list
php artisan schedule:work
~~~

Notificações de acesso são síncronas nesta etapa. Convite é enviado após o commit; falha de SMTP pode deixar conta pendente, recuperável pelo reenvio autenticado. Agenda sem tarefas de negócio, sem SLA/alertas implementados.

## Verificações e roteiro manual

~~~powershell
composer validate --strict
composer check-platform-reqs
php artisan test --testsuite=Unit,Feature,PostgreSQL
php vendor/bin/pint --test
npm.cmd run build
php artisan route:list
~~~

A suíte Feature agora exige PostgreSQL de testes migrado. Os testes que escrevem usam transações com rollback e verificam a identidade real do banco chamados_test antes de escrever. DB_URL é forçado vazio nos testes. Não há limpeza destrutiva de banco.

Roteiro de teste humano: docs/testes-etapa2.md. Evidências/resultados reais: docs/progresso.md. Se Git avisar sobre ownership, usar exceção apenas por comando: git -c safe.directory=C:/laragon/www/Sistema_de_chamado status.

## Continuidade

- docs/especificacao.md: referência original integral; não alterada.
- docs/permissoes.md: matriz de ações e escopos.
- docs/modelo-dados.md: implementado versus proposto.
- docs/decisoes-tecnicas.md: versões e decisões.
- docs/rastreabilidade.md: AC03/AC04 com evidência; AC01/AC02 permanecem pendentes.
- docs/progresso.md: estados, comandos, resultados e limitações.

Próxima etapa somente após nova solicitação: cadastros (unidades/setores/equipes/catálogos). Nenhum push, publicação, Microsoft ou alteração de rede corporativa foi realizado.
