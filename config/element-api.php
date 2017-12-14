<?php

use craft\elements\Entry;

function translate($locale, $message, $variables = array())
{
    return Craft::t('site', $message, $variables, $locale);
}

function normaliseCacheHeaders($maxAge)
{
    $headers = \Craft::$app->response->headers;

    $headers->set('access-control-allow-origin', '*');

    if ($maxAge > 0) {
        $headers->set('cache-control', 'public, max-age=' . $maxAge);
    } else {
        $headers->set('cache-control', 'no-store,no-cache,max-age=0');
    }

    header_remove('Expires');
    header_remove('Pragma');
}

function addCorsAuthHeaders() {
    $headers = \Craft::$app->response->headers;

    $headers->set('access-control-allow-origin', 'http://www.biglotteryfund.local');
    $headers->set('access-control-allow-credentials', 'true');
}

function getFundingProgramMatrix($entry, $locale)
{
    $bodyBlocks = [];
    $useNewContent = (bool) $entry->useNewContent;
    if ($entry->fundingProgramme) {
        foreach ($entry->fundingProgramme->all() as $block) {
            switch ($block->type->handle) {
                case 'fundingProgrammeBlock':
                    $fundingData = [];
                    $fundingData['title'] = $block->programmeTitle;

                    /**
                     * If useNewContent switch is enabled set linkUrl to the
                     * cannonical uri rather than the custom linkUrl field.
                     */
                    $pathLinkUrl = $locale === 'cy' ? "/welsh/$entry->uri" : "/$entry->uri";
                    $fundingData['linkUrl'] = $useNewContent ? $pathLinkUrl : $block->linkUrl;

                    $photos = [];
                    foreach ($block->photo->all() as $photo) {
                        $photos[] = $photo->url;
                    }
                    if ($photos) {
                        $fundingData['photo'] = $photos[0];
                    }

                    $orgTypes = [];
                    foreach ($block->organisationType as $o) {
                        $orgTypes[] = translate($locale, $o->label);
                    }
                    if ($orgTypes) {
                        $fundingData['organisationTypes'] = $orgTypes;
                    }

                    if ($block->description) {
                        $fundingData['description'] = $block->description;
                    }

                    if ($block->area) {
                        $fundingData['area'] = [
                            'label' => translate($locale, $block->area->label),
                            'value' => $block->area->value,
                        ];
                    }

                    if ($block->minimumFundingSize && $block->maximumFundingSize) {
                        $fundingData['fundingSize'] = [
                            'minimum' => (int) $block->minimumFundingSize,
                            'maximum' => (int) $block->maximumFundingSize,
                        ];
                    }

                    if ($block->fundingSizeDescription) {
                        $fundingData['fundingSizeDescription'] = $block->fundingSizeDescription;
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

function getFundingProgrammeRegionsMatrix($entry, $locale)
{
    $regions = [];
    if ($entry->programmeRegions) {
        foreach ($entry->programmeRegions->all() as $block) {
            switch ($block->type->handle) {
                case 'programmeRegion':
                    $region = [
                        'title' => $block->programmeRegionTitle,
                        'body' => $block->programmeRegionBody,
                    ];
                    array_push($regions, $region);
                    break;
            }
        }
    }
    return $regions;
}

function getPromotedNews($locale)
{
    normaliseCacheHeaders(300);

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'section' => 'news',
            'articlePromoted' => true,
            'site' => $locale,
        ],
        'transformer' => function (Entry $entry) {
            return [
                'id' => $entry->id,
                'title' => $entry->articleTitle,
                'summary' => $entry->articleSummary,
                'link' => $entry->articleLink,
            ];
        },
    ];
}

function getFundingProgrammes($locale)
{
    normaliseCacheHeaders(300);

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'section' => 'fundingProgrammes',
            'site' => $locale,
            'status' => 'live',
        ],
        'transformer' => function (Entry $entry) use ($locale) {
            return [
                'id' => $entry->id,
                'contentId' => $entry->id,
                'status' => $entry->status,
                'title' => $entry->title,
                'url' => $entry->url,
                'urlPath' => $entry->uri,
                'content' => getFundingProgramMatrix($entry, $locale),
            ];
        },
    ];
}

function getFundingProgramme($locale, $slug)
{
    normaliseCacheHeaders(300);

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'site' => $locale,
            'section' => 'fundingProgrammes',
            'slug' => $slug,
            /**
             * Include expired entries
             * Allows expiry date to be used to drop items of the listing,
             * but still maintain the details page for historical purposes
             */
            'status' => ['live', 'expired'],
        ],
        'one' => true,
        'transformer' => function (Entry $entry) use ($locale) {
            return [
                'id' => $entry->id,
                'contentId' => $entry->id,
                'status' => $entry->status,
                'title' => $entry->title,
                'url' => $entry->url,
                'path' => $entry->uri,
                'summary' => getFundingProgramMatrix($entry, $locale),
                'intro' => $entry->programmeIntro,
                'contentSections' => getFundingProgrammeRegionsMatrix($entry, $locale),
            ];
        },
    ];
}

function getLegacyPage($locale)
{
    normaliseCacheHeaders(300);

    $pagePath = \Craft::$app->request->getParam('path');

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'site' => $locale,
            'legacyPath' => $pagePath,
        ],
        'one' => true,
        'transformer' => function (Entry $entry) use ($locale) {
            return [
                'id' => $entry->id,
                'path' => $entry->uri,
                'url' => $entry->url,
                'title' => $entry->title,
                'subtitle' => $entry->legacySubtitle,
                'body' => $entry->legacyContent,
            ];
        },
    ];
}

function getAdminLinks($locale, $entryId)
{
    normaliseCacheHeaders(0);
    addCorsAuthHeaders();

    $user = Craft::$app->user->getIdentity();
    if (!$user || (!$user->admin && !$user->isInGroup('authors'))) {
        throw new \yii\web\ForbiddenHttpException('Not authenticated');
    }

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'id' => $entryId,
            'site' => $locale,
        ],
        'one' => true,
        'transformer' => function (Entry $entry) {
            return [
                'id' => $entry->id,
                'url' => $entry->url,
                'editUrl' => $entry->cpEditUrl,
            ];
        },
    ];
}

return [
    'endpoints' => [
        'api/v1/<locale:en|cy>/promoted-news' => getPromotedNews,
        'api/v1/<locale:en|cy>/funding-programmes' => getFundingProgrammes,
        'api/v1/<locale:en|cy>/funding-programme/<slug>' => getFundingProgramme,
        'api/v1/<locale:en|cy>/legacy' => getLegacyPage,
        'api/v1/<locale:en|cy>/admin-links/<entryId:\d+>' => getAdminLinks,
    ],
];
