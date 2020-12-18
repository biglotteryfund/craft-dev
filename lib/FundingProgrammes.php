<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\ContentHelpers;
use biglotteryfund\utils\EntryHelpers;
use biglotteryfund\utils\ProjectStoriesTransformer;
use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class FundingProgrammeTransformer extends TransformerAbstract
{
    public function __construct($locale, $isSingle = false, $includeImages = false)
    {
        $this->locale = $locale;
        $this->isSingle = $isSingle;
        $this->includeImages = $includeImages;
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
        $commonFields = ContentHelpers::getCommonFields($entry, $this->locale, $includeHeroes = $this->isSingle, $includeExpiredForTranslations = true);

        if ($entry->type->handle === 'contentPage') {
            return ContentHelpers::getFlexibleContentPage($entry, $this->locale);
        } else {
            $commonProgrammeFields = [
                'isArchived' => $commonFields['status'] === 'expired' && $entry->legacyPath !== null,
                'description' => $entry->programmeIntro ?? null,
                'trailText' => $entry->programmeTrailText ?? null,
                'area' => $entry->programmeArea ? [
                    'label' => EntryHelpers::translate($this->locale, $entry->programmeArea->label),
                    'value' => $entry->programmeArea->value,
                ] : null,
                'fundingSize' => [
                    'minimum' => $entry->minimumFundingSize ? (int)$entry->minimumFundingSize : null,
                    'maximum' => $entry->maximumFundingSize ? (int)$entry->maximumFundingSize : null,
                    'totalAvailable' => $entry->totalFundingAvailable ?? null,
                    'description' => $entry->fundingSizeDescription ?? null,
                ],
                'applicationDeadline' => $entry->applicationDeadline ?? null,
                'organisationType' => $entry->organisationType ?? null,
                'legacyPath' => $entry->legacyPath ?? null,
                'grantLength' => $entry->grantLengthDescription ?? null,
                'notificationBanner' => $entry->notificationBanner && $entry->notificationBanner->notificationTitle ? [
                    'title' => $entry->notificationBanner->notificationTitle,
                    'content' => $entry->notificationBanner->notificationMessage,
                ] : null,
            ];

            if (!$this->isSingle) {
                if ($this->includeImages) {
                    return array_merge($commonFields, $commonProgrammeFields);
                } else {
                    $imageFields = [
                        'thumbnail' => ContentHelpers::getFundingProgrammeThumbnailUrl($entry),
                        'trailImage' => self::buildTrailImage(Images::extractHeroImageField($entry->hero))
                    ];
                    return array_merge($commonFields, $commonProgrammeFields, $imageFields);
                }
            } else {
                // Add in the content fields for single programme display
                return array_merge($commonFields, $commonProgrammeFields, [
                    'footer' => $entry->outroText ?? null,
                    'contentSections' => array_map(function ($block) {
                        return [
                            'title' => $block->programmeRegionTitle,
                            'body' => $block->programmeRegionBody,
                        ];
                    }, $entry->programmeRegions ? $entry->programmeRegions->all() : []),
                    'projectStories' => array_map(function ($entry) {
                        $transformer = new ProjectStoriesTransformer($this->locale);
                        return $transformer->transform($entry);
                    }, $entry->relatedProjectStories ? $entry->relatedProjectStories->all() : [])
                ]);
            }
        }
    }
}
