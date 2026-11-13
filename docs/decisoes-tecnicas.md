# Decisões técnicas — etapa 1

**Revalidação da etapa 4 em 09/11/2026.** A abertura exige data aproximada para todos os valores booleanos aceitos pelo servidor que representem início conhecido. O caso textual `0` é exercitado via Livewire. A seção 7 da especificação prevê sinalização imediata de suspeita crítica. O portal registra abrangência, processo e condição de trabalho. A sinalização operacional depende de critérios e rota de triagem definidos.

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

## Etapa 3 — cadastros e organização (03/11/2026)

### Requisitos implementados

Unidades/cidades, setores, equipes/membros, categorias/subcategorias, serviços, referência simples de equipamento e organização dos usuários. Nenhuma nova dependência. Mantidos instalação/banco por empresa, tecnologias e migrações anteriores. Sem alterar especificacao.md, cujo SHA-256 permanece igual ao original.

CatalogDefinition mantém uma lista fechada de tabelas/campos. CatalogService autoriza `catalogs.manage` em toda alteração, exige senha recente e valida dados no servidor; o Gate usa o mesmo requisito explícito de Administrador ativo/verificado com MFA comprovado e versão de sessão vigente. CatalogPanel autoriza também buscas/renderização, edição e novo formulário; kind/selectedId/version são Locked. Rotas exigem autenticação, verificação, MFA, Gate e senha recente. Não existem exportações/anexos nesta etapa.

Organização dos usuários só permite unidade, setor, cargo e classificação interna. Não aceita alterar perfis, estado da conta, credenciais ou auth_version; os fluxos da etapa 2 continuam responsáveis por acesso. Membro de equipe, referência/substituto ou cargo N1/N2/N3 não concede permissão. Relações Eloquent teams()/members() incluem vínculos históricos: futuros consumidores deverão considerar estado do usuário/equipe/pivot e autorização específica, nunca derivar acesso de mera existência do vínculo.

### Propostas técnicas de desenvolvimento/piloto

- Setores são cadastros independentes de unidade, pois não foi definida exclusividade entre esses conceitos. Unidade guarda nome e cidade textual; sem geocodificação ou inventário patrimonial.
- Unidades/setores dos usuários existentes permanecem nulos até preenchimento administrativo. Não inventar cidades, pessoas ou 90 colaboradores. Usuário tem identificador interno estável da etapa 2.
- Equipes armazenam referência, substituto, cobertura manual de triagem, alternativa para críticos e indicação de encaminhamentos específicos. Quatro equipes iniciais mais Desenvolvimento; Redes inicia como alternativa para críticos, sem pessoa inventada. Não há escala, atribuição automática ou concessão de leitura de chamados.
- Referência e substituto devem ser membros ativos distintos da própria equipe; referência de serviço deve integrar a equipe padrão. Trocar equipe limpa referência no formulário. Remover/inativar/mover membro usado como referência exige substituir primeiro as referências, inclusive em serviços inativos, preservando a integridade dos vínculos.
- Inativação conserva relações. Novos vínculos exigem itens ativos e, para subcategoria, categoria ativa. Reativação também revalida pais. Vínculo inativo já existente continua disponível somente no formulário do registro que o utiliza; não é oferecido para outros registros. Desativar usuário não apaga membresia ou autoria; futuros atendimentos precisam revisar atribuições conforme a especificação.
- Subcategoria usada em serviço não pode mudar de categoria; criar outra para preservar referências. FK composta em services assegura categoria/subcategoria inclusive contra SQL direto. Campos nullable permitem serviço não classificado. Solus inicia sem criticidade; os valores baixa/normal/alta/critica são propostas descritivas de piloto, sem cálculo/alteração de prioridade ou SLA.
- Fornecedor é referência textual por serviço, suficiente nesta fase; protocolos e esperas ficam para atendimento. Seis exemplos configuráveis de infraestrutura: Internet, Wi-Fi, Rede cabeada, VPN, Pastas de rede e Impressão. Equipe/referência/categoria dos serviços ficam sem preenchimento automático para não inventar roteamento.
- Auditoria na mesma transação, com ator, catálogo/ID, motivo e antes/depois limitados aos campos permitidos. Sem senhas, tokens ou segredos. Não há exclusão operacional.
- version e organization_version rejeitam edição desatualizada com erro visível. Advisory lock transacional PostgreSQL 73003 serializa as alterações administrativas dos catálogos e seed; locks de linha protegem registros/relações. Essa escolha privilegia simplicidade no porte previsto; não é evidência de AC15 para futuros chamados nem benchmark de carga.

### Regra do seed

InitialCatalogSeeder importa os nomes da seção 8 sem normalizar capitalização. Chaves seed_key fixas e únicas identificam registros importados. Encontrada a chave, o seed não altera nenhum campo, não reativa nem recria nome personalizado. Nome manual conflitante sem a chave interrompe toda a transação; não adota/sobrescreve cadastro manual. Exclusão direta/alteração de seed_key não é operação suportada. DatabaseSeeder chama esse seed e não cria contas.

### Fontes, verificações e pendências

APIs conferidas no código instalado e em documentação oficial: [Laravel 13 — migrações](https://laravel.com/framework/docs/13.x/migrations), [Livewire 4 — Locked](https://livewire.laravel.com/docs/4.x/attribute-locked) e [PostgreSQL 18 — SQLSTATE](https://www.postgresql.org/docs/18/errcodes-appendix.html). A URL inicial /docs/4.x/locked redirecionou a Quickstart; a referência Locked foi aberta pelo link oficial. PostgreSQL pode retornar 23001 (restrict_violation) ao proteger exclusão, além de 23503 (foreign_key_violation); teste ajustado após observar a restrição efetiva, sem retirar a proteção.

Regressão final: 48 testes/572 assertions aprovados. Migração incremental aplicada nos dois bancos locais, seed repetido em chamados_dev sem duplicação; build/Pint/cache de views aprovados. Evidências em progresso.md. Roteiro humano em testes-etapa3.md. Criticidade final, pessoas de referência, cobertura por período e políticas corporativas seguem pendentes; nenhuma política de acesso/SLA/indicador nova foi inferida. O fluxo de chamados deverá implementar autorização própria na etapa correspondente, incluindo encaminhamento específico e sensíveis.

## Etapa 4 — portal pessoal e abertura (03/11/2026)

### Decisão de acesso confirmada pelo usuário

Perguntado se o portal pessoal seria exclusivo do perfil Colaborador ou disponível também aos demais perfis, o usuário escolheu explicitamente: **todos os usuários internos ativos, incluindo TI e administrador**. Esta decisão vale somente para abrir/consultar/comentar/anexar em chamados próprios, mantendo conta autorizada com credencial local, e-mail confirmado, sessão vigente e MFA administrativo. Não cria leitura global por cargo, equipe, N1/N2/N3 ou perfil Administrador. A especificação original não foi editada; a decisão adicional fica registrada aqui e em permissoes.md.

PortalAccess aplica essas condições em toda consulta/operação, inclusive renderização/busca, ações Livewire e downloads. IDs de componente e chaves de envio são Locked. Solicitante/autor/contexto vêm da sessão/cadastro recarregado, não de campos livres do cliente. Recurso inexistente/alheio retorna 404. Políticas de leitura operacional/sensíveis do TI permanecem para a etapa 5, sem herdar concessão deste portal.

### Dados, validação e consistência

RequesterTickets concentra abertura e comentário público. Formulário em uma coluna, descrição orientada, subcategoria dependente, serviço/equipamento opcionais e início aproximado ou desconhecido. Serviço com categoria/subcategoria configurada deve ser compatível; itens inativos não são oferecidos/aceitos em novas seleções. Itens usados continuam legíveis; alterar o pai de subcategoria usada em tickets é rejeitado no cadastro administrativo. Abertura guarda snapshot de unidade/setor/cidade; ausências do cadastro são explícitas, sem inventar dados ou bloqueio adicional.

Configuração de piloto em config/tickets.php: assunto 160, descrição/comentário 10.000 caracteres, processo afetado 500; até cinco anexos por envio, 10 MB cada. A proposta de limite é aplicada por abertura/comentário, não constitui política corporativa de retenção. Data local interpretada no fuso configurado, segundos zerados e persistência UTC; futuro é rejeitado. Impacto e condição preservam fatos relatados; não classificam P1/P2 nem calculam SLA. Chamado nasce incidente/novo, prioridade nula, sem prazo ou equipe/responsável simulados.

Chave UUID por instância de formulário, UNIQUE(requester_id,idempotency_key), lock transacional de catálogos e usuário bloqueado protegem abertura concorrente. Protocolo CH-ULID com restrição UNIQUE; colisão não sobrescreve registro. Reenvio com a mesma chave retorna o chamado original mesmo após consumo dos uploads, sem repetir anexos/evento. Nova instância do formulário representa nova solicitação; não há deduplicação por textos parecidos. Comentário tem chave própria e serialização pelo ticket. Eventos simples com ator/instante/visibilidade, sem arquitetura de event sourcing; preparação conceitual para adicionar ciclos de SLA por migrações futuras, sem simular ciclos/relógios agora.

Leituras do detalhe projetam DTO com campos explicitamente permitidos. Comentários e eventos internos não são carregados; JSONB de detalhes técnicos nunca é projetado. Anexos exigem visibilidade pública própria e, quando associados a comentário, comentário público do mesmo ticket. A FK composta ticket/comment impede associação cruzada. Ações públicas sempre gravam visibility=public; não existe controle livre de visibilidade no navegador. Não há envio de e-mail ou exportação nesta etapa.

### Arquivos privados

Upload temporário Livewire exige auth/verified/MFA administrativo e limite de frequência. Controller do framework é substituído via container por PortalTemporaryUploadController, preservando assinatura de URL e armazenamento do pacote; ações de início/finalização/remoção também autorizam no servidor. Recibo de hash canônico comprova dono, validade de 24 h e uso único. Não basta possuir ID, nome ou referência assinada de outro usuário. Discos de teste são isolados por processo; produção continua no disco local privado.

Validação no endpoint temporário e novamente no salvamento: quantidade/tamanho, bytes reais, MIME detectado por finfo, extensão compatível, dimensões de imagem válidas e TXT UTF-8 sem controles binários. PDF precisa de cabeçalho/final reconhecíveis; filtro conservador rejeita indicadores de ações/script, anexos embutidos, formulários, objetos comprimidos e criptografia, inclusive nomes PDF escapados com #hex. Esse filtro **não é scanner antivírus, sanitizador ou parser completo de PDF**; arquivos complexos podem ser recusados e não há garantia antimalware. Avaliar mecanismo de análise e procedimento operacional na homologação antes de dados reais. Não habilitar formatos ativos sem decisão específica de segurança.

Arquivos finais usam chave aleatória em storage/app/private/tickets, nome de download ASCII sanitizado e MIME/tamanho/SHA-256 persistidos. Somente rota autenticada verifica solicitante, ticket, anexo e visibilidade; download forçado como application/octet-stream, nosniff e sandbox, sem HTML inline. Pré-visualização temporária Livewire foi desabilitada; nenhum caminho público ou storage:link foi criado. Em falha, metadados/recibos revertem e cópias privadas recém-escritas são removidas. Queda abrupta pode deixar arquivo órfão; reconciliação/backup/retenção de anexos e recibos dependem de homologação, não foram tratados como políticas corporativas aprovadas. Temporários seguem limpeza de 24 h do pacote; recibos expirados são inutilizáveis, sem rotina de descarte de banco inventada nesta etapa.

### Evidências e limites

Regressão final: **73 testes/964 assertions**, incluindo RequesterPortalTest e TicketConcurrencyTest (dois processos independentes, uma abertura/um evento). Ensaios de arquivos usam bytes reais, não apenas MIME declarado no fake. Testes negam chamados/anexos alheios em GET, ações Livewire, busca e serialização; testam nota interna/arquivo vinculado a nota interna, autor adulterado, inativação, idempotência, rollback de arquivo, revalidação final e sessão revogada. Nenhuma senha administrativa fixa, mensagem real, dependência nova, publicação ou push nesta etapa.

Fontes oficiais consultadas: [Livewire 4 — uploads](https://livewire.laravel.com/docs/4.x/uploads), [Livewire 4 — segurança](https://livewire.laravel.com/docs/4.x/security), [PostgreSQL 18 — locks](https://www.postgresql.org/docs/18/explicit-locking.html) e [Laravel 13 — filesystem/downloads](https://laravel.com/framework/docs/13.x/filesystem#downloading-files). Código instalado de FileUploadController, TemporaryUploadedFile, FileUploadConfiguration, WithFileUploads e FilesystemAdapter também foi inspecionado. A primeira consulta Laravel expirou por timeout; a API instalada foi conferida e a nova consulta à página oficial foi bem-sucedida. Resultados completos das verificações em progresso.md.

Inspeção visual no Edge não executada: autorização da ferramenta de controle expirou antes de controlar a janela. Roteiro humano em testes-etapa4.md, com celular/teclado/leitor de tela ainda a homologar; AC17 permanece parcial. Interface técnica, notificações, anexar por colagem/arraste, exportações, prioridade calculada, SLA e transições ficam fora desta etapa.

## Etapa 5 — decisões de piloto

- Fila comum de suporte, analistas e gestão conforme proposta da especificação. Escrita de analistas por equipe ativa ou atribuição principal, com colaboração explícita para mensagens e esperas. Desenvolvedor por atribuição específica. Aprendiz auxilia no escopo e resolução exige outro perfil operacional autorizado. Cargo e N1/N2/N3 não são usados para autorização.
- Salvaguarda provisória: categoria inicial Segurança da informação identificada pela chave estável do catálogo, inclusive em chamados legados. A classificação nessa categoria marca a restrição e o vínculo de nova abertura herda a restrição quando aplicável. O solicitante mantém acesso público ao próprio chamado. A fila, busca, detalhe, ações e download técnicos exigem concessão explícita em chamados restritos. Segundo a usuária, a regra corporativa depende de DPO, gerente e analista de rede. Interface e procedimento de concessão permanecem pendentes dessa validação. A tabela foi exercitada por fixture.
- Primeira resposta útil tem confirmação explícita do técnico junto ao texto público. Esperas usam eventos de entrada e saída do estado, com ciclo, motivo quando obrigatório e referência ao comentário público ou protocolo de terceiro. A exceção gerencial de espera P1 é proposta de piloto. Política oficial, relógios e fechamento por calendário seguem na etapa 6.
- Ações bloqueiam a linha do chamado e conferem a versão da tela. Comentários do portal usam o mesmo bloqueio, com idempotência por envio. A redistribuição é sinalizada na fila quando o responsável está inativo e o chamado segue pendente. A desativação é auditada no módulo de acesso e a responsabilidade anterior permanece até transferência.
- Histórico operacional consultável sem edição/exclusão pela interface. Resumos públicos e comentários públicos são projeções separadas dos dados técnicos. A tabela de transições está em testes-etapa5.md.

## Etapa 6 — SLA de desenvolvimento e piloto

- A migração vincula os chamados anteriores à versão inicial. O motor reconstrói os relógios a partir do histórico. A primeira prioridade validada aplica seu orçamento desde a abertura e, se vencida, grava o vencimento original. A gravação também indica o instante de detecção.
- O calendário inicial cobre segunda a sexta, das 08h às 17h, em America/Sao_Paulo. Exceções substituem as janelas da data local. Feriados da instalação são cadastrados pela administração. Metas em minutos úteis de resposta e solução seguem a especificação.
- A triagem avalia impacto e urgência para ver a sugestão da matriz. A prioridade final é selecionada pelo técnico com justificativa em toda mudança. Alterações posteriores aplicam o orçamento novo ao saldo de tempo útil e preservam violações. A conclusão no segundo exato do vencimento cumpre a meta. Com saldo negativo no instante da alteração, a violação é imediata.
- Somente espera pelo solicitante pausa solução. Ao alterar para P1 durante essa espera, o relógio retoma, salvo exceção explícita da gerência com justificativa. A espera por fornecedor consome cobertura. Reabertura preserva o tempo útil e violações da solução, com ciclo novo. A primeira resposta original continua no ciclo 1.
- O fechamento é elegível ao atingir 27 horas de cobertura desde a resolução. Enquanto houver contestação pendente, a rotina mantém o chamado resolvido. Após recusa, volta a valer o prazo original. Se o prazo já passou, o próximo processamento fecha. O evento de fechamento identifica o sistema.
- Administração propõe e gerência valida sob senha recente, com MFA para administrador. Versões aprovadas são preservadas pelo fluxo da aplicação e valem para novas aberturas. Chamados já vinculados mantêm a versão nas transferências, reclassificações e reaberturas.
- O detalhe calcula sob demanda. A tarefa periódica persiste violações e efetua fechamentos. Alertas e indicadores ficam para a Etapa 7. Referências consultadas: [migrações Laravel](https://laravel.com/docs/13.x/migrations) e [agendamento Laravel](https://laravel.com/docs/13.x/scheduling).
