# Pendências de produção

O piloto proposto usa dados fictícios, versão de política identificada e serviço de captura local. A liberação da instalação empresarial depende de decisão registrada e de evidência no ambiente autorizado.

| Decisão | Responsável proposto | Evidência para liberação |
|---|---|---|
| Matriz impacto/urgência, metas e exceções de SLA | Gestão do TI e áreas atendidas | Versão aprovada de prioridades, resposta útil, pausa P1, terceiros, reabertura e fechamento |
| Calendário e feriados | Gestão do TI | Janelas e feriados da instalação, cobertura no almoço, exceções e revisão anual |
| Triagem e escalonamento | Gestão do TI | Titular e substituto por período, especialista e reatribuição em ausência/desligamento |
| Criticidade e segurança | Gestão, DPO e analista de rede | Serviços críticos aprovados, acesso explícito a tickets sensíveis e procedimento auditado de concessão/revogação |
| Canal de contingência | Gestão e Infraestrutura | Canal alternativo, responsáveis, recepção e reconciliação para indisponibilidade de portal/SMTP/Teams/Outlook |
| Retenção e anexos | Gestão, DPO e Segurança | Prazos de tickets, arquivos, temporários, logs e backups, tratamento de órfãos, análise antimalware e descarte autorizado |
| Backup e recuperação | Infraestrutura e gestão | Frequência, criptografia, cópia separada, custódia da chave, RPO/RTO e restauração cronometrada no destino homologado |
| SMTP e alertas | Infraestrutura e gestão | Relay, remetente, TLS, destinatários, aviso de fechamento e entrega operacional, tratamento de falha e replay auditado |
| Hospedagem, domínio, certificados e VPN | Infraestrutura | Destino aprovado, matriz de origens testada, DNS/TLS, segredos e monitoração nominativa |
| Contas administrativas | Gestão e Segurança | Provisionamento inicial real e recuperação de perda total de MFA auditáveis |
| Indicadores e divulgação | Gestão e DPO | Validar coorte de sete dias, percentis, recortes, estoque histórico e necessidade/formato/escopo de exportações |
| Limites e operação finais | Gestão e Infraestrutura | Critérios de suspeita crítica, atraso, janelas de aviso, limites de arquivos, desempenho sob volume e aceite do piloto |

Microsoft permanece integração futura opcional para o login local por e-mail e senha. Fluxos de aprovação Adobe e Teams seguem nos canais existentes.

## Checklist de liberação

- [ ] Aprovar matriz de acessos e operação de chamados restritos, calendário e SLA.
- [ ] Homologar fluxos no computador e celular com todos os perfis, teclado, temas e leitor de tela. Validar a fila técnica em cartões para telas estreitas.
- [ ] Resolver limitações do escopo que afetam o aceite e repetir as regressões.
- [ ] Conferir avisos de dependências, versão do PostgreSQL, PHP e runtime no destino.
- [ ] Validar HTTPS e origens de rede permitidas, armazenamento privado, restrições de upload e controle antimalware.
- [ ] Evidenciar provisionamento e recuperação administrativa seguros, e-mail, agenda, worker, falhas e monitoração.
- [ ] Evidenciar backup e recuperação com arquivos e permissões, dentro do RPO/RTO acordado.
- [ ] Registrar aceite do piloto e obter autorização explícita para implantar no destino designado.
