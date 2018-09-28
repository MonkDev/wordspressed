<?php

/**
 * Parse Wordpress XML documents using xml_parse_into_struct().
 *
 * http://php.net/manual/en/function.xml-parse-into-struct.php
 *
 * @author Chris Ullyott <chris@monkdevelopment.com>
 */
class WordsPressed
{
    /**
     * The path to the XML file.
     *
     * @var string
     */
    private $file;

    /**
     * A well-formed XML string.
     *
     * @var string
     */
    private $xmlString;

    /**
     * The raw XML structure.
     *
     * @var array
     */
    private $xmlStructure;

    /**
     * The parsed XML array.
     *
     * @var array
     */
    private $xmlArray;

    /**
     * An array of options for Tidy.
     *
     * @var array
     */
    private $tidyOpts;

    /**
     * Default Tidy configuration options.
     *
     * http://tidy.sourceforge.net/docs/quickref.html
     *
     * @var array
     */
    private static $defaultTidyOpts = array(
        'input-xml'  => true,
        'output-xml' => true,
        'clean'      => true
    );

    /**
     * Constructor.
     *
     * @param string $file The path to the XML file
     */
    public function __construct($file, array $tidyOpts = array())
    {
        $this->file = $file;
        $this->tidyOpts = $tidyOpts;
    }

    /**
     * Get the path to the XML file.
     *
     * @return string
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Get the complete Tidy configuration.
     *
     * @return array
     */
    public function getTidyConfig()
    {
        return array_replace(static::$defaultTidyOpts, $this->tidyOpts);
    }

    /**
     * Sanitize an XML string.
     *
     * @param  string $xmlString A well-formed XML string
     * @param  array  $tidyOpts  Custom options for Tidy
     * @return string
     */
    private function sanitizeXmlString($xmlString, array $tidyOpts = array())
    {
        if (!$xmlString) {
            return '';
        }

        $config = $this->getTidyConfig();

        if ($result = tidy_repair_string($xmlString, $config, 'utf8')) {
            return $result;
        }

        throw new Exception('Failed to repair string with Tidy');
    }

    /**
     * Get the raw XML string.
     *
     * @return string
     */
    private function getXmlString()
    {
        if (is_null($this->xmlString)) {
            $this->xmlString = file_get_contents($this->getFile());
            $this->xmlString = $this->sanitizeXmlString($this->xmlString);
        }

        return $this->xmlString;
    }

    /**
     * Parse an XML string into its basic structure.
     *
     * @return array
     */
    private function getXmlStructure()
    {
        if (is_null($this->xmlStructure)) {
            $parser = xml_parser_create();
            xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
            xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
            xml_parse_into_struct($parser, $this->getXmlString(), $this->xmlStructure);
            xml_parser_free($parser);
        }

        return $this->xmlStructure;
    }

    /**
     * Get the parsed XML array.
     *
     * @return array
     */
    private function getXmlArray()
    {
        if (is_null($this->xmlArray)) {
            $this->xmlArray = self::xmlStructureToArray($this->getXmlStructure());
        }

        return $this->xmlArray;
    }

    /**
     * Parse an XML structure into a cohesive array of parent and child nodes.
     *
     * @param  array $xmlStructure The raw XML structure
     * @return array
     */
    private static function xmlStructureToArray(array $xmlStructure)
    {
        $elements = array();
        $stack = array();

        foreach ($xmlStructure as $node) {
            $index = count($elements);

            if (in_array($node['type'], array('open', 'complete'))) {
                $tag = isset($node['tag']) ? $node['tag'] : null;
                $value = isset($node['value']) ? $node['value'] : null;
                $attributes = isset($node['attributes']) ? $node['attributes'] : null;

                $elements[$index] = array(
                    'tag' => $tag,
                    'value' => $value,
                    'attributes' => $attributes
                );

                if ($node['type'] === 'open') {
                    $elements[$index]['children'] = array();
                    $stack[count($stack)] = &$elements;
                    $elements = &$elements[$index]['children'];
                }
            }

            if ($node['type'] === 'close') {
                $elements = &$stack[count($stack) - 1];
                unset($stack[count($stack) - 1]);
            }
        }

        return $elements[0];
    }

    /**
     * Get a parsed array of content items.
     *
     * @return array
     */
    public function getItems()
    {
        $items = self::buildItems($this->getXmlArray(), 'item');

        return self::distributeKeys($items);
    }

    /**
     * Look across every item in an array of arrays ensure each array has
     * the same keys, adding empty keys where necessary.
     *
     * @param  array  $items An array of arrays
     * @return array
     */
    private static function distributeKeys(array $items)
    {
        $keys = [];

        foreach ($items as $item) {
            $keys = array_unique(array_merge($keys, array_keys($item)));
        }

        foreach ($items as $itemKey => $item) {
            $newItem = [];

            foreach ($keys as $valueKey) {
                if (isset($items[$itemKey][$valueKey])) {
                    $newItem[$valueKey] = $item[$valueKey];
                } else {
                    $newItem[$valueKey] = null;
                }
            }

            $items[$itemKey] = $newItem;
        }

        return $items;
    }

    /**
     * Build an array of arrays representing the nodes of a given name.
     *
     * @param  array  $xmlArray The array of parsed XML
     * @param  string $tagName  The name of the node to filter by
     * @return array
     */
    private static function buildItems(array $xmlArray, $tagName)
    {
        $items = array();

        // Build item values.
        if (!empty($xmlArray['children']) && $xmlArray['tag'] === $tagName) {
            $itemValues = array();

            $itemValues = array_merge($itemValues, self::buildItemValues($xmlArray));
            $itemValues = array_merge($itemValues, self::buildCategories($xmlArray));
            $itemValues = array_merge($itemValues, self::buildPostMeta($xmlArray));

            $items[] = array_map('trim', $itemValues);
        }

        // Continue iteration.
        foreach ($xmlArray as $value) {
            if (is_array($value)) {
                $items = array_merge($items, self::buildItems($value, $tagName));
            }
        }

        return $items;
    }

    /**
     * Build an array of item values from a node array.
     *
     * @param  array $nodeArray A parsed XML node
     * @return array
     */
    private static function buildItemValues(array $nodeArray)
    {
        $itemValues = array();

        foreach ($nodeArray['children'] as $i) {
            if (in_array($i['tag'], array('category', 'wp:postmeta'))) {
                continue;
            }

            $tag = $i['tag'];
            $value = $i['value'];
            $attributes = $i['attributes'];

            $itemValues[$tag] = $value;

            if ($attributes) {
                $attributeValues = self::buildAttributeValues($tag, $attributes);
                $itemValues = array_merge($itemValues, $attributeValues);
            }
        }

        return $itemValues;
    }

    /**
     * Build a comma-separated list of category names.
     *
     * @param  array $nodeArray A parsed XML node
     * @return array
     */
    private static function buildCategories(array $nodeArray)
    {
        $data = array();
        $list = array();

        foreach ($nodeArray['children'] as $i) {
            if ($i['tag'] === 'category' && isset($i['attributes']['domain'])) {
                $data[$i['attributes']['domain']][] = $i['value'];
            }
        }

        foreach ($data as $domain => $value) {
            $key = "category_{$domain}";
            $csv = implode(',', $value);
            $list[$key] = $csv;
        }

        return $list;
    }

    /**
     * Build key/value pairs from WordPress `postmeta` fields.
     *
     * @param  array $nodeArray A parsed XML node
     * @return array
     */
    private static function buildPostMeta(array $nodeArray)
    {
        $postMeta = array();

        foreach ($nodeArray['children'] as $i) {
            if ($i['tag'] !== 'wp:postmeta') {
                continue;
            }

            $key = '';
            $value = '';

            foreach ($i['children'] as $ii) {
                if ($ii['tag'] === 'wp:meta_key') {
                    $key = $ii['value'];
                } elseif ($ii['tag'] === 'wp:meta_value') {
                    $value = $ii['value'];
                }
            }

            if ($key) {
                $postMeta[$key] = $value;
            }
        }

        return $postMeta;
    }

    /**
     * Build additional item values from node attributes.
     *
     * @param  string $tagName        The XML tag name
     * @param  array  $attributeArray A parsed node attributes array
     * @return array
     */
    private static function buildAttributeValues($tagName, array $attributeArray)
    {
        $item = array();

        foreach ($attributeArray as $key => $value) {
            $attributeKey = "{$tagName}_{$key}";
            $item[$attributeKey] = $value;
        }

        return $item;
    }
}
