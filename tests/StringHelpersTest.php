<?php
declare (strict_types = 1);

use biglotteryfund\utils\StringHelpers;
use PHPUnit\Framework\TestCase;

final class StringHelpersTest extends TestCase
{
    public function testFormatBytes(): void
    {
        $this->assertEquals('100 B', StringHelpers::formatBytes(100));
        $this->assertEquals('1 KB', StringHelpers::formatBytes(1024));
        $this->assertEquals('1 MB', StringHelpers::formatBytes(1048576));
        $this->assertEquals('3.6 MB', StringHelpers::formatBytes(3774873.6));
        $this->assertEquals('1 GB', StringHelpers::formatBytes(1073741824));
    }
}
