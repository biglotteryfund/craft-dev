<?php

namespace biglotteryfund\utils;

use craft\elements\Entry;
use biglotteryfund\utils\Images;

class EntryHelpers
{
    /**
     * extractCaseStudySummary
     * Extract a summary object from a case study entry
     */
    public static function extractCaseStudySummary(Entry $entry)
    {
        return [
            'id' => $entry->id,
            'slug' => $entry->slug,
            'title' => $entry->title,
            'linkUrl' => $entry->caseStudyLinkUrl,
            'trailText' => $entry->caseStudyTrailText,
            'trailTextMore' => $entry->caseStudyTrailTextMore,
            'grantAmount' => $entry->caseStudyGrantAmount,
            'thumbnailUrl' => Images::imgixUrl($entry->caseStudyThumbnailImage->one()->url, [
                'w' => 600,
                'h' => 400,
            ]),
        ];
    }

    /**
     * Wrapper around `extractCaseStudySummary`
     * for extracting an array of summaries from a list of case studies
     */
    public static function extractCaseStudySummaries($caseStudies)
    {
        return $caseStudies ? array_map(
            'self::extractCaseStudySummary',
            $caseStudies
        ) : [];
    }

}
