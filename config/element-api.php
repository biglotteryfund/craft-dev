<?php

use craft\elements\Entry;
use craft\helpers\UrlHelper;

return [
    'endpoints' => [
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

                    // Create an array of all the "Body" Matrix field's blocks
                    $bodyBlocks = [];
                    if ($entry->fundingProgramme) {
                        foreach ($entry->fundingProgramme as $block) {
                            switch ($block->type->handle) {
                                case 'fundingProgrammeBlock':

                                    $photos = [];
                                    foreach ($block->photo as $photo) {
                                        $photos[] = $photo->url;
                                    }

                                    $orgTypes = [];
                                    foreach ($block->organisationType as $o) {
                                        $orgTypes[] = $o->label;
                                    }

                                    $bodyBlocks[] = [
                                        'title' => $block->programmeTitle,
                                        'description' => $block->description,
                                        'photo' => $photos,
                                        'area' => $block->area->label,
                                        'organisationTypes' => $orgTypes,
                                        'fundingSize' => [
                                            'minimum' => $block->minimumFundingSize,
                                            'maximum' => $block->maximumFundingSize
                                        ],
                                        'totalAvailable' => $block->totalAvailable,
                                        'applicationDeadline' => $block->applicationDeadline,
                                    ];
                                    break;
                            }
                        }
                    }

                    return [
                        'title' => $entry->title,
                        'url' => $entry->url,
                        // 'jsonUrl' => UrlHelper::url("news/{$entry->id}.json"),
                        // 'body' => $entry->body,
                        'fundingProgramme' => $bodyBlocks                        
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