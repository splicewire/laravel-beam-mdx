<?php

namespace Splicewire\Beam\Mdx\Lens;

use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * The default {@see MdxLens}: shells out to `resources/lens/mdx-lens.mjs`, as beam-ux's NodeTsxLens shells out to its
 * TSX worker. The request goes in as JSON on stdin with the host `root`, so the worker resolves
 * `@splicewire/beam-mdx/lens` from the HOST's node_modules; the result comes back as JSON on stdout. A read failure
 * is the author's MDX, so it surfaces as a 422 on `source`, never a 500.
 */
final class NodeMdxLens implements MdxLens
{
    public function __construct(
        private string $binary = 'node',
        private ?string $script = null,
        private ?string $workingDirectory = null,
        private float $timeout = 60.0,
    ) {}

    public function read(string $body, array $context = []): array
    {
        $decoded = $this->run(['op' => 'read', 'body' => $body, 'context' => (object) $context]);

        if (! is_array($decoded['doc'] ?? null) || ! (is_string($decoded['meta'] ?? null) || ($decoded['meta'] ?? null) === null)) {
            throw ValidationException::withMessages(['source' => 'the MDX lens returned no doc.']);
        }

        /** @var array<int, array<string, mixed>> $doc */
        $doc = $decoded['doc'];

        return ['doc' => $doc, 'meta' => $decoded['meta'], 'metaData' => is_array($decoded['metaData'] ?? null) ? $decoded['metaData'] : null];
    }

    public function print(array $doc): string
    {
        $decoded = $this->run(['op' => 'print', 'doc' => $doc]);

        if (! is_string($decoded['mdx'] ?? null)) {
            throw ValidationException::withMessages(['source' => 'the MDX lens printed nothing.']);
        }

        return $decoded['mdx'];
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function run(array $request): array
    {
        $script = $this->script ?? __DIR__.'/../../resources/lens/mdx-lens.mjs';

        if (! is_file($script)) {
            throw ValidationException::withMessages(['source' => "the MDX lens script is missing at [{$script}]."]);
        }

        $root = $this->workingDirectory ?? base_path();

        $process = new Process(
            [$this->binary, $script],
            $this->workingDirectory,
            null,
            json_encode([...$request, 'root' => $root], JSON_THROW_ON_ERROR),
            $this->timeout,
        );

        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            // stderr carries the lens's own diagnostic (MDX that is not a page, a missing host dependency).
            throw ValidationException::withMessages(['source' => trim($process->getErrorOutput()) ?: $e->getMessage()]);
        }

        $decoded = json_decode($process->getOutput(), true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['source' => 'the MDX lens returned no JSON.']);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
