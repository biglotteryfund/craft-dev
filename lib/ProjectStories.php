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

    public function transform(Entry $entry)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);
        $common = ContentHelpers::getCommonFields($entry, $status, $this->locale);

        return array_merge($common, [
            'grantId' => $entry->caseStudyGrantId ?? null,
            'content' => ContentHelpers::extractFlexibleContent($entry),
        ]);
    }
}
