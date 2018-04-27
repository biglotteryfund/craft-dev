<?php

use biglotteryfund\utils\EntryHelpers;
use biglotteryfund\utils\FundingProgrammesTransformer;
use biglotteryfund\utils\FundingProgrammeTransformer;
use biglotteryfund\utils\Images;
use craft\elements\Entry;
use craft\elements\Category;
use craft\elements\Tag;

function normaliseCacheHeaders()
{
    $headers = \Craft::$app->response->headers;

    $headers->set('access-control-allow-origin', '*');
    $headers->set('cache-control', 'public, max-age=0');
    header_remove('Expires');
    header_remove('Pragma');
}

function getBasicEntryData(Entry $entry)
{
    $basicData = [
        'id' => $entry->id,
        'path' => $entry->uri,
        'url' => $entry->url,
        'title' => $entry->title,
        'dateUpdated' => $entry->dateUpdated,
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

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'elementsPerPage' => 1000,
        'criteria' => [
            'section' => ['about', 'fundingProgrammes', 'fundingGuidance', 'buildingBetterOpportunities'],
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
            $newsQuery = EntryHelpers::queryPromotedNews();

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
        'transformer' => new FundingProgrammesTransformer($locale),
    ];
}

/**
 * API Endpoint: Get Funding Programme
 * Get full details of a single funding programme
 */
function getFundingProgramme($locale, $slug)
{
    normaliseCacheHeaders();

    $section = 'fundingProgrammes';

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'site' => $locale,
            'section' => $section,
            'slug' => $slug,
            /**
             * Include expired entries
             * Allows expiry date to be used to drop items of the listing,
             * but still maintain the details page for historical purposes
             */
            'status' => ['live', 'expired'],
        ],
        'one' => true,
        'transformer' => new FundingProgrammeTransformer($locale),
    ];
}

function getListing($locale)
{
    normaliseCacheHeaders();

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
            list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);

            $entryData = getBasicEntryData($entry);

            $entryData['availableLanguages'] = EntryHelpers::getAvailableLanguages($entry->id, $locale);

            $entryData['status'] = $status;

            $entryData['hero'] = Images::extractHeroImage($entry->heroImage);

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
                $entryData['caseStudies'] = EntryHelpers::extractCaseStudySummaries($entry->relatedCaseStudies->all());
            }

            return $entryData;
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

function getProfiles($locale, $section)
{
    normaliseCacheHeaders();

    if (!in_array($section, ['seniorManagementTeam', 'boardMembers'])) {
        throw new Error('Invalid section');
    }

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'section' => $section,
            'site' => $locale,
        ],
        'transformer' => function (Entry $entry) {
            return [
                'id' => $entry->id,
                'slug' => $entry->slug,
                'title' => $entry->title,
                'role' => $entry->profileRole,
                'image' => Images::extractImageUrl($entry->profilePhoto),
                'bio' => $entry->profileBio,
            ];
        },
    ];
}

function getSurveys($locale)
{
    normaliseCacheHeaders();

    $searchCriteria = [
        'section' => 'surveys',
        'site' => $locale,
    ];

    // Fetch everything, including closed surveys, if ?all=true is set
    $showAll = \Craft::$app->request->getParam('all');
    if ($showAll) {
        $searchCriteria['status'] = null;
    }

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => $searchCriteria,
        'transformer' => function (Entry $entry) use ($locale) {

            $choices = array_map(function ($choice) {
                return [
                    'id' => (int) $choice->id,
                    'title' => $choice->choiceTitle,
                    'allowMessage' => $choice->allowMessage,
                ];
            }, $entry->choices->all());

            return [
                'id' => $entry->id,
                'status' => $entry->status,
                'surveyPath' => $entry->path,
                'dateCreated' => $entry->dateCreated,
                'title' => $entry->title,
                'question' => $entry->question,
                'choices' => $choices,
                'global' => $entry->global,
            ];
        },
    ];
}

function getBlogpostsByCategory($locale, $categorySlug, $subCategorySlug = false) {
    // get the (sub)category object by its slug so we can query on it
    $slugToUse = ($subCategorySlug) ? $subCategorySlug : $categorySlug;
    $category = Category::find()->slug($slugToUse)->one();
    return getBlogposts($locale, 'category', ['relatedTo' => ['targetElement' => $category]]);
}

function getBlogpostsByTag($locale, $tagType, $tagSlug) {
    return getBlogposts($locale,
        $tagType,
        [
            'relatedTo' => [
                'targetElement' => Tag::find()->slug($tagSlug)->one(),
                'field' => ($tagType === 'tag') ? 'tags' : 'authors'
            ]
        ]
    );
}

function getBlogpostsBySlug($locale, $slug) {
    return getBlogposts($locale, 'blogpost', ['slug' => $slug]);
}

function getBlogposts($locale, $type = 'blog', $customCriteria = []) {
    normaliseCacheHeaders();

    $criteria = array_merge([
        'site' => $locale,
        'section' => 'blog'
    ], $customCriteria);

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => $criteria,
        'one' => $type === 'blogpost',
        'meta' => [
            'pageType' => $type
        ],
        'transformer' => function (Entry $entry) use ($locale, $type) {
            $primaryCategory = $entry->category->inReverse()->one();
            return [
                'id' => $entry->id,
                'slug' => $entry->slug,
                'link' => EntryHelpers::uriForLocale($entry->uri, $locale),
                'postDate' => $entry->postDate,
                'title' => $entry->title,
                'availableLanguages' => EntryHelpers::getAvailableLanguages($entry->id, $locale),
                'category' => [
                    'title' => $primaryCategory->title,
                    'link' => EntryHelpers::uriForLocale($primaryCategory->uri, $locale),
                    'slug' => $primaryCategory->slug,
                ],
                'author' => EntryHelpers::getTags($entry->authors->all()),
                'intro' => $entry->introduction,
                'body' => $entry->body,
                'tags' => EntryHelpers::getTags($entry->tags->all())
            ];
        },
    ];
}

return [
    'endpoints' => [
        'api/v1/<locale:en|cy>/case-studies' => getCaseStudies,
        'api/v1/<locale:en|cy>/funding-programme/<slug>' => getFundingProgramme,
        'api/v1/<locale:en|cy>/funding-programmes' => getFundingProgrammes,
        'api/v1/<locale:en|cy>/hero-image/<slug>' => getHeroImage,
        'api/v1/<locale:en|cy>/homepage' => getHomepage,
        'api/v1/<locale:en|cy>/listing' => getListing,
        'api/v1/<locale:en|cy>/profiles/<section>' => getProfiles,
        'api/v1/<locale:en|cy>/promoted-news' => getPromotedNews,
        'api/v1/<locale:en|cy>/surveys' => getSurveys,
        'api/v1/<locale:en|cy>/blog' => getBlogposts,
        // more specific routes (blogpost, tag/authors) take precedence and come first here
        'api/v1/<locale:en|cy>/blog/<date:\d{4}-\d{2}-\d{2}>/<slug:{slug}>' => getBlogpostsBySlug,
        'api/v1/<locale:en|cy>/blog/<tagType:tag>/<tagSlug:{slug}>' => getBlogpostsByTag,
        'api/v1/<locale:en|cy>/blog/<tagType:author>/<tagSlug:{slug}>' => getBlogpostsByTag,
        'api/v1/<locale:en|cy>/blog/<categorySlug:{slug}>' => getBlogpostsByCategory,
        'api/v1/<locale:en|cy>/blog/<categorySlug:{slug}>/<subCategorySlug:{slug}>' => getBlogpostsByCategory,
        'api/v1/list-routes' => getRoutes,
    ],
];
