<?php

namespace biglotteryfund\utils;

use Imgix\ShardStrategy;
use Imgix\UrlBuilder;
use League\Uri\Parser;

class Images
{
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

    public static function imgixUrl($originalUrl, $options = [])
    {
        $imgixDomain = getenv('CUSTOM_IMGIX_DOMAIN');
        if ($imgixDomain) {
            $parser = new Parser();
            $parsedUri = $parser($originalUrl);

            // PHP doesn't have named parameters, so…
            // UrlBuilder($domain, $useHttps, $signKey, $shardStrategy, $includeLibraryParam = true)
            $builder = new UrlBuilder($imgixDomain, true, "", ShardStrategy::CRC, false);

            $defaults = array('auto' => "compress,format", 'crop' => 'entropy', 'fit' => 'crop');
            $params = array_replace_recursive($defaults, $options);
            return $builder->createURL($parsedUri['path'], $params);
        } else {
            return $originalUrl;
        }
    }
}
