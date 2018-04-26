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
        $alternateLanguage = $currentLanguage === 'en' ? 'cy' : $currentLanguage;

        $altEntry = Entry::find()
            ->id($entryId)
            ->site($alternateLanguage)
            ->one();

        $availableLanguages = [$currentLanguage];
        if ($altEntry) {
            array_push($availableLanguages, $alternateLanguage);
        }

        return $availableLanguages;
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

    public static function queryPromotedNews()
    {
        $newsQuery = Entry::find();
        return \Craft::configure($newsQuery, [
            'section' => 'news',
            'limit' => 3,
            'articlePromoted' => true,
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

    public static function getTags($tagField)
    {
        return array_map(function ($tag) {
            return [
                'id' => (int) $tag->id,
                'title' => $tag->title,
                'slug' => $tag->slug,
            ];
        }, $tagField);
    }

}
