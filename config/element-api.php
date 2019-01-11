<?php

use biglotteryfund\utils\ContentHelpers;
use biglotteryfund\utils\EntryHelpers;
use biglotteryfund\utils\FundingProgrammeTransformer;
use biglotteryfund\utils\HomepageTransformer;
use biglotteryfund\utils\Images;
use biglotteryfund\utils\ListingTransformer;
use biglotteryfund\utils\PeopleTransformer;
use biglotteryfund\utils\ProjectStoriesTransformer;
use biglotteryfund\utils\ResearchTransformer;
use biglotteryfund\utils\StrategicProgrammeTransformer;
use biglotteryfund\utils\UpdatesTransformer;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\Tag;

function normaliseCacheHeaders()
{
    $headers = \Craft::$app->response->headers;

    $headers->set('access-control-allow-origin', '*');
    $headers->set('cache-control', 'public, max-age=0');
    header_remove('Expires');
    header_remove('Pragma');
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
    normaliseCacheHeaders();

    $allSectionHandles = array_map(function ($section) {
        return $section->handle;
    }, \Craft::$app->sections->allSections);

    $excludeList = [
        'aliases',
        'caseStudies',
        'documents',
        'heroImage',
        'homepage',
        'merchandise',
        'news',
    ];

    $allowedSectionHandles = array_diff($allSectionHandles, $excludeList);

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'elementsPerPage' => 1000,
        'criteria' => [
            'section' => $allowedSectionHandles,
            'status' => ['live', 'expired'],
            'orderBy' => 'uri',
        ],
        'transformer' => function (craft\elements\Entry $entry) {
            return [
                'id' => $entry->id,
                'title' => $entry->title,
                'path' => '/' . $entry->uri,
                'live' => $entry->status === 'live',
            ];
        },
    ];
}

/**
 * API Endpoint: Get Aliases
 * Get a list of all aliases/vanity URLs from the CMS
 */
function getAliases($locale)
{
    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'elementsPerPage' => 1000,
        'criteria' => [
            'site' => $locale,
            'status' => ['live'],
            'section' => ['aliases'],
        ],
        'transformer' => function (craft\elements\Entry $alias) use ($locale) {
            $relatedEntry = $alias->relatedEntry->status(['live', 'expired'])->one();
            if ($relatedEntry) {
                $uri = EntryHelpers::uriForLocale($relatedEntry->uri, $locale);
            } else if ($alias->externalUrl) {
                $uri = $alias->externalUrl;
            }
            return [
                'id' => $alias->id,
                'from' => '/' . $alias->uri,
                'to' => $uri ?? null,
            ];
        },
    ];
}

/**
 * API Endpoint: Get Hero Image
 * Get a given hero image by slug
 */
function getHeroImage($locale, $slug)
{
    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'site' => $locale,
            'section' => 'heroImage',
            'slug' => $slug,
        ],
        'one' => true,
        'transformer' => function (Entry $entry) {
            return array_replace_recursive([
                'id' => $entry->id,
                'slug' => $entry->slug,
            ], Images::buildHeroImage($entry));
        },
    ];
}

/**
 * API Endpoint: Homepage
 */
function getHomepage($locale)
{
    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'section' => 'homepage',
            'site' => $locale,
        ],
        'one' => true,
        'transformer' => new HomepageTransformer($locale),
    ];
}

/**
 * API Endpoint: Get Funding Programmes
 */
function getFundingProgrammes($locale, $slug = null)
{
    normaliseCacheHeaders();

    $criteria = [
        'section' => 'fundingProgrammes',
        'site' => $locale,
    ];

    if ($slug) {
        $criteria['slug'] = $slug;
        $criteria['status'] = EntryHelpers::getVersionStatuses();
    } else if (\Craft::$app->request->getParam('all') === 'true') {
        $criteria['orderBy'] = 'title asc';
        $criteria['status'] = ['live', 'expired'];
    } else {
        // For listing pages, only show programmes that can be directly applied to
        $criteria['programmeStatus'] = 'open';
    }

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => $criteria,
        'one' => $slug ? true : false,
        'elementsPerPage' => \Craft::$app->request->getParam('page-limit') ?: 100,
        'transformer' => new FundingProgrammeTransformer($locale),
    ];
}

/**
 * API Endpoint: Get our people
 */
function getOurPeople($locale)
{
    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'site' => $locale,
            'section' => 'people',
            'status' => EntryHelpers::getVersionStatuses(),
        ],
        'transformer' => new PeopleTransformer($locale),
    ];
}

/**
 * API Endpoint: Get research
 * Get full details of all research entry
 */
function getResearch($locale)
{
    normaliseCacheHeaders();

    $criteria = [
        'site' => $locale,
        'section' => 'research',
        'status' => EntryHelpers::getVersionStatuses(),
    ];

    if ($searchQuery = \Craft::$app->request->getParam('q')) {
        $criteria['orderBy'] = 'score';
        $criteria['search'] = [
            'query' => $searchQuery,
            'subLeft' => true,
            'subRight' => true,
        ];
    }

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => $criteria,
        'transformer' => new ResearchTransformer($locale),
    ];
}

/**
 * API Endpoint: Get research detail
 * Get full details of a single research entry
 */
function getResearchDetail($locale, $slug)
{
    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'site' => $locale,
            'section' => 'research',
            'slug' => $slug,
            'status' => EntryHelpers::getVersionStatuses(),
        ],
        'one' => true,
        'transformer' => new ResearchTransformer($locale),
    ];
}

/**
 * API Endpoint: Get Strategic Programmes
 * Get a list of all active strategic programmes
 */
function getStrategicProgrammes($locale)
{
    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'site' => $locale,
            'section' => 'strategicProgrammes',
        ],
        'transformer' => new StrategicProgrammeTransformer($locale),
    ];
}

/**
 * API Endpoint: Get Strategic Programme
 * Get full details of a single strategic programme
 */
function getStrategicProgramme($locale, $slug)
{
    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'slug' => $slug,
            'section' => 'strategicProgrammes',
            'site' => $locale,
            'status' => EntryHelpers::getVersionStatuses(),
        ],
        'one' => true,
        'transformer' => new StrategicProgrammeTransformer($locale),
    ];
}

function getListing($locale)
{
    normaliseCacheHeaders();

    $pagePath = \Craft::$app->request->getParam('path');

    $searchCriteria = [
        'site' => $locale,
        'status' => EntryHelpers::getVersionStatuses(),
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
        'transformer' => new ListingTransformer($locale),
    ];
}

/**
 * API Endpoint: Get flexible content
 * Get a page using the flexible content field model
 */
function getFlexibleContent($locale)
{
    normaliseCacheHeaders();

    $pagePath = \Craft::$app->request->getParam('path');

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'one' => true,
        'criteria' => [
            'site' => $locale,
            'uri' => $pagePath,
            // Limited to certain sections using flexible content
            'section' => ['aboutLandingPage'],
        ],
        'transformer' => function (Entry $entry) use ($locale) {
            list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);
            $common = ContentHelpers::getCommonFields($entry, $status, $locale);
            return array_merge($common, [
                'flexibleContent' => ContentHelpers::extractFlexibleContent($entry),
            ]);
        },
    ];
}

/**
 * Get project stories
 */
function getProjectStories($locale, $grantId = null)
{
    normaliseCacheHeaders();

    $criteria = [
        'section' => 'projectStories',
        'site' => $locale,
        'status' => EntryHelpers::getVersionStatuses(),
    ];

    if ($grantId) {
        $criteria['grantId'] = $grantId;
    }

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => $criteria,
        'one' => $grantId ? true : false,
        'transformer' => new ProjectStoriesTransformer($locale),
    ];
}

function getUpdates($locale, $type = null, $date = null, $slug = null)
{
    normaliseCacheHeaders();

    $isSinglePost = $date && $slug;
    $tagQuery = \Craft::$app->request->getParam('tag');
    $authorQuery = \Craft::$app->request->getParam('author');
    $categoryQuery = \Craft::$app->request->getParam('category');
    $regionQuery = \Craft::$app->request->getParam('region');
    $showPromoted = \Craft::$app->request->getParam('promoted');

    $defaultPageLimit = 10;
    $pageLimit = \Craft::$app->request->getParam('page-limit') ?: $defaultPageLimit;

    $criteria = [
        'site' => $locale,
        'section' => 'updates',
        'status' => EntryHelpers::getVersionStatuses(),
    ];

    if ($showPromoted) {
        $criteria['articlePromoted'] = true;
    }

    if ($type) {
        $criteria['type'] = str_replace('-', '_', $type);
    }

    $meta = [
        'activeAuthor' => null,
        'activeTag' => null,
        'activeCategory' => null,
        'activeRegion' => null,
        'pageType' => $isSinglePost ? 'single' : 'listing',
        'regions' => ContentHelpers::nestedCategorySummary(Category::find()->group('region')->site($locale)->all(), $locale),
    ];

    if ($isSinglePost) {
        $criteria['slug'] = $slug;
    } else if ($authorQuery) {
        $activeAuthor = Tag::find()->group('authors')->slug($authorQuery)->site($locale)->one();
        if ($activeAuthor) {
            $meta['pageType'] = 'author';
            $meta['activeAuthor'] = ContentHelpers::tagSummary($activeAuthor, $locale);
            $criteria['relatedTo'] = [
                'targetElement' => $activeAuthor,
            ];
        } else {
            throw new \yii\web\NotFoundHttpException('Author not found');
        }
    } else if ($tagQuery) {
        $activeTag = Tag::find()->group('tags')->slug($tagQuery)->site($locale)->one();
        if ($activeTag) {
            $meta['pageType'] = 'tag';
            $meta['activeTag'] = ContentHelpers::tagSummary($activeTag, $locale);
            $criteria['relatedTo'] = [
                'targetElement' => $activeTag,
            ];
        } else {
            throw new \yii\web\NotFoundHttpException('Tag not found');
        }
    } else if ($categoryQuery) {
        $activeCategory = Category::find()->group('blogpost')->slug($categoryQuery)->site($locale)->one();
        if ($activeCategory) {
            $meta['pageType'] = 'category';
            $meta['activeCategory'] = ContentHelpers::categorySummary($activeCategory, $locale);
            $criteria['relatedTo'] = [
                'targetElement' => $activeCategory,
            ];
        } else {
            throw new \yii\web\NotFoundHttpException('Category not found');
        }
    } else if ($regionQuery) {
        $activeRegion = Category::find()->group('region')->slug($regionQuery)->site($locale)->one();
        if ($activeRegion) {
            $meta['pageType'] = 'region';
            $meta['activeRegion'] = ContentHelpers::categorySummary($activeRegion, $locale);
            $criteria['relatedTo'] = [
                'targetElement' => $activeRegion,
            ];
        } else {
            throw new \yii\web\NotFoundHttpException('Region category not found');
        }
    }

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => $criteria,
        'resourceKey' => 'updates',
        'elementsPerPage' => $isSinglePost ? null : $pageLimit,
        'one' => $isSinglePost,
        'meta' => $meta,
        'transformer' => new UpdatesTransformer($locale),
    ];
}

/**
 * API Endpoint: Data single
 */
function getDataPage($locale)
{
    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'site' => $locale,
            'section' => 'data',
            'status' => EntryHelpers::getVersionStatuses(),
        ],
        'one' => true,
        'transformer' => function (Entry $entry) use ($locale) {
            list(
                'entry' => $entry,
                'status' => $status
            ) = EntryHelpers::getDraftOrVersionOfEntry($entry);

            $regionStats = $entry->regionStats->one();
            return [
                'id' => $entry->id,
                'title' => $entry->title,
                'url' => $entry->url,
                'regions' => [
                    'england' => array_map(function ($row) {
                        return ['label' => $row['label'], 'value' => $row['value']];
                    }, $regionStats->england),
                    'northernIreland' => array_map(function ($row) {
                        return ['label' => $row['label'], 'value' => $row['value']];
                    }, $regionStats->northernIreland),
                    'scotland' => array_map(function ($row) {
                        return ['label' => $row['label'], 'value' => $row['value']];
                    }, $regionStats->scotland),
                    'wales' => array_map(function ($row) {
                        return ['label' => $row['label'], 'value' => $row['value']];
                    }, $regionStats->wales),
                ],
                'stats' => array_map(function ($stat) {
                    return [
                        'title' => $stat->statTitle,
                        'value' => $stat->statValue,
                        'showNumberBeforeTitle' => $stat->showNumberBeforeTitle,
                        'suffix' => $stat->suffix ?? null,
                        'prefix' => $stat->prefix ?? null,
                    ];
                }, $entry->stats->all() ?? [])
            ];
        },
    ];
}

function getMerchandise($locale)
{
    normaliseCacheHeaders();

    $searchCriteria = [
        'section' => 'merchandise',
        'site' => $locale,
    ];

    // Fetch everything, including inactive products, if ?all=true is set
    $showAll = \Craft::$app->request->getParam('all');
    if ($showAll) {
        $searchCriteria['status'] = null;
    }

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => $searchCriteria,
        'transformer' => function (Entry $entry) {

            $products = [];
            foreach ($entry->products->all() as $block) {
                $product = [];
                $product['id'] = (int) $block->id;
                $product['code'] = $block->productCode;
                $product['language'] = $block->productLanguage->value;
                $product['image'] = Images::extractImageUrl($block->productPhoto);
                if ($block->productName) {
                    $product['name'] = $block->productName;
                }
                array_push($products, $product);
            }

            $data = [
                'id' => $entry->id,
                'itemId' => (int) $entry->id,
                'title' => $entry->title,
                'maximum' => (int) $entry->maximumAllowed,
                'products' => $products,
            ];

            if ($entry->description) {
                $data['description'] = $entry->description;
            }

            if ($entry->notAllowedWithTheseItems) {
                $items = [];
                foreach ($entry->notAllowedWithTheseItems->all() as $item) {
                    array_push($items, (int) $item->id);
                }
                if (count($items) > 0) {
                    $data['notAllowedWith'] = $items;
                }
            }

            return $data;
        },
    ];
}

return [
    'endpoints' => [
        'api/v1/list-routes' => getRoutes,
        'api/v1/<locale:en|cy>/project-stories' => getProjectStories,
        'api/v1/<locale:en|cy>/project-stories/<grantId>' => getProjectStories,
        'api/v2/<locale:en|cy>/funding-programmes' => getFundingProgrammes,
        'api/v2/<locale:en|cy>/funding-programmes/<slug>' => getFundingProgrammes,
        'api/v1/<locale:en|cy>/research' => getResearch,
        'api/v1/<locale:en|cy>/research/<slug>' => getResearchDetail,
        'api/v1/<locale:en|cy>/strategic-programmes' => getStrategicProgrammes,
        'api/v1/<locale:en|cy>/strategic-programmes/<slug>' => getStrategicProgramme,
        'api/v1/<locale:en|cy>/hero-image/<slug>' => getHeroImage,
        'api/v1/<locale:en|cy>/homepage' => getHomepage,
        'api/v1/<locale:en|cy>/listing' => getListing,
        'api/v1/<locale:en|cy>/flexible-content' => getFlexibleContent,
        'api/v1/<locale:en|cy>/our-people' => getOurPeople,
        'api/v1/<locale:en|cy>/data' => getDataPage,
        'api/v1/<locale:en|cy>/aliases' => getAliases,
        'api/v1/<locale:en|cy>/merchandise' => getMerchandise,
        'api/v1/<locale:en|cy>/updates' => getUpdates,
        'api/v1/<locale:en|cy>/updates/<type:{slug}>' => getUpdates,
        'api/v1/<locale:en|cy>/updates/<type:{slug}>/<date:\d{4}-\d{2}-\d{2}>/<slug:{slug}>' => getUpdates,
    ],
];
