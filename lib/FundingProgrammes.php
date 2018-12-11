<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\EntryHelpers;
use biglotteryfund\utils\ContentHelpers;
use biglotteryfund\utils\Images;
use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class FundingProgrammeTransformer extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    public function transform(Entry $entry)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);
        $commonFields = ContentHelpers::getCommonFields($entry, $status, $this->locale);

        return array_merge($commonFields, [
            'description' => $entry->programmeIntro ?? null,
            'footer' => $entry->outroText ?? null,
            'thumbnail' => ContentHelpers::getFundingProgrammeThumbnailUrl($entry),
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
            'caseStudies' => $entry->relatedCaseStudies ? EntryHelpers::extractCaseStudySummaries($entry->relatedCaseStudies->all()) : []
        ]);
    }
}
