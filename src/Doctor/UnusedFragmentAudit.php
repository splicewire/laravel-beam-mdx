<?php

namespace Splicewire\Beam\Mdx\Doctor;

use Illuminate\Support\Str;
use Rushing\Doctor\DoctorAudit;
use Rushing\Doctor\Finding;
use Splicewire\Beam\Mdx\Convert\MdxFileConverter;

/**
 * A fragment no page includes (ADR-0233 d8): it is reported, not converted, so a host finds it before the conversion
 * would silently carry or drop it. ADVISORY: which fragments a host keeps is the host's. A page includes a fragment by
 * its content name (`<Content name="fragments/advisory" />`) or, once converted, by its component's tag.
 */
class UnusedFragmentAudit implements DoctorAudit
{
    private const CHECK = 'every MDX fragment is included by a page';

    /** @return list<Finding> */
    public function run(): array
    {
        $root = rtrim((string) config('beam.mdx.content_path', resource_path('js/content')), '/');
        if (! is_dir($root)) {
            return [Finding::inconclusive(self::CHECK, "no MDX content root at {$root}.")];
        }
        $fragments = $pages = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'mdx') {
                $relative = substr($file->getPathname(), strlen($root) + 1);
                MdxFileConverter::isFragment($relative) ? $fragments[] = substr($relative, 0, -4) : $pages[] = (string) file_get_contents($file->getPathname());
            }
        }
        $text = implode("\n", $pages);
        $unused = array_values(array_filter($fragments, fn (string $name) => ! str_contains($text, 'name="'.$name.'"')
            && preg_match('/<'.preg_quote(Str::studly(basename($name)), '/').'[\s\/>]/', $text) !== 1));
        sort($unused);

        return [$unused === []
            ? Finding::pass(self::CHECK, count($fragments).' fragment(s), each included by a page.')
            : Finding::warn(self::CHECK, count($unused).' fragment(s) no page includes, reported rather than converted: '.implode(', ', $unused).'.')];
    }
}
