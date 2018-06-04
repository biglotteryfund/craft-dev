<?php

namespace biglotteryfund\utils;

use craft;
use craft\elements\Entry;
use League\Fractal\TransformerAbstract;
use League\Uri\Parser;

class VersionHelpers
{
    public static function checkValid(Entry $entry, String $expectedUri, String $expectedLocale)
    {
        // Need to parse the `url` as draft entries do not yet have a `uri` property. Craft bug?
        $parser = new Parser();
        $parsedUrl = $parser($entry->url);

        $isValid = (
            $parsedUrl['path'] === $expectedUri &&
            $entry->getSite()->handle === $expectedLocale
        );

        if ($isValid === false) {
            throw new \yii\web\ForbiddenHttpException('Forbidden');
        }
    }

    public static function getNormalisedStatus(Entry $entry)
    {
        $class = $entry->className();
        if ($class === 'craft\\models\\EntryDraft') {
            return 'draft';
        } else if ($class === 'craft\\models\\EntryVersion') {
            return 'version';
        } else {
            return $entry->status;
        }
    }

    public static function withDraftOrVersion(TransformerAbstract $transformer, $criteria)
    {
        $draftId = Craft::$app->request->getQueryParam('draft');
        $versionId = Craft::$app->request->getQueryParam('version');

        if ($draftId || $versionId) {
            $draftOrVersion = [
                'serializer' => 'jsonApi',
                'class' => craft\elementapi\resources\EntryResource::class,
                'one' => true,
                'transformer' => $transformer,
            ];

            if ($draftId) {
                $draftOrVersion['draftId'] = $draftId;
            }

            if ($versionId) {
                $draftOrVersion['versionId'] = $versionId;
            }

            return $draftOrVersion;
        } else {
            $elementQuery = [
                'serializer' => 'jsonApi',
                'class' => craft\elementapi\resources\EntryResource::class,
                'criteria' => $criteria,
                'one' => true,
                'transformer' => $transformer,
            ];

            return $elementQuery;
        }
    }
}
