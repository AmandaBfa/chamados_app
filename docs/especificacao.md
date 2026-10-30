# Sistema de chamados de TI — documentação do projeto

**Versão:** 1.0 • **Data:** 29/10/2026 • **Responsável pelo projeto:** Kayke Rodrigues

**Finalidade:** servir como referência funcional e técnica para implementação, testes e validação com a empresa. Este documento consolida as decisões da conversa; não representa autorização corporativa de implantação ou compromisso formal de SLA.

## 1. Convenções e estado das decisões

- **Definido:** escolha aprovada pelo responsável pelo projeto durante o planejamento.
- **Proposta técnica:** detalhamento acrescentado nesta documentação para tornar a implementação verificável; pode ser ajustado antes da implementação do módulo.
- **Validação corporativa:** decisão que depende da empresa antes da entrada em produção.

As regras detalhadas de reabertura, limites de arquivos, retenção e operação descritas como propostas não devem ser confundidas com aprovação prévia da empresa. Qualquer alteração futura deverá atualizar este documento e o histórico de versões.

## 2. Objetivo e contexto

Disponibilizar um portal simples para colaboradores relatarem problemas, uma área operacional completa para o TI e painéis confiáveis para análise gerencial. A simplicidade da abertura deve coexistir com informação suficiente para diagnóstico e priorização.

Contexto inicial: aproximadamente 90 colaboradores, distribuídos também em outras cidades, e seis integrantes de TI: gerente, suporte técnico, dois analistas, desenvolvedor e jovem aprendiz. Nem todos dedicam toda sua jornada ao suporte.

Atendimento definido: segunda a sexta-feira, das 8h às 17h, com cobertura contínua durante o almoço. Fuso de referência: America/Sao_Paulo. Feriados e exceções precisam ser cadastrados e validados.

## 3. Decisões de arquitetura

| Elemento | Decisão definida |
|---|---|
| Backend | Laravel/PHP |
| Interface | Livewire + Tailwind CSS |
| Banco | PostgreSQL |
| Organização | Uma aplicação organizada por módulos |
| Distribuição do produto | Mesmo código, instalação e banco separados por empresa |
| Acesso | Rede corporativa ou VPN autorizada |
| Autenticação inicial | E-mail e senha, cadastro controlado |
| Autenticação futura | Microsoft Entra ID, condicionada à autorização |
| Dispositivos | Interface responsiva para computador e celular |
| Aparência | Temas claro e escuro, preferência salva |

Versões concretas das dependências serão fixadas no início da implementação, após verificar suporte e compatibilidade. Hospedagem, domínio e fornecedor ficam pendentes, mas a infraestrutura deverá suportar HTTPS, aplicação PHP, PostgreSQL, processamento em segundo plano, tarefas agendadas, armazenamento privado e backups.

Cada empresa terá configurações, credenciais, usuários, anexos, auditoria e backups independentes. Nenhum administrador de uma instalação terá acesso implícito a outra. Configurações corporativas não devem ficar fixas no código. Atualizações compartilharão o mesmo produto, com migrações controladas por instalação.

## 4. Escopo

### Primeira versão

Cadastro controlado, autenticação local, perfis e permissões, equipes, categorias/subcategorias, serviços, abertura e acompanhamento de incidentes, triagem, atribuição, comentários públicos e internos, anexos privados, histórico, prioridades, calendários, SLA, pausas, alertas, escalonamento, envelhecimento, filtros e dashboards.

O cadastro de equipamento será uma referência simples no chamado; não será um inventário patrimonial completo.

### Evoluções

Integração Microsoft; solicitações com fluxos próprios; ligação com aprovações existentes; base de conhecimento; satisfação; inventário; integrações e automações adicionais.

### Fora da primeira versão

Substituir Adobe ou aprovações do Teams, executar assinaturas, aprovar compras/acessos, aplicativo móvel nativo, chatbot e plataforma compartilhada com dados de diversas empresas no mesmo banco.

O modelo distinguirá incidente e solicitação para evolução futura, mas o portal inicial será voltado a problemas. Pedidos sujeitos a autorização continuarão nos processos existentes. Um link de aprovação informado pelo usuário não comprova autorização por si só.

## 5. Pessoas, equipes e permissões

Cargo, nível de atendimento, equipe e perfil de acesso são conceitos independentes. N1/N2/N3 representam classificação interna do TI; P1/P2/P3/P4 representam prioridade de chamados. Conforme informado, redes utiliza N2 e suporte/gerente foram identificados como N3. O sistema não deduz poderes ou rotas a partir desses números.

Equipes iniciais: Suporte; Infraestrutura e Redes; Sistemas; Gestão. Desenvolvimento poderá receber atribuições específicas. Cada chamado terá uma equipe responsável e, após assunção, um responsável principal. Outros técnicos poderão colaborar.

| Perfil | Acesso funcional | Restrições |
|---|---|---|
| Colaborador | Abrir, consultar e comentar seus chamados; anexar; confirmar solução; solicitar reabertura | Sem chamados alheios ou notas internas |
| Jovem aprendiz | Auxiliar na triagem e em tarefas autorizadas sob supervisão | Sem administração de usuários, permissões ou SLA |
| Suporte técnico | Triar, classificar, assumir, encaminhar e resolver | Sem editar políticas; prioridade exige justificativa |
| Analistas | Atender especialidades, colaborar e receber escalonamentos | Escopo conforme equipe e autorização |
| Desenvolvedor | Atender encaminhamentos técnicos específicos | Sem administração de produção automática |
| Gerente | Visibilidade operacional, redistribuição, indicadores e decisões de exceção | Administração técnica concedida separadamente |
| Administrador | Cadastros, equipes, permissões e configurações | Designação explícita e auditoria |

Proposta técnica: suporte e analistas visualizam filas operacionais comuns para continuidade. Chamados sensíveis exigem concessão explícita, inclusive para administradores técnicos. Exports e indicadores devem respeitar o mesmo escopo de leitura. O aprendiz acessa somente equipe/tarefas autorizadas; resolução supervisionada será registrada pelo responsável.

A aprovação de políticas cabe à gestão. A alteração da configuração cabe a administrador designado. Ninguém poderá apagar auditoria ou violações pela interface operacional. Exclusão definitiva por retenção será processo controlado, separado do atendimento.

Triagem: responsabilidade designada, com analista de redes como primeira alternativa para críticos durante o almoço; outro integrante habilitado do TI como substituto. Registrar responsável e substituto do período; não depender de responsabilidade coletiva indefinida.

## 6. Cadastro e autenticação

Cadastro mínimo: identificador interno, nome, e-mail, setor, unidade/cidade, cargo opcional, classificação interna opcional, equipes, perfis, estado ativo/inativo e datas de criação/alteração. Setor e unidade são administrados por pessoas autorizadas.

Não haverá cadastro público. Administrador cria conta ou convite. E-mail deve ser confirmado antes do uso, mantendo o vínculo com um cadastro autorizado. Convites e recuperação terão tokens temporários de uso único. Envio de e-mail deverá ser configurado; desenvolvimento pode usar captura local de mensagens.

Autenticação local: senha armazenada por hash seguro pelo framework, proteção contra tentativas repetidas, recuperação e segundo fator para administradores. Não armazenar senha em texto nem registrá-la em logs. Duração de sessão e validade dos tokens serão parâmetros definidos na implementação de segurança.

Desativação: impedir novos acessos, invalidar sessões locais e verificar estado ativo em cada requisição protegida. Preservar autoria e histórico. Chamados abertos de técnicos desativados entram em revisão de atribuição; não ficam silenciosamente abandonados.

Microsoft: preparar relação de identidades externas separada do cadastro interno. Chamados apontam para o identificador interno, não para o e-mail. Integração futura utilizará organização e identificador do usuário validados; coincidência de e-mail não basta para associação automática. Após migração validada, desabilitar login local dos usuários migrados conforme política aprovada.

Dependências futuras: autorização do administrador Microsoft, registro da aplicação, Tenant ID, Client ID, credencial segura, endereços de retorno, política MFA e licenças aplicáveis. Não solicitar autorização ou registrar aplicação corporativa durante esta fase documental. Desligamento no Microsoft e revogação local precisam de procedimento explícito; não presumir sincronização automática.

## 7. Abertura do chamado

| Campo | Regra |
|---|---|
| Solicitante | Obtido da sessão; não editável pelo colaborador |
| Setor/unidade | Obtidos do cadastro; preservar contexto histórico |
| Assunto | Obrigatório, resumo claro |
| Categoria | Obrigatória, com opção Não sei identificar |
| Subcategoria | Dependente da categoria |
| Descrição | Obrigatória; orientação contextual |
| Serviço/equipamento | Conforme categoria; permitir não identificado |
| Impacto | Pessoa, equipe, unidade ou abrangência maior, com processo afetado |
| Condição de trabalho | Continua, parcialmente impedido ou impedido |
| Início | Data aproximada ou Não sei |
| Anexos | Opcionais |

Orientação: “Conte o que você estava tentando fazer, o que aconteceu e se apareceu alguma mensagem de erro.” Campos condicionais devem solicitar informações úteis, sem obrigar descrição longa artificialmente. Informar para não anexar senhas ou dados desnecessários.

Enviar gera protocolo único, confirmação, estado inicial e histórico. Duplo clique não pode duplicar chamado. O usuário não seleciona P1/P2: o sistema sugere prioridade e a triagem valida. Situação potencialmente crítica gera sinalização imediata, sem aguardar classificação definitiva.

Proposta técnica: assunto até 160 caracteres; descrição até 10.000; até cinco anexos de 10 MB cada, inicialmente PNG/JPEG/PDF/TXT. Validar conteúdo e tipo no servidor; restringir formatos ativos. Colar imagem e arrastar arquivo são facilidades desejáveis. Limites devem ser configuráveis.

## 8. Catálogo inicial

| Categoria | Subcategorias |
|---|---|
| Sistemas corporativos | Não abre; erro em operação; lentidão; indisponibilidade; integração |
| Microsoft 365 | E-mail/calendário; Teams; arquivos/sincronização; aplicativos Office |
| Computadores e periféricos | Não liga; travamento/lentidão; tela; teclado/mouse; áudio/câmera |
| Rede e conectividade | Internet; Wi-Fi; rede cabeada; VPN |
| Impressão e digitalização | Não imprime; qualidade; fila; digitalização |
| Telefonia | Não realiza/recebe chamadas; áudio; ramal; conexão |
| Arquivos e pastas de rede | Acesso negado; indisponibilidade; arquivo ausente; lentidão |
| Acesso e autenticação | Conta bloqueada; erro de login; MFA/Authenticator |
| Documentos e assinatura digital | Adobe não abre; erro de assinatura; validação; envio |
| Segurança da informação | Mensagem suspeita; suspeita de comprometimento; perda/exposição |
| Não sei identificar | Triagem geral |

Serviços iniciais: Mosia, Solus, eSolution, Teams, Outlook, OneDrive, Word, Excel, Adobe, Zoiper e serviços de infraestrutura. Cada serviço pode conter equipe padrão, responsável de referência, criticidade e fornecedor. Impressoras/equipamentos específicos podem ser associados separadamente.

Categorias e serviços poderão ser adicionados ou inativados. Registros utilizados não serão apagados de modo a quebrar históricos. A Solus é candidata a alta criticidade; sua classificação final depende da empresa.

## 9. Estados e transições

| Estado | Significado | Relógio de solução |
|---|---|---|
| Novo | Recebido, ainda não triado | Conta |
| Em triagem | Classificação/encaminhamento | Conta |
| Em atendimento | Diagnóstico e ação | Conta |
| Aguardando solicitante | Informação indispensável pendente | Pausa conforme política |
| Aguardando terceiro | Dependência identificada | Continua na política inicial |
| Resolvido | Restauração/solução comunicada | Encerra ciclo |
| Fechado | Confirmação ou fechamento automático | Encerrado |
| Cancelado | Cancelamento justificado | Encerra, com resultado separado |

Fluxo principal: Novo → Em triagem → Em atendimento → Resolvido → Fechado. A triagem pode resolver diretamente um caso simples. Esperas retornam ao atendimento. Transferência altera responsável/equipe, sem reiniciar relógios.

Resolver exige descrição da solução, classificação final e responsável. Registrar separadamente restauração e correção definitiva quando houver alternativa temporária. Pendência da causa pode gerar acompanhamento vinculado; não confundir restauração com eliminação da causa.

Proposta técnica: fechamento após três dias úteis completos no calendário de atendimento, contados da resolução, com aviso. Para esta configuração, equivalem a 27 horas de cobertura. Reabertura do mesmo problema deve ser solicitada no próprio chamado e validada pelo TI; problema diferente gera novo chamado vinculado.

Reabertura cria novo ciclo rastreável, sem eliminar resultados anteriores. Para retorno de solução ineficaz, proposta: retomar o saldo anterior de solução; se esgotado, o ciclo já retorna vencido. Registrar também tempo até nova resposta. Essa regra precisa ser validada no piloto.

## 10. Prioridade

Prioridade deriva de impacto no negócio e urgência. Quantidade de pessoas não basta: uma pessoa pode bloquear processo essencial. Nome do serviço não determina P1 automaticamente.

| Impacto / urgência | Alta | Média | Baixa |
|---|---|---|---|
| Alto | P1 | P2 | P3 |
| Médio | P2 | P3 | P4 |
| Baixo | P3 | P4 | P4 |

Matriz é proposta operacional inicial. Alto impacto significa processo essencial/unidade amplamente afetada; médio, equipe/processo relevante; baixo, situação localizada sem impacto maior. Urgência alta indica bloqueio sem alternativa e necessidade imediata; média indica prejuízo com alternativa limitada; baixa permite aguardar. A triagem considera prazos de negócio e incidentes sensíveis como exceções justificadas.

Mudanças exigem autor, motivo, classificação anterior/nova e efeito sobre vencimentos. Rebaixar prioridade nunca remove uma violação já ocorrida. Casos de segurança precisam de rota restrita e critérios validados pela empresa.

## 11. Calendário e SLA

Metas aprovadas para desenvolvimento/piloto, ainda sujeitas à aprovação corporativa:

| Prioridade | Primeira resposta humana | Restauração ou solução |
|---|---:|---:|
| P1 — Crítica | 15 minutos úteis | 4 horas úteis |
| P2 — Alta | 1 hora útil | 9 horas úteis |
| P3 — Normal | 4 horas úteis | 27 horas úteis |
| P4 — Baixa | 9 horas úteis | 45 horas úteis |

Cobertura: segunda a sexta, 08h–17h, sem pausa de almoço, America/Sao_Paulo, exceto feriados cadastrados. Chamados podem ser registrados fora do expediente; o relógio começa na próxima janela. Não há promessa de plantão.

Primeira resposta conta desde a abertura e termina com retorno humano público que demonstre entendimento ou encaminhamento útil. Confirmação automática, nota interna e mera atribuição não contam. Solução conta desde a abertura, em paralelo à primeira resposta.

Aguardando solicitante exige pergunta objetiva sobre informação indispensável. Pausa somente solução, nunca apaga resposta vencida. Resposta do usuário encerra a pausa automaticamente e alerta o responsável. Não permitir pausa sem comunicação pública ou retroativa. P1 somente pode pausar por exceção gerencial justificada — proposta a validar.

Dependência de fornecedor não pausa inicialmente. Registrar fornecedor, protocolo, início e retorno. Futuras políticas específicas deverão preservar o resultado histórico.

Exemplo: prazo de 2 horas úteis iniciado sexta às 16h vence segunda às 9h sem feriado. Prazo de 1 hora iniciado às 16h30 vence às 8h30 do próximo dia de cobertura.

Persistir versão de política/calendário usada, eventos de pausa e vencimentos. Proposta para mudança de prioridade: novo prazo = orçamento da nova prioridade menos tempo útil já consumido; saldo negativo significa atraso imediato. Preservar vencimentos anteriores e violações. Alterações de calendário não recalculam históricos silenciosamente.

Armazenar instantes em UTC e exibir no fuso configurado. Cálculo deve considerar intervalos, feriados e precisão suficiente para metas em minutos. Vencimento não depende de o usuário abrir a tela; tarefas automáticas detectam eventos, e a consulta calcula o estado correto mesmo se houver atraso no processamento.

## 12. Envelhecimento, alertas e escalonamento

Separar prioridade do incidente, risco de SLA, idade total, tempo útil contado e tempo sem movimentação. Antiguidade não transforma automaticamente P4 em P1.

Fila padrão proposta: primeiro críticos; dentro das faixas, vencidos e menores saldos de prazo; desempate por idade. Fila dedicada de antigos/parados deve ser revisada diariamente para evitar abandono diante de entradas novas. Mudança real de impacto pode justificar nova prioridade.

| Evento | Destinatário | Ação |
|---|---|---|
| P1 ou suspeita crítica | Triagem, especialista e gerente | Avaliação imediata no expediente |
| Sem responsável | Triagem e substituto | Assumir/atribuir |
| 70% do prazo de solução | Responsável | Aviso preventivo |
| 90% | Responsável e referência da equipe | Revisar atendimento |
| Vencimento | Responsável e gerente | Registrar próximo passo/previsão |
| P3/P4 sem movimentação por 2 dias úteis | Responsável ou triagem | Revisar pendência |

Proposta técnica adicional: alertar falta de responsável após 5 minutos úteis em P1, 30 minutos em P2 e 2 horas em P3/P4. Para primeira resposta, gerar aviso em 70% e no vencimento; metas curtas exigem processamento ao menos por minuto. Limites configuráveis.

Movimentação significativa: resposta, ação técnica registrada ou mudança justificada de estado. Abrir a tela ou alterar campo irrelevante não deve esconder inatividade. Escalonamento não transfere automaticamente o chamado.

Alertas internos e e-mail no MVP. Não incluir conteúdo sensível no e-mail; enviar protocolo e link privado. Deduplicar por chamado, ciclo, relógio e marco. Registrar entrega/falha e tentativas. Teams e Outlook podem estar indisponíveis durante um incidente; definir canal alternativo de contingência antes da produção.

## 13. Telas e experiência

1. **Login:** e-mail/senha inicialmente, recuperação e segundo fator quando aplicável; botão Microsoft só quando integração estiver habilitada.
2. **Início do colaborador:** abrir chamado, avisos, próprios chamados e pendências de resposta.
3. **Abertura:** formulário em uma coluna, campos condicionais e validação compreensível.
4. **Detalhe do colaborador:** protocolo, situação, próximo passo, conversa pública e anexos autorizados.
5. **Fila do TI:** tabela, busca, filtros combináveis, ordenação por risco e visões salvas como evolução.
6. **Atendimento:** conversa/histórico central; classificação, prazo e responsável em painel lateral; notas internas claramente diferenciadas.
7. **Dashboard:** abas Operação, Desempenho e Recorrências.
8. **Administração:** usuários, equipes, unidades, categorias, serviços, calendário, SLA e auditoria.

Portal claro por padrão e TI escuro por padrão, sempre com alternância e preferência salva. Em celular, tabela vira lista de cartões e painel lateral vira seção acessível. Navegação por teclado, rótulos, foco visível e informação por texto/ícone além de cor são requisitos. Feedback de salvamento/erro não pode depender só de notificações passageiras.

## 14. Indicadores e confiabilidade

Filtros: período, unidade/cidade, setor, categoria, serviço, equipe, responsável, prioridade e estado. Cada indicador permite abrir registros correspondentes dentro das permissões do usuário. Exportação de dados filtrados é proposta de implementação, com controle de acesso e auditoria.

| Indicador | Definição |
|---|---|
| Abertos atuais | Não resolvidos, fechados ou cancelados no instante consultado |
| Entradas no período | Chamados criados no intervalo |
| Resoluções no período | Ciclos resolvidos no intervalo; mostrar também chamados distintos |
| Cumprimento de resposta | Respostas dentro da meta / respostas elegíveis concluídas no período |
| Cumprimento de solução | Ciclos concluídos dentro da meta / ciclos elegíveis concluídos no período |
| Vencidos abertos | Relógios ainda ativos já vencidos, separados por resposta/solução |
| Tempos | Mediana e percentil 90, com unidade e população exibidas |
| Reabertura | Chamados resolvidos de uma coorte que reabriram / chamados resolvidos daquela coorte, com janela declarada |
| Recorrência | Frequência por categoria/serviço/equipamento; não prova causa comum |

Cancelados são apresentados separadamente, com motivo; não contam como resoluções bem-sucedidas. Mostrar denominadores, casos sem SLA e filtros aplicados. Métricas de concluídos precisam aparecer ao lado de atrasos abertos para não esconder pendências. Comparação de técnicos não deve ser ranking isolado de quantidade fechada.

Histórico do estoque por data exige reconstrução por eventos ou fotografias diárias; não usar somente estado atual. Tempo total, útil e pausado são medidas distintas. Relatórios por setor devem declarar se usam o setor da abertura ou o atual; proposta: contexto da abertura como padrão.

## 15. Modelo conceitual de dados

Proposta técnica para orientar migrações, sem fixar nomes finais:

| Entidade | Conteúdo e relações |
|---|---|
| Empresa/configuração | Identidade da instalação, fuso, preferências |
| Unidades e setores | Organização e localização |
| Usuários | Cadastro, estado, unidade/setor |
| Identidades | Usuário, provedor, identificador externo e organização |
| Perfis/permissões | Papéis, ações e atribuições |
| Equipes/membros | Especialidade, integrantes e referências |
| Categorias/subcategorias | Catálogo e relação pai/filho |
| Serviços/equipamentos | Serviço afetado e referência opcional de dispositivo |
| Chamados | Protocolo, solicitante, contexto, estado, classificação e atribuição atual |
| Participantes | Colaboradores técnicos autorizados |
| Comentários | Autor, visibilidade pública/interna e conteúdo |
| Anexos | Chave privada, nome, tipo, tamanho, autor e vínculo |
| Eventos | Autor, ação, valores anterior/novo, instante e justificativa |
| Políticas/versões SLA | Metas, condições e vigência |
| Calendários/exceções | Janelas, feriados e versões |
| Ciclos/relógios/pausas | Consumo, vencimento, resultado e reaberturas |
| Notificações | Marco, destinatário, canal, estado e deduplicação |
| Auditoria | Alterações administrativas e ações sensíveis |

Chaves estrangeiras e restrições preservam integridade. Protocolo e identificadores de identidade externa precisam de unicidade. Operações de estado/SLA/histórico usam transações. Concorrência deve impedir atribuições ou resoluções conflitantes sem aviso. Índices serão projetados para filtros de estado, equipe, responsável, serviço, criação e vencimento, após validar consultas.

## 16. Segurança e operação

Autorizar no servidor toda leitura/alteração, incluindo anexos, busca, relatórios e exportações. Ocultar botão não é controle de segurança. Downloads usam verificação de permissão; caminhos públicos permanentes são proibidos. Notas internas não aparecem em payloads do colaborador.

Aplicar proteção contra requisições forjadas, saída HTML segura, validação de entrada, limites de envio e proteção de sessão. Segredos ficam fora do código e dos logs. Produção sem modo de depuração exposto. Contas do banco e armazenamento com privilégios mínimos.

HTTPS obrigatório também na rede privada. Celular acessa pelo Wi-Fi autorizado ou VPN; rede de visitantes e 4G não têm acesso implícito. Acesso Microsoft futuro requer conectividade aos serviços de identidade mesmo com portal privado.

Backups devem cobrir banco e anexos, ser protegidos e ter restauração testada. Frequência, retenção, perda máxima aceitável de dados e prazo de recuperação dependem da empresa. Monitorar aplicação, banco, filas, agenda de tarefas, espaço e falhas de e-mail. Atualizações precisam de homologação e recuperação planejada.

Testes e homologação usam dados fictícios. Produção só recebe dados corporativos após autorização. Política de retenção e descarte será definida com responsáveis da empresa; não estabelecer retenção ilimitada por omissão.

## 17. Critérios de aceitação

| ID | Cenário e resultado esperado |
|---|---|
| AC01 | Colaborador A tenta acessar chamado/anexo de B: acesso negado em tela e requisição direta |
| AC02 | Nota interna criada: ausente de respostas e notificações do colaborador |
| AC03 | Usuário desativado com sessão aberta: próxima requisição protegida bloqueada |
| AC04 | Conta sem convite/cadastro: não consegue se registrar livremente |
| AC05 | Categoria muda: subcategoria incompatível é removida e validada no servidor |
| AC06 | Dois envios da mesma abertura: um único chamado |
| AC07 | Transferência: prazo e violações preservados |
| AC08 | Sexta 16h + 2h úteis: segunda 9h, salvo feriado |
| AC09 | Pausa legítima: solução pausa, resposta não; retorno do usuário retoma solução |
| AC10 | Espera por fornecedor: SLA continua na política inicial |
| AC11 | Prioridade alterada: motivo e prazos anteriores rastreáveis; atraso não desaparece |
| AC12 | Reabertura: novo ciclo registrado sem apagar anterior |
| AC13 | Alerta repetido pelo processamento: uma notificação por marco/destinatário/canal |
| AC14 | Dashboard filtrado: detalhamento reproduz total e respeita permissões |
| AC15 | Dois técnicos alteram simultaneamente: conflito tratado, sem perda silenciosa |
| AC16 | Arquivo inválido ou excessivo: rejeição no servidor |
| AC17 | Temas e celular: ações principais utilizáveis, rótulos e foco legíveis |
| AC18 | Restauração de backup: banco e anexos recuperados e acessíveis conforme permissões |
| AC19 | Fora da cobertura/feriado: relógios contam somente na próxima janela válida |
| AC20 | Alteração de política: chamados históricos mantêm resultado e versão rastreáveis |

Testes automatizados devem priorizar permissões, transições, cálculos de calendário/SLA, concorrência e notificações. Teste manual de usabilidade com colaboradores e TI completa a validação. Metas numéricas de desempenho serão estabelecidas em homologação com volume representativo; não há medição realizada nesta documentação.

## 18. Plano de implementação

1. **Fundação:** repositório, ambientes, versões, instalação por empresa, banco, autenticação local, perfis, auditoria e testes de autorização.
2. **Catálogos e abertura:** usuários/unidades, equipes, categorias, serviços, formulário e anexos privados.
3. **Operação:** filas, atribuição, comentários, estados, solução, espera e reabertura.
4. **SLA:** calendários, políticas versionadas, relógios, pausas, vencimentos e testes de borda.
5. **Alertas e indicadores:** processamento automático, escalonamento, envelhecimento, filtros, gráficos e reconciliação dos números.
6. **Homologação/piloto:** rede/VPN, testes de restauração, segurança, usabilidade e ajuste das metas com dados reais autorizados.
7. **Produção:** autorização corporativa, responsáveis de operação, documentação de instalação, backup e contingência.
8. **Evoluções:** Microsoft e demais módulos após estabilidade do fluxo principal.

Proposta de piloto: aproximadamente um mês. Medir volume, capacidade, atrasos, pausas e reaberturas antes de formalizar o SLA. Não há prazo de entrega estimado sem detalhamento de esforço e disponibilidade de desenvolvimento.

## 19. Pendências antes da produção

| Pendência | Responsável sugerido | Bloqueia |
|---|---|---|
| Aprovação formal de SLA e matriz | Gerência e áreas envolvidas | Compromisso oficial |
| Feriados, calendário e exceções | Gestão do TI | Cálculo definitivo |
| Referências e substitutos por período | Gerente | Cobertura operacional |
| Criticidade dos serviços e segurança | TI e responsáveis pelos processos | Classificação definitiva |
| Domínio, hospedagem, VPN e acesso entre cidades | Infraestrutura | Implantação |
| E-mail de envio | Administrador técnico | Convites, recuperação e alertas reais |
| Retenção, backup e recuperação | Empresa/TI | Operação de dados reais |
| Canal de contingência | Gestão do TI | Comunicação quando rede/login falhar |
| Reabertura, pausa crítica e fechamento automático | Gestão e piloto | Políticas finais |
| Autorização e configuração Microsoft | Administrador Microsoft | Somente integração futura |
| Nome e identidade visual do produto | Responsável pelo projeto | Apresentação final |

## 20. Referências e manutenção

A fonte principal desta versão é o planejamento e as decisões registradas na conversa. Regras adicionais foram marcadas como propostas. Referências técnicas já consultadas no planejamento:

- Laravel — autenticação e kits: https://laravel.com/starter-kits
- Laravel — autorização: https://laravel.com/framework/docs/13.x/authorization
- Laravel — filas: https://laravel.com/framework/docs/queues
- Microsoft — endereços de retorno: https://learn.microsoft.com/entra/identity-platform/how-to-add-redirect-uri
- Microsoft — revogação de acesso e sessões: https://learn.microsoft.com/en-us/entra/identity/users/users-revoke-access

Estas referências não substituem a conferência de versões e licenças no início da implementação. Não foi criada aplicação, integração ou infraestrutura nesta entrega.

**Histórico:** v1.0 — consolidação inicial das decisões, detalhamento proposto, modelo conceitual, critérios de aceitação e pendências corporativas.
