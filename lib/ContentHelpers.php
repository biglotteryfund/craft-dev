<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\Images;
use craft\elements\Entry;

class ContentHelpers
{

    private static function allPagesHaveImages(array $pages)
    {
        foreach ($pages as $page) {
            if (!$page['trailImage']) {
                return false;
            }
        }
        return true;
    }

    private static function buildTrailImage($imageField)
    {
        $photoUrl = $imageField ? Images::extractImageUrl($imageField) : null;
        return $photoUrl ? Images::imgixUrl($photoUrl, [
            // 5:2 aspect ratio image
            'w' => 360,
            'h' => 144,
            'crop' => 'faces',
        ]) : null;
    }

    public static function getCommonFields(Entry $entry, $locale, $includeHeroes = true, $includeExpiredForTranslations = false)
    {
        $fields = [
            'id' => $entry->id,
            'entryType' => $entry->type->handle,
            'slug' => $entry->slug,
            'status' => $entry->status,
            'postDate' => $entry->postDate,
            'dateCreated' => $entry->dateCreated,
            'dateUpdated' => $entry->dateUpdated,
            'availableLanguages' => EntryHelpers::getAvailableLanguages($entry->id, $locale, $includeExpiredForTranslations),
            'openGraph' => self::extractSocialMetaTags($entry),
            'linkUrl' => $entry->externalUrl ? $entry->externalUrl : EntryHelpers::uriForLocale($entry->uri, $locale),
            'title' => $entry->title,
            'trailText' => $entry->trailText ?? null,
        ];
        $extraFields = [];

        if ($includeHeroes) {
            // Looking up heroes is expensive for some API calls (eg. listing all funding programmes)
            // so we allow them to be optional
            $extraFields = [
                'hero' => Images::buildHero($entry->hero),
            ];
        }

        return array_merge($fields, $extraFields);
    }

    public static function nestedCategorySummary($categories, $locale)
    {
        $data = [];
        foreach ($categories as $category) {
            if ($category->level == 1) {
                $summary = self::categorySummary($category, $locale);

                $children = [];
                foreach ($categories as $childCategory) {
                    if ($category->isAncestorOf($childCategory)) {
                        $children[] = self::categorySummary($childCategory, $locale);
                    }
                }

                $summary['children'] = $children;
                $data[] = $summary;
            }
        }

        return $data;
    }

    public static function categorySummary($category, $locale)
    {
        return [
            'title' => $category->title,
            'link' => EntryHelpers::uriForLocale($category->uri, $locale),
            'slug' => $category->slug,
        ];
    }

    public static function tagSummary($tag, $locale)
    {
        $tagGroup = $tag->getGroup();
        $basicFields = [
            'id' => (int) $tag->id,
            'title' => $tag->title,
            'slug' => $tag->slug,
            'group' => $tagGroup->handle,
            'groupTitle' => $tagGroup->name,
            'link' => EntryHelpers::uriForLocale("blog/{$tagGroup->handle}/{$tag->slug}", $locale),
        ];

        if ($tagGroup->handle === 'authors') {
            $basicFields['authorTitle'] = $tag->authorTitle ?? null;
            $basicFields['shortBiography'] = $tag->shortBiography ?? null;
            $basicFields['fullBiography'] = $tag->fullBiography ?? null;
            $photoUrl = $tag->photo ? Images::extractImageUrl($tag->photo) : null;
            if ($photoUrl) {
                $basicFields['photo'] = Images::imgixUrl(
                    $photoUrl,
                    [
                        'w' => 200,
                        'h' => 200,
                        'crop' => 'faces',
                    ]
                );
            }
        }

        return $basicFields;
    }

    public static function getTags($tagField, $locale)
    {
        return array_map(function ($tag) use ($locale) {
            return self::tagSummary($tag, $locale);
        }, $tagField);
    }

    /**
     * Extract data for common flexible content matrix field
     * - Content area (Redactor field)
     * - Inline figure (image with a caption)
     * - Media aside (callout block with text, image, and link)
     */
    public static function extractFlexibleContent(Entry $entry, $locale, $children = array())
    {
        $parts = [];
        if (!$entry->flexibleContent) {
            return [];
        }
        foreach ($entry->flexibleContent->all() as $block) {
            $commonBlockFields = [
                'type' => $block->type->handle,
                'title' => $block->flexTitle ?? null,
                'tocTitle' => $block->tocTitle ?? null,
            ];
            $data = [];
            switch ($block->type->handle) {
                case 'contentArea':
                    $data = [
                        'content' => $block->contentBody,
                    ];
                    break;
                case 'inlineFigure':
                    $fileUrl = Images::extractImageUrl($block->photo);
                    $data = [
                        'photo' => $fileUrl ? Images::imgixUrl($fileUrl,
                            ['fit' => 'crop', 'crop' => 'entropy', 'max-w' => 2000]
                        ) : null,
                        'photoCaption' => $block->photoCaption ?? null,
                    ];
                    break;
                case 'quote':
                    $data = [
                        'quoteText' => $block->quoteText,
                        'attribution' => $block->attribution ?? null,
                    ];
                    break;
                case 'gridBlocks':
                    $gridBlocks = array();
                    $data = [
                        'introduction' => $block->introduction ?? null,
                    ];
                    if (!empty($block->blocks->all())) {
                        $gridBlocks = array_map(function ($gridBlock) {
                            return $gridBlock->blockContent;
                        }, $block->blocks->all());
                    }
                    $data['content'] = $gridBlocks;
                    break;
                case 'mediaAside':
                    $fileUrl = Images::extractImageUrl($block->photo);
                    $data = [
                        'quoteText' => $block->quoteText,
                        'linkText' => $block->linkText ?? null,
                        'linkUrl' => $block->linkUrl ?? null,
                        'photo' => $fileUrl ? Images::imgixUrl($fileUrl,
                            ['w' => '460', 'h' => '280']
                        ) : null,
                        'photoCaption' => $block->photoCaption ?? null,
                    ];
                    break;
                case 'factRiver':
                    $factRiver = array();
                    if (!empty($block->facts->all())) {
                        $factRiver = array_map(function ($fact) {
                            return [
                                'text' => $fact->factText,
                                'image' => Images::extractImageUrl($fact->factImage),
                            ];
                        }, $block->facts->all());
                    }
                    $data['content'] = $factRiver;
                    break;
                case 'person':
                    $image = $block->personPhoto->one() ?? null;
                    $data = [
                        'name' => $block->flexTitle,
                        'role' => $block->personRole ?? null,
                        'image' => $image ? [
                            // Is the source image large or small?
                            // Used to determine what layout to use
                            'type' => $image->width > 500 ? 'large' : 'small',
                            'url' => Images::imgixUrl(
                                $image->url,
                                ['fit' => 'crop', 'crop' => 'entropy', 'max-w' => 1200]
                            ),
                        ] : null,
                        'bio' => $block->personBio,
                    ];
                    break;
                case 'relatedContent':
                    $relatedContent = array();
                    if (!empty($block->relatedItems->all())) {
                        $relatedContent = array_filter(array_map(function ($gridBlock) use ($locale) {
                            $externalUrl = $gridBlock->externalLink;
                            $entry = $gridBlock->entry->one();
                            if (!$entry && !$externalUrl) {
                                return false;
                            }
                            return [
                                'title' => $gridBlock->entryTitle ? $gridBlock->entryTitle : $entry->title,
                                'summary' => $gridBlock->entryDescription ?? null,
                                'linkUrl' => $externalUrl ? $externalUrl : EntryHelpers::uriForLocale($entry->uri, $locale),
                                'trailImage' => $gridBlock->entryImage ? self::buildTrailImage($gridBlock->entryImage) : null,
                            ];
                        }, $block->relatedItems->all()));
                    }
                    $data = [
                        'content' => $relatedContent,
                    ];
                    break;
                case 'automaticContentList':

                    $updatesTransformer = new UpdatesTransformer($locale);
                    $fundingProgrammeTransformer = new FundingProgrammeTransformer($locale, false, false);

                    $items = [];
                    $section = $block->sectionType->value;
                    if ($section === 'blogposts' || $section === 'pressReleases') {
                        $typeName = $section === 'blogposts' ? 'blog' : 'press_releases';
                        $blogposts = Entry::find()->section('updates')->site($locale)->type($typeName)->limit($block->numberOfItems)->all();
                        $items = array_map(function ($entry) use ($updatesTransformer) {
                            return $updatesTransformer->transform($entry);
                        }, $blogposts);
                    } else if ($section === 'fundingProgrammes') {
                        $programmes = Entry::find()->section('fundingProgrammes')->site($locale)->level(1)->limit($block->numberOfItems)->orderBy('postDate desc')->all();
                        $items = array_map(function ($entry) use ($fundingProgrammeTransformer) {
                            return $fundingProgrammeTransformer->transform($entry);
                        }, $programmes);
                    }

                    $data = [
                        'sectionType' => $block->sectionType->value,
                        'numberOfItems' => $block->numberOfItems,
                        'items' => $items
                    ];
                    break;
                case 'tableOfContents':
                    $data = [
                        'lastUpdated' => $block->showLastUpdatedDate ? $entry->dateUpdated : null,
                        'content' => $block->tableOfContentsIntro ?? null,
                    ];
                    break;
                case 'lastUpdatedDateBlock':
                    $data = [
                        'lastUpdatedField' => $block->updatedDate ? $entry->dateUpdated : null,
                    ];
                    break;
                case 'childPageList':
                    if (count($children) > 0) {
                        // Work out if we can display a grid of photos
                        $allPagesHaveImages = self::allPagesHaveImages($children);
                        $desiredDisplayMode = $block->childPageDisplayStyle->value;
                        $displayMode = ($desiredDisplayMode === 'grid' && $allPagesHaveImages) ? 'grid' : 'list';
                        $data = [
                            'displayMode' => $displayMode,
                        ];
                    }
                    break;
            }

            array_push($parts, array_merge($commonBlockFields, $data));

        }
        return $parts;
    }

    /**
     * Extract data for common document groups field
     */
    public static function extractDocumentGroups($documentGroupsField)
    {
        return array_map(function ($group) {
            return [
                'title' => $group->documentsTitle,
                'files' => array_map(function ($file) {
                    return [
                        'label' => $file->title,
                        'href' => $file->url,
                        'filetype' => $file->extension,
                        'filesize' => StringHelpers::formatBytes($file->size, $precision = 0),
                    ];
                }, $group->documentsFiles->all() ?? []),
                'extraContent' => $group->documentsExtra ?? null,
            ];
        }, $documentGroupsField->all() ?? []);
    }

    // Use custom thumbnail if one is set, otherwise default to hero image.
    public static function getFundingProgrammeThumbnailUrl($entry)
    {
        $heroImage = Images::extractHeroImageField($entry->hero);
        $thumbnailSrc = Images::extractImage($entry->trailPhoto) ??
            ($heroImage ? $heroImage->imageMedium->one() : null);
        return $thumbnailSrc ? Images::imgixUrl($thumbnailSrc->url, [
            'w' => 100,
            'h' => 100,
            'crop' => 'faces',
        ]) : null;
    }

    // Returns a set of open graph meta tags, optionally matching a ?social=<slug> querystring
    public static function extractSocialMetaTags(Entry $entry)
    {
        $openGraph = [];

        if (!isset($entry->socialMediaTags)) {
            return $openGraph;
        }

        $socialMediaTags = $entry->socialMediaTags->type('openGraphTags')->all();

        if (!empty($socialMediaTags)) {
            $ogData = null;
            $matchingSlug = null;

            // If we've passed a ?social=<slug> parameter, try to find its
            // matching set of tags (eg. for per-URL open graph metadata)
            if ($searchQuery = \Craft::$app->request->getParam('social')) {
                $matchingSlug = $entry->socialMediaTags->type('openGraphTags')->ogSlug($searchQuery)->one();
                if ($matchingSlug) {
                    $ogData = $matchingSlug;
                }
            }

            // Find items without any custom slugs (eg. a candidate for default metadata)
            if (!$matchingSlug) {
                $blankSocialTags = array_filter($socialMediaTags, function ($tag) {
                    return $tag->ogSlug == null;
                });
                // Get the first item in the array
                if ($blankSocialTags) {
                    $ogData = reset($blankSocialTags);
                }
            }

            if ($ogData) {
                $openGraph['title'] = $ogData->ogTitle ?? null;
                $openGraph['description'] = $ogData->ogDescription ?? null;
                $openGraph['facebookImage'] = $ogData->ogFacebookImage ? Images::extractImageUrl($ogData->ogFacebookImage) : null;
                $openGraph['twitterImage'] = $ogData->ogTwitterImage ? Images::extractImageUrl($ogData->ogTwitterImage) : null;
            }
        }

        return $openGraph;
    }

    // Returns a standardised form for flexible pages, typically children of other pages
    public static function getParentInfo(Entry $entry, $locale)
    {
        $parent = $entry->getParent();
        if ($parent) {
            $parent = [
                'title' => $parent->title,
                'linkUrl' => $parent->externalUrl ? $parent->externalUrl : EntryHelpers::uriForLocale($parent->uri, $locale),
            ];
        }
        return $parent ?? null;
    }

    // Returns a standardised form for flexible pages, typically children of other pages
    public static function getFlexibleContentPage(Entry $entry, $locale)
    {
        $parent = self::getParentInfo($entry, $locale);
        return array_merge(ContentHelpers::getCommonFields($entry, $locale), [
            'flexibleContent' => ContentHelpers::extractFlexibleContent($entry, $locale),
            'parent' => $parent ?? null,
        ]);
    }
}
