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

    public static function extractRelatedResearch($field)
    {
        $entry = $field->one() ?? false;
        if ($entry) {
            return [
                'title' => $entry->title,
            ];
        }
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

        $heroImageField = Images::extractImage($entry->heroImage);

        $data = [
            'id' => $entry->id,
            'availableLanguages' => EntryHelpers::getAvailableLanguages($entry->id, $this->locale),
            'status' => $status,
            'dateUpdated' => $entry->dateUpdated,
            'title' => $entry->title,
            'path' => $entry->externalUrl ? $entry->externalUrl : EntryHelpers::uriForLocale($entry->uri, $this->locale),
            'sectionBreadcrumbs' => self::buildSectionBreadcrumbs($entry, $this->locale),
            'thumbnail' => $heroImageField ? Images::imgixUrl(
                $heroImageField->imageMedium->one()->url,
                ['w' => '640', 'h' => '360']
            ) : null,
            'hero' => $heroImageField ? Images::buildHeroImage($heroImageField) : null,
            'heroCredit' => $entry->heroImageCredit ?? null,
            'trailText' => $entry->trailText,
            'intro' => $entry->programmeIntro,
            'aims' => $entry->strategicProgrammeAims,
            'aims' => $entry->strategicProgrammeAims,
            'impact' => array_map(function ($block) {
                return [
                    'title' => $block->contentTitle,
                    'content' => $block->contentBody,
                    // 'relatedResearch' => self::extractRelatedResearch($block->relatedResearch)
                ];
            }, $entry->strategicProgrammeImpact->all() ?? []),
            'aims' => $entry->strategicProgrammeAims,
            'programmePartners' => [
                'intro' => $entry->programmePartnersIntro,
                'partners' => array_map(function ($partner) {
                    return [
                        'title' => $partner->partnerTitle,
                        'subtitle' => $partner->partnerSubtitle ?? null,
                        'logo' => $partner->partnerLogo->one() ?? null,
                        'description' => $partner->partnerDescription,
                        'link' => $partner->partnerUrl ?? null,
                    ];
                }, $entry->programmePartners->all()),
            ],
            'researchPartners' => [
                'intro' => $entry->researchPartnersIntro,
            ]
        ];

        return $data;
    }
}
