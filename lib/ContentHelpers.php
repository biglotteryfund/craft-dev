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

    public static function getCommonFields(Entry $entry, $status, $locale, $includeHeroes = true, $includeExpiredForTranslations = false)
    {
        $fields = [
            'id' => $entry->id,
            'entryType' => $entry->type->handle,
            'slug' => $entry->slug,
            'status' => $status,
            'postDate' => $entry->postDate,
            'dateCreated' => $entry->dateCreated,
            'dateUpdated' => $entry->dateUpdated,
            'availableLanguages' => EntryHelpers::getAvailableLanguages($entry->id, $locale, $includeExpiredForTranslations),
            'openGraph' => self::extractSocialMetaTags($entry),
            // @TODO: Is url used anywhere?
            'url' => $entry->url,
            // @TODO: Some older pages use path instead of linkUrl in templates, update these uses and then remove this
            'path' => $entry->uri,
            'linkUrl' => $entry->externalUrl ? $entry->externalUrl : EntryHelpers::uriForLocale($entry->uri, $locale),
            'title' => $entry->title,
            'trailText' => $entry->trailText ?? null,
        ];
        $extraFields = [];

        if ($includeHeroes) {
            // Looking up heroes is expensive for some API calls (eg. listing all funding programmes)
            // so we allow them to be optional
            $extraFields = [
                'hero' => $entry->heroImage ? Images::extractHeroImage($entry->heroImage) : null,
                'heroCredit' => $entry->heroImageCredit ?? null,
                'heroNew' => Images::buildHero($entry->hero),
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
    public static function extractFlexibleContent(Entry $entry, $locale, $children = null)
    {
        $parts = [];
        if (!$entry->flexibleContent) {
            return [];
        }
        foreach ($entry->flexibleContent->all() as $block) {
            switch ($block->type->handle) {
                case 'contentArea':
                    $data = [
                        'type' => $block->type->handle,
                        'title' => $block->flexTitle ?? null,
                        'content' => $block->contentBody,
                    ];
                    array_push($parts, $data);
                    break;
                case 'inlineFigure':
                    $data = [
                        'type' => $block->type->handle,
                        'title' => $block->flexTitle ?? null,
                        'photo' => Images::imgixUrl(
                            Images::extractImageUrl($block->photo),
                            ['fit' => 'crop', 'crop' => 'entropy', 'max-w' => 2000]
                        ),
                        'photoCaption' => $block->photoCaption ?? null,
                    ];
                    array_push($parts, $data);
                    break;
                case 'quote':
                    $data = [
                        'type' => $block->type->handle,
                        'title' => $block->flexTitle ?? null,
                        'quoteText' => $block->quoteText,
                        'attribution' => $block->attribution ?? null,
                    ];
                    array_push($parts, $data);
                    break;
                case 'gridBlocks':
                    $gridBlocks = array();
                    $data = [
                        'type' => $block->type->handle,
                        'introduction' => $block->introduction ?? null,
                        'title' => $block->flexTitle ?? null,
                    ];
                    if (!empty($block->blocks->all())) {
                        $gridBlocks = array_map(function ($gridBlock) {
                            return $gridBlock->blockContent;
                        }, $block->blocks->all());
                    }
                    $data['content'] = $gridBlocks;
                    array_push($parts, $data);
                    break;
                case 'mediaAside':
                    $data = [
                        'type' => $block->type->handle,
                        'title' => $block->flexTitle ?? null,
                        'quoteText' => $block->quoteText,
                        'linkText' => $block->linkText ?? null,
                        'linkUrl' => $block->linkUrl ?? null,
                        'photo' => Images::imgixUrl(
                            Images::extractImageUrl($block->photo),
                            ['w' => '460', 'h' => '280']
                        ),
                        'photoCaption' => $block->photoCaption ?? null,
                    ];
                    array_push($parts, $data);
                    break;
                case 'factRiver':
                    $factRiver = array();
                    $data = [
                        'type' => $block->type->handle,
                        'title' => $block->flexTitle ?? null,
                    ];
                    if (!empty($block->facts->all())) {
                        $factRiver = array_map(function ($fact) {
                            return [
                                'text' => $fact->factText,
                                'image' => Images::extractImageUrl($fact->factImage)
                            ];
                        }, $block->facts->all());
                    }
                    $data['content'] = $factRiver;
                    array_push($parts, $data);
                    break;
                case 'relatedContent':
                    $relatedContent = array();
                    $data = [
                        'type' => $block->type->handle,
                        'heading' => $block->heading
                    ];
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
                    $data['content'] = $relatedContent;
                    array_push($parts, $data);
                    break;
                case 'tableOfContents':
                    $data = [
                        'type' => $block->type->handle,
                        'content' => $block->tableOfContentsIntro ?? null
                    ];
                    array_push($parts, $data);
                    break;
                case 'childPageList':
                    if (count($children) > 0) {
                        // Work out if we can display a grid of photos
                        $allPagesHaveImages = self::allPagesHaveImages($children);
                        $desiredDisplayMode = $block->childPageDisplayStyle->value;
                        $displayMode = ($desiredDisplayMode === 'grid' && $allPagesHaveImages) ? 'grid' : 'list';
                        $data = [
                            'type' => $block->type->handle,
                            'displayMode' => $displayMode,
                        ];
                        array_push($parts, $data);
                    }
                    break;
            }
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
    // @TODO: Remove one new hero images are all in place
    public static function getFundingProgrammeThumbnailUrl($entry)
    {
        $heroImage = Images::extractImage($entry->heroImage);
        $thumbnailSrc = Images::extractImage($entry->trailPhoto) ??
            ($heroImage ? $heroImage->imageMedium->one() : null);
        return $thumbnailSrc ? Images::imgixUrl($thumbnailSrc->url, [
            'w' => 100,
            'h' => 100,
            'crop' => 'faces',
        ]) : null;
    }

    // Use custom thumbnail if one is set, otherwise default to hero image.
    public static function getFundingProgrammeThumbnailUrlNew($entry)
    {
        $heroImage = Images::extractNewHeroImageField($entry->hero);
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
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);

        $parent = self::getParentInfo($entry, $locale);
        return array_merge(ContentHelpers::getCommonFields($entry, $status, $locale), [
            'content' => ContentHelpers::extractFlexibleContent($entry, $locale),
            'parent' => $parent ?? null
        ]);
    }
}
