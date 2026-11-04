<?php
declare(strict_types=1);

namespace TuVo\AutoAddToCart\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use TuVo\AutoAddToCart\Model\LinkParser;

class LinkParserTest extends TestCase
{
    /**
     * @var LinkParser
     */
    private $parser;

    protected function setUp(): void
    {
        $this->parser = new LinkParser();
    }

    public function testParsesSkuQtyPairs()
    {
        $this->assertSame(
            ['ACC-STUFF-MA-1SET' => 4, 'ACC-NET-500-DROP-1PC' => 2],
            $this->parser->parse('ACC-STUFF-MA-1SET:4,ACC-NET-500-DROP-1PC:2')
        );
    }

    public function testQtyDefaultsToOne()
    {
        $this->assertSame(['SKU1' => 1, 'SKU2' => 3], $this->parser->parse('SKU1, SKU2:3'));
    }

    public function testMergesDuplicateSkus()
    {
        $this->assertSame(['SKU1' => 5], $this->parser->parse('SKU1:2,SKU1:3'));
    }

    public function testIgnoresInvalidChunks()
    {
        $this->assertSame(['OK' => 1], $this->parser->parse(',SKU:abc,:2,SKU2:0,SKU3:-1,OK:1,'));
    }

    public function testCapsQtyAndItemCount()
    {
        $this->assertSame(['SKU' => LinkParser::MAX_QTY], $this->parser->parse('SKU:99999'));

        $chunks = [];
        for ($i = 0; $i < LinkParser::MAX_ITEMS + 10; $i++) {
            $chunks[] = 'SKU' . $i . ':1';
        }
        $this->assertCount(LinkParser::MAX_ITEMS, $this->parser->parse(implode(',', $chunks)));
    }
}
