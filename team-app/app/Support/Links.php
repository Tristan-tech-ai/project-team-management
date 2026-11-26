<?php

namespace App\Support;

/**
 * Web links typed by people: "instagram.com/p/abc" is accepted and stored as "https://instagram.com/p/abc", so the link still
 * opens the other site when clicked (without a scheme a browser would treat it as a page of this app).
 */
class Links
{
    /** Host with at least one dot (instagram.com, drive.google.com), so a single word such as "abc" is not taken as a link. */
    public const RULE = 'regex:/^https?:\/\/[^\s\/?#.]+(\.[^\s\/?#.]+)+(:\d+)?([\/?#]\S*)?$/i';

    public const MESSAGE = 'Tautan belum lengkap. Contoh: instagram.com/p/abc atau https://drive.google.com/...';

    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return $value === null ? null : (string) $value;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (str_starts_with($value, '//')) {
            return 'https:'.$value;
        }

        return preg_match('~^[a-z][a-z0-9+.-]*://~i', $value) ? $value : 'https://'.$value;
    }
}
