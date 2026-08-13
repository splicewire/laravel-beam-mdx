<?php

namespace Splicewire\Beam\Mdx\Console;

use Illuminate\Console\Command;
use Rushing\Doctor\DoctorFailed;
use Rushing\Doctor\DoctorRegistration;
use Rushing\Doctor\DoctorRunner;
use Rushing\Doctor\DoctorStatus;
use Rushing\Doctor\Finding;
use Splicewire\Beam\Mdx\Doctor\MdxContentPlaneAudit;

/**
 * `php artisan splicewire:beam:mdx:doctor` — audits the file-driven MDX content plane against the
 * locked draft-visibility decision: the plane is wired, and no draft is reachable in a production
 * build. The checks live in {@see MdxContentPlaneAudit} (extracted there by
 * particle-doctrine-followups ticket 08); the shared {@see DoctorRunner} executes it as a gate
 * registration at the `--floor` (default `fail`), and this command renders the findings as
 * `<check>: <detail>` at info (Pass) / warn (Warn) / error (Fail), the same lines it always
 * printed — the shared renderer is deliberately not adopted.
 *
 * Exits non-zero on any hard failure — or, at `--floor=warn`, on a skipped bundle check — so
 * CI / a deploy gate can block on it.
 */
class BeamMdxDoctorCommand extends Command
{
    protected $signature = 'splicewire:beam:mdx:doctor
        {--floor=fail : Severity a finding must reach to fail the run (pass|warn|fail)}';

    protected $description = 'Audit the MDX content plane: wired, and no draft reachable in a production build.';

    public function handle(DoctorRunner $runner): int
    {
        $floor = DoctorStatus::tryFrom(strtolower((string) $this->option('floor')));

        if ($floor === null) {
            $this->components->error('Invalid --floor value; expected one of: pass, warn, fail.');

            return self::FAILURE;
        }

        $failed = false;

        try {
            $report = $runner->run([
                new DoctorRegistration('splicewire/laravel-beam-mdx', MdxContentPlaneAudit::class, gate: true),
            ], $floor);
        } catch (DoctorFailed $failure) {
            $report = $failure->report;
            $failed = true;
        }

        foreach ($report->findings as $finding) {
            $this->render($finding);
        }

        return $this->finish($failed);
    }

    private function render(Finding $finding): void
    {
        match ($finding->status) {
            DoctorStatus::Pass => $this->components->info($finding->check.': '.$finding->detail),
            DoctorStatus::Warn => $this->components->warn($finding->check.': '.$finding->detail),
            DoctorStatus::Fail => $this->components->error($finding->check.': '.$finding->detail),
        };
    }

    private function finish(bool $failed): int
    {
        if ($failed) {
            $this->newLine();
            $this->components->error('MDX plane has blocking failures — a draft could reach production.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
