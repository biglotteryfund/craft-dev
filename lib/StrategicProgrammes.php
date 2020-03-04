<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\EntryHelpers;
use biglotteryfund\utils\Images;
use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class StrategicProgrammeTransformer extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    private static function buildTrailImage($imageField)
    {
        return $imageField ? Images::imgixUrl(
            $imageField->imageMedium->one()->url,
            ['w' => '640', 'h' => '360']
        ) : null;
    }

    public function transform(Entry $entry)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);
        $commonFields = ContentHelpers::getCommonFields($entry, $status, $this->locale);

        if ($entry->type->handle === 'contentPage') {
            return ContentHelpers::getFlexibleContentPage($entry, $this->locale);
        } else {

            $latestContent = [];
            if ($entry->strategicProgrammeLatestContent) {

                $latestContent['heading'] = $entry->strategicProgrammeLatestContent->heading;
                $latestContent['introduction'] = $entry->strategicProgrammeLatestContent->introduction ?? null;

                if ($entry->strategicProgrammeLatestContent->relatedItems) {
                    $related = $entry->strategicProgrammeLatestContent->relatedItems->all();
                    $latestContent['items'] = array_map(function ($item) {
                        $entry = $item->entry->one();
                        $commonFields = ContentHelpers::getCommonFields($entry, 'live', $this->locale);
                        return array_merge($commonFields, [
                            'summary' => $item->summary,
                            'trailImage' => self::buildTrailImage(Images::extractHeroImageField($entry->hero)),
                            'isFeatured' => $item->featureThisEntry,
                        ]);
                    }, $related ?? []);
                }

            }

            return array_merge($commonFields, [
                'trailImage' => self::buildTrailImage(Images::extractHeroImageField($entry->hero)),
                'intro' => $entry->programmeIntro,
                'aims' => $entry->strategicProgrammeAims,
                'impact' => array_map(function ($block) {
                    return [
                        'title' => $block->contentTitle,
                        'content' => $block->contentBody,
                    ];
                }, $entry->strategicProgrammeImpact->all() ?? []),
                'latestContent' => $latestContent ?? null,
                'programmePartners' => [
                    'intro' => $entry->programmePartnersIntro,
                    'partners' => array_map(function ($partner) {
                        $logoUrl = $partner->partnerLogo->one()->url ?? null;
                        return [
                            'title' => $partner->partnerTitle,
                            'subtitle' => $partner->partnerSubtitle ?? null,
                            'logo' => $logoUrl ? Images::imgixUrl(
                                $logoUrl,
                                ['w' => '80', 'h' => '80']
                            ) : null,
                            'description' => $partner->partnerDescription,
                            'link' => $partner->partnerUrl ?? null,
                        ];
                    }, $entry->programmePartners->all()),
                ],
                'researchPartners' => [
                    'intro' => $entry->researchPartnersIntro,
                ],
                'resources' => array_map(function ($block) {
                    return [
                        'title' => $block->contentTitle,
                        'content' => $block->contentBody,
                    ];
                }, $entry->strategicProgrammeResources->all() ?? []),
            ]);
        }

    }
}
