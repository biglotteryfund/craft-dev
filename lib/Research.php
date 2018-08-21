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
        $common = ContentHelpers::getCommonDetailFields($entry, $this->locale);
        $researchMeta = $entry->researchMeta->one();

        return array_merge($common, [
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
                    'filesize' => StringHelpers::formatBytes($asset->size),
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
        ]);
    }
}
