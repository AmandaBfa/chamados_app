<x-layout title="Segurança da conta">
    <section class="panel mx-auto max-w-xl">
        <h1>Segurança da conta</h1>
        @if(!$securityUser->hasEnabledTwoFactorAuthentication())
            <p class="muted mt-3">O segundo fator é obrigatório para administradores. Configure um aplicativo autenticador para liberar o acesso administrativo.</p>
            @if(!$securityUser->two_factor_secret)
                <form action="{{ route('security.enable') }}" method="post" class="mt-6">
                    @csrf<button class="primary" type="submit">Iniciar configuração</button>
                </form>
            @else
                <p class="mt-5 font-medium">Leia o QR code no seu aplicativo autenticador.</p>
                <div class="mt-4 inline-block rounded-lg bg-white p-4" role="img" aria-label="QR code para configurar o aplicativo autenticador">{!! $securityUser->twoFactorQrCodeSvg() !!}</div>
                <p class="muted mt-3 text-sm">O QR code é um segredo da sua conta. Não compartilhe.</p>
                <form action="{{ route('security.confirm') }}" method="post" class="mt-5 space-y-4">
                    @csrf
                    <div><label for="code">Código de seis dígitos do aplicativo</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></div>
                    <button class="primary" type="submit">Confirmar segundo fator</button>
                </form>
            @endif
        @else
            <p class="mt-4 font-medium">Segundo fator ativo e confirmado nesta sessão.</p>
            @if(session('recovery_codes'))
                <div class="mt-6 rounded-lg border border-amber-400 p-4">
                    <h2>Guarde seus códigos de recuperação</h2>
                    <p class="muted mt-2 text-sm">Eles são exibidos apenas nesta resposta. Cada código é de uso único.</p>
                    <ul class="mt-3 space-y-2 font-mono">@foreach(session('recovery_codes') as $code)<li>{{ $code }}</li>@endforeach</ul>
                </div>
            @endif
            <form action="{{ route('security.codes') }}" method="post" class="mt-6">
                @csrf<button class="secondary" type="submit">Gerar novos códigos de recuperação</button>
            </form>
            <p class="muted mt-3 text-sm">Gerar novos códigos invalida os anteriores. O segundo fator não pode ser desativado por esta interface.</p>
            <a class="text-link mt-6 inline-block" href="{{ route('home') }}">Continuar para o início</a>
        @endif
    </section>
</x-layout>
