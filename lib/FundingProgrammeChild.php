<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\ContentHelpers;
use biglotteryfund\utils\EntryHelpers;
use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class FundingProgrammeChildTransformer extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    public function transform(Entry $entry)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);

        $parent = $entry->getParent();
        if ($parent) {
            $parent = [
                'title' => $parent->title,
                'linkUrl' => $parent->externalUrl ? $parent->externalUrl : EntryHelpers::uriForLocale($parent->uri, $this->locale),
            ];
        }
        return array_merge(ContentHelpers::getCommonFields($entry, $status, $this->locale), [
            'content' => ContentHelpers::extractFlexibleContent($entry),
            'parent' => $parent
        ]);
    }
}
