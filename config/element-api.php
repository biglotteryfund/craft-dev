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

return [
    'endpoints' => [
        'content/<locale:en|cy>/programs.json' => function($locale) {

            $siteId = ($locale === 'en' || !$locale) ? 1 : 2;

            return [
                'elementType' => Entry::class,
                'criteria' => [
                    'section' => 'fundingProgrammes',
                    'siteId' => $siteId
                ],
                'transformer' => function(Entry $entry) {
                    $request = Craft::$app->getRequest();
                    $location = $request->getParam('l');
                    return [
                        'title' => $entry->title,
                        'url' => $entry->url,
                        'content' => getFundingProgramMatrix($entry),
                        'location' => $location,
                        'test' => $entry->fundingProgramme[0]->area->label
                    ];
                },
            ];
        },
        'news.json' => [
            'elementType' => Entry::class,
            'criteria' => [
                'section' => 'funding',
                'site' => 'default'
            ],
            'transformer' => function(Entry $entry) {
                return [
                    'title' => $entry->title,
                    'url' => $entry->url,
                    'jsonUrl' => UrlHelper::url("news/{$entry->id}.json"),
                    'body' => $entry->body,
                    'heroText' => $entry->text,
                    'heroImage' => $entry->image->one()['filename'],
                    'heroLink' => $entry->herolink->one(),
                ];
            },
        ],
        'content/<locale:en|cy>/<uri:.*>.json' => function($locale, $uri) {

            $siteId = ($locale === 'en' || !$locale) ? 1 : 2;

            return [
                'elementType' => Entry::class,
                'criteria' => [
                    'uri' => $uri,
                    'siteId' => $siteId
                ],
                'transformer' => function(Entry $entry) {
                    return [
                        'title' => $entry->title,
                        'url' => $entry->url,
                        'jsonUrl' => UrlHelper::url("news/{$entry->id}.json"),
                        'body' => $entry->body
                    ];
                }
            ];
        },
        'welsh/news.json' => [
            'elementType' => Entry::class,
            'criteria' => [
                'section' => 'funding',
                'site' => 'blfWelsh'
            ],
            'transformer' => function(Entry $entry) {
                return [
                    'title' => $entry->title,
                    'url' => $entry->url,
                    'jsonUrl' => UrlHelper::url("news/{$entry->id}.json"),
                    'body' => $entry->body,
                    'heroText' => $entry->text,
                    'heroImage' => $entry->image->one()['filename'],
                    'heroLink' => $entry->herolink->one(),
                ];
            },
        ],
        'news/<entryId:\d+>.json' => function($entryId) {
            return [
                'elementType' => Entry::class,
                'criteria' => ['id' => $entryId],
                'one' => true,
                'transformer' => function(Entry $entry) {
                    return [
                        'title' => $entry->title,
                        'url' => $entry->url,
                        'summary' => $entry->summary,
                        'body' => $entry->body,
                    ];
                },
            ];
        },
    ]
];