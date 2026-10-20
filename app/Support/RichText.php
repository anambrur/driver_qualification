<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Cleans editor (Trix) HTML before it is stored. Only basic formatting and http(s)/mailto
 * links survive; scripts, styles, event handlers and other tags are dropped.
 */
final class RichText
{
    private const ELEMENTS = [
        'p', 'div', 'br', 'strong', 'b', 'em', 'i', 'del', 'h2', 'h3', 'h4',
        'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'hr',
    ];

    public static function clean(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            ->allowElement('a', ['href'])
            ->forceAttribute('a', 'rel', 'noopener')
            ->withMaxInputLength(500_000);

        foreach (self::ELEMENTS as $element) {
            $config = $config->allowElement($element);
        }

        return trim((new HtmlSanitizer($config))->sanitize($html));
    }
}
