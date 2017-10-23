<?php
// namespace Craft;

use craft\elements\Entry;
use craft\helpers\UrlHelper;

function getFundingProgramMatrix($entry) {
    $bodyBlocks = [];
    if ($entry->fundingProgramme) {
        foreach ($entry->fundingProgramme->all() as $block) {
            switch ($block->type->handle) {
                case 'fundingProgrammeBlock':

                    $fundingData = [
                        'title' => $block->programmeTitle
                    ];

                    $photos = [];
                    foreach ($block->photo->all() as $photo) {
                        $photos[] = $photo->url;
                    }
                    if ($photos) {
                        $fundingData['photo'] = $photos[0];
                    }

                    $orgTypes = [];
                    foreach ($block->organisationType as $o) {
                        $orgTypes[] = $o->label;
                    }
                    if ($orgTypes) {
                        $fundingData['organisationTypes'] = $orgTypes;
                    }

                    if ($block->description) {
                        $fundingData['description'] = $block->description;
                    }

                    if ($block->area) {
                        $fundingData['area'] = [
                            'label' => $block->area->label,
                            'value' => $block->area->value
                        ];
                    }

                    if ($block->minimumFundingSize && $block->maximumFundingSize) {
                        $fundingData['fundingSize'] = [
                            'minimum' => (int)$block->minimumFundingSize,
                            'maximum' => (int)$block->maximumFundingSize
                        ];
                    }

                    if ($block->totalAvailable) {
                        $fundingData['totalAvailable'] = $block->totalAvailable;
                    }

                    if ($block->applicationDeadline) {
                        $fundingData['applicationDeadline'] = $block->applicationDeadline;
                    }


                    $bodyBlocks = $fundingData;
                    break;
            }
        }
    }
    return $bodyBlocks;
}

function getNews($locale) {
    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'section' => 'news',
            'site' => $locale
        ],
        'transformer' => function(Entry $entry) {
            return [
                'id' => $entry->id,
                'title' => $entry->articleTitle,
                'summary' => $entry->articleSummary,
                'url' => $entry->url
            ];
        },
    ];
}

function getFundingProgrammes($locale) {
    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'section' => 'fundingProgrammes',
            'site' => $locale
        ],
        'transformer' => function(Entry $entry) {
            return [
                'id' => $entry->id,
                'title' => $entry->title,
                'url' => $entry->url,
                'content' => getFundingProgramMatrix($entry)
            ];
        }
    ];
}

return [
    'endpoints' => [
        'api/v1/<locale:en|cy>/news' => getNews,
        'api/v1/<locale:en|cy>/funding-programmes' => getFundingProgrammes
    ]
];