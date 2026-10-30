# Rastreabilidade

Etapa 2: AC03 e AC04 atendidos no escopo de autenticação/administração local, com testes PostgreSQL indicados abaixo. AC01/AC02 e demais fluxos futuros permanecem pendentes. Evidências pontuais não substituem o fluxo completo. Numeração de etapas segue o pedido de execução, detalhando a seção 18 da especificação sem modificar a cópia original.

| Critério | Etapa responsável | Estado / evidência atual |
|---|---|---|
| AC01 acesso a chamado/anexo alheio | 2, 4, 5 | Pendente; disco privado configurado, autorização funcional ainda ausente |
| AC02 isolamento de notas internas | 5 | Pendente |
| AC03 desativação em sessão aberta | 2 | Atendido: AccessControlTest::test_deactivation_revokes_database_sessions_and_old_session_cannot_return_after_reactivation e test_deactivated_session_is_blocked_on_livewire_update; sessão pendente MFA também bloqueada |
| AC04 cadastro controlado | 2 | Atendido: GET/POST /register 404; convites autorizados, expiração/uso único, perfis comuns proibidos de administrar e bootstrap restrito; evidências em AccessControlTest |
| AC05 subcategoria dependente | 3, 4 | Pendente |
| AC06 idempotência na abertura | 4 | Pendente |
| AC07 transferência preserva SLA | 5, 6 | Pendente |
| AC08 sexta 16h + 2h úteis | 6 | Pendente |
| AC09 pausa de solução / retorno | 5, 6 | Pendente |
| AC10 fornecedor não pausa | 5, 6 | Pendente |
| AC11 prioridade preserva histórico | 5, 6 | Pendente |
| AC12 reabertura com novo ciclo | 5, 6 | Pendente |
| AC13 deduplicação de alerta | 7 | Pendente |
| AC14 reconciliação de dashboard | 7 | Pendente |
| AC15 concorrência | 4, 5, 6 | Pendente |
| AC16 validação de arquivos | 4 | Pendente |
| AC17 temas, teclado e celular | 1, 4, 5, 8 | Parcial: tema persistido e estilos responsivos implementados; login conferido em desktop/390px, temas e foco; áreas funcionais futuras e homologação em aparelho físico pendentes |
| AC18 restauração de backup | 8 | Pendente |
| AC19 cobertura e feriado | 6 | Pendente |
| AC20 políticas históricas | 6 | Pendente |

## Requisitos por seção da especificação

| Seções | Requisito | Etapas | Evidência / pendência |
|---|---|---|---|
| 1–4 | decisões, arquitetura e distribuição isolada | 1, 8 | Base Laravel/Livewire/Tailwind e PostgreSQL configurado; instalação isolada documentada; produção pendente |
| 5–6 | usuários, identidades, funções, equipes, cadastro controlado e MFA administrativo | 2–3 | Autenticação local, sete perfis, convites, MFA, desativação/sessões e auditoria implementados/testados; equipes/unidades/setores ficam na etapa 3 |
| 7–8 | abertura e catálogo integral | 3–4 | Preservados na referência; sem tabelas funcionais |
| 9–10 | fluxo, colaboração, prioridades e histórico | 5 | Apenas modelo proposto |
| 11 | calendário e dois relógios versionados | 6 | Apenas modelo proposto; sem implementação |
| 12 | alertas e escalonamento | 7 | Infraestrutura de fila preparada; sem jobs de negócio |
| 13 | três experiências, temas e acessibilidade | 1, 4–5, 8 | Tela mínima de fundação e tema; demais telas pendentes |
| 14 | indicadores e confiabilidade | 7 | Sem dashboard nem números fictícios |
| 15 | modelo relacional completo | 1–7 | docs/modelo-dados.md; infraestrutura e identidade/acesso migrados, demais entidades propostas |
| 16 | autorização, anexos privados, operação e backup | 1–2, 4–5, 8 | Disco privado preparado; controles funcionais/operação pendentes |
| 17 | critérios de aceitação | 2–8 | Tabela acima; AC03/AC04 com evidência, demais pendentes ou parciais |
| 18–20 | execução, validações e manutenção | 1–8 | docs/progresso.md e decisões técnicas; aprovações corporativas preservadas |
