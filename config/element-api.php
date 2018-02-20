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
    $headers->set('cache-control', 'public, max-age=' . $maxAge);
    header_remove('Expires');
    header_remove('Pragma');
}

function getBasicEntryData($entry)
{
    $basicData = [
        'id' => $entry->id,
        'path' => $entry->uri,
        'url' => $entry->url,
        'title' => $entry->title,
    ];

    if ($entry->themeColour) {
        $basicData['themeColour'] = $entry->themeColour->value;
    }

    if ($entry->trailText) {
        $basicData['trailText'] = $entry->trailText;
    }

    if ($entry->trailPhoto) {
        $photos = [];
        foreach ($entry->trailPhoto->all() as $photo) {
            $photos[] = $photo->url;
        }
        if ($photos) {
            $basicData['photo'] = $photos[0];
        }
    }

    return $basicData;
}

function getRelatedEntries($entry, $relationType)
{
    $relatedEntries = [];
    $relatedSearch = [];

    if ($relationType == 'children') {
        $relatedSearch = $entry->getChildren()->all();
    } else if ($relationType == 'siblings') {
        // get parent first to allow including self as a sibling
        $parent = $entry->getParent();
        if ($parent) {
            $relatedSearch = $parent->getDescendants(1)->all();
        }
    }

    foreach ($relatedSearch as $relatedItem) {
        $relatedData = getBasicEntryData($relatedItem);
        $relatedData['isCurrent'] = $entry->uri == $relatedData['path'];
        $relatedEntries[] = $relatedData;
    }

    return $relatedEntries;
}

function parseSegmentMatrix($entry, $locale)
{
    $segments = [];
    if ($entry->contentSegment) {
        foreach ($entry->contentSegment->all() as $block) {
            $segment = [];
            $segment['title'] = $block->segmentTitle;
            $segment['content'] = $block->segmentContent;

            $segmentImage = $block->segmentImage->one();
            if ($segmentImage) {
                $segment['photo'] = $segmentImage->url;
            }

            array_push($segments, $segment);
        }
    }
    return $segments;
}

function getHeroImage($entry)
{
    $result = false;
    if ($entry->heroImage->all()) {
        $hero = $entry->heroImage->one();
        $result = [
            'title' => $hero->title,
            'caption' => $hero->caption,
            'default' => $hero->imageMedium->one()->url,
            'small' => $hero->imageSmall->one()->url,
            'medium' => $hero->imageMedium->one()->url,
            'large' => $hero->imageLarge->one()->url,
        ];

        if ($hero->captionFootnote) {
            $result['captionFootnote'] = $hero->captionFootnote;
        }
    }

    return $result;
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

/**
 * extractCaseStudySummary
 * Extract a summary object from a case study entry
 */
function extractCaseStudySummary($entry)
{
    return [
        'title' => $entry->title,
        'linkUrl' => $entry->uri,
        'trailText' => $entry->caseStudyTrailText,
        'trailTextMore' => $entry->caseStudyTrailTextMore,
        'grantAmount' => $entry->caseStudyGrantAmount,
        'thumbnailUrl' => $entry->caseStudyThumbnailImage->one()->url,
    ];
}

/**********************************************************
 * API ENDPOINTS
 **********************************************************/

/**
 * API Endpoint: Get Routes
 * Get a list of all canonical URLs from the CMS
 */
function getRoutes()
{
    normaliseCacheHeaders(300);

    $pagePath = \Craft::$app->request->getParam('path');

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'elementsPerPage' => 1000,
        'criteria' => [
            'section' => ['fundingProgrammes', 'fundingGuidance', 'buildingBetterOpportunities'],
            'status' => ['live', 'pending', 'expired'],
            'orderBy' => 'uri',
        ],
        'transformer' => function (craft\elements\Entry $entry) {
            return [
                'id' => $entry->id,
                'title' => $entry->title,
                'path' => '/' . $entry->uri,
                'live' => $entry->status === 'live',
                'isFromCms' => true,
            ];
        },
    ];
}

/**
 * API Endpoint: Get Promoted News
 * Get a list of all promoted news articles
 */
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

/**
 * API Endpoint: Get Funding Programmes
 * Get a list of all active funding programmes
 */
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
                'status' => $entry->status,
                'title' => $entry->title,
                'url' => $entry->url,
                'urlPath' => $entry->uri,
                'content' => getFundingProgramMatrix($entry, $locale),
            ];
        },
    ];
}

/**
 * API Endpoint: Get Funding Programme
 * Get full details of a single funding programme
 */
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
            if (!$entry->useNewContent) {
                throw new \yii\web\NotFoundHttpException('Programme not found');
            }

            $data = [
                'id' => $entry->id,
                'status' => $entry->status,
                'title' => $entry->title,
                'url' => $entry->url,
                'path' => $entry->uri,
                'summary' => getFundingProgramMatrix($entry, $locale),
                'intro' => $entry->programmeIntro,
                'contentSections' => getFundingProgrammeRegionsMatrix($entry, $locale),
            ];

            if ($hero = getHeroImage($entry)) {
                $data['hero'] = $hero;
            }

            return $data;
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

function getListing($locale)
{
    normaliseCacheHeaders(300);

    $pagePath = \Craft::$app->request->getParam('path');

    $searchCriteria = [
        'site' => $locale,
    ];

    if ($pagePath) {
        $searchCriteria['uri'] = $pagePath;
    } else {
        $searchCriteria['level'] = 1;
    }

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => $searchCriteria,
        'transformer' => function (Entry $entry) use ($locale, $pagePath) {

            $entryData = getBasicEntryData($entry);

            if ($hero = getHeroImage($entry)) {
                $entryData['hero'] = $hero;
            }

            if ($entry->introductionText) {
                $entryData['introduction'] = $entry->introductionText;
            }

            // casting to string prevents empty fields
            if ((string) $entry->outroText) {
                $entryData['outro'] = $entry->outroText;
            }

            $segments = parseSegmentMatrix($entry, $locale);
            if ($segments) {
                $entryData['segments'] = $segments;
            }

            if ($entry->relatedContent) {
                $entryData['relatedContent'] = $entry->relatedContent;
            }

            $children = getRelatedEntries($entry, 'children');
            if (count($children) > 0) {
                $entryData['children'] = $children;
            }

            $siblings = getRelatedEntries($entry, 'siblings');
            if (count($siblings) > 0) {
                $entryData['siblings'] = $siblings;
            }

            if ($entry->relatedCaseStudies) {
                $entryData['caseStudies'] = array_map('extractCaseStudySummary', $entry->relatedCaseStudies->all());
            }

            return $entryData;
        },
    ];
}

return [
    'endpoints' => [
        'api/v1/list-routes' => getRoutes,
        'api/v1/<locale:en|cy>/promoted-news' => getPromotedNews,
        'api/v1/<locale:en|cy>/funding-programmes' => getFundingProgrammes,
        'api/v1/<locale:en|cy>/funding-programme/<slug>' => getFundingProgramme,
        'api/v1/<locale:en|cy>/legacy' => getLegacyPage,
        'api/v1/<locale:en|cy>/listing' => getListing,
    ],
];
