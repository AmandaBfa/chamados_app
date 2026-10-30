# Modelo de dados proposto

Estado: etapas 1 e 2 implementadas conforme detalhamento abaixo; entidades das etapas 3–7 continuam propostas técnicas e não constituem política corporativa aprovada. A especificação original permanece em especificacao.md. Uma instalação/banco por empresa; não haverá tenant_id nem autorização transversal.

## Fundação implementada

Fundação: cache, cache_locks, jobs, job_batches e failed_jobs. Etapa 2 acrescentou usuários, perfis, vínculo de perfis, identidades externas reservadas, convites, recuperação, sessões e auditoria. Sessões locais agora usam banco. Nenhuma tabela de chamados/SLA foi implementada.

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
