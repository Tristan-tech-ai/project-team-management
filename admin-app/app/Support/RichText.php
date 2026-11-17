<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Simple formatted text for descriptions (bold, italic, underline, lists, line breaks). The editor in the browser sends
 * HTML; only an allowlist of tags without any attributes is kept, so a description can never carry a script, a style,
 * an event handler or a link. Older descriptions are plain text and are shown with their line breaks.
 */
final class RichText
{
    /** tag => tag it is stored as */
    private const TAGS = ['p' => 'p', 'div' => 'p', 'br' => 'br', 'b' => 'strong', 'strong' => 'strong', 'i' => 'em', 'em' => 'em',
        'u' => 'u', 'ul' => 'ul', 'ol' => 'ol', 'li' => 'li'];

    /** Elements dropped together with everything inside them. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'svg', 'math', 'head', 'title', 'noscript', 'textarea', 'select', 'button'];

    /** Clean HTML from the editor, or null when nothing readable is left. */
    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }
        if (! self::isHtml($html)) {
            return trim($html);
        }
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $body = $doc->getElementsByTagName('body')->item(0);
        $out = $body ? self::children($body) : '';
        $out = preg_replace('#<p>(\s|<br>)*</p>#', '', $out) ?? '';
        $out = preg_replace('#(<br>\s*)+(</(p|li)>)#', '$2', $out) ?? '';
        $out = trim($out);

        return trim(strip_tags($out)) === '' ? null : $out;
    }

    /** HTML that is safe to print for a stored description (cleaned again, plain text keeps its line breaks). */
    public static function toHtml(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }
        if (! self::isHtml($value)) {
            return nl2br(e(trim($value)), false);
        }

        return self::clean($value) ?? '';
    }

    /** The description as one line of plain text (search results, short previews). */
    public static function plain(?string $value): string
    {
        $html = self::toHtml($value);
        $text = html_entity_decode(strip_tags(preg_replace('#<(br|/p|/li)>#', ' ', $html) ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private static function isHtml(string $value): bool
    {
        return (bool) preg_match('#</?[a-z][a-z0-9]*[\s>/]#i', $value);
    }

    private static function children(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= self::node($child);
        }

        return $out;
    }

    private static function node(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            return htmlspecialchars($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (! $node instanceof DOMElement) {
            return '';
        }
        $tag = strtolower($node->tagName);
        if (in_array($tag, self::DROP, true)) {
            return '';
        }
        $inner = self::children($node);
        $as = self::TAGS[$tag] ?? null;
        if ($as === null) {
            return $inner;
        }
        if ($as === 'br') {
            return '<br>';
        }
        if ($as === 'li' && $node->parentNode instanceof DOMElement && ! in_array(strtolower($node->parentNode->tagName), ['ul', 'ol'], true)) {
            return '<p>'.$inner.'</p>';
        }
        // Browsers wrap lists and lines in <div>; a paragraph never holds another block, so such a wrapper is left out.
        if ($as === 'p') {
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['p', 'div', 'ul', 'ol', 'li'], true)) {
                    return $inner;
                }
            }
        }

        return "<{$as}>{$inner}</{$as}>";
    }
}
