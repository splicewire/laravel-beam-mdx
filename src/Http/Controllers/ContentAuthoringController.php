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
 *
 * ## Why these two actions carry no `#[ResponseFromData]` (beam-docs-satellite ticket 29)
 *
 * `UndeclaredSurfaceAudit` reports both of them, at every host that mounts them, and that is
 * CORRECT reporting — not a missing edit. You have almost certainly arrived here from that
 * finding's `location`, so the argument lives here rather than in a closed ticket.
 *
 * Neither action has a Data shape to declare. `show` serves the file's bytes as
 * `text/markdown; charset=utf-8`; `update` returns **204, no body at all**. Every legal
 * declaration site the doctrine offers — `#[ResponseFromData]`, `#[StreamsFromData]`, a particle
 * operation's `output:` slot — names a Spatie Data class describing a JSON body, and
 * `#[StreamsFromData]` refuses even to be constructed without one ("an event with no shape is not
 * a declaration. Omit the attribute instead."). There is no spelling here for "a document in its
 * own media type", which is what this pair returns.
 *
 * And wrapping the source in an envelope to manufacture one is not neutral: the raw
 * `text/markdown` PUT exists precisely so the body arrives **byte-exact past `TrimStrings`**,
 * which reaches JSON string input and does not reach a raw request body. A `{ "source": … }`
 * envelope would silently re-open the trailing-whitespace defect this wire was shaped to avoid.
 * The controller still ACCEPTS that JSON spelling as a convenience; it is not the canonical wire,
 * and declaring it would make codegen emit a typed client for the lossy half.
 *
 * ## Why `update` declares no request body either (api-surface-coherence ticket 64)
 *
 * The same argument runs on the input axis, and the sweep that came looking for a DTO here found it
 * already settled. `update`'s canonical wire is a **raw `text/markdown` body**, which exists precisely
 * so the source arrives byte-exact past `TrimStrings`; a `#[RequestFromData]` would name a JSON Data
 * class and thereby publish the lossy `{ "source": … }` spelling as the contract, generating a typed
 * client that can only send the wrong half. There is no Data shape for "the document itself".
 *
 * What the sweep *did* owe this route was a judgement about what it should reject, and the answer is
 * that the two refusals it owns already exist one layer down, in {@see Mdx::write()} — containment
 * (traversal, absolute paths, NUL bytes, symlink escapes) and the preview-env hard stop. They were
 * reaching the client as **500s**, because the controller let their exceptions escape; they are now
 * mapped to the statuses they mean. Nothing new is rejected: exactly the same requests fail, with an
 * honest status. There is deliberately no minimum-length or non-empty rule on the body — clearing a
 * document is a legitimate edit, and inventing a rule to forbid it is what ticket 28 warns against.
 *
 * The general question — whether a document-media-type response is outside the invariant's
 * extension the way `beam/openapi.yaml` already is, and how to say so without handing every future
 * surface an easy escape hatch — is beam-facade ticket 114, opened over
 * `Schemastud\DataSchemas`' schema door. This pair is its second independent specimen. Do not
 * invent a carve-out mechanism here; 114 owns it, and one built for two routes would be the
 * loosely-defined version 114 explicitly reserved.
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

        // Authoring outside a preview environment is refused by the model layer regardless of the
        // mount's own gate. Asked here first so the refusal reads as the policy it is (403) rather than
        // the 500 the escaping RuntimeException produced — and so a genuinely broken content root, which
        // throws the same exception class, still surfaces as the server fault it is.
        abort_unless(Mdx::previewAllowed(), 403, 'Content authoring is disabled outside preview environments.');

        try {
            Mdx::write($name, $raw);
        } catch (\InvalidArgumentException $e) {
            // A caller-supplied name that escapes the content root is a bad request, not a server
            // fault. The message names the rejection only, never a resolved filesystem path.
            abort(422, 'Unsafe content name.');
        }

        return response()->noContent();
    }
}
