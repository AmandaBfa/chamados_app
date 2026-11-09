<section class="panel mx-auto max-w-2xl">
    <x-portal-errors />
    <p class="muted mb-6">Unidade: {{ $unit ?? 'Não informada no cadastro' }} · Setor: {{ $department ?? 'Não informado no cadastro' }}. Esses dados vêm da sua conta.</p>
    <form wire:submit="submit" class="space-y-6">
        <div><label for="ticket-subject">Assunto</label><input id="ticket-subject" wire:model="form.subject" maxlength="{{ config('tickets.max_subject') }}" required autocomplete="off"></div>
        <div><label for="ticket-category">Categoria</label><select id="ticket-category" wire:model.live="form.category_id" required><option value="">Selecione, ou escolha Não sei identificar</option>@foreach($categories as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></div>
        @if($subcategories->isNotEmpty())
            <div><label for="ticket-subcategory">Subcategoria (opcional)</label><select id="ticket-subcategory" wire:model.live="form.subcategory_id"><option value="">Não sei identificar</option>@foreach($subcategories as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></div>
        @endif
        <div><label for="ticket-description">Descrição do problema</label><p id="description-help" class="muted text-sm">Conte o que você estava tentando fazer, o que aconteceu e se apareceu alguma mensagem de erro.</p><textarea id="ticket-description" wire:model="form.description" required rows="5" maxlength="{{ config('tickets.max_description') }}" aria-describedby="description-help"></textarea></div>
        <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="identifyResource">Sei qual serviço ou equipamento foi afetado</label>
        @if($identifyResource)
            <div><label for="ticket-service">Serviço (opcional)</label><select id="ticket-service" wire:model="form.service_id"><option value="">Não identificado</option>@foreach($services as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></div>
            <div><label for="ticket-equipment">Equipamento (opcional)</label><select id="ticket-equipment" wire:model="form.equipment_reference_id"><option value="">Não identificado</option>@foreach($equipment as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></div>
        @endif
        <fieldset class="space-y-5"><legend class="mb-2 font-semibold">Como o problema afeta o trabalho?</legend>
            <div><label for="ticket-impact">Quem foi afetado?</label><select id="ticket-impact" wire:model="form.impact_scope" required><option value="">Selecione</option><option value="pessoa">Uma pessoa</option><option value="equipe">Uma equipe</option><option value="unidade">Uma unidade</option><option value="maior">Mais de uma unidade / abrangência maior</option></select></div>
            <div><label for="ticket-process">Atividade ou processo prejudicado</label><textarea id="ticket-process" wire:model="form.affected_process" required rows="2" maxlength="500"></textarea></div>
            <div><label for="ticket-work">Você consegue continuar trabalhando?</label><select id="ticket-work" wire:model="form.work_condition" required><option value="">Selecione</option><option value="continua">Sim, consigo continuar</option><option value="parcialmente_impedido">Consigo parcialmente</option><option value="impedido">Não consigo continuar</option></select></div>
        </fieldset>
        <div>
            <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="form.started_unknown">Não sei quando começou</label>
            @if(!$form['started_unknown'])<label class="mt-4 block" for="ticket-start">Início aproximado ({{ config('installation.display_timezone') }})</label><input id="ticket-start" wire:model="form.started_at" type="datetime-local" required>@endif
        </div>
        <x-portal-files :files="$files" id="opening-files" />
        <button class="primary" type="submit" wire:loading.attr="disabled" wire:target="submit,files">Registrar chamado</button>
    </form>
</section>
