<?php
declare (strict_types = 1);

use biglotteryfund\utils\Images;
use PHPUnit\Framework\TestCase;

final class ImagesTest extends TestCase
{
    public function testBuildsImgixUrl(): void
    {
        $this->assertRegExp(
            '/image\.jpg\?auto=compress%2Cformat&crop=entropy&fit=crop&s=.*$/',
            Images::imgixUrl("https://media.example.com/path/to/image.jpg")
        );

        $this->assertRegExp(
            '/image\.jpg\?auto=compress%2Cformat&crop=entropy&fit=crop&w=100&s=.*$/',
            Images::imgixUrl("https://media.example.com/path/to/image.jpg", ['w' => 100])
        );
    }
}
