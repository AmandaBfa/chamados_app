<?php

namespace Tests\Feature;

use App\Livewire\FoundationCheck;
use Livewire\Livewire;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_foundation_renders_in_portuguese_with_assets_and_livewire(): void
    {
        $this->get('/verificacao')->assertOk()
            ->assertSee('lang="pt-BR"', false)
            ->assertSee('Etapa 1 · Fundação')
            ->assertSee('Acessar o sistema')
            ->assertSee('wire:click="verify"', false)
            ->assertSee('/build/assets/', false);
    }

    public function test_livewire_communication_check(): void
    {
        Livewire::test(FoundationCheck::class)
            ->assertSee('Aguardando verificação.')
            ->call('verify')
            ->assertSee('Comunicação com o servidor verificada.');
    }

    public function test_public_registration_and_private_storage_are_unavailable(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
        $this->get('/storage/private/example.txt')->assertNotFound();
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->assertSame('private', config('filesystems.disks.local.visibility'));
        $this->assertSame('pgsql', config('database.default'));
    }
}
