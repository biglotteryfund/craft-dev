<?php

use biglotteryfund\utils\BlogTransformer;
use biglotteryfund\utils\ContentHelpers;
use biglotteryfund\utils\EntryHelpers;
use biglotteryfund\utils\FundingProgrammeTransformer;
use biglotteryfund\utils\FundingProgrammeTransformerNew;
use biglotteryfund\utils\Images;
use biglotteryfund\utils\ListingTransformer;
use biglotteryfund\utils\PeopleTransformer;
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

function getFundingProgramMatrix($entry, $locale)
{
    $bodyBlocks = [];
    if ($entry->fundingProgramme) {
        foreach ($entry->fundingProgramme->all() as $block) {
            switch ($block->type->handle) {
                case 'fundingProgrammeBlock':
                    $fundingData = [];
                    $fundingData['title'] = $block->programmeTitle;

                    $pathLinkUrl = $locale === 'cy' ? "/welsh/$entry->uri" : "/$entry->uri";
                    $fundingData['linkUrl'] = $pathLinkUrl;

                    // Use custom thumbnail if one is set, otherwise default to hero image.
                    $heroImage = Images::extractImage($entry->heroImage);
                    $thumbnailSrc = Images::extractImage($block->photo) ?? ($heroImage ? $heroImage->imageMedium->one() : null);

                    if ($thumbnailSrc) {
                        $fundingData['photo'] = Images::imgixUrl($thumbnailSrc->url, [
                            'w' => 100,
                            'h' => 100,
                            'crop' => 'faces',
                        ]);
                    } else {
                        $fundingData['photo'] = null;
                    }

                    $image = $heroImage ? $heroImage->imageMedium->one() : null;
                    $fundingData['image'] = $image ? Images::imgixUrl($image->url, [
                        'w' => 343,
                        'h' => 126,
                        'crop' => 'faces',
                    ]) : null;

                    $orgTypes = [];
                    foreach ($block->organisationType as $o) {
                        $orgTypes[] = EntryHelpers::translate($locale, $o->label);
                    }
                    if ($orgTypes) {
                        $fundingData['organisationTypes'] = $orgTypes;
                    }

                    if ($block->description) {
                        $fundingData['description'] = $block->description;
                    }

                    if ($block->area) {
                        $fundingData['area'] = [
                            'label' => EntryHelpers::translate($locale, $block->area->label),
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
        // @TODO: Remove when launching this section
        'updates'
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
            return [
                'id' => $alias->id,
                'from' => '/' . $alias->uri,
                'to' => EntryHelpers::uriForLocale($relatedEntry->uri, $locale),
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
        'transformer' => function (Entry $entry) use ($locale) {
            $finder = Entry::find();
            $newsQuery = \Craft::configure($finder, [
                'section' => 'news',
                'limit' => 3,
                'articlePromoted' => true,
                'site' => $locale,
            ]);

            $data = [
                'id' => $entry->id,
                'heroImages' => [
                    'default' => Images::extractHomepageHeroImage($entry->homepageHeroImages->one()),
                    'candidates' => Images::extractHomepageHeroImages($entry->homepageHeroImages->all()),
                ],
                'newsArticles' => EntryHelpers::extractNewsSummaries($newsQuery->all()),
            ];

            return $data;
        },
    ];
}

/**
 * API Endpoint: Get Promoted News
 * Get a list of all promoted news articles
 */
function getPromotedNews($locale)
{
    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'section' => 'news',
            'articlePromoted' => true,
            'site' => $locale,
        ],
        'transformer' => function (Entry $entry) {
            return array_replace_recursive([
                'id' => $entry->id,
            ], EntryHelpers::extractNewsSummary($entry));
        },
    ];
}

/**
 * API Endpoint: Get Funding Programmes
 * Get a list of all active funding programmes
 */
function getFundingProgrammes($locale)
{
    normaliseCacheHeaders();

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
    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'slug' => $slug,
            'section' => 'fundingProgrammes',
            'site' => $locale,
            'status' => EntryHelpers::getVersionStatuses(),
        ],
        'one' => true,
        'transformer' => new FundingProgrammeTransformer($locale),
    ];
}

/**
 * API Endpoint: Get Funding Programmes
 * Get full details of a single funding programme
 */
function getFundingProgrammesNext($locale, $slug = null)
{
    normaliseCacheHeaders();

    $showAll = \Craft::$app->request->getParam('all');

    $criteria = [
        'section' => 'fundingProgrammes',
        'site' => $locale,
        'programmeStatus' => 'open',
    ];

    if ($slug) {
        $criteria['slug'] = $slug;
        $criteria['status'] = EntryHelpers::getVersionStatuses();
    } else if ($showAll) {
        $criteria['orderBy'] = 'title asc';
        $criteria['status'] = $showAll ? ['live', 'expired'] : ['live'];
    }

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => $criteria,
        'one' => $slug ? true : false,
        'elementsPerPage' => \Craft::$app->request->getParam('page-limit') ?: 100,
        'transformer' => new FundingProgrammeTransformerNew($locale),
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
 * API Endpoint: Get case studies
 * Get a list of summaries for all case studies
 */
function getCaseStudies($locale)
{
    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'section' => 'caseStudies',
            'site' => $locale,
            'status' => 'live',
        ],
        'transformer' => function (Entry $entry) {
            return EntryHelpers::extractCaseStudySummary($entry);
        },
    ];
}

/**
 * API Endpoint: Get blog posts
 * Get a list of all blog posts
 */
function getBlogposts($locale)
{
    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'site' => $locale,
            'section' => 'blog',
            'status' => EntryHelpers::getVersionStatuses(),
        ],
        'elementsPerPage' => \Craft::$app->request->getParam('page-limit') ?: 10,
        'meta' => [
            'pageType' => 'blog',
        ],
        'transformer' => new BlogTransformer($locale),
    ];
}

/**
 * API Endpoint: Get blog posts by category
 * Get a list of blog posts for a given category
 */
function getBlogpostsByCategory($locale, $categorySlug, $subCategorySlug = false)
{
    $slugToUse = ($subCategorySlug) ? $subCategorySlug : $categorySlug;
    $category = Category::find()->slug($slugToUse)->one();

    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'site' => $locale,
            'section' => 'blog',
            'status' => EntryHelpers::getVersionStatuses(),
            'relatedTo' => ['targetElement' => $category],
        ],
        'meta' => [
            'pageType' => 'category',
            'activeCategory' => ContentHelpers::categorySummary($category, $locale),
        ],
        'transformer' => new BlogTransformer($locale),
    ];
}

/**
 * API Endpoint: Get blog posts by author
 * Get a list of blog posts for a given author
 */
function getBlogpostsByAuthor($locale, $author)
{
    normaliseCacheHeaders();

    $activeAuthor = Tag::find()->group('authors')->slug($author)->one();

    if (!$activeAuthor) {
        throw new \yii\web\NotFoundHttpException('Author not found');
    }

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'site' => $locale,
            'section' => 'blog',
            'status' => EntryHelpers::getVersionStatuses(),
            'relatedTo' => [
                'targetElement' => $activeAuthor,
                'field' => 'authors',
            ],

        ],
        'meta' => [
            'pageType' => 'authors',
            'activeAuthor' => ContentHelpers::tagSummary($activeAuthor, $locale),
        ],
        'transformer' => new BlogTransformer($locale),
    ];
}

/**
 * API Endpoint: Get blog posts by tag
 * Get a list of blog posts for a given tag
 */
function getBlogpostsByTag($locale, $tag)
{
    normaliseCacheHeaders();

    $activeTag = Tag::find()->group('tags')->slug($tag)->one();

    if (!$activeTag) {
        throw new \yii\web\NotFoundHttpException('Tag not found');
    }

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'site' => $locale,
            'section' => 'blog',
            'status' => EntryHelpers::getVersionStatuses(),
            'relatedTo' => [
                'targetElement' => $activeTag,
                'field' => 'tags',
            ],
        ],
        'meta' => [
            'pageType' => 'tags',
            'activeTag' => ContentHelpers::tagSummary($activeTag, $locale),
        ],
        'transformer' => new BlogTransformer($locale),
    ];
}

function getBlogpostsBySlug($locale, $slug)
{
    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'site' => $locale,
            'section' => 'blog',
            'slug' => $slug,
            'status' => EntryHelpers::getVersionStatuses(),
        ],
        'one' => true,
        'meta' => [
            'pageType' => 'blogpost',
        ],
        'transformer' => new BlogTransformer($locale),
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

    $defaultPageLimit = 10;
    $pageLimit = \Craft::$app->request->getParam('page-limit') ?: $defaultPageLimit;

    $criteria = [
        'site' => $locale,
        'section' => 'updates',
        'status' => EntryHelpers::getVersionStatuses(),
    ];

    if ($type) {
        $criteria['type'] = str_replace('-', '_', $type);
    }

    $meta = [
        'activeAuthor' => null,
        'activeTag' => null,
        'activeCategory' => null,
        'activeRegion' => null,
        'pageType' => 'single',
        'regions' => ContentHelpers::nestedCategorySummary(Category::find()->group('region')->all(), $locale),
    ];

    if ($isSinglePost) {
        $criteria['slug'] = $slug;
    } else if ($authorQuery) {
        $activeAuthor = Tag::find()->group('authors')->slug($authorQuery)->one();
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
        $activeTag = Tag::find()->group('tags')->slug($tagQuery)->one();
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
        $activeCategory = Category::find()->group('blogpost')->slug($categoryQuery)->one();
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
        $activeRegion = Category::find()->group('region')->slug($regionQuery)->one();
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
        'api/v1/<locale:en|cy>/case-studies' => getCaseStudies,
        'api/v1/<locale:en|cy>/funding-programme/<slug>' => getFundingProgramme,
        'api/v1/<locale:en|cy>/funding-programmes' => getFundingProgrammes,
        'api/v2/<locale:en|cy>/funding-programmes/<slug>' => getFundingProgrammesNext,
        'api/v2/<locale:en|cy>/funding-programmes' => getFundingProgrammesNext,
        'api/v1/<locale:en|cy>/research' => getResearch,
        'api/v1/<locale:en|cy>/research/<slug>' => getResearchDetail,
        'api/v1/<locale:en|cy>/strategic-programmes' => getStrategicProgrammes,
        'api/v1/<locale:en|cy>/strategic-programmes/<slug>' => getStrategicProgramme,
        'api/v1/<locale:en|cy>/hero-image/<slug>' => getHeroImage,
        'api/v1/<locale:en|cy>/homepage' => getHomepage,
        'api/v1/<locale:en|cy>/listing' => getListing,
        'api/v1/<locale:en|cy>/flexible-content' => getFlexibleContent,
        'api/v1/<locale:en|cy>/our-people' => getOurPeople,
        'api/v1/<locale:en|cy>/promoted-news' => getPromotedNews,
        'api/v1/<locale:en|cy>/data' => getDataPage,
        'api/v1/<locale:en|cy>/aliases' => getAliases,
        'api/v1/<locale:en|cy>/merchandise' => getMerchandise,
        'api/v1/<locale:en|cy>/updates' => getUpdates,
        'api/v1/<locale:en|cy>/updates/<type:{slug}>' => getUpdates,
        'api/v1/<locale:en|cy>/updates/<type:{slug}>/<date:\d{4}-\d{2}-\d{2}>/<slug:{slug}>' => getUpdates,

        // @TODO: Remove blog endpoints when we've switched over to the new updates section
        'api/v1/<locale:en|cy>/blog' => getBlogposts,
        'api/v1/<locale:en|cy>/blog/<date:\d{4}-\d{2}-\d{2}>/<slug:{slug}>' => getBlogpostsBySlug,
        'api/v1/<locale:en|cy>/blog/authors/<author:{slug}>' => getBlogpostsByAuthor,
        'api/v1/<locale:en|cy>/blog/tags/<tag:{slug}>' => getBlogpostsByTag,
        'api/v1/<locale:en|cy>/blog/<categorySlug:{slug}>' => getBlogpostsByCategory,
        'api/v1/<locale:en|cy>/blog/<categorySlug:{slug}>/<subCategorySlug:{slug}>' => getBlogpostsByCategory,
    ],
];
