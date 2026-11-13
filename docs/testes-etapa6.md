# Etapa 6 — roteiro local de SLA

Use contas e chamados fictícios em instalação isolada. Aplique a migração incremental antes de abrir as telas.

1. Em **Calendário e políticas de SLA**, proponha um feriado fictício com uma conta administradora. Valide com uma conta gerente. Abra outro chamado e compare as versões no detalhe do TI. Confirme que o primeiro mantém a versão anterior.
2. Abra um incidente no portal. Na triagem, selecione impacto alto e urgência alta. Confira a sugestão P1, escolha uma prioridade justificada e verifique idade corrida, tempo útil, saldo e vencimento.
3. Envie nota interna e confirme que a primeira resposta continua aberta. Envie uma resposta pública marcada como útil. Coloque em espera pelo solicitante, responda pelo portal e confira a retomada. Repita com terceiro e verifique que o relógio continua.
4. Transfira o chamado e confira a preservação do prazo. Altere a prioridade, confira a revisão e, se houver violação, verifique que ela continua visível.
5. Resolva e solicite reabertura do mesmo problema. Ao aceitar, confira o novo ciclo e a preservação do saldo e da primeira resposta original. Use os testes com relógio controlado para exercitar a espera de 27 horas, a contestação e a recusa sem aguardar em tempo real.

Regressão local com PostgreSQL dedicado: `php vendor/bin/phpunit --testsuite Unit,Feature,PostgreSQL`.
