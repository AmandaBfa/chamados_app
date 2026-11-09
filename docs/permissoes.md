# Permissões — etapas 2, 3 e 4

Estado: implementado para autenticação, administração de acesso, cadastros e portal pessoal. Perfis definidos pela especificação; parâmetros técnicos de segurança registrados como escolhas de desenvolvimento/piloto, sujeitos à homologação. Não modifica docs/especificacao.md.

## Regras implementadas

O catálogo contém colaborador, jovem aprendiz, suporte, analistas, desenvolvedor, gerente e administrador. Um usuário pode receber mais de um perfil; atribuições são explícitas em role_user. Cargo (job_title), classificação N1/N2/N3 (support_level), equipe e P1/P2/P3/P4 não concedem permissões. Não há administrador implícito por ser desenvolvedor ou gerente, nem Gate::before que libere tudo.

| Ação / escopo | Colaborador | Aprendiz | Suporte | Analistas | Desenvolvedor | Gerente | Administrador |
|---|---|---|---|---|---|---|---|
| Entrar com senha, se ativo, autorizado e com credencial local | Própria conta | Própria conta | Própria conta | Própria conta | Própria conta | Própria conta | Própria conta + MFA |
| Confirmar e-mail / recuperar senha | Própria conta | Própria conta | Própria conta | Própria conta | Própria conta | Própria conta | Própria conta; recuperação não remove MFA |
| Encerrar sessão | Própria sessão | Própria sessão | Própria sessão | Própria sessão | Própria sessão | Própria sessão | Própria sessão |
| users.view — listar usuários | Não | Não | Não | Não | Não | Não | Sim, após e-mail e MFA confirmados |
| users.create — autorizar/reemitir convite | Não | Não | Não | Não | Não | Não | Sim, com senha recentemente confirmada e justificativa |
| users.update — nome, estado e perfis | Não | Não | Não | Não | Não | Não | Sim, com senha recente e justificativa |
| roles.assign — conceder/remover perfis | Não | Não | Não | Não | Não | Não | Concessão específica, auditada e com proteção do último administrador |
| catalogs.manage — listar/buscar/editar/inativar catálogos, membros e organização | Não | Não | Não | Não | Não | Não | Sim, com MFA, senha recente e justificativa nas alterações |
| Editar/apagar auditoria pela aplicação | Não | Não | Não | Não | Não | Não | Não |
| Portal pessoal: abrir, buscar, consultar, comentar e anexar | Próprios chamados | Próprios chamados | Próprios chamados | Próprios chamados | Próprios chamados | Próprios chamados | Próprios chamados + MFA |
| Notas internas / chamados alheios / exports no portal | Não | Não | Não | Não | Não | Não | Não |

As cinco abilities são Gates separados no servidor. Todas exigem conta ativa, e-mail confirmado, perfil administrador, MFA confirmado no cadastro e comprovado na sessão atual, e versão de autenticação válida. As escritas exigem adicionalmente senha confirmada nos últimos 900 segundos; catálogos também exigem essa confirmação nas leituras administrativas. A página e o componente Livewire verificam autorização; AdminUserService/CatalogService repetem as verificações em cada operação, inclusive IDs/perfis adulterados no cliente.

Etapa 3 implementa unidade/setor/equipes sem duplicação de cadastros. Administração de acesso mantém nome, estado e perfis; Organização dos usuários só altera unidade/setor/cargo/classificação. Mudança de e-mail não é disponibilizada, para não contornar confirmação/vínculo da identidade. Os dois formulários não trocam implicitamente permissões entre si.

## MFA e recuperação

- Administradores sem MFA acessam somente o fluxo necessário para confirmar e-mail, confirmar senha e configurar seu próprio fator, além de sair. /inicio e administração ficam bloqueados até a confirmação.
- Login com MFA configurado exige TOTP ou um código de recuperação válido. O marcador de MFA é gerado no servidor após prova válida, nunca por campo Livewire.
- Semente e códigos são criptografados pelo encrypter do framework e ocultados na serialização de User.
- Configuração inicial exige senha recente e prova TOTP. Códigos de recuperação são mostrados na resposta da confirmação/geração, com Cache-Control no-store.
- Regenerar códigos requer senha recente e MFA comprovado; invalida os anteriores. Consumo de códigos no login é serializado por bloqueio do usuário.
- Não há endpoint para desativar MFA, alterar o fator de outro usuário ou desligá-lo pela recuperação de senha. Ex-administrador com fator configurado continua passando pelo desafio; pode gerenciar seus códigos.
- Perda do autenticador E de todos os códigos exige procedimento de recuperação administrativa fora da interface, com validação de identidade e política corporativa antes de produção. Não foi criado bypass ou reset automático de MFA.

## Estado e sessões

Desativar ou mudar perfis incrementa auth_version, troca o remember_token e remove sessões do banco, tokens de recuperação e convites pendentes. Reativar também incrementa a versão: sessões antigas não retornam. A conta e a autoria permanecem. Middleware de web e persistente do Livewire consulta o estado atual a cada requisição, incluindo etapas pendentes de MFA. A revisão de chamados atribuídos depende da etapa 5.

O último administrador ativo não pode ser desativado nem perder o perfil sem outro administrador ativo, com credencial definida e e-mail confirmado. Convite ainda não aceito não conta como substituto. A linha do perfil administrador é bloqueada nas transações de atribuição para serializar alterações concorrentes.

## Escopos futuros preservados

| Perfil | Requisitos da especificação para etapas 4–7 |
|---|---|
| Colaborador | Somente próprios chamados e anexos autorizados; sem notas internas |
| Jovem aprendiz | Equipe/tarefas autorizadas e supervisão; sem administração de usuários/permissões/SLA |
| Suporte | Triagem/classificação/atribuição/resolução; justificativas de prioridade; sem editar políticas |
| Analistas | Especialidades, colaboração e escalonamentos conforme equipe/autorização |
| Desenvolvedor | Encaminhamentos técnicos específicos; sem administração automática |
| Gerente | Visibilidade operacional/redistribuição/indicadores/exceções; administração concedida separadamente |
| Administrador | Cadastros/permissões/configurações; sem visibilidade universal de chamados sensíveis |

Fila operacional comum de suporte/analistas e exceções sensíveis continuam propostas da referência. Não foram transformadas em acesso real nesta etapa. Quando chamados, anexos, busca e exportações existirem, deverão aplicar políticas no servidor sobre cada registro e escopo; AC01/AC02 continuam pendentes.

## Evidências

tests/Feature/AccessControlTest.php testa todos os seis perfis não administrativos, rótulos de cargo/N3, mount Livewire negado, adulteração de alvo, validação de perfil, ações administrativas positivas, senha recente, último administrador, MFA e revogação. AC03 e AC04 estão associados a esses testes em rastreabilidade.md.

tests/Feature/CatalogTest.php cobre URLs/componentes/serviço negados aos seis perfis comuns, ação Livewire após perda de permissão, Locked contra adulteração de catálogo, whitelist de organização contra alteração de roles/credenciais/estado, equipe/referência sem elevação, membros restritos à equipe e proteção dos vínculos. Escopo de chamados específicos/sensíveis continua reservado às etapas 4/5; specific_assignments_only é dado preparatório, sem implementar ou conceder acesso a chamados.

## Portal pessoal — etapa 4

Decisão explícita do usuário em 03/11/2026: todos os usuários internos ativos, inclusive TI e administrador, podem usar o portal de chamados próprios. Conta autorizada com credencial local, e-mail confirmado, sessão vigente e MFA para administrador continuam obrigatórios. Não é necessário conceder perfil Colaborador adicional aos demais perfis. Não há exceção de leitura global por administração, gerente, equipe ou referência.

PortalAccess é a autoridade do escopo pessoal, aplicada por RequesterTickets, componentes e controller de download. Leitura/ação alheia retorna 404. Cada busca começa por requester_id da sessão; solicitante/autor/organização/visibilidade não são aceitos do navegador. Upload temporário tem dono e validade; ID/referência de outro usuário não pode ser reutilizado. Download verifica ticket+anexo+visibilidade do comentário. Conteúdo interno é excluído antes da projeção/serialização.

Evidências: RequesterPortalTest cobre os sete perfis em chamados próprios, administrador sem leitura global, negação em GET/Livewire/busca/download, troca de IDs e autor, notas internas ausentes, uploads alheios e revogação de sessão em ações. Leitura operacional do TI/sensíveis, aprovação, SLA e exportações continuam não implementados, exigindo políticas próprias nas próximas etapas.
