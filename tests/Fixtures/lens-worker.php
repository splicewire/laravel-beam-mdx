<?php

// A local subprocess speaking the worker's real stdin/stdout contract; no network or host package is used.
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$mode = $input['op'] === 'read' ? $input['body'] : ($input['doc'][0]['mode'] ?? 'no-print');

if ($mode === 'stderr') {
    fwrite(STDERR, "  RAW diagnostic :missing Ω /path?x=1\n");
    exit(7);
}
if ($mode === 'empty-stderr') {
    exit(9);
}
if ($mode === 'no-json') {
    echo 'not-json';
    exit(0);
}

echo json_encode($mode === 'valid' ? ['doc' => [], 'meta' => null] : [], JSON_THROW_ON_ERROR);
