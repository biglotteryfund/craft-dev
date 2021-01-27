<?php

namespace Biglotteryfund;

use Biglotteryfund\EntryHelpers;
use Biglotteryfund\Images;
use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class StrategicProgrammes extends TransformerAbstract
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
        $commonFields = ContentHelpers::getCommonFields($entry, $this->locale);

        if ($entry->type->handle === 'contentPage') {
            return ContentHelpers::getFlexibleContentPage($entry, $this->locale);
        } else {

            $latestContent = [];
            if ($entry->strategicProgrammeLatestContent) {

                $latestContent['heading'] = $entry->strategicProgrammeLatestContent->heading;
                $latestContent['introduction'] = $entry->strategicProgrammeLatestContent->introduction;
                $latestContent['outro'] = $entry->strategicProgrammeLatestContent->outro;

                if ($entry->strategicProgrammeLatestContent->relatedItems) {
                    $related = $entry->strategicProgrammeLatestContent->relatedItems->all();
                    $latestContent['items'] = array_map(function ($item) {
                        $entry = $item->entry->one();
                        $commonFields = ContentHelpers::getCommonFields($entry, 'live', $this->locale);
                        return array_merge($commonFields, [
                            'tags' => ContentHelpers::getTags($entry->tags->all(), $this->locale),
                            'authors' => ContentHelpers::getTags($entry->authors->all(), $this->locale),
                            'isFeatured' => $item->featureThisEntry,
                        ]);
                    }, $related ?? null);
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
