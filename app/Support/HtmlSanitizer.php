<?php

namespace App\Support;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Pembersih HTML untuk isi dokumen evidence.
 *
 * Isi dokumen diketik pengguna melalui editor WYSIWYG, lalu ditampilkan ulang
 * di halaman cetak dan disuntikkan ke mesin PDF. Karena itu markup-nya disaring
 * dengan daftar putih tag/atribut/properti CSS — apa pun di luar daftar ini
 * dibuang, termasuk <script>, atribut on*, dan URL javascript:.
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'small',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'blockquote', 'pre', 'code',
        'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'sub', 'sup', 'a', 'img', 'figure', 'figcaption', 'caption', 'col', 'colgroup',
    ];

    private const GLOBAL_ATTRIBUTES = ['style', 'class', 'align', 'valign', 'width', 'height'];

    private const TAG_ATTRIBUTES = [
        'a'     => ['href', 'title', 'target', 'rel'],
        'img'   => ['src', 'alt', 'title'],
        'td'    => ['colspan', 'rowspan'],
        'th'    => ['colspan', 'rowspan', 'scope'],
        'table' => ['border', 'cellpadding', 'cellspacing'],
        'col'   => ['span'],
    ];

    private const ALLOWED_CSS_PROPERTIES = [
        'color', 'background', 'background-color', 'font', 'font-size', 'font-weight',
        'font-style', 'font-family', 'text-align', 'text-decoration', 'text-indent',
        'text-transform', 'line-height', 'letter-spacing', 'vertical-align', 'white-space',
        'width', 'height', 'min-width', 'max-width', 'min-height', 'max-height',
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'border', 'border-top', 'border-right', 'border-bottom', 'border-left',
        'border-color', 'border-style', 'border-width', 'border-collapse', 'border-spacing',
        'list-style', 'list-style-type', 'list-style-position',
        'page-break-before', 'page-break-after', 'page-break-inside', 'float', 'clear',
    ];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"?><div id="__evidence_root__">' . $html . '</div>',
            LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED | LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = (new DOMXPath($document))->query('//*[@id="__evidence_root__"]')->item(0);

        if (! $root instanceof DOMElement) {
            return '';
        }

        self::cleanNode($root);

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private static function cleanNode(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes ?? []) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->nodeName);

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    // Tag terlarang dibuang; isi teksnya dipertahankan kecuali
                    // untuk elemen yang memang membawa kode.
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'link', 'meta'], true)) {
                        $child->parentNode?->removeChild($child);
                    } else {
                        self::cleanNode($child);
                        self::unwrap($child);
                    }

                    continue;
                }

                self::cleanAttributes($child, $tag);
                self::cleanNode($child);
                continue;
            }

            // Komentar & instruksi pemrosesan tidak dibutuhkan di dokumen cetak.
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $child->parentNode?->removeChild($child);
            }
        }
    }

    private static function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = array_merge(self::GLOBAL_ATTRIBUTES, self::TAG_ATTRIBUTES[$tag] ?? []);

        foreach (iterator_to_array($element->attributes) as $attribute) {
            /** @var DOMAttr $attribute */
            $name = strtolower($attribute->nodeName);

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);
                continue;
            }

            $value = $attribute->nodeValue ?? '';

            if ($name === 'style') {
                $clean = self::cleanStyle($value);
                $clean === '' ? $element->removeAttribute('style') : $element->setAttribute('style', $clean);
                continue;
            }

            if (($name === 'href' || $name === 'src') && ! self::isSafeUrl($value, $name)) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        if ($tag === 'a' && $element->hasAttribute('target')) {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function cleanStyle(string $style): string
    {
        $declarations = [];

        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            $lower    = strtolower($value);

            if (! in_array($property, self::ALLOWED_CSS_PROPERTIES, true) || $value === '') {
                continue;
            }

            // url(), expression() dan sejenisnya bisa memuat kode/permintaan keluar.
            if (preg_match('/(expression|javascript:|vbscript:|behaviou?r|@import|url\s*\()/i', $lower)) {
                continue;
            }

            $declarations[] = $property . ': ' . $value;
        }

        return implode('; ', $declarations);
    }

    private static function isSafeUrl(string $url, string $attribute): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        // Gambar hanya boleh berupa data URI atau berkas milik aplikasi sendiri.
        // Menutup pintu bagi gambar pihak ketiga yang bisa dipakai melacak
        // siapa saja yang membuka dokumen.
        if ($attribute === 'src') {
            return (bool) preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $url)
                || (str_starts_with($url, '/') && ! str_starts_with($url, '//'));
        }

        if (str_starts_with($url, '#') || (str_starts_with($url, '/') && ! str_starts_with($url, '//'))) {
            return true;
        }

        return (bool) preg_match('#^https?://#i', $url) || str_starts_with(strtolower($url), 'mailto:');
    }

    /** Ganti sebuah elemen dengan anak-anaknya. */
    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (! $parent) {
            return;
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            $parent->insertBefore($child, $element);
        }

        $parent->removeChild($element);
    }
}
