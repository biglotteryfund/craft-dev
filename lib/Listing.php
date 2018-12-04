<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\ContentHelpers;
use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class ListingTransformer extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    private static function extractBasicEntryData(Entry $entry)
    {
        $basicData = [
            'id' => $entry->id,
            'path' => $entry->uri,
            'url' => $entry->url,
            'title' => $entry->title,
            'dateUpdated' => $entry->dateUpdated,
        ];

        if ($entry->themeColour) {
            $basicData['themeColour'] = $entry->themeColour->value;
        }

        if ($entry->trailText) {
            $basicData['trailText'] = $entry->trailText;
        }

        return $basicData;
    }

    private function getRelatedEntries($entry, $relationType)
    {
        $relatedEntries = [];
        $relatedSearch = [];

        if ($relationType === 'ancestors') {
            $relatedSearch = $entry->getAncestors()->all();
        } else if ($relationType === 'children') {
            $relatedSearch = $entry->getChildren()->all();
        } else if ($relationType === 'siblings') {
            // get parent first to allow including self as a sibling
            $parent = $entry->getParent();
            if ($parent) {
                $relatedSearch = $parent->getDescendants(1)->all();
            }
        }

        foreach ($relatedSearch as $relatedItem) {
            $relatedData = self::extractBasicEntryData($relatedItem);
            $relatedData['isCurrent'] = $entry->uri == $relatedData['path'];
            $relatedData['link'] = EntryHelpers::uriForLocale($relatedItem->uri, $this->locale);

            $heroImage = Images::extractImage($relatedItem->heroImage);
            $relatedData['photo'] = Images::imgixUrl($heroImage->imageSmall->one()->url, [
                'w' => 500,
                'h' => 333,
                'crop' => 'faces',
            ]);

            // Some sub-pages are just links to external sites or internal files
            // so we replace the canonical (empty) page with a link
            $entryType = $relatedItem->type->handle;
            if ($entryType === 'linkItem') {
                $relatedData['entryType'] = $entryType;
                // is this a document?
                if ($relatedItem->documentLink && $relatedItem->documentLink->one()) {
                    $relatedData['link'] = $relatedItem->documentLink->one()->url;
                    // or is it an external URL?
                } else if ($relatedItem->externalUrl) {
                    $relatedData['link'] = $relatedItem->externalUrl;
                }
            }

            $relatedEntries[] = $relatedData;
        }

        return $relatedEntries;
    }


    public function transform(Entry $entry)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);
        $commonFields = ContentHelpers::getCommonFields($entry, $status, $this->locale);

        $customFields = [
            'introduction' => $entry->introductionText ?? null,
            'segments' => array_map(function ($block) {
                $segmentImage = $block->segmentImage->one();
                return [
                    'title' => $block->segmentTitle,
                    'content' => $block->segmentContent,
                    'photo' => $segmentImage ? $segmentImage->url : null,
                ];
            }, $entry->contentSegment->all() ?? []),
            'outro' => $entry->outroText ?? null,
            'relatedContent' => $entry->relatedContent ?? null
        ];

        $ancestors = self::getRelatedEntries($entry, 'ancestors');
        if (count($ancestors) > 0) {
            $customFields['ancestors'] = $ancestors;
        }

        $children = self::getRelatedEntries($entry, 'children');
        if (count($children) > 0) {
            $customFields['children'] = $children;
        }

        $siblings = self::getRelatedEntries($entry, 'siblings');
        if (count($siblings) > 0) {
            $customFields['siblings'] = $siblings;
        }

        if ($entry->relatedCaseStudies) {
            $customFields['caseStudies'] = EntryHelpers::extractCaseStudySummaries($entry->relatedCaseStudies->all());
        }

        return array_merge($commonFields, $customFields);
    }
}
