<?php

namespace Biglotteryfund;

use Biglotteryfund\EntryHelpers;
use Biglotteryfund\Images;
use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class KeyInitiatives extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    public function transform(Entry $entry)
    {
        $commonFields = ContentHelpers::getCommonFields($entry, $this->locale);

        if ($entry->type->handle === 'contentPage') {
            return ContentHelpers::getFlexibleContentPage($entry, $this->locale);
        }

    }
}
