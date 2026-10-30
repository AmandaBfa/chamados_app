# Configuracao local inicial. Nao altera bancos ou usuarios que ja existam.
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$psqlExe = 'C:\Program Files\PostgreSQL\18\bin\psql.exe'
$previousPassword = $env:PGPASSWORD
$previousClientEncoding = $env:PGCLIENTENCODING
$previousPgOptions = $env:PGOPTIONS
$previousTimeout = $env:PGCONNECT_TIMEOUT
$secretValues = @()

function Invoke-ProjectSql([string] $sqlText) {
    $sqlOutput = $sqlText | & $psqlExe -X -w -q -A -t -v ON_ERROR_STOP=1 -h 127.0.0.1 -p 5432 -U postgres -d postgres 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw 'O PostgreSQL recusou a operacao. Verifique a senha e se a conta postgres permite criar bancos. Nenhuma senha foi exibida.'
    }
    return (($sqlOutput | ForEach-Object { [string] $_ }) -join "`n").Trim()
}

function New-ProjectPassword {
    $buffer = New-Object byte[] 32
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $generator.GetBytes($buffer) } finally { $generator.Dispose() }
    return ([System.BitConverter]::ToString($buffer)).Replace('-', '').ToLowerInvariant()
}

function Set-ProjectDatabase([string] $filePath, [string] $databaseName, [string] $roleName, [string] $rolePassword) {
    $contents = [System.IO.File]::ReadAllText($filePath)
    $settings = [ordered]@{
        DB_CONNECTION = 'pgsql'
        DB_HOST = '127.0.0.1'
        DB_PORT = '5432'
        DB_DATABASE = $databaseName
        DB_USERNAME = $roleName
        DB_PASSWORD = $rolePassword
    }
    foreach ($entry in $settings.GetEnumerator()) {
        $pattern = '(?m)^' + [regex]::Escape($entry.Key) + '=.*$'
        $line = $entry.Key + '=' + $entry.Value
        if ([regex]::IsMatch($contents, $pattern)) {
            $contents = [regex]::Replace($contents, $pattern, $line)
        } else {
            $contents += "`n" + $line + "`n"
        }
    }
    [System.IO.File]::WriteAllText($filePath, $contents, (New-Object System.Text.UTF8Encoding($false)))
}

try {
    Set-Location -LiteralPath $projectRoot
    if (-not (Test-Path -LiteralPath $psqlExe)) { throw 'Cliente PostgreSQL 18 nao encontrado.' }
    foreach ($envName in @('.env', '.env.testing')) {
        $envFile = Join-Path $projectRoot $envName
        if (-not (Test-Path -LiteralPath $envFile)) { throw "Arquivo $envName ausente. Pare e informe no chat." }
        $fileText = [System.IO.File]::ReadAllText($envFile)
        $expectedDatabase = if ($envName -eq '.env') { 'chamados_dev' } else { 'chamados_test' }
        if ($fileText -notmatch ('(?m)^DB_DATABASE=' + $expectedDatabase + '\s*$')) {
            throw "Configuracao de banco diferente da esperada em $envName. Nenhuma alteracao foi feita."
        }
        if ($fileText -match '(?m)^DB_URL=\s*[^\s#]+') { throw "DB_URL configurado em $envName. Revisao manual necessaria." }
    }
    Write-Host 'Configuracao dos bancos locais do sistema de chamados.'
    Write-Host 'Digite a senha do usuario postgres definida quando o PostgreSQL foi instalado.'
    Write-Host 'A senha fica oculta e nao sera salva no projeto ou enviada ao chat.'
    $adminSecret = Read-Host 'Senha do postgres' -AsSecureString
    $secretHandle = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($adminSecret)
    try {
        $env:PGPASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($secretHandle)
    } finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($secretHandle)
        $adminSecret.Dispose()
    }
    $env:PGCLIENTENCODING = 'UTF8'
    $env:PGCONNECT_TIMEOUT = '5'
    # Evita registrar SQL com credenciais na captura de erros da sessao.
    $env:PGOPTIONS = '-c log_statement=none -c log_min_error_statement=panic'
    $existingCount = Invoke-ProjectSql "SELECT (SELECT count(*) FROM pg_roles WHERE rolname IN ('chamados_app','chamados_test')) + (SELECT count(*) FROM pg_database WHERE datname IN ('chamados_dev','chamados_test'));"
    if ($existingCount -ne '0') {
        throw 'Ja existem bancos ou usuarios com os nomes reservados ao projeto. Pare e informe no chat; nada foi sobrescrito.'
    }
    $devPassword = New-ProjectPassword
    $testPassword = New-ProjectPassword
    $secretValues = @($devPassword, $testPassword)
    $null = Invoke-ProjectSql "CREATE ROLE chamados_app LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD '$devPassword'; CREATE ROLE chamados_test LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD '$testPassword';"
    # CREATE DATABASE exige comandos fora de bloco transacional.
    $null = Invoke-ProjectSql 'CREATE DATABASE chamados_dev OWNER chamados_app;'
    $null = Invoke-ProjectSql 'CREATE DATABASE chamados_test OWNER chamados_test;'
    $null = Invoke-ProjectSql "REVOKE ALL ON DATABASE chamados_dev FROM PUBLIC; REVOKE ALL ON DATABASE chamados_test FROM PUBLIC; ALTER DATABASE chamados_dev SET timezone TO 'UTC'; ALTER DATABASE chamados_test SET timezone TO 'UTC';"
    Set-ProjectDatabase (Join-Path $projectRoot '.env') 'chamados_dev' 'chamados_app' $devPassword
    Set-ProjectDatabase (Join-Path $projectRoot '.env.testing') 'chamados_test' 'chamados_test' $testPassword
    Write-Host ''
    Write-Host 'Bancos criados e arquivos .env e .env.testing configurados.' -ForegroundColor Green
    Write-Host 'Volte ao chat e informe: configuracao concluida. O assistente pode executar as migracoes e testes.'
} catch {
    # Nunca imprime SQL, senhas ou excecoes nativas do psql.
    Write-Host ''
    Write-Host $_.Exception.Message -ForegroundColor Red
    Write-Host 'A configuracao nao foi concluida. Informe essa mensagem no chat. Nao envie senhas.'
    Write-Host 'Se algum recurso foi criado antes da falha, nao o exclua nem execute novamente sem revisao.'
} finally {
    $env:PGPASSWORD = $previousPassword
    $env:PGCLIENTENCODING = $previousClientEncoding
    $env:PGOPTIONS = $previousPgOptions
    $env:PGCONNECT_TIMEOUT = $previousTimeout
    $devPassword = $null
    $testPassword = $null
    $secretValues = $null
}
