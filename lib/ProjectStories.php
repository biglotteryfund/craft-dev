<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\ContentHelpers;
use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class ProjectStoriesTransformer extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    private static function getTrailPhoto($entry)
    {
        $hero = Images::extractNewHeroImageField($entry->hero);
        $heroImage = $hero ? $hero->imageMedium->one() : null;
        $trailImage = $entry->trailPhoto->one() ?? null;

        $imageSrc = $trailImage ?? $heroImage;

        return $imageSrc ? Images::imgixUrl($imageSrc->url, [
            'w' => 475,
            'h' => 320,
            'crop' => 'faces',
        ]) : null;
    }

    public function transform(Entry $entry)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);
        $common = ContentHelpers::getCommonFields($entry, $status, $this->locale);

        return array_merge($common, [
            // @TODO: Move trailSummary and trailPhoto to getCommonFields?
            'trailSummary' => $entry->trailSummary ?? null,
            'trailPhoto' => self::getTrailPhoto($entry),
            'content' => ContentHelpers::extractFlexibleContent($entry, $this->locale),
            'outro' => $entry->outroText ?? null,
            'grantId' => $entry->grantId ?? null,
        ]);
    }
}
