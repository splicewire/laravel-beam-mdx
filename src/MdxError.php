<?php

namespace Splicewire\Beam\Mdx;

/** The lens's owned validation identities, also available in a host without Beam. */
enum MdxError: string
{
    case LensNoDocument = 'mdx_lens_no_document';
    case LensPrintedNothing = 'mdx_lens_printed_nothing';
    case LensScriptMissing = 'mdx_lens_script_missing';
    case LensFailureDetail = 'mdx_lens_failure_detail';
    case LensNoJson = 'mdx_lens_no_json';

    public function code(): string
    {
        return $this->value;
    }

    public function defaultMessage(): string
    {
        return match ($this) {
            self::LensNoDocument => 'the MDX lens returned no doc.',
            self::LensPrintedNothing => 'the MDX lens printed nothing.',
            self::LensScriptMissing => 'the MDX lens script is missing at [:script].',
            self::LensFailureDetail => ':detail',
            self::LensNoJson => 'the MDX lens returned no JSON.',
        };
    }

    /** @param array<string, scalar> $params */
    public function message(array $params = [], ?string $locale = null): string
    {
        $prefix = 'beam-mdx::';
        $allocator = 'Splicewire\\Beam\\Localization\\TextNamespaces';
        if (app()->bound($allocator) && app($allocator)->namespaceOf('splicewire/laravel-beam-mdx') !== null) {
            $prefix = app($allocator)->ownerOfPackage('splicewire/laravel-beam-mdx')->keyPrefix();
        }
        $suffix = 'messages.'.match ($this) {
            self::LensNoDocument => 'lens_no_document',
            self::LensPrintedNothing => 'lens_printed_nothing',
            self::LensScriptMissing => 'lens_script_missing',
            self::LensFailureDetail => 'lens_failure_detail',
            self::LensNoJson => 'lens_no_json',
        };
        $key = $prefix === null ? null : $prefix.$suffix;
        $line = $key === null ? null : trans($key, $params, $locale);

        if (is_string($line) && $line !== $key) {
            return $line;
        }

        $replace = [];
        foreach ($params as $name => $value) {
            $replace[':'.$name] = (string) $value;
        }

        return strtr($this->defaultMessage(), $replace);
    }
}
