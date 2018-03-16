<?php

namespace biglotteryfund\utils;

use Imgix\UrlBuilder;
use League\Uri\Parser;

class Images
{
    private static function _getImgixConfig() {
        $imgixDomain = getenv('CUSTOM_IMGIX_DOMAIN');
        $imgixSignKey = getenv('CUSTOM_IMGIX_SIGN_KEY');

        if ($imgixDomain && $imgixSignKey) {
            return [
                'domain' => $imgixDomain,
                'signKey' => $imgixSignKey
            ];
        }
    }

    public static function extractImage($imageField)
    {
        $image = $imageField->one();
        return $image ?? null;
    }

    public static function extractImageUrl($imageField)
    {
        $image = $imageField->one();
        return $image ? $image->url : null;
    }

    public static function extractHeroImage($imageField)
    {
        $result = null;
        $hero = self::extractImage($imageField);
        if ($hero) {

            $imageSmall = self::imgixUrl($hero->imageSmall->one()->url, ['w' => '644 ', 'h' => '425']);
            $imageMedium = self::imgixUrl($hero->imageMedium->one()->url, ['w' => '1280', 'h' => '720']);
            $imageLarge = self::imgixUrl($hero->imageLarge->one()->url, ['w' => '1373 ', 'h' => '405']);

            $result = [
                'title' => $hero->title,
                'caption' => $hero->caption,
                'default' => $imageMedium,
                'small' => $imageSmall,
                'medium' => $imageMedium,
                'large' => $imageLarge,
            ];

            if ($hero->captionFootnote) {
                $result['captionFootnote'] = $hero->captionFootnote;
            }
        }

        return $result;
    }

    public static function extractHomepageHeroImage($hero)
    {
        $imageSmall = self::imgixUrl($hero->imageSmall->one()->url, ['w' => '644', 'h' => '573']);
        $imageMedium = self::imgixUrl($hero->imageMedium->one()->url, ['w' => '1280', 'h' => '720']);
        $imageLarge = self::imgixUrl($hero->imageLarge->one()->url, ['w' => '1373', 'h' => '503']);

        $result = [
            'caption' => $hero->caption,
            'default' => $imageMedium,
            'small' => $imageSmall,
            'medium' => $imageMedium,
            'large' => $imageLarge,
        ];

        if ($hero->captionFootnote) {
            $result['captionFootnote'] = $hero->captionFootnote;
        }

        return $result;
    }

    /**
     * Wrapper around `extractHomepageHeroImage`
     * for extracting an array of summaries from a list of hero images
     */
    public static function extractHomepageHeroImages($homepageHeroImages)
    {
        return array_map(
            'self::extractHomepageHeroImage',
            $homepageHeroImages
        );
    }

    public static function imgixUrl($originalUrl, $options = [])
    {
        $imgixConfig = self::_getImgixConfig();

        if ($imgixConfig) {
            $parser = new Parser();
            $parsedUri = $parser($originalUrl);

            $builder = new UrlBuilder($imgixConfig['domain']);
            $builder->setSignKey($imgixConfig['signKey']);
            $builder->setUseHttps(true);
            $builder->setIncludeLibraryParam(false);

            $defaults = array('auto' => "compress,format", 'crop' => 'entropy', 'fit' => 'crop');
            $params = array_replace_recursive($defaults, $options);
            return $builder->createURL($parsedUri['path'], $params);
        } else {
            return $originalUrl;
        }
    }
}
