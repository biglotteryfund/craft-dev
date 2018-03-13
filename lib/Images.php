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

            $defaults = array('auto' => "compress,format");

            if (array_key_exists('w', $options)) {
                $defaults['crop'] = 'entropy';
                $defaults['fit'] = 'crop';
            }

            $params = array_replace_recursive($defaults, $options);

            return $builder->createURL($parsedUri['path'], $params);
        } else {
            return $originalUrl;
        }
    }

    private static function innerHTML($node)
    {
        return implode(
            array_map(
                [$node->ownerDocument, "saveHTML"],
                iterator_to_array($node->childNodes)
            )
        );
    }

    public static function replaceInlineImgixUrls($content)
    {
        $imgixDomain = getenv('CUSTOM_IMGIX_DOMAIN');
        if ($imgixDomain) {
            $document = new DOMDocument();
            // https://stackoverflow.com/questions/6090667/php-domdocument-errors-warnings-on-html5-tags#6090728
            libxml_use_internal_errors(true);
            $document->loadHTML(mb_convert_encoding($content->getParsedContent(), 'HTML-ENTITIES', 'UTF-8'));
            libxml_clear_errors();

            $tags = $document->getElementsByTagName('img');
            foreach ($tags as $tag) {
                $originalSrc = $tag->getAttribute('src');
                if (preg_match("/media\.biglotteryfund\.org\.uk/", $originalSrc)) {
                    $newSrc = self::imgixUrl($originalSrc, [
                        'fit' => 'crop',
                        'crop' => 'entropy',
                        'max-w' => 2000,
                    ]);
                    $tag->setAttribute('src', $newSrc);
                }
            }

            return self::innerHTML($document->getElementsByTagName('body')[0]);
        } else {
            return $content;
        }
    }
}
