<?php

/**
 * @coversDefaultClass WordsPressed
 *
 * PHPUnit usage example:
 *
 * $ wget https://phar.phpunit.de/phpunit-3.7.10.phar
 * $ php phpunit-3.7.10.phar WordsPressedTest
 *
 * https://phar.phpunit.de/
 */

require_once '../src/WordsPressed.php';

class WordsPressedTest extends \PHPUnit_Framework_TestCase
{
    protected $parser;
    protected $items = array();

    protected $file = 'posts.xml';
    protected $tidyOpts = array('ascii-chars' => true);

    protected function setUp()
    {
        $this->parser = new WordsPressed($this->file, $this->tidyOpts);
        $this->items = $this->parser->getItems();
    }

    public function testParse()
    {
        $this->assertTrue(!empty($this->items));
    }

    public function testCategories()
    {
        $this->assertTrue(!empty($this->items[0]['category_tag']));
    }

    public function testPostMeta()
    {
        $this->assertEquals($this->items[0]['_edit_lock'], '1221689350');
    }
}
