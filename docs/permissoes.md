# Permissões — etapa 2

Estado: implementado para autenticação/administração mínima. Perfis definidos pela especificação; parâmetros técnicos de segurança registrados como escolhas de desenvolvimento/piloto, sujeitos à homologação. Não modifica docs/especificacao.md.

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
| Editar/apagar auditoria pela aplicação | Não | Não | Não | Não | Não | Não | Não |
| Acesso a chamados/anexos/buscas/exports | Não implementado nesta etapa | Não implementado | Não implementado | Não implementado | Não implementado | Não implementado | Não implementado |

As quatro abilities são Gates separados no servidor. Todas exigem conta ativa, e-mail confirmado, perfil administrador, MFA confirmado no cadastro e comprovado na sessão atual, e versão de autenticação válida. As escritas exigem adicionalmente senha confirmada nos últimos 900 segundos. A página e o componente Livewire verificam autorização; AdminUserService repete as verificações em cada operação, inclusive IDs/perfis adulterados no cliente.

Unidade/setor/equipes ficam para etapa 3, sem duplicação de cadastros. Nesta etapa o administrador altera nome, estado e perfis; mudança de e-mail não é disponibilizada, para não contornar confirmação/vínculo da identidade.

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
