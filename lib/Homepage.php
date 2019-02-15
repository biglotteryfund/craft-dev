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

    private static function buildHomepageLinks($links)
    {
        $parts = [];
        foreach ($links as $link) {
            $data = [
                'label' => $link->linkText,
                'href' => $link->linkUrl,
                'image' => Images::extractHeroImage($link->heroImage),
            ];
            array_push($parts, $data);
        }
        return $parts;
    }

    public function transform(Entry $entry)
    {
        $promotedUpdates = \Craft::configure(Entry::find(), [
            'site' => $this->locale,
            'section' => 'updates',
            'articlePromoted' => true,
            'limit' => 3,
        ]);

        return [
            'id' => $entry->id,
            'featuredLinks' => self::buildHomepageLinks($entry->featuredLinks->all()),
            'promotedUpdates' => array_map(function ($entry) {
                $transformer = new UpdatesTransformer($this->locale);
                return $transformer->transform($entry);
            }, $promotedUpdates ? $promotedUpdates->all() : [])
        ];
    }
}
