<?php

namespace Splicewire\Beam\Mdx\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Splicewire\Beam\Mdx\Mdx;

/**
 * The policy-free content-authoring endpoints — the write twin of the read gate, so every
 * beam-mdx site inherits raw-MDX read/write by content name.
 *
 *  - `show`   returns the raw current source of a content name as `text/markdown`, for seeding
 *             an in-browser editor.
 *  - `update` writes an edited body back to the content name's `.mdx` file.
 *
 * **This controller decides nothing about *who* may read or write.** Authorization is the
 * HOST's job: the app mounts these routes behind its own permission (`can:author-content`) and
 * the preview-env gate (`beam-mdx.preview`), keeping the package transport-agnostic and
 * policy-free (ADR-0116 — the host owns the wire and the policy). The only refusals the
 * controller itself owns are existence (404 on an absent name) and the preview-env hard stop
 * inherited from {@see Mdx::write()} (belt to the mount's suspenders).
 */
class ContentAuthoringController
{
    /**
     * Show authored content
     *
     * The raw current source behind a content name, for seeding an editor. 404 when no content is
     * authored under that name.
     */
    public function show(Request $request, string $name): Response
    {
        $path = Mdx::path($name);

        abort_if($path === null, 404);

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Update authored content
     *
     * Save an edited body back to a content name. Send it either as a raw `text/markdown` request body
     * or as JSON `{ "source": "…" }`.
     *
     * Returns 204 on success.
     */
    public function update(Request $request, string $name): Response
    {
        $raw = $request->isJson()
            ? (string) $request->input('source', '')
            : $request->getContent();

        Mdx::write($name, $raw);

        return response()->noContent();
    }
}
