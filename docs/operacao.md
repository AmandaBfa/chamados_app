# Operação por instalação

## Contas e atendimento

- Antes de desativar um técnico, identifique chamados pendentes na fila. Um gerente com acesso revisa cada atribuição, escolhe integrante ativo da equipe ou equipe de destino e registra o motivo da transferência. A transferência preserva o SLA. Em desligamento urgente, desative primeiro e use a sinalização de responsável inativo para redistribuir. Registre os casos restritos na fila de acompanhamento autorizada e encaminhe a decisão de acesso ao responsável corporativo.
- Desative em **Administração → Usuários**, como administrador com e-mail confirmado, MFA e senha recente. Confirme que a sessão anterior perdeu acesso. O sistema preserva autoria, histórico e o responsável anterior até a redistribuição.
- Em perda do autenticador, use um código de recuperação no desafio MFA. Valide o acesso e regenere os códigos em Segurança depois de confirmar a senha. Se houve comprometimento da semente ou perda do fator e de todos os códigos, acione a gestão de identidade. Um segundo administrador pode suspender a conta comprometida quando houver administrador utilizável remanescente. A recuperação total ou troca de semente requer procedimento aprovado, identificação por canal independente, revogação de sessões, auditoria e novo vínculo de fator. A recuperação por e-mail altera apenas a senha.

## Agenda, fila e indisponibilidade

- Confira `php artisan schedule:list`, supervisão do worker, última execução bem-sucedida de cada tarefa, idade da fila e `php artisan queue:failed`. No dashboard, examine ocorrências de entrega e ausência de destinatário. Relacione o ID do job ao alerta e confira escopo, destinatário e janela útil. Proteja logs e payloads como dados internos.
- Corrija a causa da falha de infraestrutura antes de retomar o worker. Um alerta terminal `failed` não volta a `queued` com `queue:retry`, pois o job só trabalha alertas `queued`. Registre a ocorrência e use o canal de contingência aprovado. Trate a entrega SMTP de resultado ambíguo como possível duplicação. Uma rotina de reenvio terminal requer revisão e trilha de auditoria.
- Em indisponibilidade, registre início e impacto, acione o canal de contingência e verifique aplicação, banco, disco, TLS, agenda e fila. Preserve o horário original dos incidentes no registro de contingência para reconciliação pela gestão. A regularização manual deve preservar os registros, sem retroagir ou recalcular o SLA por SQL.
- Monitore espaço/IO, validade do certificado, erros HTTP, latência, PostgreSQL, atraso de agenda, falhas de entrega, idade dos jobs e idade do último backup verificável. Defina responsáveis e limiares por instalação antes do piloto.

## Backup consistente e restauração

1. Identifique instalação, versão do código e ferramentas, data UTC, banco de origem, raiz privada e destino. Prepare armazenamento cifrado e acesso restrito. Registre referência da chave e dos segredos no cofre, sem valores no manifesto. Exija uma origem confiável para o dump.
2. Abra janela de manutenção. Pause o ingresso web e o agendador. Drene requisições e jobs em execução e pare os workers. Impeça escritas por integrações ou console durante a captura. Confirme que o banco de origem e a pasta pertencem à mesma instalação. `pg_dump` dá uma fotografia do banco, e a pausa mantém os anexos alinhados à mesma janela.
3. Use as ferramentas oficiais com host, usuário e banco explícitos e credenciais por prompt ou mecanismo autorizado. Confira códigos de saída. Gere inventário SHA-256 do dump e dos arquivos, com caminhos relativos. Preserve os metadados de `attachments` para validar tamanho e conteúdo de cada chave privada. Ao identificar órfãos, registre a ocorrência sem apagar arquivos durante a captura.

   ```text
   pg_dump --host HOST_ORIGEM --username USUARIO_ORIGEM --dbname BANCO_ORIGEM --format=custom --no-owner --no-acl --file=chamados.dump
   tar -cf anexos.tar -C RAIZ_PRIVADA tickets
   pg_restore --list chamados.dump
   ```

4. Se ainda não existir a pasta `tickets`, confirme base sem anexos e registre o inventário vazio. Valide manifesto e acesso ao cofre antes de retomar aplicação, agenda e workers. Registre duração e resultado.
5. Provisione banco **novo e vazio** com login próprio e uma cópia isolada da aplicação, com raiz privada vazia, sem tráfego de usuários nem envio externo. Confira a identidade do banco antes da restauração. Use runtime e release correspondentes, chave da instalação para recuperar MFA e um ambiente fechado para evitar reaproveitamento de sessões. Mantenha jobs, SMTP e agendador parados até decidir como tratar mensagens e jobs restaurados. Reveja a revogação de sessões antes de qualquer promoção.

   ```text
   psql --host HOST_DESTINO --username USUARIO_DESTINO --dbname BANCO_DESTINO -X -c "SELECT current_database(), current_user"
   pg_restore --host HOST_DESTINO --username USUARIO_DESTINO --dbname BANCO_DESTINO --single-transaction --exit-on-error --no-owner --no-acl chamados.dump
   tar -tf anexos.tar
   tar -xf anexos.tar -C RAIZ_PRIVADA_VAZIA
   ```

6. Inspecione o inventário antes da extração e use apenas caminhos relativos sob `tickets/`. Ajuste propriedade e ACL para o processo da aplicação. Compare contagens e amostras de chamados, ciclos, eventos e alertas. Confira cada anexo por chave, tamanho e SHA-256, abra um arquivo com seu solicitante autorizado e confirme o bloqueio para outro solicitante. Inclua um anexo interno e confira que aparece apenas no escopo técnico permitido. Registre os tempos de recuperação da base, arquivos e verificação. Em falha, mantenha origem e pacote intactos e repita em destino novo.
7. Avalie o resultado frente ao RPO e RTO acordados. A promoção do destino e o descarte de backups exigem autorização do responsável.

Referências dos comandos. [pg_dump](https://www.postgresql.org/docs/18/app-pgdump.html) · [pg_restore](https://www.postgresql.org/docs/18/app-pgrestore.html)

## Atualizar uma instalação

Selecione uma empresa e uma release auditada. Ensaie o upgrade em restauração isolada, registre migrações e preserve o pacote anterior. Em janela aprovada, pause escritas e processos, gere backup consistente, aplique migrações incrementais e regenere caches da release. Reinicie workers e observe agenda/fila. Teste login, ações, anexo e autorização antes de reabrir. Em falha de migração ou incompatibilidade de esquema, mantenha manutenção e siga o plano de recuperação ensaiado. Se a janela já recebeu dados novos, avalie perda e reconciliação com a gestão antes de escolher o ponto de retorno.
