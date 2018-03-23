# wordspressed

Parses a [WordPress export file](https://codex.wordpress.org/Tools_Export_Screen) (XML) with PHP, including categories and "post meta" (custom fields).

By [chrisullyott](https://github.com/chrisullyott/).

## Basic usage

```
$file = 'path/to/wordpress_export.xml';
$parser = new WordsPressed($file);
$items = $parser->getItems();

print_r($items[0]);
```

```
Array
(
    [0] => Array
        (
            [title] => A Simple Post with Text
            [link] => http://dev.site.com/a-simple-post-with-text/
            [pubDate] => Sun, 03 Aug 2018 00:52:26 +0000
            [dc:creator] => admin
            [category] => My Tag
            [category_nicename] => my-tag
            [guid] => http://dev.site.com/wordpress/?p=22
            [guid_isPermaLink] => false
            [description] => Lorem ipsum...
            [content:encoded] => Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
            ...
```

Also parses `postmeta` key/value pairs, adding data such as:

```
[_edit_last] => 1
[_edit_lock] => 1221689350
```

## Customizing Tidy options

You can customize the [PHP Tidy](http://php.net/manual/en/book.tidy.php) configuration to sanitize the file in a particular way before parsing.

```
$tidyOpts = array(
    'ascii-chars'                 => true,
    'logical-emphasis'            => true,
    'drop-empty-paras'            => true,
    'drop-font-tags'              => true,
    'drop-proprietary-attributes' => true
);

$parser = new WordsPressed($file, $tidyOpts);
```

For a list of options, see [http://tidy.sourceforge.net/docs/quickref.html](http://tidy.sourceforge.net/docs/quickref.html).
