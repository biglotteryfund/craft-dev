<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\ContentHelpers;
use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class ResearchTransformer extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    public function transform(Entry $entry)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);
        $common = ContentHelpers::getCommonFields($entry, $status, $this->locale);

        $researchMeta = $entry->researchMeta->one();
        $heroImage = Images::getHeroImageField($entry->hero);

        return array_merge($common, [
            'trailImage' => $heroImage ? Images::imgixUrl(
                $heroImage->imageMedium->one()->url,
                ['w' => '640', 'h' => '360']
            ) : null,

            'introduction' => $entry->introductionText ?? null,
            'contactEmail' => $researchMeta ? $researchMeta->contactEmail : null,
            'researchPartners' => $researchMeta ? $researchMeta->researchPartners : null,

            'documentsPrefix' => $entry->researchDocumentsPrefix ?? null,
            'documents' => array_map(function ($document) {
                $asset = $document->documentAsset->one();
                return [
                    'title' => $document->documentTitle,
                    'url' => $asset->url,
                    'filetype' => $asset->kind,
                    'filesize' => StringHelpers::formatBytes($asset->size, $precision = 0),
                    'contents' => $document->documentContents ? explode("\n", $document->documentContents) : [],
                ];
            }, $entry->researchDocuments->all() ?? []),

            'relatedFundingProgrammes' => array_map(function ($programme) {
                return [
                    'title' => $programme->title,
                    'linkUrl' => $programme->externalUrl ? $programme->externalUrl : EntryHelpers::uriForLocale($programme->uri, $this->locale),
                ];
            }, $entry->relatedFundingProgrammes->all() ?? []),

            'sectionsPrefix' => $entry->researchSectionsPrefix ?? null,
            'sections' => array_map(function ($row) {
                return [
                    'title' => $row->sectionTitle,
                    'prefix' => $row->sectionPrefix ?? null,
                    'parts' => array_map(function ($block) {
                        switch ($block->type->handle) {
                            case 'contentArea':
                                return [
                                    'type' => $block->type->handle,
                                    'title' => $block->contentTitle,
                                    'content' => $block->contentBody,
                                ];
                                break;
                            case 'callout':
                                return [
                                    'type' => $block->type->handle,
                                    'content' => $block->calloutContent,
                                    'credit' => $block->calloutCredit ?? null,
                                    'isQuote' => $block->isQuote,
                                ];
                                break;
                        }
                    }, $row->contentSections->all() ?? [])
                ];
            }, $entry->researchSections->all() ?? []),

            'meta' => [
                'searchScore' => $entry->searchScore ?? null,
            ],
        ]);
    }
}
