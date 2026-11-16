# Implantação privada por empresa

Este roteiro é para execução futura, em destino expressamente autorizado. A base é Laravel + Livewire + Tailwind CSS + PostgreSQL, com release, domínio, banco, credenciais, chave, armazenamento, logs e backups próprios por empresa.

## Preparar o ambiente

1. Inventariar a versão do PostgreSQL, PHP, extensões e servidor web. Validar os lockfiles e gerar os assets em ambiente de build com `npm ci` e `npm run build`. No release, usar `composer install --no-dev --prefer-dist --optimize-autoloader` e conferir `composer check-platform-reqs --no-dev`.
2. Configurar um banco e um login PostgreSQL dedicados, sem superusuário, limitados à aplicação. Permitir conexão apenas do host autorizado. Se remoto, usar TLS com verificação do servidor conforme a infraestrutura. Manter datas persistidas em UTC e configurar `INSTALLATION_TIMEZONE=America/Sao_Paulo` para exibição.
3. Publicar apenas `public/` no servidor web e encaminhar requisições Laravel para `public/index.php`. Restringir o acesso aos segredos, código, logs e diretório privado. Conceder escrita em `storage` e `bootstrap/cache` à identidade do serviço. Manter `storage/app/private` fora da raiz pública. Alinhar os limites de corpo e arquivo do proxy/PHP aos cinco anexos de 10 MB mais a sobrecarga da requisição.
4. Configurar `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` em HTTPS, `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, `AUTH_BOOTSTRAP_ENABLED=false` e `TICKET_ALERT_LOCAL_MAIL=false`. Gerar `APP_KEY` uma única vez em instalação nova, armazená-la em cofre e preservar a chave entre releases e restaurações. Limitar hosts aceitos e encaminhar cabeçalhos HTTPS somente de proxies explicitamente confiáveis, quando houver.
5. Aplicar `php artisan migrate --force` no banco dedicado e conferir `php artisan migrate:status`. Em instalação nova, importar o catálogo com `php artisan db:seed --class=InitialCatalogSeeder --force`. Executar `php artisan optimize` depois de fixar o ambiente e verificar `php artisan route:list`.
6. Supervisionar `php artisan queue:work ticket-alerts --queue=ticket-alerts --timeout=20 --tries=3` e executar `php artisan schedule:run` a cada minuto com a identidade do serviço, a partir da pasta do release. Usar cache persistente para locks do agendador. Validar processos, logs e backlog antes de liberar.
7. Configurar o SMTP autorizado para convites, verificação e recuperação e testar com destinatários aprovados no ambiente de homologação. O transporte operacional de alertas disponível hoje é exclusivo da captura local. A entrega operacional de produção exige implementação e homologação do canal aprovado.
8. Validar HTTPS, redirecionamento, cookies, links de e-mail, relógio, capacidade de upload, testes positivos e negativos de acesso, fila e agenda. O endpoint `/up` verifica a inicialização da aplicação. Monitorar conexão com banco, processos e armazenamento separadamente.

## Matriz de acesso a validar com Infraestrutura

| Origem | Resultado esperado |
|---|---|
| Wi-Fi corporativo autorizado | HTTPS alcançável, autenticação e perfis obrigatórios |
| Wi-Fi de visitantes | Portal privado inacessível |
| Estação remota via VPN autorizada | HTTPS alcançável conforme grupo e rotas aprovadas |
| Celular em 4G sem VPN | Portal privado inacessível |
| Celular em 4G com VPN autorizada | HTTPS alcançável conforme política de dispositivos |

Registrar origem, horário, DNS/TLS e resultado para cada linha no destino autorizado. [Implantação Laravel 13](https://laravel.com/docs/13.x/deployment)

## Administrador inicial

Em laboratório, use o procedimento interativo do [README](../README.md#primeiro-administrador), com e-mail fictício em captura local, senha digitada de forma oculta, confirmação de e-mail e cadastro de MFA antes de qualquer operação. Desative o bootstrap logo após a criação. Guarde os códigos de recuperação em local seguro.

Em produção, `auth:bootstrap` é bloqueado e só aceita domínios fictícios quando executado localmente. O provisionamento inicial de uma conta nominativa real precisa de procedimento aprovado e implementado antes da liberação. Requisitos mínimos desse procedimento são execução autenticada por operador autorizado, transação com verificação de ausência de administrador, convite individual de uso único com confirmação de endereço, MFA obrigatório, registro de autoria da operação e revogação da capacidade de bootstrap após uso.
