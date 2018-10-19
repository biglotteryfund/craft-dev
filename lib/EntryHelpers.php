<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\Images;
use craft\elements\Entry;

class EntryHelpers
{
    public static function translate($locale, $message, $variables = array())
    {
        return \Craft::t('site', $message, $variables, $locale);
    }

    public static function getAvailableLanguages($entryId, $currentLanguage)
    {
        $alternateLanguage = $currentLanguage === 'en' ? 'cy' : 'en';

        $altEntry = Entry::find()
            ->id($entryId)
            ->site($alternateLanguage)
            ->one();

        $availableLanguages = [$currentLanguage];
        if ($altEntry) {
            array_push($availableLanguages, $alternateLanguage);
        }

        sort($availableLanguages);

        return $availableLanguages;
    }

    public static function uriForLocale($uri, $locale)
    {
        // @TODO: Can craft construct this for us?
        return $locale === 'cy' ? "/welsh/$uri" : "/$uri";
    }

    public static function isDraftOrVersion()
    {
        $isDraft = \Craft::$app->request->getParam('draft');
        $isVersion = \Craft::$app->request->getParam('version');

        return $isDraft || $isVersion;
    }

    /**
     * getDraftOrVersionOfEntry
     * Looks up an old version or draft of an entry
     * @usage: `list('entry' => $entry, 'status' => $status) = getDraftOrVersionOfEntry($entry);`
     */
    public static function getDraftOrVersionOfEntry(Entry $entry)
    {
        $isDraft = \Craft::$app->request->getParam('draft');
        $isVersion = \Craft::$app->request->getParam('version');

        if ($isDraft) {
            $status = 'draft';
            $revisionId = $isDraft;
            $revisionMethod = 'getDraftsByEntryId';
            $entryRevisionMethod = 'getDraftById';
            $revisionIdParam = 'draftId';
        } else if ($isVersion) {
            $status = 'version';
            $revisionId = $isVersion;
            $revisionMethod = 'getVersionsByEntryId';
            $entryRevisionMethod = 'getVersionById';
            $revisionIdParam = 'versionId';
        }

        if (($isDraft || $isVersion) && $revisionId) {

            // Get all drafts/revisions of this post
            $revisions = \Craft::$app->entryRevisions->{$revisionMethod}($entry->id, $entry->siteId);

            // Filter drafts/revisions for the requested ID
            $revisions = array_filter($revisions, function ($revision) use ($revisionId, $revisionIdParam, $entryRevisionMethod) {
                return $revision->{$revisionIdParam} == $revisionId;
            });

            // Is this draft/revision ID valid for this post?
            if (count($revisions) > 0) {

                // Look up the revision itself
                $revision = \Craft::$app->entryRevisions->{$entryRevisionMethod}($revisionId);

                if ($revision) {
                    // Non-live content has a null URI in Craft,
                    // so restore it to its base entry's URI
                    $revision->uri = $entry->uri;
                    return [
                        'entry' => $revision,
                        'status' => $status,
                    ];
                }
            }
        }

        // default to the original, unmodified entry
        return [
            'entry' => $entry,
            'status' => $entry->status,
        ];
    }

    public static function getVersionStatuses()
    {
        /**
         * Include expired entries
         * Allows expiry date to be used to drop items of the listing,
         * but still maintain the details page for historical purposes
         */
        $statuses = ['live', 'expired'];

        /**
         * Allow disabled versions when requesting drafts
         * to support previews of brand new or disabled pages.
         */
        if (EntryHelpers::isDraftOrVersion()) {
            $statuses[] = 'disabled';
        }

        return $statuses;
    }

    public static function extractBasicEntryData(Entry $entry)
    {
        $basicData = [
            'id' => $entry->id,
            'path' => $entry->uri,
            'url' => $entry->url,
            'title' => $entry->title,
            'displayTitle' => $entry->displayTitle ?? null,
            'dateUpdated' => $entry->dateUpdated,
        ];

        if ($entry->themeColour) {
            $basicData['themeColour'] = $entry->themeColour->value;
        }

        if ($entry->trailText) {
            $basicData['trailText'] = $entry->trailText;
        }

        return $basicData;
    }

    public static function extractFlexibleContent(Entry $entry)
    {
        $parts = [];
        foreach ($entry->flexibleContent->all() as $block) {
            switch ($block->type->handle) {
                case 'contentArea':
                    $data = [
                        'type' => $block->type->handle,
                        'content' => $block->contentBody,
                    ];

                    array_push($parts, $data);
                    break;
                case 'inlineFigure':
                    $data = [
                        'type' => $block->type->handle,
                        'photo' => Images::imgixUrl(
                            Images::extractImageUrl($block->photo),
                            ['fit' => 'crop', 'crop' => 'entropy', 'max-w' => 2000]
                        ),
                        'photoCaption' => $block->photoCaption ?? null,
                    ];
                    array_push($parts, $data);
                    break;
                case 'mediaAside':
                    $data = [
                        'type' => $block->type->handle,
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
            }
        }
        return $parts;
    }

    public static function queryPromotedNews($locale)
    {
        $newsQuery = Entry::find();
        return \Craft::configure($newsQuery, [
            'section' => 'news',
            'limit' => 3,
            'articlePromoted' => true,
            'site' => $locale,
        ]);
    }

    /**
     * extractNewsSummary
     * Extract a summary object from a news entry
     */
    public static function extractNewsSummary(Entry $entry)
    {
        return [
            'title' => $entry->articleTitle,
            'summary' => $entry->articleSummary,
            'link' => $entry->articleLink,
        ];
    }

    /**
     * Wrapper around `extractNewsSummary`
     * for extracting an array of summaries from a list of news articles
     */
    public static function extractNewsSummaries($newsArticles)
    {
        return $newsArticles ? array_map(
            'self::extractNewsSummary',
            $newsArticles
        ) : [];
    }

    /**
     * extractCaseStudySummary
     * Extract a summary object from a case study entry
     */
    public static function extractCaseStudySummary(Entry $entry)
    {
        return [
            'id' => $entry->id,
            'slug' => $entry->slug,
            'title' => $entry->title,
            'linkUrl' => $entry->caseStudyLinkUrl,
            'trailText' => $entry->caseStudyTrailText,
            'trailTextMore' => $entry->caseStudyTrailTextMore,
            'grantAmount' => $entry->caseStudyGrantAmount,
            'grantId' => $entry->caseStudyGrantId ? $entry->caseStudyGrantId : null,
            'thumbnailUrl' => Images::imgixUrl($entry->caseStudyThumbnailImage->one()->url, [
                'w' => 600,
                'h' => 400,
            ]),
        ];
    }

    /**
     * Wrapper around `extractCaseStudySummary`
     * for extracting an array of summaries from a list of case studies
     */
    public static function extractCaseStudySummaries($caseStudies)
    {
        return $caseStudies ? array_map(
            'self::extractCaseStudySummary',
            $caseStudies
        ) : [];
    }
}
