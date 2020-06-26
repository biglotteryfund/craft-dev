<?php

use biglotteryfund\utils\ContentHelpers;
use biglotteryfund\utils\EntryHelpers;
use biglotteryfund\utils\FundingProgrammeTransformer;
use biglotteryfund\utils\HomepageTransformer;
use biglotteryfund\utils\Images;
use biglotteryfund\utils\ListingTransformer;
use biglotteryfund\utils\ProjectStoriesTransformer;
use biglotteryfund\utils\ResearchTransformer;
use biglotteryfund\utils\ResearchDocumentTransformer;
use biglotteryfund\utils\StrategicProgrammeTransformer;
use biglotteryfund\utils\UpdatesTransformer;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\Tag;
use craft\elements\GlobalSet;

function normaliseCacheHeaders()
{
    $headers = \Craft::$app->response->headers;

    $headers->set('access-control-allow-origin', '*');
    $headers->set('cache-control', 'public, max-age=10');
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
function getFundingProgrammes($locale, $programmeSlug = null, $childPageSlug = null)
{
    normaliseCacheHeaders();

    $criteria = [
        'section' => 'fundingProgrammes',
        'site' => $locale,
    ];

    $isSingle = $programmeSlug || $childPageSlug;
    $showAllProgrammes = \Craft::$app->request->getParam('all') === 'true';

    if ($isSingle) {
        // First look for child pages, then defer to the parent programme
        $criteria['slug'] = $childPageSlug ? $childPageSlug : $programmeSlug;
        $criteria['status'] = EntryHelpers::getVersionStatuses();
    } else if ($showAllProgrammes) {
        $criteria['orderBy'] = 'title asc';
        $criteria['status'] = ['live', 'expired'];
    } else if (\Craft::$app->request->getParam('newest') === 'true') {
        $criteria['orderBy'] = 'postDate desc';
    } else {
        // For listing pages, only show programmes that can be directly applied to
        $criteria['programmeStatus'] = 'open';
    }

    // Don't return child pages when listing funding programmes
    if (!$isSingle) {
        $criteria['type'] = 'fundingProgrammes';
    }

    $covidStatuses = GlobalSet::find()->handle('covid19Messaging')->site($locale)->one();
    if ($covidStatuses) {
        $meta['covid19Statuses'] = array_map(function ($status) {
            return [
                'country' => $status->country->value,
                'status' => $status->statusMessage,
            ];
        }, $covidStatuses->covid19Status->all() ?? []);
    }

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => $criteria,
        'one' => $isSingle,
        'elementsPerPage' => \Craft::$app->request->getParam('page-limit') ?: 100,
        'meta' => $meta ?? null,
        'transformer' => new FundingProgrammeTransformer($locale, $isSingle, $showAllProgrammes)
    ];
}

/**
 * API Endpoint: Get research
 * Get full details of all research entry
 */
function getResearch($locale, $type = false)
{
    normaliseCacheHeaders();

    $transformer = $type === 'documents' ? new ResearchDocumentTransformer($locale) : new ResearchTransformer($locale);

    $criteria = [
        'site' => $locale,
        'level' => 1, // Skip child pages
        'section' => 'research',
        'status' => EntryHelpers::getVersionStatuses(),
        'type' => ($type === 'documents') ? 'researchDocument' : 'research'
    ];

    $sortParam = \Craft::$app->request->getParam('sort');
    $searchQuery = \Craft::$app->request->getParam('q');
    if ($searchQuery && ($sortParam === 'score' || !$sortParam)) {
        $criteria['orderBy'] = 'score';
        $criteria['search'] = [
            'query' => $searchQuery,
            'subLeft' => true,
            'subRight' => true,
        ];
    } else if ($sortParam === 'newest') {
        $criteria['orderBy'] = 'postDate desc';
    } else if ($sortParam === 'oldest') {
        $criteria['orderBy'] = 'postDate asc';
    } else {
        $criteria['orderBy'] = 'postDate desc';
    }

    // Document-specific search query fields
    if ($type === 'documents') {

        // @TODO include non-live programmes?
        $allProgrammes = array_map(function ($programme) use ($locale) {
            return [
                'label' => $programme->title,
                'value' => $programme->slug,
            ];
        }, Entry::find()->section(['fundingProgrammes', 'strategicProgrammes'])->status(['live', 'expired'])->orderBy('title')->site($locale)->level(1)->all());

        $allRegions = array_map(function ($region) use ($locale) {
            return [
                'label' => $region->title,
                'value' => $region->slug
            ];
        }, Category::find()->group('region')->orderBy('title')->site($locale)->all());

        $allDocTypes = array_map(function ($type) use ($locale) {
            return [
                'label' => $type->title,
                'value' => $type->slug
            ];
        }, Category::find()->group('insightDocumentType')->orderBy('title')->site($locale)->all());

        $meta = [
            'activeTag' => null,
            'activeProgramme' => null,
            'activePortfolio' => null,
            'activeDocType' => null,
            'portfolios' => $allRegions,
            'docTypes' => $allDocTypes,
            'programmes' => $allProgrammes,
        ];

        $elementsToRelateTo = array();

        // Filter: content tags
        if ($tagQuery = \Craft::$app->request->getParam('tag')) {
            $activeTag = Tag::find()->group('tags')->slug($tagQuery)->site($locale)->one();
            if ($activeTag) {
                $meta['activeTag'] = [
                    'label' => $activeTag->title,
                    'value' => $activeTag->slug
                ];
                $elementsToRelateTo[] = [
                    'targetElement' => $activeTag,
                ];
            }
        }

        // Filter: funding programme
        if ($programmeQuery = \Craft::$app->request->getParam('programme')) {
            $activeProgramme = Entry::find()->section(['fundingProgrammes', 'strategicProgrammes'])->status(['live', 'expired'])->slug($programmeQuery)->site($locale)->one();
            if ($activeProgramme) {
                $meta['activeProgramme'] = [
                    'label' => $activeProgramme->title,
                    'value' => $activeProgramme->slug
                ];
                $elementsToRelateTo[] = [
                    'targetElement' => $activeProgramme,
                ];
            }
        }

        // Filter: portfolio (eg. region)
        if ($portfolioQuery = \Craft::$app->request->getParam('portfolio')) {
            $activePortfolio = Category::find()->group('region')->slug($portfolioQuery)->site($locale)->one();
            if ($activePortfolio) {
                $meta['activePortfolio'] = [
                    'label' => $activePortfolio->title,
                    'value' => $activePortfolio->slug,
                ];
                $elementsToRelateTo[] = [
                    'targetElement' => $activePortfolio,
                ];
            }
        }

        // Filter: document type
        if ($docTypeQuery = \Craft::$app->request->getParam('doctype')) {
            $activeDocType = Category::find()->group('insightDocumentType')->slug($docTypeQuery)->site($locale)->one();
            if ($activeDocType) {
                $meta['activeDocType'] = [
                    'label' => $activeDocType->title,
                    'value' => $activeDocType->slug,
                ];
                $elementsToRelateTo[] = [
                    'targetElement' => $activeDocType,
                ];
            }
        }

        if ($slugQuery = \Craft::$app->request->getParam('slug')) {
            $criteria['slug'] = $slugQuery;
        }

        if (!empty($elementsToRelateTo)) {
            // ensure this query requires all relations (eg. AND not OR)
            array_unshift($elementsToRelateTo, 'and');
            $criteria['relatedTo'] = $elementsToRelateTo;
        }
    }

    $defaultPageLimit = 10;
    $pageLimit = \Craft::$app->request->getParam('page-limit') ?: $defaultPageLimit;

    return [
        'serializer' => 'jsonApi',
        'elementsPerPage' => $pageLimit,
        'elementType' => Entry::class,
        'criteria' => $criteria,
        'meta' => $meta ?? null,
        'transformer' => $transformer,
    ];
}

/**
 * API Endpoint: Get publication
 * Get full details of all publications
 */
function getPublication($locale, $programmeSlug, $pageSlug = null)
{
    normaliseCacheHeaders();

    $criteria = [
        'site' => $locale,
        'section' => 'publications',
        'status' => EntryHelpers::getVersionStatuses()
    ];

    $isSingle = $pageSlug ? true : false;
    if ($pageSlug) {
        $criteria['slug'] = $pageSlug;
    }

    $associatedProgramme = Entry::find()->section(['fundingProgrammes', 'strategicProgrammes'])->status(['live', 'expired'])->slug($programmeSlug)->site($locale)->one();

    $sortParam = \Craft::$app->request->getParam('sort');
    $searchQuery = \Craft::$app->request->getParam('q');
    if ($searchQuery && ($sortParam === 'score' || !$sortParam)) {
        $criteria['orderBy'] = 'score';
        $criteria['search'] = [
            'query' => $searchQuery,
            'subLeft' => true,
            'subRight' => true,
        ];
    } else if ($sortParam === 'newest') {
        $criteria['orderBy'] = 'postDate desc';
    } else if ($sortParam === 'oldest') {
        $criteria['orderBy'] = 'postDate asc';
    } else {
        $criteria['orderBy'] = 'postDate desc';
    }


    $meta = [
        'activeTag' => null
    ];

    $elementsToRelateTo = array();
    if ($associatedProgramme) {
        $meta['programme'] = [
            'title' => $associatedProgramme->title,
            'linkUrl' => $associatedProgramme->externalUrl ? $associatedProgramme->externalUrl : EntryHelpers::uriForLocale($associatedProgramme->uri, $locale),
            'intro' => $associatedProgramme->programmeIntro,
            'slug' => $associatedProgramme->slug,
            'thumbnail' => ContentHelpers::getFundingProgrammeThumbnailUrl($associatedProgramme),
        ];
        $elementsToRelateTo[] = [
            'targetElement' => $associatedProgramme
        ];
    }

    // Filter: content tags
    if ($tagQuery = \Craft::$app->request->getParam('tag')) {
        $activeTag = Tag::find()->group('tags')->slug($tagQuery)->site($locale)->one();
        if ($activeTag) {
            $meta['activeTag'] = [
                'label' => $activeTag->title,
                'value' => $activeTag->slug
            ];
            $elementsToRelateTo[] = [
                'targetElement' => $activeTag,
            ];
        }
    }

    if (!empty($elementsToRelateTo)) {
        // ensure this query requires all relations (eg. AND not OR)
        array_unshift($elementsToRelateTo, 'and');
        $criteria['relatedTo'] = $elementsToRelateTo;
    }

    $defaultPageLimit = 10;
    $pageLimit = \Craft::$app->request->getParam('page-limit') ?: $defaultPageLimit;

    return [
        'serializer' => 'jsonApi',
        'elementsPerPage' => $pageLimit,
        'one' => $isSingle,
        'elementType' => Entry::class,
        'criteria' => $criteria,
        'meta' => $meta ?? null,
        'transformer' => function (Entry $entry) use ($locale) {
            $common = ContentHelpers::getCommonFields($entry, $locale);
            return array_merge($common, [
                'tags' => ContentHelpers::getTags($entry->tags->all(), $locale),
                'authors' => ContentHelpers::getTags($entry->authors->all(), $locale),
                'flexibleContent' => ContentHelpers::extractFlexibleContent($entry, $locale),
            ]);
        },
    ];
}

function getPublicationTags($locale, $programmeSlug)
{
    normaliseCacheHeaders();

    $criteria = [
        'site' => $locale,
        'section' => 'publications',
        'status' => EntryHelpers::getVersionStatuses()
    ];

    $associatedProgramme = Entry::find()->section(['fundingProgrammes', 'strategicProgrammes'])->status(['live', 'expired'])->slug($programmeSlug)->site($locale)->one();
    $criteria['relatedTo'] = [
        'targetElement' => $associatedProgramme,
    ];

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'paginate' => false,
        'criteria' => $criteria,
        'meta' => $meta ?? null,
        'transformer' => function (Entry $entry) use ($locale) {
            return [
                'id' => $entry->id,
                'tags' => ContentHelpers::getTags($entry->tags->all(), $locale)
            ];
        }
    ];
}

/**
 * API Endpoint: Get research detail
 * Get full details of a single research entry
 */
function getResearchDetail($locale, $slug, $childPageSlug = null)
{
    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'site' => $locale,
            'section' => 'research',
            'type' => 'research',
            'slug' => $childPageSlug ? $childPageSlug : $slug,
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
            'level' => 1
        ],
        'transformer' => new StrategicProgrammeTransformer($locale),
    ];
}

/**
 * API Endpoint: Get Strategic Programme
 * Get full details of a single strategic programme
 * or one of its children
 */
function getStrategicProgramme($locale, $slug, $childPageSlug = null)
{
    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'slug' => $childPageSlug ? $childPageSlug : $slug,
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
            $regionStats = $entry->regionStats->one();

            $common = ContentHelpers::getCommonFields($entry, $locale);
            return array_merge($common, [
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
                }, $entry->stats->all() ?? []),
                'flexibleContent' => ContentHelpers::extractFlexibleContent($entry, $locale),
            ]);
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
                'mustBeOrderedDirectly' => $entry->mustBeOrderedDirectly,
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
        'api/v2/<locale:en|cy>/funding-programmes/<programmeSlug:{slug}>' => getFundingProgrammes,
        'api/v2/<locale:en|cy>/funding-programmes/<programmeSlug:{slug}>/<childPageSlug:{slug}>' => getFundingProgrammes,
        'api/v1/<locale:en|cy>/funding/publications/<programmeSlug:{slug}>/tags' => getPublicationTags,
        'api/v1/<locale:en|cy>/funding/publications/<programmeSlug:{slug}>' => getPublication,
        'api/v1/<locale:en|cy>/funding/publications/<programmeSlug:{slug}>/<pageSlug:{slug}>' => getPublication,
        'api/v1/<locale:en|cy>/research/<type:documents>' => getResearch,
        'api/v1/<locale:en|cy>/research' => getResearch,
        'api/v1/<locale:en|cy>/research/<slug>' => getResearchDetail,
        'api/v1/<locale:en|cy>/research/<slug>/<childPageSlug:{slug}>' => getResearchDetail,
        'api/v1/<locale:en|cy>/strategic-programmes' => getStrategicProgrammes,
        'api/v1/<locale:en|cy>/strategic-programmes/<slug:{slug}>' => getStrategicProgramme,
        'api/v1/<locale:en|cy>/strategic-programmes/<slug:{slug}>/<childPageSlug:{slug}>' => getStrategicProgramme,
        'api/v1/<locale:en|cy>/hero-image/<slug>' => getHeroImage,
        'api/v1/<locale:en|cy>/homepage' => getHomepage,
        'api/v1/<locale:en|cy>/listing' => getListing,
        'api/v1/<locale:en|cy>/data' => getDataPage,
        'api/v1/<locale:en|cy>/aliases' => getAliases,
        'api/v1/<locale:en|cy>/merchandise' => getMerchandise,
        'api/v1/<locale:en|cy>/updates' => getUpdates,
        'api/v1/<locale:en|cy>/updates/<type:{slug}>' => getUpdates,
        'api/v1/<locale:en|cy>/updates/<type:{slug}>/<date:\d{4}-\d{2}-\d{2}>/<slug:{slug}>' => getUpdates,
    ],
];
