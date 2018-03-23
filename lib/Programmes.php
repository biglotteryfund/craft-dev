<?php

namespace biglotteryfund\utils;

use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class ProgrammeHelpers
{
    public static function extractSummary($entry, $locale)
    {
        $useNewContent = (bool) $entry->useNewContent;
        $block = $entry->fundingProgramme->one();

        $fundingData = [
            'title' => $block->programmeTitle
        ];

        /**
         * If useNewContent switch is enabled set linkUrl to the
         * cannonical uri rather than the custom linkUrl field.
         */
        $pathLinkUrl = $locale === 'cy' ? "/welsh/$entry->uri" : "/$entry->uri";
        $fundingData['linkUrl'] = $useNewContent ? $pathLinkUrl : $block->linkUrl;

        // Use custom thumbnail if one is set, otherwise default to hero image.
        $heroImage = Images::extractImage($entry->heroImage);
        $thumbnailSrc = Images::extractImage($block->photo) ?? $heroImage->imageMedium->one();

        $fundingData['photo'] = Images::imgixUrl($thumbnailSrc->url, [
            'w' => 100,
            'h' => 100,
            'crop' => 'faces',
        ]);

        $orgTypes = [];
        foreach ($block->organisationType as $o) {
            $orgTypes[] = EntryHelpers::translate($locale, $o->label);
        }

        if ($orgTypes) {
            $fundingData['organisationTypes'] = $orgTypes;
        }

        $fundingData['description'] = $entry->programmeIntro ?: null;

        if ($block->area) {
            $fundingData['area'] = [
                'label' => EntryHelpers::translate($locale, $block->area->label),
                'value' => $block->area->value,
            ];
        }

        if ($block->minimumFundingSize && $block->maximumFundingSize) {
            $fundingData['fundingSize'] = [
                'minimum' => (int) $block->minimumFundingSize,
                'maximum' => (int) $block->maximumFundingSize,
            ];
        }

        if ($block->fundingSizeDescription) {
            $fundingData['fundingSizeDescription'] = $block->fundingSizeDescription;
        }

        if ($block->totalAvailable) {
            $fundingData['totalAvailable'] = $block->totalAvailable;
        }

        if ($block->applicationDeadline) {
            $fundingData['applicationDeadline'] = $block->applicationDeadline;
        }

        return $fundingData;
    }

    public static function extractContentSections($entry)
    {
        return array_map(function ($block) {
            return [
                'title' => $block->programmeRegionTitle,
                'body' => $block->programmeRegionBody,
            ];
        }, $entry->programmeRegions->all());
    }
}

class FundingProgrammeTransformer extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    public function transform(Entry $entry)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);

        if ($entry->useNewContent === false) {
            throw new \yii\web\NotFoundHttpException('Programme not found');
        }

        $data = [
            'id' => $entry->id,
            'availableLanguages' => EntryHelpers::getAvailableLanguages($entry->id, $this->locale),
            'status' => $status,
            'dateUpdated' => $entry->dateUpdated,
            'title' => $entry->title,
            'url' => $entry->url,
            'path' => $entry->uri,
            'intro' => $entry->programmeIntro,
            'hero' => Images::extractHeroImage($entry->heroImage),
            'summary' => ProgrammeHelpers::extractSummary($entry, $this->locale),
            'contentSections' => ProgrammeHelpers::extractContentSections($entry),
        ];

        if ($entry->relatedCaseStudies) {
            $data['caseStudies'] = EntryHelpers::extractCaseStudySummaries($entry->relatedCaseStudies->all());
        }

        return $data;
    }
}

class FundingProgrammesTransformer extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    public function transform(Entry $entry)
    {
        return [
            'id' => $entry->id,
            'status' => $entry->status,
            'title' => $entry->title,
            'url' => $entry->url,
            'urlPath' => $entry->uri,
            'content' => ProgrammeHelpers::extractSummary($entry, $this->locale),
        ];
    }
}
