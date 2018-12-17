<?php

namespace biglotteryfund\utils;

use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class HomepageTransformer extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    private static function buildHomepageHero($hero)
    {
        $defaults = ['lossless' => true, 'q' => 90];

        $imageSmall = Images::imgixUrl(
            $hero->imageSmall->one()->url,
            array_replace_recursive($defaults, ['w' => '644', 'h' => '573'])
        );

        $imageMedium = Images::imgixUrl(
            $hero->imageMedium->one()->url,
            array_replace_recursive($defaults, ['w' => '1280', 'h' => '720'])
        );

        $imageLarge = Images::imgixUrl(
            $hero->imageLarge->one()->url,
            array_replace_recursive($defaults, ['w' => '1373', 'h' => '505'])
        );

        $result = [
            'caption' => $hero->caption,
            'default' => $imageMedium,
            'small' => $imageSmall,
            'medium' => $imageMedium,
            'large' => $imageLarge,
        ];

        if ($hero->captionFootnote) {
            $result['captionFootnote'] = $hero->captionFootnote;
        }

        return $result;
    }

    public function transform(Entry $entry)
    {
        return [
            'id' => $entry->id,
            'heroImages' => [
                'default' => self::buildHomepageHero($entry->homepageHeroImages->one()),
                'candidates' => array_map(
                    'self::buildHomepageHero',
                    $entry->homepageHeroImages->all() ?? []
                )
            ],
        ];
    }
}
