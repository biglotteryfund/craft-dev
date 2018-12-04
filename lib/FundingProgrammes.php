<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\EntryHelpers;
use biglotteryfund\utils\Images;
use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

// @TODO: Replace this with FundingProgrammeTransformerNew when moving to new api endpoint
class FundingProgrammeTransformer extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    public function transform(Entry $entry)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);

        // Use custom thumbnail if one is set, otherwise default to hero image.
        $heroImage = Images::extractImage($entry->heroImage);
        $thumbnailSrc = Images::extractImage($entry->trailPhoto) ??
            ($heroImage ? $heroImage->imageMedium->one() : null);

        $data = [
            'id' => $entry->id,
            'availableLanguages' => EntryHelpers::getAvailableLanguages($entry->id, $this->locale),
            'status' => $status,
            'dateUpdated' => $entry->dateUpdated,
            'title' => $entry->title,
            'url' => $entry->url,
            'path' => $entry->uri,
            'hero' => Images::extractHeroImage($entry->heroImage),
            'heroCredit' => $entry->heroImageCredit ?? null,
            'intro' => $entry->programmeIntro,
            'footer' => $entry->outroText ?? null,
            'summary' => getFundingProgramMatrix($entry, $this->locale)
        ];

        $contentSections = [];
        if ($entry->programmeRegions) {
            foreach ($entry->programmeRegions->all() as $block) {
                switch ($block->type->handle) {
                    case 'programmeRegion':
                        $region = [
                            'title' => $block->programmeRegionTitle,
                            'body' => $block->programmeRegionBody,
                        ];
                        array_push($contentSections, $region);
                        break;
                }
            }
        }

        $data['contentSections'] = $contentSections;

        if ($entry->relatedCaseStudies) {
            $data['caseStudies'] = EntryHelpers::extractCaseStudySummaries($entry->relatedCaseStudies->all());
        }

        return $data;
    }
}

class FundingProgrammeTransformerNew extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    public function transform(Entry $entry)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);
        $commonFields = ContentHelpers::getCommonFields($entry, $status, $this->locale);

        // Use custom thumbnail if one is set, otherwise default to hero image.
        $heroImage = Images::extractImage($entry->heroImage);
        $thumbnailSrc = Images::extractImage($entry->trailPhoto) ??
            ($heroImage ? $heroImage->imageMedium->one() : null);

        return array_merge($commonFields, [
            'description' => $entry->programmeIntro ?? null,
            'footer' => $entry->outroText ?? null,
            'thumbnail' => $thumbnailSrc ? Images::imgixUrl($thumbnailSrc->url, [
                'w' => 100,
                'h' => 100,
                'crop' => 'faces',
            ]) : null,
            'contentSections' => array_map(function($block) {
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
                'maximum' => $entry->maximumFundingSize ? (int) $entry->maximumFundingSize  : null,
                'totalAvailable' => $entry->totalFundingAvailable ?? null,
                'description' => $entry->fundingSizeDescription ?? null
            ],
            'applicationDeadline' => $entry->applicationDeadline ?? null,
            'organisationType' => $entry->organisationType ?? null,
            'legacyPath' => $entry->legacyPath ?? null
        ]);

        $data['contentSections'] = $contentSections;

        if ($entry->relatedCaseStudies) {
            $data['caseStudies'] = EntryHelpers::extractCaseStudySummaries($entry->relatedCaseStudies->all());
        }

        return $data;
    }
}
