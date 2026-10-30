# Captura de e-mail apenas neste computador. Nao instala servico do Windows.
$ErrorActionPreference = 'Stop'
$rootPath = Split-Path -Parent $PSScriptRoot
$mailpitPath = Join-Path $rootPath '.tools\mailpit\mailpit.exe'
if (-not (Test-Path -LiteralPath $mailpitPath)) {
    throw 'Mailpit ausente. Consulte a instalacao local no README.'
}
$expectedPath = [System.IO.Path]::GetFullPath($mailpitPath)
$running = @(Get-Process mailpit -ErrorAction SilentlyContinue | Where-Object Path -eq $expectedPath)
if ($running.Count -gt 0) {
    $info = Invoke-RestMethod -Uri 'http://127.0.0.1:8025/api/v1/info' -TimeoutSec 5
    if ($info.Version -ne 'v1.32.0') { throw 'Captura local diferente da versao esperada. Nenhum processo foi encerrado.' }
    Write-Host 'Mailpit ja esta em execucao: http://127.0.0.1:8025'
    return
}
foreach ($port in @(1025,8025)) {
    $socket = New-Object System.Net.Sockets.TcpClient
    try {
        $connection = $socket.ConnectAsync('127.0.0.1', $port)
        $occupied = $connection.Wait(1000) -and $socket.Connected
    } catch { $occupied = $false } finally { $socket.Dispose() }
    if ($occupied) { throw "Porta $port ocupada. Nenhum processo foi encerrado." }
}
# Nao herdar configuracoes MP_* que possam ativar encaminhamento externo.
$savedMailpitEnvironment = @{}
foreach ($item in @(Get-ChildItem Env: | Where-Object Name -like 'MP_*')) {
    $savedMailpitEnvironment[$item.Name] = $item.Value
    [Environment]::SetEnvironmentVariable($item.Name, $null, 'Process')
}
try {
    Start-Process -FilePath $mailpitPath -ArgumentList @('--listen','127.0.0.1:8025','--smtp','127.0.0.1:1025','--disable-version-check','--smtp-disable-rdns','--quiet') -WorkingDirectory $rootPath -WindowStyle Hidden
} finally {
    foreach ($name in $savedMailpitEnvironment.Keys) {
        [Environment]::SetEnvironmentVariable($name, $savedMailpitEnvironment[$name], 'Process')
    }
}
Write-Host 'Captura iniciada: http://127.0.0.1:8025. Mensagens temporarias, sem encaminhamento externo.'
