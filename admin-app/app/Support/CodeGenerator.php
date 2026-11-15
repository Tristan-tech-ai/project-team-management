<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Readable unique codes made from a name (U4), so nobody has to invent one: "PT Kreasi Energi Kita" -> KREASI-ENERGI-KITA,
 * "Kopi Senja" -> KOPI-SENJA, and KOPI-SENJA-2 when it is already used. Legal forms (PT, CV, ...) are left out.
 * The same rule runs in the browser as a preview (resources/js/core.js, initCodePreview).
 */
final class CodeGenerator
{
    private const LEGAL_FORMS = ['PT', 'CV', 'UD', 'TBK', 'PERSERO', 'FIRMA', 'KOPERASI', 'YAYASAN'];

    private const MAX_BASE = 24;

    public static function base(string $name): string
    {
        $words = preg_split('/[^A-Z0-9]+/', Str::upper(Str::ascii($name))) ?: [];
        $words = array_values(array_filter($words, fn ($w) => $w !== '' && ! in_array($w, self::LEGAL_FORMS, true)));
        $code = '';
        foreach ($words as $word) {
            $next = $code === '' ? $word : $code.'-'.$word;
            if (strlen($next) > self::MAX_BASE) {
                break;
            }
            $code = $next;
        }
        if ($code === '' && $words !== []) {
            $code = substr($words[0], 0, self::MAX_BASE);
        }

        return $code !== '' ? $code : 'DATA';
    }

    /** @param  callable(string): bool  $taken  whether a code is already used (soft-deleted rows included) */
    public static function unique(string $name, callable $taken): string
    {
        $base = self::base($name);
        $code = $base;
        for ($n = 2; $taken($code); $n++) {
            $code = $base.'-'.$n;
        }

        return $code;
    }
}
