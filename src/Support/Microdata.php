<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Support;

use Illuminate\Support\HtmlString;
use Pushery\AiContentMarking\DigitalSourceType;

/**
 * Writes a marking into HTML as schema.org microdata.
 *
 * The content becomes the `text` of a `CreativeWork` item whose `digitalSourceType` names the
 * IPTC term:
 *
 *     <span itemscope itemtype="https://schema.org/CreativeWork">
 *       <link itemprop="digitalSourceType" href="https://schema.org/TrainedAlgorithmicMediaDigitalSource">
 *       <span itemprop="text">…</span>
 *     </span>
 *
 * The term travels in a `link` element because microdata reads a URL value only from `href`; on
 * any other element the value of the property would be its text. A `link` that carries `itemprop`
 * is allowed in the body and inside phrasing content.
 *
 * @internal
 */
final class Microdata
{
    /** The schema.org type of the item that carries the marking. */
    public const string ITEM_TYPE = 'https://schema.org/CreativeWork';

    /** The schema.org property that names the term. */
    public const string PROPERTY = 'digitalSourceType';

    /**
     * Put content into an item that carries the term.
     *
     * The content is inserted as it is given: the caller escapes plain text before it gets here.
     * `$element` is the element of both the item and the text inside it, so a `span` stays valid
     * inside a paragraph and a `div` can hold blocks.
     */
    public static function wrap(string $content, DigitalSourceType $sourceType, string $element): HtmlString
    {
        $href = htmlspecialchars($sourceType->uri(), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        return new HtmlString(
            '<'.$element.' itemscope itemtype="'.self::ITEM_TYPE.'">'
            .'<link itemprop="'.self::PROPERTY.'" href="'.$href.'">'
            .'<'.$element.' itemprop="text">'.$content.'</'.$element.'>'
            .'</'.$element.'>',
        );
    }
}
