<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\ContentHelpers;
use biglotteryfund\utils\EntryHelpers;
use biglotteryfund\utils\ProjectStoriesTransformer;
use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class FundingProgrammeTransformer extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    private static function buildTrailImage($imageField)
    {
        return $imageField ? Images::imgixUrl($imageField->imageMedium->one()->url, [
            // 5:2 aspect ratio image
            'w' => 360,
            'h' => 144,
            'crop' => 'faces',
        ]) : null;
    }

    public function transform(Entry $entry)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);
        $commonFields = ContentHelpers::getCommonFields($entry, $status, $this->locale);

        return array_merge($commonFields, [
            'isArchived' => $commonFields['status'] === 'expired' && $entry->legacyPath !== null,
            'description' => $entry->programmeIntro ?? null,
            'footer' => $entry->outroText ?? null,
            'thumbnail' => ContentHelpers::getFundingProgrammeThumbnailUrl($entry),
            'trailImage' => self::buildTrailImage($entry->heroImage->one()),
            'trailImageNew' => self::buildTrailImage(Images::extractNewHeroImageField($entry->hero)),
            'contentSections' => array_map(function ($block) {
                return [
                    'title' => $block->programmeRegionTitle,
                    'body' => $block->programmeRegionBody,
                ];
            }, $entry->programmeRegions->all() ?? []),
            'area' => $entry->programmeArea ? [
                'label' => EntryHelpers::translate($this->locale, $entry->programmeArea->label),
                'value' => $entry->programmeArea->value,
            ] : null,
            'fundingSize' => [
                'minimum' => $entry->minimumFundingSize ? (int) $entry->minimumFundingSize : null,
                'maximum' => $entry->maximumFundingSize ? (int) $entry->maximumFundingSize : null,
                'totalAvailable' => $entry->totalFundingAvailable ?? null,
                'description' => $entry->fundingSizeDescription ?? null,
            ],
            'applicationDeadline' => $entry->applicationDeadline ?? null,
            'organisationType' => $entry->organisationType ?? null,
            'legacyPath' => $entry->legacyPath ?? null,
            'projectStories' => array_map(function ($entry) {
                $transformer = new ProjectStoriesTransformer($this->locale);
                return $transformer->transform($entry);
            }, $entry->relatedProjectStories ? $entry->relatedProjectStories->all() : [])
        ]);
    }
}
