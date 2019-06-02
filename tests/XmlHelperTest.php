<?php

namespace kozhindev\tests;

include __DIR__ . '/../XmlHelper.php';

use DOMDocument;
use kozhindev\XmlHelper;
use \PHPUnit_Framework_TestCase;

class XmlHelperTest extends PHPUnit_Framework_TestCase
{
    public $sampleXml = <<<XML
<?xml version="1.0"?>
<root>
 <library>
  <address>
   <street>Cross st.</street>
   <detailedAddress>
    <![CDATA[end of the cross st.]]>
   </detailedAddress>
  </address>
  <books containsNonBooksEntities="1">
   <book name="Moby Dick">
    <chapters>
     <count>10</count>
    </chapters>
   </book>
   <book name="Hard Times"/>
   <nonBookEntity/>
  </books>
 </library>
</root>
XML;

    public $sampleArray = [
        'library' => [
            'address' => [
                'street' => 'Cross st.',
                'detailedAddress' => [
                    '@@content' => 'end of the cross st.',
                ],
            ],
            'books' => [
                'book' => [
                    [
                        'chapters' => [
                            'count' => '10',
                        ],
                        '@attributes' => [
                            'name' => 'Moby Dick',
                        ],
                    ],
                    [
                        '@attributes' => [
                            'name' => 'Hard Times',
                        ],
                    ],
                ],
                'nonBookEntity' => [],
                '@attributes' => [
                    'containsNonBooksEntities' => '1',
                ],
            ],
        ],
        '@root' => 'root',
    ];

    public function testXmlToArray()
    {
        // CDATA node (@@content) is collapsed in text node
        $sampleArray = $this->sampleArray;
        $sampleArray['library']['address']['detailedAddress'] = $sampleArray['library']['address']['detailedAddress']['@@content'];

        $this->assertEquals(
            $sampleArray,
            XmlHelper::xmlToArray($this->sampleXml)
        );
    }

    public function testArrayToXml()
    {
        $expectedXml = new DOMDocument();
        $expectedXml->loadXML($this->sampleXml);

        $actualXml = new DOMDocument();
        $actualXml->loadXML(XmlHelper::arrayToXml($this->sampleArray));

        $this->assertEqualXMLStructure($expectedXml->documentElement, $actualXml->documentElement);
    }
}
