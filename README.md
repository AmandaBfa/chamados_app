# Sistema de chamados de TI

Etapas 1–6: fundação, autenticação local, administração, cadastros, portal pessoal, atendimento do TI e SLA de piloto. Laravel 13.39.0, Livewire 4.4.8, Tailwind 4.3.3, Vite 7.3.7, Fortify 1.41.0 e PostgreSQL.

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

Notificações de acesso são síncronas. Convite é enviado após o commit. Em caso de falha de SMTP, o reenvio autenticado permite recuperar a conta pendente. A agenda executa `sla:processar` a cada minuto para registrar violações e fechar chamados elegíveis. Alertas pertencem à Etapa 7.

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

Roteiros de teste humano: docs/testes-etapa2.md, docs/testes-etapa3.md e docs/testes-etapa4.md. Evidências/resultados reais: docs/progresso.md. Regressão final da etapa 4 em 03/11/2026: 73 testes/964 assertions aprovados. Se Git avisar sobre ownership, usar exceção apenas por comando: git -c safe.directory=C:/laragon/www/Sistema_de_chamado status.

## Continuidade

- docs/especificacao.md: referência original integral; não alterada.
- docs/permissoes.md: matriz de ações e escopos.
- docs/modelo-dados.md: implementado versus proposto.
- docs/decisoes-tecnicas.md: versões e decisões.
- docs/rastreabilidade.md: AC03/AC04 com evidência; AC01/AC02 permanecem pendentes.
- docs/progresso.md: estados, comandos, resultados e limitações.

Próxima etapa somente após nova solicitação: atendimento do TI (fila, atribuição, notas internas e transições). Na etapa 4 não houve push, publicação, Microsoft ou alteração de rede corporativa. O envio da etapa 3 ao GitHub foi autorizado separadamente e permanece no histórico.

## Cadastros e organização — etapa 3

Após migrar uma cópia nova, importar o catálogo:

~~~powershell
php artisan db:seed --class=InitialCatalogSeeder --no-interaction
~~~

Nesta instalação local a migração já foi aplicada e o seed já foi executado duas vezes. São 5 equipes (quatro iniciais e Desenvolvimento restrito a encaminhamentos específicos), 11 categorias, 41 subcategorias e 16 serviços (dez nominados e seis exemplos configuráveis de infraestrutura). Nenhuma pessoa, unidade ou setor real é criado.

Entrar com administrador, MFA confirmado e senha recente; acessar **Cadastros e organização** no início. Páginas em /administracao/cadastros/units, departments, teams, team_members, categories, subcategories, services, equipment_references e users. A administração de acesso continua em /administracao/usuarios; organização/perfis são separados.

Cadastrar unidade/cidade e setor; vincular usuários em Organização dos usuários. Cadastrar equipe, depois membros, e só então escolher referência/substituto. Serviços aceitam equipe padrão, referência membro da equipe, categoria/subcategoria, fornecedor e criticidade proposta. Cobertura de triagem e alternativa para críticos são indicações manuais por equipe, sem escala ou automação. Criticidade não define P1; Solus inicia sem classificação.

O seed usa seed_key estável: repetição não atualiza registros existentes, nomes alterados, inativação ou personalizações. Conflito com nome de cadastro manual aborta toda a transação; resolver o conflito explicitamente antes de repetir, sem adotar/sobrescrever registros manuais. Não apagar nem alterar seed_key diretamente. Não há exclusão operacional; inativação preserva referências. Alterações exigem justificativa e geram auditoria; edição desatualizada exige reabrir o registro.

Os valores são dados da instalação separada de cada empresa. Não há condição fixa por nome de empresa/serviço, concessão automática por equipe/N1–N3, algoritmo de SLA ou encaminhamento operacional antecipado. Unidades, cidades, setores, pessoas e referências devem ser preenchidos pelo operador com dados fictícios no piloto; dados corporativos exigem autorização posterior.

## Portal pessoal — etapa 4

Migração incremental 2026_11_03_000002 aplicada nesta instalação local e em testes, sem recriar bancos. Em cópia nova, usar os comandos de migração já descritos; não executar fresh/refresh/rollback no banco existente.

Entrar com conta autorizada, e-mail confirmado e MFA se administrador; no início, escolher **Abrir chamado** ou **Meus chamados**. Rotas: /chamados/abrir, /meus-chamados e /meus-chamados/{id}. Todos os sete perfis podem relatar problemas próprios, conforme decisão explícita do usuário; administrador também vê somente seus chamados neste portal. Chamados/anexos alheios retornam 404. Atendimento técnico é etapa futura, sem acesso operacional implícito por equipe ou perfil.

Abertura usa solicitante e contexto de unidade/setor do cadastro, categoria/subcategoria dependentes, serviço/equipamento opcionais, descrição, abrangência/processo afetado, condição de trabalho e início aproximado ou desconhecido. Confirmação mostra protocolo único; o mesmo envio é idempotente inclusive sob concorrência. Prioridade começa nula, sem escolha P1/P2 ou prazos calculados. Detalhe oferece conversa/histórico públicos e downloads privados; notas internas não integram as consultas/respostas do portal.

Limites em config/tickets.php: até cinco anexos por abertura/comentário, 10 MB cada, PNG/JPEG/PDF simples/TXT UTF-8. Upload temporário autenticado, vinculado ao usuário, válido por 24 h e de uso único; conteúdo é revalidado ao salvar. Arquivos em storage/app/private/tickets, sem storage:link ou URLs públicas; download autorizado e forçado como arquivo. PDF com indicadores ativos, formulários, criptografia ou objetos comprimidos é recusado conservadoramente. **Não há scanner antivírus nem sanitizador completo de PDF**; análise antimalware, retenção, reconciliação de órfãos e backups de anexos dependem de homologação antes da produção.

Os testes de upload usam discos fictícios separados por processo; testes de escrita usam chamados_test com rollback. O ensaio de concorrência com dois processos precisa de fixtures temporariamente gravadas com commit nesse banco para serem compartilhadas; finally remove exclusivamente os IDs fictícios criados pelo ensaio. Não rodar o worker manualmente nem apontá-lo a outro banco. Não há envio de e-mail na abertura/comentário, exportações, atendimento/SLA ou automações de negócio nesta etapa.

Validação funcional por HTTP, Livewire e PostgreSQL. A revalidação de 09/11/2026 aprovou 73 testes e 967 asserções. Teclado, tela reduzida, celular físico e leitor de tela ainda precisam ser verificados pelo roteiro em docs/testes-etapa4.md. A etapa está concluída para desenvolvimento e piloto, com homologação visual e autorização de produção pendentes.

## Atendimento do TI

A fila fica em **Início → Fila do TI** para perfis operacionais habilitados. Consulte a [tabela de transições e o roteiro da etapa 5](docs/testes-etapa5.md). A definição corporativa de concessão a chamados restritos segue pendente com a DPO, o gerente e o analista de rede.

## SLA de piloto

Em uma instalação local isolada, aplique as migrações incrementais pelo procedimento acima. Em **Início → Calendário e políticas de SLA**, um administrador propõe a versão e a gerência valida. O detalhe do chamado no TI calcula os relógios no momento da consulta. Rode `php artisan schedule:work` no ambiente local ou execute `php artisan sla:processar` para processar imediatamente as violações e os fechamentos elegíveis. Consulte o [roteiro de testes da Etapa 6](docs/testes-etapa6.md).
