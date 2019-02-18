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

    public static function buildSectionBreadcrumbs(Entry $entry, $locale)
    {
        $parentSection = Entry::find()->section('strategicInvestments')->site($locale)->one();
        $breadcrumbs = array([
            'label' => $parentSection->title,
            'url' => EntryHelpers::uriForLocale($parentSection->uri, $locale),
        ], [
            'label' => $entry->title,
        ]);
        return $breadcrumbs;
    }

    public function transform(Entry $entry)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);
        $commonFields = ContentHelpers::getCommonFields($entry, $status, $this->locale);

        $heroImageField = Images::extractImage($entry->heroImage);

        return array_merge($commonFields, [
            // @TODO: Update template URLs to use linkUrl
            'path' => $commonFields['linkUrl'],
            'sectionBreadcrumbs' => self::buildSectionBreadcrumbs($entry, $this->locale),
            // @TODO: Remove thumbnail in favour of trailImage once all pages have new hero images
            'thumbnail' => self::buildTrailImage(Images::extractImage($entry->heroImage)),
            'trailImage' => self::buildTrailImage(Images::extractNewHeroImageField($entry->hero)),
            'intro' => $entry->programmeIntro,
            'aims' => $entry->strategicProgrammeAims,
            'impact' => array_map(function ($block) {
                return [
                    'title' => $block->contentTitle,
                    'content' => $block->contentBody,
                ];
            }, $entry->strategicProgrammeImpact->all() ?? []),
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
