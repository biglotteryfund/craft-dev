<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\ContentHelpers;
use biglotteryfund\utils\EntryHelpers;
use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class BlogTransformer extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    public function transform(Entry $entry)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);

        $primaryCategory = $entry->category->inReverse()->one();

        return [
            'id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'status' => $entry->status,
            'link' => EntryHelpers::uriForLocale($entry->uri, $this->locale),
            'postDate' => $entry->postDate,
            'availableLanguages' => EntryHelpers::getAvailableLanguages($entry->id, $this->locale),
            'category' => ContentHelpers::categorySummary($primaryCategory, $this->locale),
            'authors' => ContentHelpers::getTags($entry->authors->all(), $this->locale),
            'tags' => ContentHelpers::getTags($entry->tags->all(), $this->locale),
            'intro' => $entry->introduction,
            'body' => $entry->body ?? null,
            'flexibleContent' => ContentHelpers::extractFlexibleContent($entry),
        ];
    }
}
