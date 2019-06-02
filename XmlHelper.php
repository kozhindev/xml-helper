<?php

namespace kozhindev;

use DOMDocument;
use DOMElement;
use DOMNode;

class XmlHelper
{
    public const TAG_ATTRIBUTES = '@attributes';
    public const TAG_TEXT_CONTENT = '@content';
    public const TAG_CDATA = '@@content';

    /**
     * @param $array
     * @param string $root root node name
     * @param bool $removeXmlTag
     * @return string
     * @example see tests/XmlHelperTest.php for example
     */
    public static function arrayToXml($array, $root = 'root', $removeXmlTag = false)
    {
        $doc = new DOMDocument();
        $doc->appendChild($root = $doc->createElement($root));
        static::appendNode($doc, $root, $array);
        $xml = $doc->saveXML();

        if ($removeXmlTag) {
            $xml = preg_replace('/^<\?xml[^>]+>\\n?/', '', $xml);
        }

        return $xml;
    }

    /**
     * @param string $xml
     * @return array
     */
    public static function xmlToArray($xml)
    {
        $doc = new DOMDocument();
        $doc->loadXML($xml);
        $root = $doc->documentElement;
        $output = static::domNodeToArray($root);
        $output['@root'] = $root->tagName;
        return $output;
    }

    /**
     * @param string $xml
     * @return string
     */
    public static function beautifyXml($xml)
    {
        // Fix internal xml
        $xml = str_replace(['&lt;', '&gt;'], ['<', '>'], $xml);

        // add marker linefeeds to aid the pretty-tokeniser (adds a linefeed between all tag-end boundaries)
        $xml = preg_replace('/(>)(<)(\/*)/', "$1\n$2$3", $xml);

        // now indent the tags
        $token = strtok($xml, "\n");
        $result = ''; // holds formatted version as it is built
        $pad = 0; // initial indent
        $matches = array(); // returns from preg_matches()

        // scan each line and adjust indent based on opening/closing tags
        while ($token !== false) :

            // test for the various tag states

            // 1. open and closing tags on same line - no change
            if (preg_match('/.+<\/\w[^>]*>$/', $token, $matches)) :
                $indent = 0;
            // 2. closing tag - outdent now
            elseif (preg_match('/^<\/\w/', $token, $matches)) :
                $pad--;
            // 3. opening tag - don't pad this one, only subsequent tags
            elseif (preg_match('/^<\w[^>]*[^\/]>.*$/', $token, $matches)) :
                $indent = 1;
            // 4. no indentation needed
            else :
                $indent = 0;
            endif;

            // pad the line with the required number of leading spaces
            $line = str_pad($token, strlen($token) + $pad, ' ', STR_PAD_LEFT);
            $result .= $line . "\n"; // add to the cumulative result, with linefeed
            $token = strtok("\n"); // get the next token
            $pad += $indent; // update the pad size for subsequent lines
        endwhile;

        return $result;
    }

    /**
     * @param DOMDocument $doc
     * @param DOMElement $node
     * @param $array
     */
    protected static function appendNode($doc, $node, $array)
    {
        foreach ($array as $key => $value) {
            if (substr($key, 0, 1) === '@') {
                continue;
            }

            $element = $doc->createElement($key);

            if (is_array($value)) {
                if (isset($value[static::TAG_ATTRIBUTES])) {
                    foreach ($value[static::TAG_ATTRIBUTES] as $attribute => $attributeValue) {
                        $element->setAttribute($attribute, $attributeValue);
                    }
                }
                if (isset($value[static::TAG_CDATA])) {
                    $element->appendChild($doc->createCDATASection($value[static::TAG_CDATA]));
                } elseif (isset($value[static::TAG_TEXT_CONTENT])) {
                    $element->textContent = $value[static::TAG_TEXT_CONTENT];
                }

                $hasNumericKeys = count(array_filter(array_keys($value),function($arrayKey) {
                    return is_int($arrayKey);
                })) > 0;

                if ($hasNumericKeys) {
                    foreach (array_values($value) as $arrayElement) {
                        static::appendNode($doc, $node, [$key => $arrayElement]);
                    }
                } else {
                    $node->appendChild($element);
                    static::appendNode($doc, $element, $value);
                }
            } else {
                $node->appendChild($element);
                $element->textContent = (string)$value;
            }
        }
    }

    /**
     * @param DOMNode $node
     * @return array|string
     */
    protected static function domNodeToArray($node)
    {
        $output = [];
        switch ($node->nodeType) {
            case XML_CDATA_SECTION_NODE:
            case XML_TEXT_NODE:
                $output = trim($node->textContent);
                break;

            case XML_ELEMENT_NODE:
                for ($i = 0, $m = $node->childNodes->length; $i < $m; $i++) {
                    $child = $node->childNodes->item($i);
                    $v = static::domNodeToArray($child);
                    if (isset($child->tagName)) {
                        $t = $child->tagName;
                        if (!isset($output[$t])) {
                            $output[$t] = [];
                        }
                        $output[$t][] = $v;
                    } elseif ($v || $v === '0') {
                        $output = (string)$v;
                    }
                }
                if ($node->attributes->length && !is_array($output)) { // Has attributes but isn't an array
                    $output = [static::TAG_TEXT_CONTENT => $output]; // Change output into an array.
                }
                if (is_array($output)) {
                    if ($node->attributes->length) {
                        $a = [];
                        foreach ($node->attributes as $attrName => $attrNode) {
                            $a[$attrName] = (string)$attrNode->value;
                        }
                        $output[static::TAG_ATTRIBUTES] = $a;
                    }
                    foreach ($output as $t => $v) {
                        if (is_array($v) && count($v) == 1 && $t != static::TAG_ATTRIBUTES) {
                            $output[$t] = $v[0];
                        }
                    }
                }
                break;
        }
        return $output;
    }
}
