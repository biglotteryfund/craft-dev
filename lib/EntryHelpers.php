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

    public static function getAvailableLanguages($entryId, $currentLanguage, $includeExpiredForTranslations = false)
    {
        $statuses = ['live'];
        $alternateLanguage = $currentLanguage === 'en' ? 'cy' : 'en';

        if ($includeExpiredForTranslations) {
            $statuses[] = 'expired';
        }

        $altEntry = Entry::find()
            ->id($entryId)
            ->site($alternateLanguage)
            ->status($statuses)
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
        return $locale === 'cy' ? "/welsh/$uri" : "/$uri";
    }

    public static function isDraftOrVersion()
    {
        $isDraft = \Craft::$app->request->getParam('draft');
        $isVersion = \Craft::$app->request->getParam('version');

        return $isDraft || $isVersion;
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

}
