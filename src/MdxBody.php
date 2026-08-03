<?php

namespace Splicewire\Beam\Mdx;

/**
 * The **free-tier MDX body machinery** (MIT `laravel-beam-mdx`) folded into BeamUx as the MDX codec's
 * engine (ADR-0164). It is the codec-facing twin of {@see Mdx} (the file-gate twin): where `Mdx` owns
 * *visibility/containment* over a `.mdx` file on disk, `MdxBody` owns the **body ⇄ particle-payload**
 * translation so an mdx entry rides the SAME particle/version/storage treatment a tsx entry gets.
 *
 * It stays free-tier and vendor-neutral: it holds NO notion of `BeamUxEntry`, `type`, or the paid
 * `BodyCodec` port — it only knows "raw MDX text ⇄ a structured body payload". The paid
 * `Splicewire\Beam\Ux\Codec\MdxBodyCodec` adapter references it (paid depends DOWN on free), which is
 * how the MDX codec is *folded in* rather than deleted.
 *
 * The **runtime compile** of that MDX against the kit is a client concern (the `runtime-mdx-plugins`
 * seam, ADR-0122/0139) — the server side deliberately does not transform JSX; it round-trips the raw
 * source through the versioned body, splitting the leading `---` frontmatter block from the content so
 * both survive the payload structure the particle stores.
 */
class MdxBody
{
    /** The particle-payload key holding the raw MDX content body (frontmatter stripped). */
    public const CONTENT_KEY = 'content';

    /** The particle-payload key holding the parsed leading frontmatter fields. */
    public const FRONTMATTER_KEY = 'frontmatter';

    /**
     * Encode raw MDX source text into a structured particle body: the flat frontmatter fields and the
     * content are separated so both are queryable/versioned facets of the same body.
     *
     * @return array<string, mixed>
     */
    public static function encode(string $raw): array
    {
        [$frontmatter, $content] = self::split($raw);

        return [
            self::FRONTMATTER_KEY => $frontmatter,
            self::CONTENT_KEY => $content,
        ];
    }

    /**
     * Decode a structured particle body back into raw MDX source text — the inverse of {@see encode()},
     * so an mdx entry round-trips through the versioned body without loss. A body carrying frontmatter
     * re-emits the leading `---` block; a bare body emits just the content.
     *
     * @param  array<string, mixed>  $body
     */
    public static function decode(array $body): string
    {
        $content = (string) ($body[self::CONTENT_KEY] ?? '');
        $frontmatter = (array) ($body[self::FRONTMATTER_KEY] ?? []);

        if ($frontmatter === []) {
            return $content;
        }

        $lines = ['---'];
        foreach ($frontmatter as $key => $value) {
            $lines[] = $key.': '.$value;
        }
        $lines[] = '---';

        return implode("\n", $lines)."\n".$content;
    }

    /**
     * Split raw MDX into [frontmatter fields, content-without-frontmatter]. Mirrors the flat-scalar
     * frontmatter reader in {@see Mdx} so the two can never disagree on what a `---` block means.
     *
     * @return array{0: array<string, string>, 1: string}
     */
    private static function split(string $raw): array
    {
        if (! preg_match('/^---\r?\n(.*?)\r?\n---\r?\n?/s', $raw, $match)) {
            return [[], $raw];
        }

        $fields = [];
        foreach (preg_split('/\r?\n/', $match[1]) as $line) {
            if (preg_match('/^([A-Za-z0-9_-]+):\s*(.*)$/', $line, $kv)) {
                $fields[$kv[1]] = trim($kv[2], " \t\"'");
            }
        }

        $content = substr($raw, strlen($match[0]));

        return [$fields, $content];
    }
}
