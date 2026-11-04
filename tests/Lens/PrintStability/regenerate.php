<?php

/*
 * Regenerate the lens-print stability expectations: `composer lens-print:regenerate -- --host=<root> [--write]`.
 *
 * `<root>` is a host whose node_modules hold `@splicewire/beam-mdx` built (a beam JS checkout serves; default: the
 * BEAM_MDX_LENS_HOST environment variable). Without `--write` this is a dry run: it prints each item's state and
 * writes nothing. With `--write` it rewrites every `<slug>.print.mdx` from the current lens. It never edits a
 * `<slug>.source.mdx` or corpus.json; adding a corpus item is a hand edit of those two.
 *
 * A print change re-hashes every blocks row, so commit the regenerated prints with a re-stamp note
 * (see LensPrintCorpus::RESTAMP_NOTE). The verification half is LensPrintStabilityTest, which never writes.
 */

use Splicewire\Beam\Mdx\Lens\NodeMdxLens;
use Splicewire\Beam\Mdx\Tests\Lens\PrintStability\LensPrintCorpus;

require __DIR__.'/../../../vendor/autoload.php';

$options = getopt('', ['host:', 'write']);
$host = is_string($options['host'] ?? null) ? $options['host'] : getenv(LensPrintCorpus::HOST_ENV);
$write = array_key_exists('write', $options);

if (! is_string($host) || ! is_file($host.'/node_modules/@splicewire/beam-mdx/package.json')) {
    fwrite(STDERR, 'Pass --host=<root> (or set '.LensPrintCorpus::HOST_ENV.") naming a host whose node_modules hold @splicewire/beam-mdx built.\n");
    exit(2);
}

$lens = new NodeMdxLens(binary: getenv('BEAM_MDX_LENS_NODE') ?: 'node', workingDirectory: $host);
fwrite(STDOUT, 'lens: '.LensPrintCorpus::lensIdentity($host)."\n");

$changed = 0;
foreach (LensPrintCorpus::slugs() as $slug) {
    $print = LensPrintCorpus::printOnce($lens, LensPrintCorpus::body($slug));
    $path = LensPrintCorpus::printPath($slug);
    $state = ! is_file($path) ? 'new' : (file_get_contents($path) === $print ? 'unchanged' : 'changed');
    $changed += $state === 'unchanged' ? 0 : 1;
    fwrite(STDOUT, sprintf("%-10s %s\n", $state, $slug));
    if ($write && $state !== 'unchanged') {
        file_put_contents($path, $print);
    }
}

fwrite(STDOUT, $write
    ? "{$changed} written.".($changed > 0 ? ' Commit them with a re-stamp note: register-from-disk --restamp --sources.' : '')."\n"
    : "{$changed} would change. Nothing was written; pass --write to write.\n");
