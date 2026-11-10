# Modelo de dados proposto

Estado: etapas 1–4 implementadas e etapa 5 parcialmente implementada conforme detalhamento abaixo. Relógios de SLA e alertas continuam propostas técnicas e não constituem política corporativa aprovada. A especificação original permanece em especificacao.md. Uma instalação/banco por empresa; não haverá tenant_id nem autorização transversal.

## Fundação implementada

Fundação: cache, cache_locks, jobs, job_batches e failed_jobs. Etapa 2 acrescentou identidade/acesso; etapa 3, organização/catálogos; etapa 4, chamados, comentários, anexos e eventos. Sessões locais usam banco. Não há tabelas/cálculo de SLA implementados.

## Entidades, relações e restrições planejadas

| Grupo / etapa | Entidades e relações | Restrições e índices propostos |
|---|---|---|
| Instalação / 3 | installation_settings, units, departments; usuários pertencem à unidade e setor | Configurações por instalação; inativação de cadastros utilizados; fuso configurável |
| Identidade / 2 | users (id interno, nome, contato, active, unidade/setor, cargo e nível opcionais); login_identities N:1 users; hash local em users.password para guard/broker/Fortify | Identidade externa UNIQUE(provider, organization_id, subject); campos externos obrigatórios para provedor externo; e-mail normalizado de contato não vincula identidades automaticamente; hash local no usuário, sem associação automática de identidade externa |
| Acesso / 2 | roles e role_user implementados; abilities administrativas fixas em Gates; permissions/role_permissions configuráveis ficam como evolução; teams e team_members na etapa 3 | UNIQUE nos pares das tabelas de vínculo; concessão administrativa explícita; cargo, N1/N2/N3 e equipe não concedem privilégios; concessões sensíveis separadas |
| Segurança / 2 | invitations, password_reset_tokens, sessions, MFA e recovery codes | Tokens com hash, expiração e consumed_at; MFA e códigos protegidos; revogação de sessões e checagem active em cada requisição; duração/limites serão decididos na etapa 2 |
| Catálogo / 3 | categories e subcategories N:1; services, equipment_references; services vinculam equipe/fornecedor opcionais | FK composta categoria/subcategoria no chamado ou validação transacional equivalente; inativar sem apagar históricos. Preservar integralmente catálogo da seção 8, inclusive Mosia, Solus, eSolution, Microsoft 365, Adobe, Zoiper e infraestrutura |
| Chamados / 4–5 | tickets com requester_id interno, contexto da abertura, tipo incidente/solicitação, protocolo, estado, prioridade, impacto/urgência, equipe/responsável, serviço/equipamento, início aproximado e lock_version; ticket_participants e ticket_links | Protocolo UNIQUE; chave de idempotência UNIQUE por solicitante; FK para usuários sem cascata de exclusão; CHECK estados/prioridades válidos; índice em estado/equipe/responsável/serviço/created_at conforme consultas |
| Conversa / 4–5 | comments N:1 ticket; attachments vinculados ao chamado e opcionalmente comentário; autor interno | Visibilidade pública/interna explícita e autorização no servidor; chave aleatória privada UNIQUE; tamanho/tipo/nome original e autor registrados; sem URLs públicas permanentes |
| Histórico / 5 | ticket_events, assignments, priority_changes, supplier_waits, resolution_records | Evento com ator, instante UTC, motivo e antes/depois; snapshots de setor/unidade da abertura; histórico sem edição operacional; fornecedores com protocolo/início/retorno; não reiniciar relógios nas transferências |
| Calendário / 6 | calendars, calendar_versions, weekly_windows, calendar_exceptions | Versões imutáveis após uso; janelas válidas e sem sobreposição; feriados/exceções vinculados à versão; America/Sao_Paulo inicial, instantes UTC |
| SLA / 6 | sla_policies, sla_policy_versions, sla_targets; ticket_cycles N:1 ticket; sla_clocks N:1 cycle; sla_pauses, deadline_revisions, sla_breaches | UNIQUE(cycle_id, clock_type); resposta e solução separados; referências à versão exata de calendário/política; pausas com motivo e comentário público; violações preservadas; índices nos vencimentos ativos |
| Alertas / 7 | notification_events, notification_deliveries, escalation_events | UNIQUE(ticket, cycle, clock, milestone, recipient, channel); tentativas/falhas/entrega registradas; persistência/outbox transacional a avaliar; canal sem conteúdo sensível |
| Auditoria / 2–8 | audit_events com ator, ação, objeto, antes/depois sanitizados e instante | Sem senhas/tokens/conteúdo desnecessário; sem edição/exclusão pela interface; acesso mínimo no banco; retenção controlada futura, sem promessa de inviolabilidade contra DBA |
| Indicadores / 7 | Consultas de eventos/ciclos; snapshots de estoque se necessários | Mesmas políticas de leitura; denominadores/filtros/unidades explícitos; detalhamento reconciliável; tempo total, útil e pausado separados |

## Transações e concorrência

Alterar estado, atribuição, prioridade e ciclo/relógios em uma transação com evento correspondente. Usar lock_version ou bloqueio de linha e rejeitar conflito visivelmente. Reabertura cria ciclo novo referenciando anterior; não sobrescrever resultados. Restrições NOT NULL, FK e CHECK devem acompanhar cada migração funcional. Exclusões de usuários, catálogo e políticas referenciadas usam RESTRICT; retenção será processo separado. Índices finais dependem de EXPLAIN em dados fictícios representativos.

## Pendências preservadas

Matriz de impacto/urgência, fechamento em 27 horas de cobertura, saldo ao reabrir, pausa excepcional P1, limites/retensão de anexos e limiares adicionais de alerta continuam propostas. Metas P1–P4 são aprovadas para desenvolvimento/piloto, sujeitas à aprovação corporativa. SLA: P1 15 min/4h; P2 1h/9h; P3 4h/27h; P4 9h/45h úteis. Segunda a sexta 08–17 sem almoço coletivo; fornecedor não pausa inicialmente. Os algoritmos serão implementados na etapa 6, não aqui.

Ciclo de convite, credencial local, parâmetros de sessão/tokens e administrador/MFA foram detalhados na etapa 2 em decisoes-tecnicas.md e permissoes.md. Microsoft Entra ID permanece evolução futura sem credenciais ou integração nesta etapa.

## Estrutura efetivamente implementada na etapa 2

Migração incremental 2026_10_30_000001_create_identity_and_access_tables, sem modificar as migrações aplicadas da fundação:

| Tabela | Campos/relações e garantias atuais |
|---|---|
| users | ID interno, nome, e-mail UNIQUE com CHECK de normalização, password hash nullable até aceitar convite, email_verified_at, active, local_login_enabled, auth_version, cargo/classificação opcionais, remember_token e campos MFA criptografados |
| roles | Catálogo de sete perfis com name UNIQUE e label em português; sem privilégio derivado de cargo/classificação |
| role_user | FKs restritivas para users/roles, PK composta; múltiplos perfis explícitos |
| login_identities | FK para users e UNIQUE(provider, organization_id, subject), todos identificadores não nulos; reserva estrutural sem endpoint/integração externa |
| invitations | FK usuário/criador, token_hash SHA-256 UNIQUE, expires_at, consumed_at; consumo transacional e reenvio revogando anterior |
| password_reset_tokens | E-mail como chave do broker local, hash do token e criação; não é ID de usuário de negócio nem chave de identidade externa |
| sessions | ID do framework, FK opcional usuário, IP/user-agent/payload e last_activity; revogação física mais versão lógica no usuário |
| audit_events | FKs ator/alvo, ação indexada, JSONB com whitelist de metadados e instante UTC indexado; sem API de edição/exclusão |

Escolha técnica: credencial local em users.password em lugar de local_credentials evita provider de autenticação próprio. Não altera a separação de usuário/identidade externa. A matriz em permissoes.md documenta Gates atuais; não há tabela de permissões editáveis ou permissão universal de leitura de chamados.

Convite confirma e-mail na aceitação por posse do token enviado ao endereço autorizado. Cadastro inicial local por console usa confirmação assinada separada. Reset usa broker oficial e incrementa auth_version. Sessões antigas não retornam após reativação. Unidade/setor/equipes ficam para etapa 3, sem tabelas duplicadas. Demais tabelas acima são o planejamento das próximas etapas, não funcionalidades já migradas.

## Estrutura efetivamente implementada na etapa 3

Migração incremental 2026_11_03_000001_create_organization_and_catalog_tables aplicada em chamados_dev e chamados_test. Seções anteriores registram também o estado histórico das etapas 1/2; a tabela abaixo define o estado atual dos cadastros. Não há tenant_id, chamados, SLA ou aprovações.

| Tabela | Campos/relações e restrições |
|---|---|
| units | id, name UNIQUE, city obrigatório; active, version, seed_key nullable UNIQUE e timestamps |
| departments | id, name UNIQUE; active, version, seed_key nullable UNIQUE e timestamps; independente de unidade |
| teams | id, name UNIQUE; reference_user_id/substitute_user_id FKs restritivas users; specific_assignments_only, triage_coverage, critical_alternative; active/version/seed_key/timestamps |
| team_members | id; FKs restritivas team_id/user_id; UNIQUE(team_id,user_id); active/version/timestamps; sem função/perfil de acesso |
| categories | id, name UNIQUE, active/version/seed_key/timestamps |
| subcategories | id, category_id NOT NULL FK restritiva; UNIQUE(category_id,name), UNIQUE(category_id,id) para FK composta; active/version/seed_key/timestamps |
| services | id, name UNIQUE; category_id FK restritiva opcional, subcategory_id opcional com FK composta (category_id,subcategory_id) e CHECK exigindo categoria quando houver subcategoria; team_id/reference_user_id FKs restritivas opcionais; supplier textual; proposed_criticality nullable com CHECK baixa/normal/alta/critica; active/version/seed_key/timestamps |
| equipment_references | id, name UNIQUE, identifier opcional, unit_id FK restritiva opcional; active/version/seed_key/timestamps; somente referência simples |
| users (extensão) | unit_id e department_id FKs restritivas opcionais; organization_version; reutiliza job_title/support_level já existentes, sem mudar significado de roles/auth_version |

Índices atuais: nomes únicos (subcategoria única dentro da categoria), seed_key único por tabela importável, active nos catálogos/membros, UNIQUE de membresia e índice (user_id,active). O índice composto (category_id,id) suporta integridade categoria/subcategoria. Não se alegou otimização por EXPLAIN/carga; índices operacionais de chamados serão definidos nas etapas futuras.

Regras transacionais complementares: referência/substituto deve pertencer à equipe; serviço só referencia membro da equipe padrão; impedir inativação/movimentação de membro referenciado; revalidar pais na reativação; registros inativos não entram em novos vínculos; subcategoria utilizada não é movida de pai. Esses controles usam CatalogService e serialização de catálogo, além das FKs/CHECKs do banco. Sem exclusão pela interface e sem alteração das migrações aplicadas.

Dados persistidos no desenvolvimento após seed repetido: 5 equipes, 11 categorias, 41 subcategorias, 16 serviços. Unidades, setores e membros permanecem vazios, para preenchimento fictício autorizado pelo operador. Serviços iniciam sem roteamento, pessoa ou criticidade inventados. Demais configurações da instalação seguem em config/installation.php/.env e nos próprios dados dos catálogos; installation_settings não foi necessária nesta etapa e permanece apenas proposta, sem tabela criada.

## Estrutura efetivamente implementada na etapa 4

Migração incremental 2026_11_03_000002_create_requester_portal_tables aplicada nos dois bancos locais; preservadas todas as migrações anteriores. Sem seed de chamados, contas ou arquivos reais. Os estados anteriores descritos neste documento permanecem como histórico.

| Tabela | Relações e garantias |
|---|---|
| tickets | ID interno, protocol UNIQUE, requester_id FK restritiva users; idempotency_key UUID com UNIQUE(requester_id,idempotency_key); unit_id/department_id FKs opcionais e opening_context JSONB com nomes/cidade da abertura; type CHECK incidente/solicitacao (portal só cria incidente); subject/description; categoria obrigatória e FK composta (category_id,subcategory_id); serviço/equipamento opcionais com FKs restritivas; impact_scope, affected_process, work_condition; started_unknown/started_at com CHECK coerente; status CHECK, priority nullable CHECK P1–P4, lock_version, timestamps UTC |
| comments | FKs ticket/author restritivas; body, visibility CHECK public/internal; chave UUID e UNIQUE(ticket_id,author_id,idempotency_key); UNIQUE(ticket_id,id) para referência composta; timestamps UTC |
| attachments | FKs ticket/uploader restritivas; comment_id opcional com FK composta (ticket_id,comment_id), impedindo comentário de outro chamado; visibility public/internal; storage_key UNIQUE aleatória e privada; download_name seguro, MIME detectado, size, sha256 e timestamps. Caminho não é enviado ao cliente |
| ticket_events | FKs ticket/actor restritivas; event_type, visibility public/internal, public_summary opcional, details JSONB e occurred_at UTC; sem API de alteração/exclusão |
| temporary_upload_receipts | path_hash SHA-256 como PK (nome temporário canônico), FK usuário, expires_at indexado e consumed_at; comprova propriedade/validade/uso único do upload temporário, sem guardar conteúdo ou nome original |

Índices: tickets(requester_id,created_at,id), tickets(requester_id,status); comments(ticket_id,visibility,id); attachments(ticket_id,visibility,comment_id); ticket_events(ticket_id,visibility,id), além de PKs/UNIQUEs/FKs compostas. Não houve benchmark ou alegação de otimização por EXPLAIN.

Criação serializada pelo lock transacional de catálogos 73003 e pelo usuário bloqueado; a chave única é defesa adicional contra duplo envio. Comentários serializam pela linha do chamado. Ticket/evento/anexos/consumo de recibos são gravados na mesma transação; falha remove cópias privadas recém-escritas e reverte metadados. Arquivos e PostgreSQL não formam uma transação distribuída: queda abrupta do processo pode deixar órfãos privados, exigindo procedimento de reconciliação antes de produção; não foi inventada rotina automática de descarte.

Snapshots de unidade/setor não se recalculam quando o usuário/cadastro muda. Itens inativados continuam legíveis nos chamados antigos. Reparentear subcategoria usada em tickets é bloqueado no serviço administrativo, além da FK. Serviço configurado com categoria/subcategoria deve ser compatível; serviço sem classificação é opcional. Campos nulos do cadastro organizacional ficam explícitos como não informados, sem bloquear acesso com regra nova não definida.

Compatibilidade com SLA futuro: abertura persistida em created_at e evento ticket.opened com ator/instante; tipo, prioridade nullable, lock_version e eventos não exigem prazo calculado nem ciclo simulado. Etapas 5/6 poderão acrescentar responsáveis, ciclos, versões de políticas/calendários e referências de ciclo aos eventos por migrações novas. Não há relógio contado por comentários nem inferência de P1 pelo nome Solus.

## Etapa 5 — atendimento

A migração incremental adiciona à tabela tickets FKs opcionais para equipe, responsável principal e chamado anterior vinculado, além de indicador de restrição, número de ciclo (inicial 1), instante de primeira resposta útil no ciclo e instante do pedido pendente de reabertura. Índices por equipe/situação e responsável/situação servem à fila. Tickets existentes mantêm sua classificação, autoria e histórico. A proteção de Segurança da informação considera a chave do catálogo em consultas, inclusive para chamados existentes.

ticket_participants e ticket_access_grants usam PK composta (ticket_id, user_id) e FKs restritivas. Participação técnica e concessão a chamado restrito são independentes. A validação de atribuição verifica usuário ativo, perfil e vínculo ativo à equipe. Um único assignee_id representa o principal.

ticket_events registra o ciclo e o antes/depois dos campos afetados, além de motivo e vínculos de comentário quando pertinentes. Os intervalos de espera são delimitados pelos eventos de entrada/saída no mesmo ciclo. A reabertura incrementa o ciclo e limpa o marcador de primeira resposta atual, preservando o anterior nos eventos. Os eventos antigos de abertura pertencem ao ciclo 1. A solução mantém o texto público em comments e o detalhe separado de restauração no evento técnico. Nenhuma política ou versão de SLA é gravada nesta etapa.
