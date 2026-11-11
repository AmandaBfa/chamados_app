# Rastreabilidade

Regressão da etapa 5 em 09/11/2026: 76 testes e 1019 asserções aprovados no PostgreSQL dedicado. Inclui abertura idempotente e atribuição concorrente em processos independentes. AC17 permanece parcial.

Etapas 2–4: AC03/AC04 atendidos na autenticação; AC01/AC02 verificados no portal pessoal, sem antecipar acesso operacional/notificações do TI. AC05/AC06/AC16 têm evidências funcionais de abertura/anexos; AC17 segue parcial por ausência de homologação visual. Evidências pontuais não substituem fluxos futuros. Numeração de etapas detalha a seção 18 sem modificar a cópia original.

| Critério | Etapa responsável | Estado / evidência atual |
|---|---|---|
| AC01 acesso a chamado/anexo alheio | 2, 4, 5 | Atendido no portal: RequesterPortalTest::test_own_ticket_and_attachment_isolation_in_get_livewire_search_and_service, test_livewire_ticket_id_is_locked_against_switching_to_another_requester; TicketOperationsTest verifica o escopo de leitura por perfil, restrição na busca, detalhe e download negado e leitura com concessão por fixture. O fluxo corporativo de concessão permanece pendente |
| AC02 isolamento de notas internas | 4, 5 | Atendido nas respostas do portal: test_internal_notes_events_and_attachments_absent_from_payloads_and_download; nota/evento/anexo internos criados como fixtures e ausentes de GET, Livewire, DTO e download. TicketOperationsTest cria nota no atendimento e verifica sua ausência no detalhe pessoal. Notificações futuras permanecem pendentes |
| AC03 desativação em sessão aberta | 2 | Atendido: AccessControlTest::test_deactivation_revokes_database_sessions_and_old_session_cannot_return_after_reactivation e test_deactivated_session_is_blocked_on_livewire_update; sessão pendente MFA também bloqueada |
| AC04 cadastro controlado | 2 | Atendido: GET/POST /register 404; convites autorizados, expiração/uso único, perfis comuns proibidos de administrar e bootstrap restrito; evidências em AccessControlTest |
| AC05 subcategoria dependente | 3, 4 | Atendido: CatalogTest e RequesterPortalTest::test_category_change_clears_selection_and_server_rejects_incompatible_or_inactive_catalog; FK composta em tickets e preservação de catálogo histórico testadas |
| AC06 idempotência na abertura | 4 | Atendido: test_livewire_double_submit_and_retry_produce_one_ticket_one_event_and_same_protocol, test_opening_with_upload_via_livewire_is_idempotent_even_after_file_is_consumed e TicketConcurrencyTest com duas conexões/processos independentes |
| AC07 transferência preserva SLA | 5, 6 | Parcial: atribuição auditada e ciclo preservado. Prazos e violações dependem da etapa 6 |
| AC08 sexta 16h + 2h úteis | 6 | Pendente |
| AC09 pausa de solução / retorno | 5, 6 | Parcial: pergunta pública e retorno com mudança de estado exercitados em TicketOperationsTest. Efeito nos relógios pendente |
| AC10 fornecedor não pausa | 5, 6 | Parcial: dependência, protocolo e retorno testados no fluxo. Continuidade do relógio pendente |
| AC11 prioridade preserva histórico | 5, 6 | Parcial: mudança de prioridade com justificativa e antes/depois no evento. Vencimentos e violações pendentes |
| AC12 reabertura com novo ciclo | 5, 6 | Parcial: TicketOperationsTest confirma novo ciclo e preservação do evento de solução. Saldo, relógios e política oficial pendentes |
| AC13 deduplicação de alerta | 7 | Pendente |
| AC14 reconciliação de dashboard | 7 | Pendente |
| AC15 concorrência | 4, 5, 6 | Parcial: TicketConcurrencyTest prova abertura idempotente e duas assunções concorrentes com um único responsável e conflito para a segunda. TicketOperationsTest verifica versão antiga rejeitada. Concorrência com os relógios de SLA pendente |
| AC16 validação de arquivos | 4 | Atendido na validação da aplicação: test_invalid_excessive_and_active_uploads_rejected_by_endpoint_and_final_submit, test_valid_image_pdf_text_and_safe_download_names, test_final_submit_revalidates_content_after_temporary_upload, test_failed_attachment_rolls_back_metadata_receipts_and_written_private_files. Não equivale a análise antivírus/homologação de produção |
| AC17 temas, teclado e celular | 1, 4, 5, 8 | Parcial: tema/layout/rótulos implementados e views renderizadas; inspeção do portal no Edge não iniciada por timeout de autorização da ferramenta. Roteiro humano, celular físico e leitor de tela pendentes |
| AC18 restauração de backup | 8 | Pendente |
| AC19 cobertura e feriado | 6 | Pendente |
| AC20 políticas históricas | 6 | Pendente |

## Requisitos por seção da especificação

| Seções | Requisito | Etapas | Evidência / pendência |
|---|---|---|---|
| 1–4 | decisões, arquitetura e distribuição isolada | 1, 8 | Base Laravel/Livewire/Tailwind e PostgreSQL configurado; instalação isolada documentada; produção pendente |
| 5–6 | usuários, identidades, funções, equipes, cadastro controlado e MFA administrativo | 2–3 | Autenticação, sete perfis, convites, MFA, desativação/sessões, auditoria e organização implementados; CatalogTest cobre membros, referências, vínculos e ausência de autoelevação |
| 7–8 | abertura e catálogo integral | 3–4 | Catálogo, abertura idempotente, snapshot de contexto, próprios chamados, comentário e anexos privados implementados/testados; sem classificação automática/SLA |
| 9–10 | fluxo, colaboração, prioridades e histórico | 5 | Fluxo comum implementado e testes manuais concluídos segundo a operadora em 11/11/2026. Validação da rota de restritos adiada |
| 11 | calendário e dois relógios versionados | 6 | Apenas modelo proposto; sem implementação |
| 12 | alertas e escalonamento | 7 | Infraestrutura de fila preparada; sem jobs de negócio |
| 13 | três experiências, temas e acessibilidade | 1, 4–5, 8 | Portal pessoal, administração e área TI implementados. Homologação visual e de acessibilidade pendente |
| 14 | indicadores e confiabilidade | 7 | Sem dashboard nem números fictícios |
| 15 | modelo relacional completo | 1–7 | docs/modelo-dados.md; infraestrutura, identidade/acesso, cadastros, tickets/comments/attachments/events e extensão de atendimento migrados. SLA e alertas pendentes |
| 16 | autorização, anexos privados, operação e backup | 1–2, 4–5, 8 | Isolamento do portal, upload e download privado testados; antimalware/operação/backup/homologação pendentes |
| 17 | critérios de aceitação | 2–8 | Tabela acima com evidências e limites por escopo; critérios futuros preservados |
| 18–20 | execução, validações e manutenção | 1–8 | docs/progresso.md e decisões técnicas; aprovações corporativas preservadas |
