@if($errors->any())
    <div role="alert" class="mb-6 rounded-lg border border-red-400 p-4">
        <p class="font-semibold">Confira os dados antes de enviar:</p>
        <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
