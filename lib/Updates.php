<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\ContentHelpers;
use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class UpdatesTransformer extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    public function transform(Entry $entry)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);
        $common = ContentHelpers::getCommonDetailFields($entry, $status, $this->locale);

        $primaryCategory = $entry->category ? $entry->category->inReverse()->one() : null;

        return array_merge($common, [
            'trailPhoto' => Images::extractImageUrl($entry->trailPhoto), // @TODO Raw image. What size(s) should we crop this to?
            'category' => $primaryCategory ? ContentHelpers::categorySummary($primaryCategory, $this->locale) : null,
            'authors' => ContentHelpers::getTags($entry->authors->all(), $this->locale),
            'tags' => ContentHelpers::getTags($entry->tags->all(), $this->locale),
            'summary' => $entry->articleSummary,
            'content' => EntryHelpers::extractFlexibleContent($entry),
        ]);
    }
}
