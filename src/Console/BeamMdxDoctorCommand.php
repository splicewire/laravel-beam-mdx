<?php

namespace Splicewire\Beam\Mdx\Console;

use Illuminate\Console\Command;
use Rushing\Doctor\Concerns\RunsDoctorFloor;
use Rushing\Doctor\DoctorRegistration;
use Rushing\Doctor\DoctorRunner;
use Splicewire\Beam\Mdx\Doctor\MdxContentPlaneAudit;

/**
 * `php artisan splicewire:beam:mdx:doctor` — audits the file-driven MDX content plane against the
 * locked draft-visibility decision: the plane is wired, and no draft is reachable in a production
 * build. The checks live in {@see MdxContentPlaneAudit} (extracted there by
 * particle-doctrine-followups ticket 08); the shared {@see DoctorRunner} executes it as a gate
 * registration at the `--floor` (default `fail`), and the findings render as
 * `<check>: <detail>` at info (Pass) / warn (Warn) / error (Fail) via {@see RunsDoctorFloor} —
 * the same lines this command always printed; the shared DoctorRenderer is deliberately not adopted.
 *
 * Exits non-zero on any hard failure — or, at `--floor=warn`, on a skipped bundle check — so
 * CI / a deploy gate can block on it.
 */
class BeamMdxDoctorCommand extends Command
{
    use RunsDoctorFloor;

    protected $signature = 'splicewire:beam:mdx:doctor
        {--floor=fail : Severity a finding must reach to fail the run (pass|warn|fail)}';

    protected $description = 'Audit the MDX content plane: wired, and no draft reachable in a production build.';

    public function handle(DoctorRunner $runner): int
    {
        $floor = $this->parseFloor();

        if ($floor === null) {
            return self::FAILURE;
        }

        [$report, $failed] = $this->runAtFloor($runner, [
            new DoctorRegistration('splicewire/laravel-beam-mdx', MdxContentPlaneAudit::class, gate: true),
        ], $floor);

        $this->renderFindings($report->findings);

        return $this->finish($failed);
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
