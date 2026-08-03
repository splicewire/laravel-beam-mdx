<?php

namespace Splicewire\Beam\Mdx\Docs\Regenerate;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The shared HTTP-capture seam. Captures a live JSON response from the dev platform through
 * the framework HTTP client — bearer auth, timeout, and the dev-cert TLS-skip — replacing the
 * `curl … -H "Authorization: Bearer …" | jq` the scripts used. Because it goes through the
 * Http facade, tests capture canned responses with `Http::fake()` (no live spend).
 */
class CaptureClient
{
    /**
     * GET a JSON document from the dev platform, relative to the configured base URL.
     *
     * @return array<mixed>
     */
    public function getJson(string $path): array
    {
        return $this->get($path)->json();
    }

    /**
     * GET the verbatim response body — used where a guide commits the raw platform response
     * (e.g. the tenant manifest capture), so its exact bytes (escaped slashes and all) are kept.
     */
    public function getRaw(string $path): string
    {
        return $this->get($path)->body();
    }

    private function get(string $path): Response
    {
        $base = rtrim((string) config('docs-regenerate.platform_base_url'), '/');
        $url = $base.'/'.ltrim($path, '/');

        return Http::withToken((string) config('docs-regenerate.api_token'))
            ->withOptions(['verify' => (bool) config('docs-regenerate.verify_tls')])
            ->timeout(30)
            ->acceptJson()
            ->get($url)
            ->throw();
    }
}
