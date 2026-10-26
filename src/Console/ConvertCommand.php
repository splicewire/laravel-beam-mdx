<?php

namespace Splicewire\Beam\Mdx\Console;

use Illuminate\Console\Command;
use Splicewire\Beam\Mdx\Convert\MdxFileConverter;

/**
 * Converts a host's MDX files once, at adoption (ADR-0233 d9): every page printed canonically, its fragments placed as
 * components and a static meta made frontmatter, for one reviewed commit. It writes the files and commits nothing; a dry
 * run lists what would change. Pages it leaves as they are, and fragments no page includes, are reported.
 */
class ConvertCommand extends Command
{
    protected $signature = 'splicewire:beam:mdx:convert
        {--path= : The MDX content root (default: beam.mdx.content_path)}
        {--dry-run : List what would change, and write nothing}';

    protected $description = 'Convert a host\'s MDX files once, at adoption: canonical print, fragments as components, meta as frontmatter (ADR-0233 d9).';

    public function handle(MdxFileConverter $converter): int
    {
        $root = (string) ($this->option('path') ?: config('beam.mdx.content_path', resource_path('js/content')));
        $dry = (bool) $this->option('dry-run');
        $report = $converter->convert($root, write: ! $dry);

        $this->info(($dry ? 'Would convert ' : 'Converted ').count($report->converted).' page(s) under '.$root.'; '.count($report->unchanged).' already canonical.');
        foreach ($report->converted as $page) {
            $this->line("  {$page}".(isset($report->legal[$page]) ? '  (now places '.implode(', ', array_map(fn ($c) => "<{$c} />", $report->legal[$page])).', shipped by laravel-beam-mdx)' : ''));
        }
        if ($report->leftOut !== []) {
            $this->warn('Left as they are ('.count($report->leftOut).'):');
            foreach ($report->leftOut as $page => $why) {
                $this->line("  {$page}: {$why}");
            }
        }
        if ($report->hostComponents !== []) {
            $this->line('This host\'s fragments that become its own Authored Components (with its stored pages):');
            foreach ($report->hostComponents as $fragment => $component) {
                $this->line("  {$fragment} → <{$component} />");
            }
        }
        if ($report->unusedFragments !== []) {
            $this->warn('Fragments no page includes (reported, not converted):');
            foreach ($report->unusedFragments as $fragment) {
                $this->line("  {$fragment}");
            }
        }

        return self::SUCCESS;
    }
}
