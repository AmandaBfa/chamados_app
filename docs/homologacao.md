# Homologação — etapa 8

**16/11/2026 · Estado parcial.** A linha de base foi o commit `9892f19`, com histórico das etapas 1–7 e migrações conferidas no PostgreSQL de testes. A especificação original foi preservada. A [rastreabilidade](rastreabilidade.md) registra a classificação de AC01–AC20.

## Resultados e escopo

| Área | Evidência e alcance |
|---|---|
| Identidade e organização | `AccessControlTest` cobre convite, login/logout, confirmação, recuperação, limitação de tentativas, TOTP e códigos de recuperação, desativação e bloqueio de sessão web/Livewire. `CatalogTest` cobre os Gates dos sete perfis, a edição e os vínculos. Cargo, equipe, nível de atendimento e prioridade continuam independentes. |
| Portal | `RequesterPortalTest` abre e consulta chamado próprio pelos sete perfis. Exercita abertura idempotente, comentário, filtros, anexos públicos/privados, isolamento, uploads e desativação. |
| Atendimento e SLA | `TicketOperationsTest` cobre triagem, resposta, nota, esperas, encaminhamento, transferência, solução, confirmação e reabertura, com cenários de escopo para os sete perfis, concessão restrita por fixture e desfechos automáticos. `TicketConcurrencyTest` usa processos independentes. `BusinessCalendarTest` cobre bordas de calendário. Os perfis foram exercitados conforme as ações permitidas, em cenários complementares. |
| Relatórios e alertas | `TicketMonitoringTest` reconcilia indicadores e listas, escopos, filtros e recortes, deduplicação, atraso fora da cobertura, fila PostgreSQL, falhas e revogação. O conteúdo do e-mail operacional é protocolo e link autenticado. |
| Revisão de servidor | Conferidos rotas e middlewares, autorizações de ações Livewire e de serviços, revisão de versão de sessão, MFA, escopos de busca/detalhamento, armazenamento privado, limite e revalidação de upload, concorrência, agenda e worker. Não existe rota de exportação. Segredos de ambiente e ferramentas locais permanecem em caminhos ignorados. |
| Limites | Prioridade formal é classificada na triagem com sugestão por matriz, e a abertura detecta suspeita crítica pelos critérios de piloto. Permanecem pendentes a operação aprovada dos restritos, estoque histórico, exportação proposta, avisos próprios do solicitante e entrega operacional em produção. O aviso de fechamento está no detalhe do ticket resolvido. A fila técnica usa tabela com rolagem horizontal em tela estreita. A lista de cartões prevista para celular permanece pendente. O escopo e a necessidade de aviso ativo, exportação e estoque histórico exigem decisão. A inspeção e os testes são de homologação funcional e revisão defensiva. |

## Comandos e evidência

| Comando | Resultado |
|---|---|
| `php artisan migrate:status --env=testing` | Aprovado. Nove migrações registradas como aplicadas no ambiente dedicado. |
| `php vendor/bin/phpunit --testsuite Unit,Feature,PostgreSQL --colors=never` | Aprovado após estabilizar o vendor. 94 testes, 1.222 asserções, 88,85 s e pico de 76 MB. Banco `chamados_test`, com identidade verificada pela suíte e e-mail em memória. A primeira tentativa restrita falhou ao ler o autoload. Uma execução iniciada antes do fim do Composer falhou por diretórios removidos durante a troca de dependências. Outra rodada detectou a troca do rótulo de acesso da página técnica. O rótulo original foi restaurado. |
| `composer validate --strict` e `composer check-platform-reqs --lock` | Aprovados em PHP 8.4.8. A exceção de ownership do Git foi limitada ao processo. |
| `composer audit --locked` | Primeira tentativa restrita falhou por DNS. Consulta com acesso à rede encontrou 10 avisos em 5 pacotes. Atualização do lock concluída com Laravel 13.40.0 e quatro componentes Symfony 7.4.23. A consulta seguinte retornou zero avisos. |
| `npm.cmd audit` e `npm.cmd run build` | Auditoria restrita falhou por DNS. Consulta ao registro npm retornou zero vulnerabilidades. Build final Vite aprovado em 10,12 s. |
| `php artisan route:list --except-vendor`, `php artisan route:list -v --path=livewire`, `php artisan schedule:list` e `php artisan view:cache` | Aprovados. Duas tarefas a cada minuto. Livewire com middleware web na atualização e autenticação, verificação, MFA administrativo e limite no upload. |
| `php vendor/bin/pint --test` | Falhou em cinco arquivos preexistentes por importações, finais de linha e formatação. Nenhuma reformatação em massa foi feita. |
| `node .tools/etapa8-restauracao/navegador.cjs` em Edge headless, 1366 e 390 px | Aprovado nos cenários executados após ajustar seletor de busca e preparação das contas. Login fictício, erro de credencial, rótulos e foco após Tab, formulário de abertura renderizado, filtro vazio, lista/detalhe e anexo público visível. Sem rolagem horizontal da página nos cenários de abertura e detalhe. Contraste do título do formulário de 16,28:1 no claro e 18,40:1 no escuro. Na área de TI, o tema inicial escuro e a preferência salva foram confirmados em 390 px. |
| Leitor de tela e dispositivo físico | Não executados. A emulação de viewport cobre parte do roteiro. |
| `php .tools/etapa8-restauracao/captura.php` com Mailpit 1.32.0 | Aprovado em loopback. Notificação fictícia Laravel capturada para endereço .test. A tentativa inicial com MAIL_URL vazia falhou por sobrescrever o transporte. |
| SMTP operacional, HTTPS/VPN, proxy, carga representativa e alertas de infraestrutura no destino | Não executados. Dependem de ambiente e autorização próprios. |

A página local de verificação informa o propósito técnico e dá acesso à aplicação. A área de TI inicia em tema escuro, conforme a especificação. A preferência salva prevalece ao navegar.

## Ensaio AC18

Instância PostgreSQL isolada em loopback, porta 55438, criada com `initdb` e iniciada por `pg_ctl`. Apliquei as migrações apenas na origem fictícia com `php artisan migrate --env=testing --force --no-interaction`. Um chamado foi aberto pelo serviço da aplicação. Os anexos público e interno foram criados como fixtures do ensaio. Origem e destino usaram discos privados separados e o destino foi conferido vazio antes do restore. Nenhuma base preexistente foi usada como destino.

| Comando ou verificação | Resultado |
|---|---|
| `pg_dump -h 127.0.0.1 -p 55438 -U chamados_lab -d chamados_lab_origem -Fc --no-owner --no-acl -f chamados.dump` e `tar -cf anexos.tar -C origem tickets` | Aprovados. Pacote e SHA-256 registrados em 1,68 s. |
| `pg_restore -h 127.0.0.1 -p 55438 -U chamados_lab -d chamados_lab_restaurado --single-transaction --exit-on-error --no-owner --no-acl chamados.dump` | Aprovado. Banco restaurado em 2,77 s. |
| `tar -tf anexos.tar` e `tar -xf anexos.tar -C restaurado` | Aprovados. Dois arquivos sob tickets, em destino privado separado. Banco e arquivos em 2,93 s. |
| `php .tools/etapa8-restauracao/validar.php verify` | Aprovado. Recuperação e verificação em 5,93 s. Protocolo fictício `CH-01M7JPQSZ8E66RWG0WEBRDR0RF`. Três contas, um chamado com versão de SLA, um evento de abertura e dois arquivos íntegros por tamanho e SHA-256. Seis testes pelo controlador da aplicação. Titular baixa o público e recebe 404 para o interno, outra conta recebe 404 para ambos, e suporte baixa ambos no escopo técnico. |

Hashes do pacote fictício, comparados antes da restauração. O pacote e o manifesto estão em `.tools/etapa8-restauracao/`, fora dos arquivos versionados. O PostgreSQL de ensaio foi encerrado e a credencial temporária removida.

- `chamados.dump` · `C8C6AE94D08A3A2D35466131ED49ADD1E391D3DB6D8F4567914AF01706B2791C`
- `anexos.tar` · `13F05CB0B6107DBBA598A5E286B12A728FA5222176979444866AE00E216D6BCE`

O tempo inclui o restore, a extração e a verificação de amostra. O ensaio local comprova AC18 nesse cenário. RPO/RTO, recuperação de chave, ACL do serviço e capacidade com volume representativo ainda precisam de teste no ambiente aprovado. [Procedimento operacional](operacao.md#backup-consistente-e-restauração)

## Roteiro manual

1. Em cópia isolada, abra o Mailpit e crie o administrador inicial pelo README. Confirme e-mail, MFA e senha recente. Convide os seis perfis restantes com endereços fictícios, teste aceite e confirme que o convite reaproveitado falha.
2. Abra o mesmo relato no portal de cada perfil. Confira situação, protocolo, campos condicionais e envio duplicado. Em outra conta, tente abrir o detalhe e o anexo do primeiro solicitante.
3. Como suporte, assuma a triagem, classifique, responda e registre nota interna. Faça uma espera pelo solicitante e retome com resposta pelo portal. Faça espera por terceiro e observe a continuidade do relógio. Encaminhe entre equipes e pessoas mantendo o prazo. Exercite o escopo de aprendiz, analista, desenvolvedor e gerente conforme a [matriz](permissoes.md), incluindo ações negadas. Teste caso restrito com e sem concessão apenas em laboratório.
4. Registre restauração provisória e solução. Confirme pelo portal, peça reabertura para o mesmo problema e valide novo ciclo. Teste recusa motivada em chamado separado. Desative um técnico com sessão aberta e confira revogação, sinalização e redistribuição.
5. Como gerente, combine filtros de unidade/setor, período, responsável e situação no dashboard, confira denominadores e abra as listas. Verifique fila, alertas e destinatários com os comandos do guia operacional.
6. Repita telas essenciais nos dois temas, desktop e celular físico. Use Tab, Shift+Tab e Enter, amplie a tela, teste mensagens de erro e estado vazio e leia rótulos e mudanças com leitor de tela. Registre navegador, viewport, perfil, caminho e resultado.

## Piloto proposto

- Semana 1 · Treinar perfis e confirmar escala, critérios de triagem, acesso e quadro inicial de pendências.
- Semanas 2 e 3 · Revisar semanalmente entradas por período/categoria, estoque aberto, casos sem responsável, relógios vencidos, pausas, reabertura e capacidade disponível em horas de cobertura. Registre tempo corrido, útil e pausado separadamente, e compare contagens com os chamados do filtro. Colete feedback estruturado de colaboradores e TI sobre abertura, comunicação, espera e redistribuição.
- Semana 4 · Reconciliar dados e revisar casos com política de piloto. Use somente coortes com janela de reabertura encerrada, informe denominadores e colete feedback final. Registre por evidência data, política, perfil, filtros, amostra anonimizada, contagem, desvio e responsável. Não usar dados reais sem autorização. Nenhum resultado de uso foi presumido.

A próxima fase depende de validação manual das áreas restantes, revisão das decisões empresariais e ensaio no destino autorizado. [Checklist de liberação](pendencias-producao.md#checklist-de-liberação) · [Implantação](implantacao.md)
