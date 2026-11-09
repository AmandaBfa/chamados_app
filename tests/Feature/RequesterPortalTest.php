<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Livewire\Portal\MyTickets;
use App\Livewire\Portal\OpenTicket;
use App\Livewire\Portal\TicketDetail;
use App\Models\Role;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\RequesterTickets;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class RequesterPortalTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private beginDedicatedTransaction;
    }

    private User $user;

    private int $category;

    private int $sub;

    public function beginDatabaseTransaction(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('chamados_test', config('database.connections.pgsql.database'));
        $this->assertEmpty(config('database.connections.pgsql.url'));
        $identity = DB::selectOne('SELECT current_database() AS database, current_user AS username');
        $this->assertSame('chamados_test', $identity->database);
        $this->assertSame('chamados_test', $identity->username);
        $this->beginDedicatedTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Evita que duas execuções independentes apaguem os anexos fictícios uma da outra.
        Storage::set('local', Storage::fake('portal-local-'.getmypid()));
        Storage::set('tmp-for-tests', Storage::fake('portal-temp-'.getmypid()));
        Notification::fake();
        $this->user = User::factory()->create();
        $this->asUser($this->user);
        $this->category = DB::table('categories')->insertGetId(['name' => 'Categoria fictícia '.Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
        $this->sub = DB::table('subcategories')->insertGetId(['category_id' => $this->category, 'name' => 'Subcategoria fictícia', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function asUser(User $user): void
    {
        $this->actingAs($user)->withSession(['auth.version' => $user->auth_version]);
    }

    private function input(array $replace = []): array
    {
        return array_replace(['subject' => 'Problema fictício', 'description' => 'Descrição fictícia para testes.', 'category_id' => $this->category,
            'subcategory_id' => $this->sub, 'impact_scope' => 'pessoa', 'affected_process' => 'Atividade de teste', 'work_condition' => 'impedido',
            'started_unknown' => true, 'started_at' => null], $replace);
    }

    private function open(array $input = [], array $files = []): object
    {
        return app(RequesterTickets::class)->open($this->user, $this->input($input), (string) Str::uuid(), $files);
    }

    private function upload(array $files): array
    {
        $url = URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), [], false);
        $response = $this->post($url, ['files' => $files], ['Accept' => 'application/json'])->assertOk();

        return $response->json('paths');
    }

    private function temporary(array $files): array
    {
        $temporary = array_map(fn ($path) => TemporaryUploadedFile::createFromLivewire(TemporaryUploadedFile::extractPathFromSignedPath($path)), $this->upload($files));
        foreach ($temporary as $file) {
            $receipt = DB::table('temporary_upload_receipts')->where('path_hash', hash('sha256', $file->getFilename()))->first();
            $this->assertNotNull($receipt);
            $this->assertSame($this->user->id, $receipt->user_id);
            $this->assertNull($receipt->consumed_at);
            $this->assertTrue(now()->lt($receipt->expires_at));
        }

        return $temporary;
    }

    private function txt(string $name = 'relato.txt'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, 'Conteúdo fictício de diagnóstico, sem dados reais.');
    }

    private function invalid(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail('A operação deveria ter sido rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_pages_opening_author_and_context_come_from_session(): void
    {
        $unit = DB::table('units')->insertGetId(['name' => 'Unidade fictícia', 'city' => 'Cidade de teste']);
        $department = DB::table('departments')->insertGetId(['name' => 'Setor fictício']);
        $this->user->forceFill(['unit_id' => $unit, 'department_id' => $department])->save();
        $other = User::factory()->create();
        $this->get('/chamados/abrir')->assertOk()->assertSee('Unidade fictícia')->assertSee('Setor fictício');
        $this->get('/meus-chamados')->assertOk()->assertSee('Nenhum chamado encontrado');
        $component = Livewire::test(OpenTicket::class)->set('form', $this->input(['requester_id' => $other->id, 'priority' => 'P1', 'status' => 'fechado', 'unit_id' => null]))
            ->call('submit')->assertHasNoErrors();
        $ticket = DB::table('tickets')->where('requester_id', $this->user->id)->first();
        $this->assertNotNull($ticket);
        $component->assertRedirect(route('portal.show', $ticket->id));
        $this->assertSame('novo', $ticket->status);
        $this->assertNull($ticket->priority);
        $this->assertSame($unit, $ticket->unit_id);
        $this->assertSame($department, $ticket->department_id);
        DB::table('units')->where('id', $unit)->update(['name' => 'Unidade renomeada', 'active' => false]);
        $this->user->forceFill(['unit_id' => null, 'department_id' => null])->save();
        $this->get(route('portal.show', $ticket->id))->assertOk()->assertSee('Unidade fictícia')->assertSee('Setor fictício');
        $this->assertDatabaseHas('ticket_events', ['ticket_id' => $ticket->id, 'actor_id' => $this->user->id, 'event_type' => 'ticket.opened', 'visibility' => 'public']);
        Notification::assertNothingSent();
    }

    public function test_own_ticket_and_attachment_isolation_in_get_livewire_search_and_service(): void
    {
        $files = $this->temporary([$this->txt()]);
        $ticket = $this->open(['subject' => 'Assunto privado de A'], $files);
        $attachment = DB::table('attachments')->where('ticket_id', $ticket->id)->first();
        $other = User::factory()->create();
        $this->asUser($other);
        $this->get(route('portal.show', $ticket->id))->assertNotFound();
        $this->get(route('portal.attachment', [$ticket->id, $attachment->id]))->assertNotFound();
        Livewire::test(TicketDetail::class, ['ticketId' => $ticket->id])->assertNotFound();
        Livewire::test(MyTickets::class)->set('search', 'Assunto privado de A')->assertDontSee('Assunto privado de A')->assertSee('Nenhum chamado encontrado');
        $otherTicket = app(RequesterTickets::class)->open($other, $this->input(), (string) Str::uuid());
        $this->get(route('portal.attachment', [$otherTicket->id, $attachment->id]))->assertNotFound();
        $this->asUser($this->user);
        $response = $this->get(route('portal.attachment', [$ticket->id, $attachment->id]));
        $response->assertOk()->assertDownload('relato.txt')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'application/octet-stream');
        Storage::disk('local')->assertExists($attachment->storage_key);
        $this->assertStringStartsWith('tickets/', $attachment->storage_key);
        $this->get('/storage/'.$attachment->storage_key)->assertNotFound();
    }

    public function test_internal_notes_events_and_attachments_absent_from_payloads_and_download(): void
    {
        $ticket = $this->open();
        $internal = DB::table('comments')->insertGetId(['ticket_id' => $ticket->id, 'author_id' => $this->user->id, 'idempotency_key' => Str::uuid(),
            'body' => 'SEGREDO-INTERNO-NAO-VAZAR', 'visibility' => 'internal', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ticket_events')->insert(['ticket_id' => $ticket->id, 'actor_id' => $this->user->id, 'event_type' => 'internal.action',
            'visibility' => 'internal', 'public_summary' => 'EVENTO-INTERNO-NAO-VAZAR', 'details' => '{"secret":"DETALHE-INTERNO"}', 'occurred_at' => now()]);
        $attachment = DB::table('attachments')->insertGetId(['ticket_id' => $ticket->id, 'comment_id' => $internal, 'uploader_id' => $this->user->id,
            'storage_key' => 'tickets/internal.txt', 'download_name' => 'ANEXO-INTERNO.txt', 'mime' => 'text/plain', 'size' => 20, 'sha256' => str_repeat('a', 64), 'visibility' => 'public']);
        $this->get(route('portal.show', $ticket->id))->assertOk()->assertDontSee('SEGREDO-INTERNO')->assertDontSee('EVENTO-INTERNO')->assertDontSee('ANEXO-INTERNO')->assertDontSee('DETALHE-INTERNO');
        $component = Livewire::test(TicketDetail::class, ['ticketId' => $ticket->id])->set('body', 'Comentário público normal')->call('comment')
            ->assertHasNoErrors()->assertSee('Comentário público normal')->assertDontSee('SEGREDO-INTERNO')->assertDontSee('ANEXO-INTERNO')->assertDontSee('DETALHE-INTERNO');
        $serialized = json_encode(app(RequesterTickets::class)->detail($this->user, $ticket->id));
        $this->assertStringNotContainsString('SEGREDO-INTERNO', $serialized);
        $this->assertStringNotContainsString('EVENTO-INTERNO', $serialized);
        $this->assertStringNotContainsString('storage_key', $serialized);
        $this->get(route('portal.attachment', [$ticket->id, $attachment]))->assertNotFound();
        Notification::assertNothingSent();
    }

    public function test_category_change_clears_selection_and_server_rejects_incompatible_or_inactive_catalog(): void
    {
        $other = DB::table('categories')->insertGetId(['name' => 'Outra categoria fictícia']);
        Livewire::test(OpenTicket::class)->set('form.category_id', $this->category)->set('form.subcategory_id', $this->sub)
            ->set('form.category_id', $other)->assertSet('form.subcategory_id', null)->assertSet('form.service_id', null);
        Livewire::test(OpenTicket::class)->set('form', $this->input(['category_id' => $other]))->call('submit')->assertHasErrors('subcategory_id');
        DB::table('categories')->where('id', $this->category)->update(['active' => false]);
        Livewire::test(OpenTicket::class)->set('form', $this->input())->call('submit')->assertHasErrors('category_id');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_livewire_double_submit_and_retry_produce_one_ticket_one_event_and_same_protocol(): void
    {
        $component = Livewire::test(OpenTicket::class)->set('form', $this->input());
        $component->call('submit')->assertHasNoErrors();
        $first = DB::table('tickets')->first();
        $component->call('submit')->assertHasNoErrors()->assertRedirect(route('portal.show', $first->id));
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('ticket_events', 1);
        $same = app(RequesterTickets::class)->open($this->user, [], $first->idempotency_key);
        $this->assertSame($first->protocol, $same->protocol);
        $this->assertSame($first->id, $same->id);
    }

    public function test_comment_and_upload_through_livewire_use_public_author_and_link_correctly(): void
    {
        $ticket = $this->open();
        $paths = $this->upload([$this->txt('diagnostico.txt')]);
        Livewire::test(TicketDetail::class, ['ticketId' => $ticket->id])->call('_finishUpload', 'files', $paths, true)
            ->set('body', '<script>alert("teste")</script> Texto público')->call('comment')->assertHasNoErrors()->assertSee('Texto público');
        $comment = DB::table('comments')->where('ticket_id', $ticket->id)->first();
        $this->assertSame($this->user->id, $comment->author_id);
        $this->assertSame('public', $comment->visibility);
        $this->assertDatabaseHas('attachments', ['ticket_id' => $ticket->id, 'comment_id' => $comment->id, 'visibility' => 'public']);
        $this->get(route('portal.show', $ticket->id))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(', false);
        app(RequesterTickets::class)->comment($this->user, $ticket->id, 'Repetido', $comment->idempotency_key);
        $this->assertDatabaseCount('comments', 1);
    }

    public function test_invalid_excessive_and_active_uploads_rejected_by_endpoint_and_final_submit(): void
    {
        $url = URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), [], false);
        foreach ([UploadedFile::fake()->createWithContent('falso.png', '<html><script>alert(1)</script></html>'),
            UploadedFile::fake()->createWithContent('ativo.pdf', "%PDF-1.4\n1 0 obj << /OpenAction << /S /JavaScript /JS (alert(1)) >> >> endobj\n%%EOF"),
            UploadedFile::fake()->createWithContent('programa.exe', 'programa'),
            UploadedFile::fake()->createWithContent('imagem.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')] as $file) {
            $this->post($url, ['files' => [$file]], ['Accept' => 'application/json'])->assertUnprocessable();
        }
        $this->post($url, ['files' => array_fill(0, 6, $this->txt())], ['Accept' => 'application/json'])->assertUnprocessable();
        $large = UploadedFile::fake()->create('grande.txt', 10241, 'text/plain');
        $this->post($url, ['files' => [$large]], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->invalid(fn () => $this->open([], [$large]), 'files.0');
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('temporary_upload_receipts', 0);
    }

    public function test_valid_image_pdf_text_and_safe_download_names(): void
    {
        $png = UploadedFile::fake()->createWithContent('imagem.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRz8AAAAASUVORK5CYII='));
        $pdf = UploadedFile::fake()->createWithContent('simples.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n");
        $files = $this->temporary([$png, $pdf, $this->txt('../relatório estranho.txt')]);
        $ticket = $this->open([], $files);
        $this->assertSame(3, DB::table('attachments')->where('ticket_id', $ticket->id)->count());
        foreach (DB::table('attachments')->get() as $file) {
            $this->assertDoesNotMatchRegularExpression('/[\r\n\\\\\/]/', $file->download_name);
            Storage::disk('local')->assertExists($file->storage_key);
        }
    }

    public function test_temporary_upload_is_owned_and_cannot_be_reused_by_another_account(): void
    {
        $paths = $this->upload([$this->txt()]);
        $files = array_map(fn ($p) => TemporaryUploadedFile::createFromLivewire(TemporaryUploadedFile::extractPathFromSignedPath($p)), $paths);
        $other = User::factory()->create();
        $this->asUser($other);
        Livewire::test(OpenTicket::class)->call('_finishUpload', 'files', $paths, true)->assertForbidden();
        $this->invalid(fn () => app(RequesterTickets::class)->open($other, $this->input(), (string) Str::uuid(), $files), 'files');
        $this->assertDatabaseCount('tickets', 0);
        $this->asUser($this->user);
        $this->open([], $files);
        $this->invalid(fn () => $this->open([], $files), 'files');
        $this->assertDatabaseCount('tickets', 1);
        $this->get('/livewire/preview-file/'.basename($files[0]->getRealPath()))->assertNotFound();
    }

    public function test_session_revocation_blocks_livewire_actions_and_upload_and_unverified_cannot_enter(): void
    {
        $component = Livewire::test(OpenTicket::class)->set('form', $this->input());
        $this->user->forceFill(['active' => false])->save();
        $component->call('submit')->assertForbidden();
        $this->assertDatabaseCount('tickets', 0);
        $new = User::factory()->create(['email_verified_at' => null]);
        $this->asUser($new);
        $this->get('/meus-chamados')->assertRedirect('/email/verify');
        $url = URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), [], false);
        $this->post($url, ['files' => [$this->txt()]], ['Accept' => 'application/json'])->assertForbidden();
        auth()->logout();
        $this->get('/chamados/abrir')->assertRedirect('/login');
        $this->post($url, ['files' => [$this->txt()]], ['Accept' => 'application/json'])->assertUnauthorized();
    }

    public function test_list_search_status_pagination_and_dates_are_validated(): void
    {
        for ($n = 0; $n < 12; $n++) {
            $this->open(['subject' => 'Chamado fictício '.$n]);
        }
        Livewire::test(MyTickets::class)->assertSee('Chamado fictício 11')->assertDontSee('Chamado fictício 0<', false)
            ->call('gotoPage', 2)->assertSee('Chamado fictício 0')->set('search', 'inexistente')->assertSee('Nenhum chamado encontrado')
            ->set('search', '')->set('status', 'fechado')->assertSee('Nenhum chamado encontrado');
        Livewire::test(OpenTicket::class)->set('form', $this->input(['started_unknown' => false, 'started_at' => null]))->call('submit')->assertHasErrors('started_at');
        Livewire::test(OpenTicket::class)->set('form', $this->input(['started_unknown' => '0', 'started_at' => null]))->call('submit')->assertHasErrors('started_at');
        $this->invalid(fn () => $this->open(['started_unknown' => false, 'started_at' => '2099-01-01T12:00']), 'started_at');
        $ticket = $this->open(['started_unknown' => false, 'started_at' => '2026-01-05T09:30']);
        $this->assertStringContainsString('12:30', $ticket->started_at);
    }

    public function test_database_parent_constraints_and_catalog_history_are_preserved(): void
    {
        $ticket = $this->open();
        $other = DB::table('categories')->insertGetId(['name' => 'Pai incompatível']);
        foreach ([fn () => DB::table('tickets')->where('id', $ticket->id)->update(['category_id' => $other]),
            fn () => DB::table('categories')->where('id', $this->category)->delete()] as $operation) {
            try {
                DB::transaction($operation);
                $this->fail('Restrição relacional deveria impedir a operação.');
            } catch (QueryException $exception) {
                $this->assertContains($exception->errorInfo[0], ['23503', '23001']);
            }
        }
        DB::table('categories')->where('id', $this->category)->update(['active' => false]);
        DB::table('subcategories')->where('id', $this->sub)->update(['active' => false]);
        $this->get(route('portal.show', $ticket->id))->assertOk()->assertSee('Subcategoria fictícia');
    }

    public function test_opening_with_upload_via_livewire_is_idempotent_even_after_file_is_consumed(): void
    {
        $paths = $this->upload([$this->txt()]);
        $component = Livewire::test(OpenTicket::class)->call('_finishUpload', 'files', $paths, true)->set('form', $this->input());
        $component->call('submit')->assertHasNoErrors()->call('submit')->assertHasNoErrors();
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('attachments', 1);
        $this->assertDatabaseCount('ticket_events', 1);
        $this->assertNotNull(DB::table('temporary_upload_receipts')->value('consumed_at'));
    }

    public function test_failed_attachment_rolls_back_metadata_receipts_and_written_private_files(): void
    {
        $files = $this->temporary([$this->txt('primeiro.txt'), $this->txt('segundo.txt')]);
        DB::table('temporary_upload_receipts')->where('path_hash', hash('sha256', $files[1]->getFilename()))->update(['consumed_at' => now()]);
        $this->invalid(fn () => $this->open([], $files), 'files');
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('attachments', 0);
        $this->assertDatabaseCount('ticket_events', 0);
        $this->assertNull(DB::table('temporary_upload_receipts')->where('path_hash', hash('sha256', $files[0]->getFilename()))->value('consumed_at'));
        $this->assertSame([], Storage::disk('local')->allFiles('tickets'));
    }

    public function test_final_submit_revalidates_content_after_temporary_upload(): void
    {
        $files = $this->temporary([$this->txt()]);
        file_put_contents($files[0]->getRealPath(), '<html><script>alert(1)</script></html>');
        $this->invalid(fn () => $this->open([], $files), 'files');
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_admin_has_no_global_ticket_access_and_cannot_reparent_used_subcategory(): void
    {
        $ticket = $this->open();
        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'administrador')->value('id')]);
        $admin->forceFill(['two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();
        $this->asUser($admin);
        $this->withSession(['mfa.user_id' => $admin->id, 'auth.password_confirmed_at' => time()]);
        $this->get(route('portal.show', $ticket->id))->assertNotFound();
        Livewire::test(MyTickets::class)->assertSee('Nenhum chamado encontrado');
        $other = DB::table('categories')->insertGetId(['name' => 'Novo pai fictício']);
        $this->invalid(fn () => app(CatalogService::class)->save($admin, 'subcategories', $this->sub,
            ['name' => 'Subcategoria fictícia', 'category_id' => $other, 'active' => true], 1, 'Tentativa de trocar pai histórico'), 'category_id');
    }

    public function test_livewire_ticket_id_is_locked_against_switching_to_another_requester(): void
    {
        $ticket = $this->open();
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(TicketDetail::class, ['ticketId' => $ticket->id])->set('ticketId', $ticket->id + 1);
    }

    public function test_expired_session_blocks_upload_start_and_comment_action_not_only_get(): void
    {
        $ticket = $this->open();
        $detail = Livewire::test(TicketDetail::class, ['ticketId' => $ticket->id])->set('body', 'Não deve ser salvo');
        $opening = Livewire::test(OpenTicket::class);
        $this->user->forceFill(['auth_version' => $this->user->auth_version + 1])->save();
        $detail->call('comment')->assertForbidden();
        $opening->call('_startUpload', 'files', [], true)->assertForbidden();
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_all_seven_profiles_can_open_and_read_their_own_tickets_as_approved(): void
    {
        foreach (RoleName::cases() as $role) {
            $user = User::factory()->create();
            $user->roles()->sync([Role::where('name', $role->value)->value('id')]);
            if ($role === RoleName::Administrator) {
                $user->forceFill(['two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();
            }
            $this->asUser($user);
            $this->withSession(['mfa.user_id' => $user->id]);
            $component = Livewire::test(OpenTicket::class)->set('form', $this->input())->call('submit')->assertHasNoErrors();
            $ticket = DB::table('tickets')->where('requester_id', $user->id)->first();
            $this->assertNotNull($ticket);
            $component->assertRedirect(route('portal.show', $ticket->id));
            $this->get(route('portal.show', $ticket->id))->assertOk();
        }
    }
}
