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

    private static function buildTrailImage($imageField)
    {
        return $imageField ? Images::imgixUrl(
            $imageField->imageSmall->one()->url,
            ['w' => '500', 'h' => '333', 'crop' => 'faces']
        ) : null;
    }

    private function getRelatedEntries($entry, $relationType)
    {
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

        $relatedEntries = [];

        foreach ($relatedSearch as $relatedEntry) {
            list('entry' => $relatedEntry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($relatedEntry);
            $commonFields = ContentHelpers::getCommonFields($relatedEntry, $status, $this->locale);

            /**
             * Custom link URL
             * Some sub-pages are just links to external sites or internal files,
             * so we replace the canonical (empty) page with a link.
             */
            $customLinkUrl = null;
            if ($relatedEntry->type->handle === 'linkItem') {
                if ($relatedEntry->documentLink && $relatedEntry->documentLink->one()) {
                    $customLinkUrl = $relatedEntry->documentLink->one()->url;
                } else if ($relatedEntry->externalUrl) {
                    $customLinkUrl = $relatedEntry->externalUrl;
                }
            }

            if ($customLinkUrl) {
                $commonFields['linkUrl'] = $customLinkUrl;
            }

            $relatedEntries[] = array_merge($commonFields, [
                // @TODO: Remove photo in favour of trailImage once all pages have new hero images
                'photo' => self::buildTrailImage(Images::extractImage($relatedEntry->heroImage)),
                'trailImage' => self::buildTrailImage(Images::extractNewHeroImageField($relatedEntry->hero)),
            ]);
        }

        return $relatedEntries;
    }

    public function transform(Entry $entry)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);
        $commonFields = ContentHelpers::getCommonFields($entry, $status, $this->locale);

        $customFields = [
            'introduction' => $entry->introductionText ?? null,
            'segments' => $entry->contentSegment ? array_map(function ($block) {
                $segmentImage = $block->segmentImage->one();
                return [
                    'title' => $block->segmentTitle,
                    'content' => $block->segmentContent,
                    'photo' => $segmentImage ? $segmentImage->url : null,
                ];
            }, $entry->contentSegment->all() ?? []) : [],
            'flexibleContent' => ContentHelpers::extractFlexibleContent($entry),
            'outro' => $entry->outroText ?? null,
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

        return array_merge($commonFields, $customFields);
    }
}
