# Etapa 7 — roteiro local

Use uma instalação isolada e contas fictícias. Em desenvolvimento, aplique a migração incremental da etapa antes de abrir a fila. Configure pelo menos uma referência de equipe, triagem, substituto e gerente com perfis explícitos.

1. Rode `php artisan schedule:work` e, em outro terminal, `php artisan queue:work ticket-alerts --queue=ticket-alerts`. Para captura local, inicie o Mailpit pelo script do projeto e habilite `TICKET_ALERT_LOCAL_MAIL=true` no ambiente local. Reinicie o worker após mudança de configuração.
2. Em um chamado fictício, classifique como P1. Em horário de cobertura, rode `php artisan alertas:processar` duas vezes. Confira **Alertas do TI** com as contas destinatárias e confira protocolo e link na captura local. Abra o link como colaborador para verificar o bloqueio.
3. Em um chamado P3/P4, confira os três tempos na **Fila do TI**. Abra o detalhe e volte. A movimentação deve continuar igual. Envie nota técnica e confirme a mudança. A visão de parados usa 18 horas de cobertura desde a última movimentação. Use a suite automatizada para reproduzir feriado e pausa sem aguardar em tempo real.
4. Em **Indicadores de TI**, use um gerente. Combine cidade, unidade, prioridade e datas. Abra os totais, confira as listas e compare com a aba Recorrências. Verifique um intervalo sem entradas e um chamado cancelado com motivo. Confira que o detalhe de um chamado restrito permanece invisível sem concessão.
5. Em tela estreita e nos dois temas, use teclado para trocar de aba, aplicar filtros, rolar a tabela da fila e abrir protocolos. Confira o foco e os rótulos dos gráficos e links.

## Conjunto de verificação automatizada

Dois chamados comuns e um restrito abrem no mesmo dia, com a mesma cidade fictícia e P1. Um comum responde em 10 minutos, resolve em 1 hora útil, reabre e resolve após mais 4 horas úteis, excedendo o saldo de 3 horas. O segundo comum é cancelado. Para o gerente sem concessão, o esperado é 2 entradas, 1 cancelamento, 2 ciclos resolvidos em 1 chamado distinto, resposta 1/1, solução 1/2, 1 evento de reabertura e 0 vencidos abertos no fim. A concessão aumenta o total de chamados distintos resolvidos para 2. A fila de parados exercita sexta-feira, feriado na segunda e cruzamento exato do limiar na quarta-feira.

`php vendor/bin/phpunit tests/Feature/TicketMonitoringTest.php` executa os cenários em transações no PostgreSQL local `chamados_test`. O teste usa fila real de banco e transportador de e-mail em memória. O roteiro interativo e a integração Mailpit podem ser exercitados separadamente.

No complemento do piloto, após sete dias corridos da primeira resolução, o cenário com concessão do restrito tem dois chamados na coorte e um reaberto na janela, totalizando 50%. Os três ciclos resolvidos têm 60, 60 e 240 minutos corridos, mediana de 60 e P90 de 204. Para suspeita crítica, compare a abertura de trabalho impedido para uma equipe com um relato individual e confira que a prioridade fica pendente de triagem.
