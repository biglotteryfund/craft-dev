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
                    'heroImage' => $entry->image->first()['filename'],
                    'heroLink' => $entry->herolink->first(),
                ];
            },
        ],
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
                        'heroImage' => $entry->image->first()['filename'],
                        'heroLink' => $entry->herolink->first(),
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