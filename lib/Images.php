<?php

namespace biglotteryfund\utils;

use Imgix\UrlBuilder;
use League\Uri\Parser;

const IMAGE_SIZES = [
    'small' => [
        'w' => '644',
        'h' => '425'
    ],
    'medium' => [
        'w' => '1280',
        'h' => '720'
    ],
    'large' => [
        'w' => '1373',
        'h' => '405'
    ]
];

class Images
{
    private static function _getImgixConfig()
    {
        $imgixDomain = getenv('CUSTOM_IMGIX_DOMAIN');
        $imgixSignKey = getenv('CUSTOM_IMGIX_SIGN_KEY');

        if ($imgixDomain && $imgixSignKey) {
            return [
                'domain' => $imgixDomain,
                'signKey' => $imgixSignKey,
            ];
        }
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

    public static function getStandardCrops($imageUrl)
    {
        return [
            'small' => self::imgixUrl(
                $imageUrl,
                IMAGE_SIZES['small']
            ),
            'medium' => self::imgixUrl(
                $imageUrl,
                IMAGE_SIZES['medium']
            ),
            'large' => self::imgixUrl(
                $imageUrl,
                IMAGE_SIZES['large']
            ),
        ];
    }

    public static function buildHeroImage($heroEntry)
    {
        $imageSmall = self::imgixUrl(
            $heroEntry->imageSmall->one()->url,
            IMAGE_SIZES['small']
        );

        $imageMedium = self::imgixUrl(
            $heroEntry->imageMedium->one()->url,
            IMAGE_SIZES['medium']
        );

        $imageLarge = self::imgixUrl(
            $heroEntry->imageLarge->one()->url,
            IMAGE_SIZES['large']
        );

        return [
            'title' => $heroEntry->title,
            'caption' => $heroEntry->caption,
            'default' => $imageMedium,
            'small' => $imageSmall,
            'medium' => $imageMedium,
            'large' => $imageLarge,
            'grantId' => $heroEntry->heroGrantId ?? null,
        ];
    }

    public static function extractHeroImage($imageField)
    {
        $hero = self::extractImage($imageField);
        return $hero ? self::buildHeroImage($hero) : null;
    }

    public static function extractHomepageHeroImage($hero)
    {
        $defaults = ['lossless' => true, 'q' => 90];

        $imageSmall = self::imgixUrl(
            $hero->imageSmall->one()->url,
            array_replace_recursive($defaults, ['w' => '644', 'h' => '573'])
        );

        $imageMedium = self::imgixUrl(
            $hero->imageMedium->one()->url,
            array_replace_recursive($defaults, ['w' => '1280', 'h' => '720'])
        );

        $imageLarge = self::imgixUrl(
            $hero->imageLarge->one()->url,
            array_replace_recursive($defaults, ['w' => '1373', 'h' => '505'])
        );

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
}
