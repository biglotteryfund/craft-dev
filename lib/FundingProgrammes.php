<?php
namespace biglotteryfund\utils;

use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class FundingProgrammeTransformer extends TransformerAbstract
{
    public function __construct($locale, $slug)
    {
        $this->locale = $locale;
        $this->slug = $slug;
    }

    public function transform(Entry $entry)
    {
        VersionHelpers::checkValid($entry, "/funding/programmes/$this->slug", $this->locale);

        $data = [
            'id' => $entry->id,
            'availableLanguages' => EntryHelpers::getAvailableLanguages($entry->id, $this->locale),
            'status' => VersionHelpers::getNormalisedStatus($entry),
            'dateUpdated' => $entry->dateUpdated,
            'title' => $entry->title,
            'url' => $entry->url,
            'path' => $entry->uri,
            'hero' => Images::extractHeroImage($entry->heroImage),
            'summary' => getFundingProgramMatrix($entry, $this->locale),
            'intro' => $entry->programmeIntro,
            'contentSections' => array_map(function ($block) {
                return [
                    'title' => $block->programmeRegionTitle,
                    'body' => $block->programmeRegionBody,
                ];
            }, $entry->programmeRegions->all()),
        ];

        if ($entry->relatedCaseStudies) {
            $data['caseStudies'] = EntryHelpers::extractCaseStudySummaries($entry->relatedCaseStudies->all());
        }

        return $data;
    }
}
