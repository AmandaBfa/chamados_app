# Teste manual da etapa 2

Usar somente dados fictícios e a captura local de e-mail. Não usar dados/credenciais corporativos.

## Preparar e criar o primeiro administrador

1. Na raiz do projeto, iniciar Mailpit com & '.\scripts\iniciar-mailpit.ps1'.
2. Em .env, alterar temporariamente AUTH_BOOTSTRAP_ENABLED=false para AUTH_BOOTSTRAP_ENABLED=true. Não mexer nas credenciais PostgreSQL.
3. Executar php artisan config:clear e php artisan auth:bootstrap no PowerShell. Informar nome e e-mail fictícios (por exemplo admin@example.test); digitar uma senha própria de pelo menos 12 caracteres, com maiúsculas/minúsculas/números. A senha é solicitada de forma oculta duas vezes. Não há senha fixa nem argumento de linha de comando contendo segredo.
4. Voltar AUTH_BOOTSTRAP_ENABLED=false e executar php artisan config:clear. O comando recusa ambiente de produção e instalação que já tenha administrador.
5. Iniciar php artisan serve --host=127.0.0.1 --port=8000, se ainda não estiver aberto. Acessar http://127.0.0.1:8000/login.
6. Entrar com a conta criada. Acessar http://127.0.0.1:8025 e abrir a mensagem “Confirme seu e-mail”. O link só confirma a conta autenticada correspondente. Não copiar links de acesso para o chat ou registros compartilhados.
7. Confirmar a senha quando solicitado; em Segurança da conta, iniciar MFA, ler QR code com autenticador e confirmar código TOTP. Antes disso a administração deve permanecer bloqueada.
8. Guardar os códigos de recuperação exibidos. Não anexar QR code ou códigos em prints de teste.

## Exercitar fluxos

- Sair/entrar: senha incorreta deve falhar; senha correta de administrador deve pedir segundo fator. Depois do código correto, entrar e confirmar senha para ações administrativas.
- Cadastro público: abrir /register e enviar POST não deve criar conta (404).
- Usuários: entrar em Administração de usuários, autorizar colaborador@example.test, perfil Colaborador e justificativa. Abrir convite na captura em uma janela sem outra conta autenticada; definir senha e entrar. Reusar o link deve falhar. Convite expirado pode ser reemitido por administrador com justificativa, invalidando anterior.
- Perfil comum: entrar como colaborador (ou gerente/desenvolvedor) e tentar /administracao/usuarios; acesso negado. Alterar campos do navegador não deve conceder privilégio.
- Recuperação: usar “Esqueci minha senha”, abrir mensagem da captura, redefinir e tentar reutilizar o link; segundo uso deve falhar. Recuperar senha de administrador continua exigindo MFA.
- Sessão aberta: manter colaborador autenticado em outra janela; desativá-lo pela administração, com motivo. Na próxima requisição, acesso bloqueado. Reativar e repetir com sessão antiga: deve continuar bloqueada; novo login necessário.
- Último administrador: tentar desativar/remover seu perfil sem outro administrador elegível; alteração recusada. Nenhum usuário pode ser excluído pela interface.
- Temas/teclado: alternar tema, recarregar, usar Tab e Enter, reduzir largura. Campos e erros devem continuar legíveis.

## Testes automatizados

Em PowerShell na raiz, com .env.testing configurado e migrações aplicadas:

~~~powershell
php artisan test --testsuite=Unit,Feature,PostgreSQL
php vendor/bin/pint --test
npm.cmd run build
~~~

AccessControlTest verifica banco/conexão PostgreSQL reais antes de iniciar transação. As escritas são revertidas; não há RefreshDatabase, migrate:fresh ou limpeza de banco. Notifications são simuladas na suíte. O teste separado de captura SMTP foi executado contra Mailpit com registros fictícios em transação e rollback.

## Limites da validação

Os fluxos completos foram exercitados por testes HTTP/Livewire com PostgreSQL. No navegador, login foi conferido em desktop/390 px, ambos os temas, persistência e foco de teclado. Não foi provisionada uma conta humana com senha do operador durante o trabalho; criação inicial é intencionalmente interativa. Teste em celular físico, revisão completa por leitor de tela e recuperação de perda total de MFA dependem de homologação/procedimento operacional.

Se auth:bootstrap recusar os dados, leia os motivos específicos exibidos (por exemplo senha curta, confirmação diferente, senha vazia ou e-mail já cadastrado), corrija e execute novamente. Nenhuma conta é criada em caso de validação inválida. Não compartilhe senha nem a confirmação pelo chat; basta a mensagem de erro.

Se ocorrer erro de conexão com 127.0.0.1:1025 depois de informar senha válida, a conta pode já ter sido criada. Não excluir usuário ou repetir cadastro. Iniciar Mailpit com & '.\scripts\iniciar-mailpit.ps1', entrar no sistema com a senha criada e usar Reenviar confirmação. O comando mostra esse estado explicitamente em falha de transporte. Após cadastrar, manter AUTH_BOOTSTRAP_ENABLED=false.

## Conclusão informada pelo operador — 03/11/2026

Usuário confirmou a finalização dos testes manuais da etapa 2. Conta inicial criada e e-mail/MFA confirmados; acesso administrativo declarado funcionando após relato transitório de 403. Este registro reflete o retorno do usuário e não substitui evidência individual de cada cenário nem homologação em dispositivo físico/leitor de tela/produção. Não houve alteração de credenciais, permissões ou código para registrar esse retorno.
