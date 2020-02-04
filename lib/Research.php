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

    private static function buildTrailImage($imageField)
    {
        return $imageField ? Images::imgixUrl(
            $imageField->imageMedium->one()->url,
            ['w' => '640', 'h' => '360']
        ) : null;
    }

    public function transform(Entry $entry)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);
        $researchMeta = $entry->researchMeta->one();
        return array_merge(ContentHelpers::getCommonFields($entry, $status, $this->locale), [
            // @TODO: Remove thumbnail in favour of trailImage once all pages have new hero images
            'thumbnail' => self::buildTrailImage(Images::extractImage($entry->heroImage)),
            'trailImage' => self::buildTrailImage(Images::extractNewHeroImageField($entry->hero)),

            'parent' => ContentHelpers::getParentInfo($entry, $this->locale),

            'introduction' => $entry->introductionText ?? null,
            'contactEmail' => $researchMeta ? $researchMeta->contactEmail : null,
            'researchPartners' => $researchMeta ? $researchMeta->researchPartners : null,

            'documentsPrefix' => $entry->researchDocumentsPrefix ?? null,
            'documents' => array_map(function ($document) {
                $asset = $document->documentAsset->one();
                return [
                    'title' => $document->documentTitle,
                    'url' => $asset->url,
                    'filetype' => $asset->extension,
                    'filesize' => StringHelpers::formatBytes($asset->size, $precision = 0),
                    'contents' => $document->documentContents ? explode("\n", $document->documentContents) : [],
                ];
            }, $entry->researchDocuments->all() ?? []),

            'relatedFundingProgrammes' => array_map(function ($programme) {
                return [
                    'title' => $programme->title,
                    'linkUrl' => $programme->externalUrl ? $programme->externalUrl : EntryHelpers::uriForLocale($programme->uri, $this->locale),
                ];
            }, $entry->relatedFundingProgrammes->status(['live', 'expired'])->all() ?? []),

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
