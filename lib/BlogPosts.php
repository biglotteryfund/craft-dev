<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\EntryHelpers;
use craft\elements\Category;
use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class BlogHelpers
{
    public static function tagSummary($tag, $locale)
    {
        $tagGroup = $tag->getGroup();
        return [
            'id' => (int) $tag->id,
            'title' => $tag->title,
            'slug' => $tag->slug,
            'group' => $tagGroup->handle,
            'groupTitle' => $tagGroup->name,
            'link' => EntryHelpers::uriForLocale("blog/{$tagGroup->handle}/{$tag->slug}", $locale),
        ];
    }

    public static function categorySummary($category, $locale)
    {
        return [
            'title' => $category->title,
            'link' => EntryHelpers::uriForLocale($category->uri, $locale),
            'slug' => $category->slug,
        ];
    }

    public static function getTags($tagField, $locale)
    {
        return array_map(function ($tag) use ($locale) {
            return self::tagSummary($tag, $locale);
        }, $tagField);
    }
}

class BlogTransformer extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    public function transform(Entry $entry)
    {
        $primaryCategory = $entry->category->inReverse()->one();
        return [
            'id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'link' => EntryHelpers::uriForLocale($entry->uri, $this->locale),
            'postDate' => $entry->postDate,
            'availableLanguages' => EntryHelpers::getAvailableLanguages($entry->id, $this->locale),
            'category' => BlogHelpers::categorySummary($primaryCategory, $this->locale),
            'authors' => BlogHelpers::getTags($entry->authors->all(), $this->locale),
            'tags' => BlogHelpers::getTags($entry->tags->all(), $this->locale),
            'intro' => $entry->introduction,
            'body' => $entry->body ?? null,
            'flexibleContent' => EntryHelpers::extractFlexibleContent($entry)
        ];
    }
}
