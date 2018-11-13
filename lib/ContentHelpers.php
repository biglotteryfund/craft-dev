<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\Images;
use craft\elements\Entry;

class ContentHelpers
{
    public static function getCommonDetailFields(Entry $entry, $status, $locale)
    {
        return [
            'id' => $entry->id,
            'slug' => $entry->slug,
            'status' => $status,
            'postDate' => $entry->postDate,
            'dateCreated' => $entry->dateCreated,
            'dateUpdated' => $entry->dateUpdated,
            'availableLanguages' => EntryHelpers::getAvailableLanguages($entry->id, $locale),
            'linkUrl' => $entry->externalUrl ? $entry->externalUrl : EntryHelpers::uriForLocale($entry->uri, $locale),
            'title' => $entry->title,
            'trailText' => $entry->trailText ?? null,
            'hero' => $entry->heroImage ? Images::extractHeroImage($entry->heroImage) : null,
            'heroCredit' => $entry->heroImageCredit ?? null,
        ];
    }

}
