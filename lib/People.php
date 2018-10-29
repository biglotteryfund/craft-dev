<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\ContentHelpers;
use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class PeopleTransformer extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    public function transform(Entry $entry)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);
        $common = ContentHelpers::getCommonDetailFields($entry, $this->locale);

        $documents = $entry->documents->one();

        return array_merge($common, [
            'people' => array_map(function ($person) {
                return [
                    'name' => $person->personName,
                    'role' => $person->personRole ?? null,
                    'image' => Images::extractImageUrl($person->personPhoto),
                    'bio' => $person->personBio
                ];
            }, $entry->people->all() ?? []),
            'documents' => $documents ? [
                'title' => $documents->documentsTitle,
                'files' => array_map(function ($document) {
                    $file = $document->documentFile->one();
                    return [
                        'label' => $document->documentTitle,
                        'caption' => $document->documentDescription ?? null,
                        'href' => $file->url,
                        'filetype' => $file->kind,
                        'filesize' => StringHelpers::formatBytes($file->size, $precision = 0),
                    ];
                }, $documents->documents->all() ?? []),
            ] : null
        ]);
    }
}
