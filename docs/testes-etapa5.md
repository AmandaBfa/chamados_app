# Etapa 5 — atendimento do TI

## Transições e eventos

O atendimento comum usa a proposta de piloto da especificação. Suporte e gerente operam a fila comum. Analistas a consultam e conduzem chamados da sua equipe ativa ou sob sua responsabilidade. Desenvolvedor atua em chamado explicitamente encaminhado. Aprendiz auxilia na triagem e em notas no seu escopo, com supervisão operacional, sem resolver. Participantes analistas e desenvolvedores podem colaborar na conversa e nas esperas. O perfil administrativo isolado não abre a fila. Todo acesso técnico a chamado restrito exige concessão adicional individual.

| Origem | Ação e destino | Quem pode executar | Campos e eventos |
|---|---|---|---|
| Novo | Iniciar triagem → Em triagem | Condutor ou aprendiz no escopo | `ticket.triage` |
| Em andamento | Classificar → mesmo estado | Condutor | Categoria ativa, subcategoria/serviço compatíveis quando informados. Motivo obrigatório na alteração de prioridade. `ticket.classify` |
| Em andamento | Atribuir/transferir → Em triagem se Novo, senão mantém estado | Condutor | Equipe ativa, técnico elegível da equipe se indicado, justificativa. `ticket.assign`. A troca de equipe encerra as designações anteriores e as registra no evento |
| Novo, Em triagem | Assumir → Em atendimento | Condutor elegível da equipe | Chamado sem outro responsável. `ticket.claim`. Em outros estados ativos, assumir preserva o estado |
| Em triagem | Iniciar atendimento → Em atendimento | Condutor ou técnico habilitado no chamado | Responsável principal atribuído. `ticket.start` |
| Em andamento | Designar colaborador → mesmo estado | Condutor | Técnico da equipe diferente do principal, justificativa. `ticket.collaborate` |
| Em andamento | Responder → mesmo estado | Condutor ou técnico habilitado no chamado | Mensagem pública, marcação explícita de utilidade quando aplicável. `ticket.reply` |
| Qualquer | Nota interna → mesmo estado | Técnico autorizado para atuar no chamado | Texto interno. `ticket.note` |
| Em atendimento | Aguardar solicitante → Aguardando solicitante | Condutor ou técnico habilitado no chamado | Pergunta pública indispensável. Em P1, gerente e justificativa. `ticket.wait_requester` |
| Em atendimento | Aguardar terceiro → Aguardando terceiro | Condutor ou técnico habilitado no chamado | Dependência e protocolo. `ticket.wait_third` |
| Aguardando solicitante | Comentário do solicitante → Em atendimento | Solicitante | Texto público. `ticket.requester_returned` |
| Aguardando solicitante ou terceiro | Retomar → Em atendimento | Condutor ou técnico habilitado no chamado | Justificativa. `ticket.resume` |
| Em triagem ou atendimento | Resolver → Resolvido | Condutor ou desenvolvedor responsável | Solução, classificação final e responsável ativo. Restauração temporária separada quando aplicável. `ticket.resolve` |
| Resolvido | Confirmar → Fechado | Solicitante | Sem pedido de reabertura pendente. `ticket.confirmed` |
| Resolvido ou Fechado | Pedir reabertura → mesmo estado | Solicitante | Relato do mesmo problema. `ticket.reopen_requested` |
| Resolvido ou Fechado | Aceitar → Em atendimento, novo ciclo | Condutor ou desenvolvedor responsável | Pedido pendente e justificativa. `ticket.reopen` |
| Resolvido ou Fechado | Recusar → mesmo estado | Condutor ou desenvolvedor responsável | Pedido pendente e motivo público. `ticket.decline_reopen` |
| Em andamento | Cancelar → Cancelado | Condutor | Motivo público. `ticket.cancel` encerra a espera eventualmente aberta e representa desfecho separado |

Em andamento compreende Novo, Em triagem, Em atendimento e as duas esperas. Condutor significa suporte, gerente ou analista dentro do escopo de escrita acima. O início e o fim de cada espera são reconstruídos a partir das mudanças de estado de cada ciclo, ordenadas pelo ID do evento. O instante de resolução vem de `ticket.resolve`. A interface marca a primeira resposta útil por declaração do técnico em uma ação pública com texto. A aplicação valida esses requisitos e guarda a primeira ocorrência por ciclo, sem avaliar o sentido do texto. A nota interna e a atribuição não alimentam esse campo.

## Roteiro manual

Use duas contas fictícias, solicitante e suporte, e uma equipe ativa com o técnico cadastrado como membro. Execute no ambiente local após a migração incremental.

1. Abra um chamado comum no portal. Na fila do TI, combine busca com filtros, confira os resultados e navegue páginas quando houver volume. Em sessão de outro colaborador, tente o detalhe e o download por URL.
2. Clique em **Assumir e iniciar triagem** com uma conta de suporte que pertença a uma equipe ativa. Se houver mais de uma equipe elegível, selecione uma. Confira o responsável no topo, classifique com justificativa se alterar a prioridade e clique em **Continuar atendimento** ou **Encaminhar**. Para encaminhar, indique equipe, novo responsável e motivo. Abra o detalhe em duas abas e confirme que uma edição antiga é rejeitada depois de uma alteração na outra aba.
3. Envie nota interna e pergunta pública ao colocar o chamado em espera. Confira a distinção no TI e a ausência da nota no portal. Responda pelo portal e verifique a retomada e o evento. Teste também espera por terceiro e retorno com protocolo e justificativa.
4. Tente resolver sem solução. Resolva com classificação, solução e restauração temporária separada se aplicável. Confirme no portal, solicite reabertura do mesmo problema e valide no TI. Confira ciclo anterior e novo. Abra outro problema pelo link de chamado vinculado. Cancele um chamado separado com motivo.
5. Com administrador autorizado e MFA, desative um técnico fictício com chamado aberto e confira a indicação **Redistribuir**. Teste a fila com contas de perfis diferentes e verifique que Segurança da informação não aparece na fila comum.
6. Confira foco e rótulos via teclado, contraste em ambos os temas e disposição em janela de 390 px. Verifique que a tabela tem região própria de rolagem.

Conclusão dos testes manuais informada pela operadora em 11/11/2026. Fechamento automático após 27 horas de cobertura, relógios, alertas, prazos e efeitos de prioridade seguem nas etapas correspondentes. A liberação de chamados restritos depende de validação com DPO, gerente e analista de rede.
