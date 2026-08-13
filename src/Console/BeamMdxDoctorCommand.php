<?php

namespace Splicewire\Beam\Mdx\Console;

use Illuminate\Console\Command;
use Rushing\Doctor\DoctorStatus;
use Rushing\Doctor\Finding;
use Splicewire\Beam\Mdx\Doctor\MdxContentPlaneAudit;

/**
 * `php artisan splicewire:beam:mdx:doctor` — audits the file-driven MDX content plane against the
 * locked draft-visibility decision: the plane is wired, and no draft is reachable in a production
 * build. The checks live in {@see MdxContentPlaneAudit} (extracted there by
 * particle-doctrine-followups ticket 08); this command renders its findings as `<check>: <detail>`
 * at info (Pass) / warn (Warn) / error (Fail), the same lines it always printed.
 *
 * Exits non-zero on any hard failure so CI / a deploy gate can block on it.
 */
class BeamMdxDoctorCommand extends Command
{
    protected $signature = 'splicewire:beam:mdx:doctor';

    protected $description = 'Audit the MDX content plane: wired, and no draft reachable in a production build.';

    public function handle(MdxContentPlaneAudit $audit): int
    {
        $failed = false;

        foreach ($audit->run() as $finding) {
            $this->render($finding);
            $failed = $failed || $finding->status === DoctorStatus::Fail;
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
