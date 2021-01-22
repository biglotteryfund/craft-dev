<?php

namespace Biglotteryfund\utils;

use Biglotteryfund\utils\ContentHelpers;
use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class ResearchDocumentTransformer extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    public function transform(Entry $entry)
    {
        $asset = !empty($entry->document) ? $entry->document->one() : null;
        $documentData = $asset ? [
            'url' => $asset->url,
            'filetype' => $asset->extension,
            'filesize' => StringHelpers::formatBytes($asset->size, $precision = 0)
        ] : null;

        $relatedInsightsPage = null;
        if (!empty($entry->relatedInsightsPage->one())) {
            $page = $entry->relatedInsightsPage->one();
            $relatedInsightsPage = [
                'title' => $page->title,
                'linkUrl' => $page->externalUrl ? $page->externalUrl : EntryHelpers::uriForLocale($page->uri, $this->locale),
            ];
        }

        return array_merge(ContentHelpers::getCommonFields($entry, $this->locale), [
            'summary' => $entry->summary,
            'relatedFundingProgrammes' => array_map(function ($programme) {
                return [
                    'title' => $programme->title,
                    'linkUrl' => $programme->externalUrl ? $programme->externalUrl : EntryHelpers::uriForLocale($programme->uri, $this->locale),
                ];
            }, $entry->programme->status(['live', 'expired'])->all() ?? []),
            'portfolio' => !empty($entry->portfolio) ? ContentHelpers::nestedCategorySummary($entry->portfolio->all(), $this->locale) : [],
            'partnershipName' => $entry->partnershipName,
            'documentType' => !empty($entry->documentType->all()) ? ContentHelpers::categorySummary($entry->documentType->one(), $this->locale) : [],
            'document' => $documentData,
            'publisher' => $entry->publisher,
            'tags' => !empty($entry->documentTags) ? ContentHelpers::getTags($entry->documentTags->all(), $this->locale) : null,
            'relatedInsightsPage' => $relatedInsightsPage
        ]);
    }
}
