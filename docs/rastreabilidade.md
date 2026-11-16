# Rastreabilidade

Etapa 6 em 13/11/2026. A regressão no PostgreSQL dedicado passou com 89 testes e 1119 asserções. A homologação visual permanece pendente.

Regressão da etapa 5 em 09/11/2026: 76 testes e 1019 asserções aprovados no PostgreSQL dedicado. Inclui abertura idempotente e atribuição concorrente em processos independentes. AC17 permanece parcial.

Etapas 2–4: AC03/AC04 atendidos na autenticação; AC01/AC02 verificados no portal pessoal, sem antecipar acesso operacional/notificações do TI. AC05/AC06/AC16 têm evidências funcionais de abertura/anexos; AC17 segue parcial por ausência de homologação visual. Evidências pontuais não substituem fluxos futuros. Numeração de etapas detalha a seção 18 sem modificar a cópia original.

## Classificação da Etapa 8 — 16/11/2026

A regressão desta etapa confirmou 94 testes e 1.222 asserções em PostgreSQL dedicado. As classificações aplicam-se aos cenários explicitados. [Comandos, condições e limites](homologacao.md)

| Critério | Classificação | Evidência conferida |
|---|---|---|
| AC01 acesso a chamado/anexo alheio | Atendido com evidência | `RequesterPortalTest::test_own_ticket_and_attachment_isolation_in_get_livewire_search_and_service` verifica tela, GET direto, Livewire e busca. `TicketOperationsTest` verifica escopo operacional e concessão explícita por fixture. |
| AC02 isolamento de notas internas | Parcialmente atendido | `RequesterPortalTest::test_internal_notes_events_and_attachments_absent_from_payloads_and_download` confirma ausência em GET, Livewire, DTO e download. `TicketOperationsTest` cria nota técnica e verifica o portal. O produto ainda carece de um fluxo próprio de notificações ao solicitante para comprovar essa parcela. |
| AC03 desativação em sessão aberta | Atendido com evidência | `AccessControlTest` revoga sessões do banco, nega a atualização Livewire e bloqueia sessão pendente de MFA. Sessão antiga segue inválida após reativação. |
| AC04 cadastro controlado | Atendido com evidência | `AccessControlTest` verifica GET/POST /register com 404, convite autorizado, expirado/forjado, uso único e impossibilidade de elevação por formulário. |
| AC05 subcategoria dependente | Atendido com evidência | `RequesterPortalTest` verifica limpeza ao mudar a categoria e rejeição de seleção incompatível ou inativa. `CatalogTest` confirma FK composta e histórico. |
| AC06 idempotência na abertura | Atendido com evidência | `RequesterPortalTest` repete submissões com e sem arquivo, preserva protocolo e grava um ticket/evento. `TicketConcurrencyTest` reproduz dois processos com mesmo envio. |
| AC07 transferência preserva SLA | Atendido com evidência | `TicketOperationsTest::test_sla_tracks_late_priority_pauses_transfer_reopening_and_automatic_close` preserva vencimento e violação na atribuição. |
| AC08 sexta 16h + 2h úteis | Atendido com evidência | `BusinessCalendarTest` obtém segunda às 9h e terça quando segunda é feriado. |
| AC09 pausa e retorno | Atendido com evidência | `TicketOperationsTest` cobre exigência de pergunta pública, pausa atravessando fim de semana, resposta ativa, reclassificação para P1 e retomada após resposta no portal. |
| AC10 fornecedor conta normalmente | Atendido com evidência | `TicketOperationsTest` registra espera com protocolo de terceiro e observa vencimento em espera. |
| AC11 prioridade rastreável | Atendido com evidência | `TicketOperationsTest` verifica classificação tardia, revisão de prazos e preservação de violação após ampliar a meta. |
| AC12 reabertura com ciclo novo | Atendido com evidência | `TicketOperationsTest` verifica aceitação, novo ciclo, atraso herdado e primeira resposta original preservada. |
| AC13 deduplicação de alerta | Atendido com evidência | `TicketMonitoringTest` repete detector e verifica unicidade por marco/destinatário/canal, fila de banco, falha, revogação e cobertura. Entrega automatizada usa captura em memória. |
| AC14 detalhamento de dashboard | Atendido com evidência | `TicketMonitoringTest` reconcilia filtros, contagens, ciclos, SLA, cancelamento, recortes de tempo/reabertura e caso restrito com/sem concessão. A evidência considera o mesmo conjunto de dados e escopo. |
| AC15 atualização concorrente | Atendido com evidência | `TicketConcurrencyTest` disputa assunção entre dois processos e serializa contestação/fechamento. `TicketOperationsTest` verifica o conflito por versão. |
| AC16 rejeição de arquivo inválido | Atendido com evidência | `RequesterPortalTest` rejeita tipos, conteúdo, excesso e temporário adulterado, revalida no envio e verifica compensação de metadados/arquivos em falha. Análise antimalware de produção segue em aberto. |
| AC17 temas, teclado e celular | Parcialmente atendido | Portal verificado no Edge em 1366 e 390 px, nos dois temas, com foco de teclado e contraste amostral. Tema padrão corrigido e verificado no TI. Faltam fila em cartões, cobertura integral em celular físico e leitor de tela. |
| AC18 restauração com permissões | Atendido com evidência local | Ensaio em origem/destino novos e discos privados separados. Dump e dois anexos restaurados, SHA-256 e seis decisões de download conferidas. Recuperação e verificação em 5,93 s para o conjunto fictício. RPO/RTO corporativo em aberto. |
| AC19 cobertura e feriados | Atendido com evidência | `BusinessCalendarTest` cobre antes/depois do expediente, fim de semana, feriado, exceção, almoço contínuo e limites exatos. |
| AC20 versão histórica preservada | Atendido com evidência | `TicketOperationsTest` valida autorização, vigência e cálculo com versões diferentes em tickets anteriores e posteriores. |

## Requisitos por seção da especificação

| Seções | Requisito | Etapas | Evidência / pendência |
|---|---|---|---|
| 1–4 | decisões, arquitetura e distribuição isolada | 1, 8 | Base Laravel/Livewire/Tailwind e PostgreSQL configurado; instalação isolada documentada; produção pendente |
| 5–6 | usuários, identidades, funções, equipes, cadastro controlado e MFA administrativo | 2–3 | Autenticação, sete perfis, convites, MFA, desativação/sessões, auditoria e organização implementados; CatalogTest cobre membros, referências, vínculos e ausência de autoelevação |
| 7–8 | abertura e catálogo integral | 3–4 | Catálogo, abertura idempotente, snapshot de contexto, próprios chamados, comentário e anexos privados implementados/testados; sem classificação automática/SLA |
| 9–10 | fluxo, colaboração, prioridades e histórico | 5 | Fluxo comum implementado e testes manuais concluídos segundo a operadora em 11/11/2026. Validação da rota de restritos adiada |
| 11 | calendário e dois relógios versionados | 6 | Calendário e metas de piloto versionados, relógios projetados dos eventos, revisões e violações persistidas |
| 12 | alertas e escalonamento | 7 | Detecção agendada, fila, falhas e envelhecimento implementados. Suspeita crítica testada para equipe ou mais com trabalho impedido |
| 13 | três experiências, temas e acessibilidade | 1, 4–5, 8 | Portal pessoal, administração e área TI implementados. Homologação visual e de acessibilidade pendente |
| 14 | indicadores e confiabilidade | 7 | Três abas e detalhamento autorizado. Mediana, P90 e taxa de reabertura no piloto. Estoque histórico pendente |
| 15 | modelo relacional completo | 1–7 | docs/modelo-dados.md descreve estrutura de atendimento e SLA. Alertas persistidos com deduplicação por destinatário/canal |
| 16 | autorização, anexos privados, operação e backup | 1–2, 4–5, 8 | Isolamento do portal, upload e download privado testados; antimalware/operação/backup/homologação pendentes |
| 17 | critérios de aceitação | 2–8 | Tabela acima com evidências e limites por escopo; critérios futuros preservados |
| 18–20 | execução, validações e manutenção | 1–8 | docs/progresso.md e decisões técnicas; aprovações corporativas preservadas |
