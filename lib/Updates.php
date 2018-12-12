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
        $commonFields = ContentHelpers::getCommonFields($entry, $status, $this->locale);
        $primaryCategory = $entry->category ? $entry->category->inReverse()->one() : null;

        $trailPhotoUrl = $entry->trailPhoto->one() ? $entry->trailPhoto->one()->url : null;

        $extraFields = [
            'promoted' => $entry->articlePromoted,
            'trailPhoto' => Images::extractImageUrl($entry->trailPhoto),
            'thumbnail' =>  $trailPhotoUrl ? Images::getStandardCrops($trailPhotoUrl) : null,
            'category' => $primaryCategory ? ContentHelpers::categorySummary($primaryCategory, $this->locale) : null,
            'authors' => ContentHelpers::getTags($entry->authors->all(), $this->locale),
            'regions' => $entry->regions ? ContentHelpers::nestedCategorySummary($entry->regions->all(), $this->locale) : [],
            'tags' => ContentHelpers::getTags($entry->tags->all(), $this->locale),
            'summary' => $entry->articleSummary,
            'content' => ContentHelpers::extractFlexibleContent($entry),
            'relatedFundingProgrammes' => array_map(function ($programme) {
                return [
                    'title' => $programme->title,
                    'linkUrl' => $programme->externalUrl ? $programme->externalUrl : EntryHelpers::uriForLocale($programme->uri, $this->locale),
                    'intro' => $programme->programmeIntro,
                    'photo' => Images::extractHeroImage($programme->heroImage),
                    'thumbnail' => ContentHelpers::getFundingProgrammeThumbnailUrl($programme),
                ];
            }, $entry->relatedFundingProgrammes->all() ?? []),
            'updateType' => [
                'name' => $entry->type->name,
                'slug' => str_replace('_', '-', $entry->type->handle),
            ],
        ];

        $siblingCriteria = [
            'section' => 'updates',
            'type' => $entry->type->handle,
        ];

        $nextPost = $entry->getNext($siblingCriteria);
        $prevPost = $entry->getPrev($siblingCriteria);

        $extraFields['siblings'] = [
            'next' => $nextPost ? [
                'title' => $nextPost->title,
                'linkUrl' => EntryHelpers::uriForLocale($nextPost->uri, $this->locale),
            ] : null,
            'prev' => $prevPost ? [
                'title' => $prevPost->title,
                'linkUrl' => EntryHelpers::uriForLocale($prevPost->uri, $this->locale),
            ] : null,
        ];

        if ($entry->type->handle === 'press_releases') {
            $extraFields['articleLink'] = $entry->articleLink ?? null;
            $extraFields['contacts'] = $entry->pressReleaseContacts ?? null;
            $extraFields['notesToEditors'] = $entry->pressReleaseNotesToEditors ?? null;
            $extraFields['documentGroups'] = ContentHelpers::extractDocumentGroups($entry->documentGroups);
        }

        return array_merge($commonFields, $extraFields);
    }
}
