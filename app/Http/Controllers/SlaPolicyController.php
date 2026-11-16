<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Services\PortalAccess;
use App\Services\Sla\BusinessCalendar;
use App\Services\Sla\TicketSla;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;

class SlaPolicyController extends Controller
{
    public function index(Request $request, PortalAccess $access)
    {
        $actor = $request->user();
        $access->authorize($actor);
        abort_unless($actor->hasRole(RoleName::Administrator) || $actor->hasRole(RoleName::Manager), 403);
        $current = DB::table('sla_versions')->find(TicketSla::effectiveVersion(CarbonImmutable::now('UTC')));
        $versions = DB::table('sla_versions')
            ->leftJoin('users as configurator', 'configurator.id', '=', 'sla_versions.configured_by')
            ->leftJoin('users as approver', 'approver.id', '=', 'sla_versions.approved_by')
            ->orderByDesc('sla_versions.id')
            ->paginate(15, ['sla_versions.*', 'configurator.name as configurator_name', 'approver.name as approver_name']);

        $holidays = [];
        foreach (config('sla_holidays.movable') as $year => $movable) {
            foreach (config('sla_holidays.fixed') + $movable as $day => $name) {
                $holidays[$year.'-'.$day] = $name;
            }
        }
        $today = CarbonImmutable::now($current->timezone);

        return view('admin.sla', compact('current', 'versions', 'holidays', 'today'));
    }

    public function store(Request $request, PortalAccess $access)
    {
        $actor = $request->user();
        $access->authorize($actor);
        abort_unless($actor->hasRole(RoleName::Administrator), 403);
        $data = $request->validate([
            'base_version_id' => ['required', 'integer'],
            'timezone' => ['required', 'string', 'max:64'],
            'weekly_windows' => ['required', 'string', 'max:8000'],
            'exceptions' => ['required', 'string', 'max:32000'],
            'exception_notes' => ['nullable', 'string', 'max:64000'],
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
            'targets' => ['required', 'array:P1,P2,P3,P4'],
            'targets.P1' => ['required', 'array:0,1'],
            'targets.P2' => ['required', 'array:0,1'],
            'targets.P3' => ['required', 'array:0,1'],
            'targets.P4' => ['required', 'array:0,1'],
            'targets.*.0' => ['required', 'integer', 'min:1', 'max:525600'],
            'targets.*.1' => ['required', 'integer', 'min:1', 'max:525600'],
        ]);
        try {
            $week = json_decode($data['weekly_windows'], true, 8, JSON_THROW_ON_ERROR);
            $exceptions = json_decode($data['exceptions'], true, 8, JSON_THROW_ON_ERROR);
            if (! is_array($week) || ! is_array($exceptions)) {
                throw new InvalidArgumentException('Informe as janelas e exceções como objetos JSON.');
            }
            new BusinessCalendar($data['timezone'], $week, $exceptions);
            $notes = json_decode($data['exception_notes'] ?? '{}', true, 8, JSON_THROW_ON_ERROR);
            if (! is_array($notes)) {
                throw new InvalidArgumentException('Confira as observações das datas.');
            }
            foreach ($notes as $date => $note) {
                if (! array_key_exists($date, $exceptions) || ! is_string($note) || mb_strlen($note) > 160) {
                    throw new InvalidArgumentException('Cada observação deve ter até 160 caracteres e corresponder a uma exceção do calendário.');
                }
            }
        } catch (JsonException|InvalidArgumentException $error) {
            throw ValidationException::withMessages(['weekly_windows' => $error instanceof JsonException
                ? 'Confira o formato JSON das janelas e exceções.' : $error->getMessage()]);
        }

        $targets = [];
        foreach ($data['targets'] as $priority => [$response, $solution]) {
            $targets[$priority] = [(int) $response, (int) $solution];
        }

        DB::transaction(function () use ($access, $actor, $data, $week, $exceptions, $notes, $targets) {
            DB::select('SELECT pg_advisory_xact_lock(73006)');
            $access->authorize($actor);
            abort_unless($actor->hasRole(RoleName::Administrator), 403);
            $now = CarbonImmutable::now('UTC');
            $base = TicketSla::effectiveVersion($now);
            if ($base !== (int) $data['base_version_id']) {
                throw ValidationException::withMessages(['base_version_id' => 'A política vigente mudou. Revise a nova versão antes de propor alterações.']);
            }
            if (! isset($data['exception_notes'])) {
                $previous = DB::table('sla_versions')->where('id', $base)->value('exception_notes');
                $notes = array_intersect_key(json_decode($previous ?? '{}', true, 8, JSON_THROW_ON_ERROR), $exceptions);
            }
            DB::table('sla_versions')->insert([
                'base_version_id' => $base,
                'timezone' => $data['timezone'],
                'weekly_windows' => json_encode($week, JSON_THROW_ON_ERROR),
                'exceptions' => json_encode((object) $exceptions, JSON_THROW_ON_ERROR),
                'exception_notes' => json_encode((object) $notes, JSON_THROW_ON_ERROR),
                'targets' => json_encode($targets, JSON_THROW_ON_ERROR),
                'reason' => $data['reason'],
                'configured_by' => $actor->id,
                'created_at' => $now,
            ]);
        });

        return redirect()->route('sla.index')->with('status', 'Versão enviada para validação da gerência.');
    }

    public function approve(Request $request, int $version, PortalAccess $access)
    {
        $actor = $request->user();
        $access->authorize($actor);
        abort_unless($actor->hasRole(RoleName::Manager), 403);
        DB::transaction(function () use ($access, $actor, $version) {
            DB::select('SELECT pg_advisory_xact_lock(73006)');
            $access->authorize($actor);
            abort_unless($actor->hasRole(RoleName::Manager), 403);
            $proposal = DB::table('sla_versions')->whereNull('effective_at')->find($version);
            abort_unless($proposal, 404);
            $now = CarbonImmutable::now('UTC');
            if ($proposal->base_version_id !== TicketSla::effectiveVersion($now)) {
                throw ValidationException::withMessages(['version' => 'A versão de referência mudou. Solicite uma proposta atualizada.']);
            }
            DB::table('sla_versions')->where('id', $version)->update(['approved_by' => $actor->id, 'effective_at' => $now]);
        });

        return redirect()->route('sla.index')->with('status', 'Nova versão ativada para as próximas aberturas.');
    }
}
