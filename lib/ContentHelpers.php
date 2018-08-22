<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\Images;
use craft\elements\Entry;

class ContentHelpers
{
    public static function getCommonDetailFields(Entry $entry, $locale)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);

        return [
            'id' => $entry->id,
            'status' => $status,
            'postDate' => $entry->postDate,
            'dateCreated' => $entry->dateCreated,
            'dateUpdated' => $entry->dateUpdated,
            'availableLanguages' => EntryHelpers::getAvailableLanguages($entry->id, $locale),
            'linkUrl' => $entry->externalUrl ? $entry->externalUrl : EntryHelpers::uriForLocale($entry->uri, $locale),
            'title' => $entry->title,
            'trailText' => $entry->trailText ?? null,
            'hero' => Images::extractHeroImage($entry->heroImage),
            'heroCredit' => $entry->heroImageCredit ?? null,
        ];
    }

}
