<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\Images;
use craft\elements\Entry;

class ContentHelpers
{
    public static function extractNewHero(Entry $entry) {
        $newHeroField = $entry->hero ? $entry->hero->one() : null;

        if ($newHeroField) {
            return [
                'image' => $newHeroField->image ? Images::extractHeroImage($newHeroField->image) : null,
                'credit' => $newHeroField->credit ?? null
            ];
        } else {
            return null;
        }
    }

    public static function getCommonFields(Entry $entry, $status, $locale)
    {
        return [
            'id' => $entry->id,
            'entryType' => $entry->type->handle,
            'slug' => $entry->slug,
            'status' => $status,
            'postDate' => $entry->postDate,
            'dateCreated' => $entry->dateCreated,
            'dateUpdated' => $entry->dateUpdated,
            'availableLanguages' => EntryHelpers::getAvailableLanguages($entry->id, $locale),
            // @TODO: Some older pages use path instead of linkUrl in templates, update these uses and then remove this
            'path' => $entry->uri,
            'linkUrl' => $entry->externalUrl ? $entry->externalUrl : EntryHelpers::uriForLocale($entry->uri, $locale),
            'title' => $entry->title,
            // @TODO: Is displayTitle definitely distinct from trailText?
            'displayTitle' => $entry->displayTitle ?? null,
            'trailText' => $entry->trailText ?? null,
            'hero' => $entry->heroImage ? Images::extractHeroImage($entry->heroImage) : null,
            'heroCredit' => $entry->heroImageCredit ?? null,
            'heroNew' => self::extractNewHero($entry)
        ];
    }

    public static function nestedCategorySummary($categories, $locale)
    {
        $data = [];
        foreach ($categories as $category) {
            if ($category->level == 1) {
                $summary = self::categorySummary($category, $locale);
                
                $children = [];
                foreach ($categories as $childCategory) {
                    if ($category->isAncestorOf($childCategory)) {
                        $children[] = self::categorySummary($childCategory, $locale);
                    }
                }
                    
                $summary['children'] = $children;
                $data[] = $summary;
            }
        }

        return $data;
    }

    public static function categorySummary($category, $locale)
    {
        return [
            'title' => $category->title,
            'link' => EntryHelpers::uriForLocale($category->uri, $locale),
            'slug' => $category->slug
        ];
    }

    public static function tagSummary($tag, $locale)
    {
        $tagGroup = $tag->getGroup();
        $basicFields = [
            'id' => (int) $tag->id,
            'title' => $tag->title,
            'slug' => $tag->slug,
            'group' => $tagGroup->handle,
            'groupTitle' => $tagGroup->name,
            'link' => EntryHelpers::uriForLocale("blog/{$tagGroup->handle}/{$tag->slug}", $locale),
        ];

        if ($tagGroup->handle === 'authors') {
            $basicFields['authorTitle'] = $tag->authorTitle ?? null;
            $basicFields['shortBiography'] = $tag->shortBiography ?? null;
            $basicFields['fullBiography'] = $tag->fullBiography ?? null;
            $photoUrl = $tag->photo ? Images::extractImageUrl($tag->photo) : null;
            if ($photoUrl) {
                $basicFields['photo'] = Images::imgixUrl(
                    $photoUrl,
                    [
                        'w' => 200,
                        'h' => 200,
                        'crop' => 'faces',
                    ]
                );
            }
        }

        return $basicFields;
    }

    public static function getTags($tagField, $locale)
    {
        return array_map(function ($tag) use ($locale) {
            return self::tagSummary($tag, $locale);
        }, $tagField);
    }

    /**
     * Extract data for common flexible content matrix field
     * - Content area (Redactor field)
     * - Inline figure (image with a caption)
     * - Media aside (callout block with text, image, and link)
     */
    public static function extractFlexibleContent(Entry $entry)
    {
        $parts = [];
        foreach ($entry->flexibleContent->all() as $block) {
            switch ($block->type->handle) {
                case 'contentArea':
                    $data = [
                        'type' => $block->type->handle,
                        'content' => $block->contentBody,
                    ];

                    array_push($parts, $data);
                    break;
                case 'inlineFigure':
                    $data = [
                        'type' => $block->type->handle,
                        'photo' => Images::imgixUrl(
                            Images::extractImageUrl($block->photo),
                            ['fit' => 'crop', 'crop' => 'entropy', 'max-w' => 2000]
                        ),
                        'photoCaption' => $block->photoCaption ?? null,
                    ];
                    array_push($parts, $data);
                    break;
                case 'mediaAside':
                    $data = [
                        'type' => $block->type->handle,
                        'quoteText' => $block->quoteText,
                        'linkText' => $block->linkText ?? null,
                        'linkUrl' => $block->linkUrl ?? null,
                        'photo' => Images::imgixUrl(
                            Images::extractImageUrl($block->photo),
                            ['w' => '460', 'h' => '280']
                        ),
                        'photoCaption' => $block->photoCaption ?? null,
                    ];

                    array_push($parts, $data);
                    break;
            }
        }
        return $parts;
    }

    /**
     * Extract data for common document groups field
     */
    public static function extractDocumentGroups($documentGroupsField)
    {
        return array_map(function ($group) {
            return [
                'title' => $group->documentsTitle,
                'files' => array_map(function ($file) {
                    return [
                        'label' => $file->title,
                        'href' => $file->url,
                        'filetype' => $file->extension,
                        'filesize' => StringHelpers::formatBytes($file->size, $precision = 0),
                    ];
                }, $group->documentsFiles->all() ?? []),
                'extraContent' => $group->documentsExtra ?? null,
            ];
        }, $documentGroupsField->all() ?? []);
    }
}
