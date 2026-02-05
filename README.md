HTMLParser Class
A robust, session-safe PHP HTML/XML parser with XPath-like querying
Created by Tsiorifinidy Razafindrazaka • tsiorifinidy@yahoo.com

Overview
HTMLParser is a PHP class that solves critical issues with PHP's built-in DOMDocument when used with sessions, caching, or debugging functions.

The Problem
Traditional PHP DOM parsers cause fatal errors when:

Stored in $_SESSION with session_start()

Passed to var_dump() or print_r()

Serialized for caching

Used in debugging contexts

The Solution
HTMLParser creates a pure PHP object tree with:

No internal PHP resources

Complete serialization support

Safe for sessions and caching

Full debugging compatibility

Key Features
Feature	Description
Session Safe	Can be stored in $_SESSION without fatal errors
Robust Parsing	Handles malformed HTML, special elements, namespaces
Tree Structure	Creates navigable node hierarchy with depth tracking
XPath Queries	XPath-like syntax for element selection
Built-in Cache	Static cache avoids re-parsing identical HTML
Debug Friendly	Works with var_dump(), print_r(), serialize()
Quick Start
Installation
php
<?php
require_once 'HTMLParser.php';
use Reno\core\HTMLParser;
?>
Basic Example
php
<?php
// Parse HTML
$html = '<div class="container"><h1>Hello World</h1></div>';
$parser = HTMLParser::parse($html);

// Store in session - SAFELY!
$_SESSION['page'] = $parser;

// Query elements
$div = $parser->first('div');
echo $div->getAttribute('class'); // "container"
echo $parser->getText(); // "Hello World"
?>
Public Methods 
attr(string $query, string $attribute): ?string
Gets an attribute value from the first matching element.

Parameters:

string $query - XPath query

string $attribute - Attribute name

Returns: ?string - Attribute value or null

Example:

php
<?php
$class = $parser->attr('div', 'class'); // "container"
?>
clearCache(): void
Clears the internal static cache.

Example:

php
<?php
HTMLParser::clearCache();
?>
count(string $query): int
Counts matching elements.

Parameters:

string $query - XPath query

Returns: int - Number of matches

Example:

php
<?php
$count = $parser->count('img'); // 5
?>
exists(string $query): bool
Checks if element exists.

Parameters:

string $query - XPath query

Returns: bool - True if exists

Example:

php
<?php
if ($parser->exists('.error')) {
    echo 'Error found!';
}
?>
filter(callable $callback): array
Filters nodes with a callback.

Parameters:

callable $callback - Function returning boolean

Returns: array - Filtered nodes

Example:

php
<?php
$active = $parser->filter(function($node) {
    return $node->getAttribute('class') === 'active';
});
?>
find(string $tagName): array
Recursive search by tag name.

Parameters:

string $tagName - Tag name

Returns: array - Matching nodes

Example:

php
<?php
$allDivs = $parser->find('div');
?>
first(string $query): ?HTMLParser
Gets first matching element.

Parameters:

string $query - XPath query

Returns: ?HTMLParser - Node or null

Example:

php
<?php
$firstDiv = $parser->first('div');
?>
getAttribute(string $name): ?string
Gets a specific attribute.

Parameters:

string $name - Attribute name

Returns: ?string - Value or null

Example:

php
<?php
$id = $node->getAttribute('id');
?>
getAttributes(): array
Gets all attributes.

Returns: array - Key-value pairs

Example:

php
<?php
$attrs = $node->getAttributes();
// ['class' => 'container', 'id' => 'main']
?>
getChildren(): array
Gets all child nodes.

Returns: array - Child nodes

Example:

php
<?php
foreach ($node->getChildren() as $child) {
    echo $child->getName();
}
?>
getChildrenByType(string $type): array
Gets children by type.

Parameters:

string $type - 'element', 'text', 'comment', 'doctype'

Returns: array - Filtered children

Example:

php
<?php
$textNodes = $node->getChildrenByType('text');
?>
getCommentChildren(): array
Gets comment children.

Returns: array - Comment nodes

getContent(): ?string
Gets node content.

Returns: ?string - Content

getDepth(): int
Gets node depth.

Returns: int - Depth level

Example:

php
<?php
echo $node->getDepth(); // 2
?>
getDirectChildren(): array
Gets direct children only.

Returns: array - Direct children

getDoctypeChildren(): array
Gets doctype children.

Returns: array - Doctype nodes

getName(): ?string
Gets element name.

Returns: ?string - Tag name

Example:

php
<?php
echo $node->getName(); // "div"
?>
getNameSpace(): ?string
Gets namespace prefix.

Returns: ?string - Namespace

getText(): string
Gets all text content.

Returns: string - Combined text

Example:

php
<?php
echo $parser->getText(); // "Hello World"
?>
getTextChildren(): array
Gets text children.

Returns: array - Text nodes

getTextContent(): string
Alias of getText().

Returns: string - Text content

getType(): string
Gets node type.

Returns: string - 'root', 'element', 'text', 'comment', 'doctype'

Example:

php
<?php
echo $node->getType(); // "element"
?>
hasChildren(): bool
Checks if has children.

Returns: bool - True if has children

countChildren(): int
Counts direct children.

Returns: int - Child count

parse(string $html): HTMLParser
Parses HTML/XML string.

Parameters:

string $html - HTML/XML to parse

Returns: HTMLParser - Root node

Throws: InvalidArgumentException - If XML declaration is malformed

Example:

php
<?php
try {
    $parser = HTMLParser::parse($html);
} catch (InvalidArgumentException $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
printTree(int $indent = 0): void
Prints tree structure.

Parameters:

int $indent - Indentation level

Example:

php
<?php
$parser->printTree();
?>
text(string $query): ?string
Gets text of first match.

Parameters:

string $query - XPath query

Returns: ?string - Text or null

Example:

php
<?php
$title = $parser->text('h1'); // "Page Title"
?>
toArray(): array
Converts to array for serialization.

Returns: array - Array representation

Example:

php
<?php
$array = $parser->toArray();
$json = json_encode($array);
?>
toDebugString(): string
Gets debug string.

Returns: string - Debug info

Example:

php
<?php
echo $node->toDebugString(); // "element{name: div, children: 3, depth: 2}"
?>
toHTML(): string
Converts back to HTML.

Returns: string - HTML string

Example:

php
<?php
$html = $parser->toHTML();
file_put_contents('output.html', $html);
?>
toString(): string
Gets string representation.

Returns: string - String version

__toString(): string
Magic string conversion.

Returns: string - Calls toString()

xpath(string $query, bool $returnSingle = false): HTMLParser|array|null
Executes XPath-like query.

Parameters:

string $query - XPath query

bool $returnSingle - Return single node

Returns: HTMLParser|array|null - Node(s) or null

Example:

php
<?php
// All divs
$divs = $parser->xpath('//div');

// Single div with class
$container = $parser->xpath('//div[@class="container"]', true);
?>
xpathAdvanced(string $query): array
Advanced XPath with predicates.

Parameters:

string $query - Advanced query

Returns: array - Matching nodes

Example:

php
<?php
$nodes = $parser->xpathAdvanced('//div[@class="test"][2]');
?>
xpathAttribute(string $query, string $attribute): array
Gets attribute values.

Parameters:

string $query - XPath query

string $attribute - Attribute name

Returns: array - Attribute values

Example:

php
<?php
$hrefs = $parser->xpathAttribute('//a', 'href');
?>
xpathText(string $query): array
Gets text from matches.

Parameters:

string $query - XPath query

Returns: array - Text strings

Example:

php
<?php
$texts = $parser->xpathText('//p');
// ["Para 1", "Para 2"]
?>
Advanced Examples
Session & Cache Storage
php
<?php
// Parse once
$parser = HTMLParser::parse($html);

// Store in session
$_SESSION['parsed_page'] = $parser;

// Store in cache
$cache->set('page_cache', $parser, 3600);

// Debug safely
var_dump($parser); // Works perfectly!
?>
Complex Document Querying
php
<?php
// External links
$external = $parser->xpath('//a[starts-with(@href, "http")]');

// Table data
$rows = $parser->xpath('//table[@id="data"]//tr');

// Form inputs
$inputs = $parser->xpath('//input[@type="text"]');
?>
Content Extraction
php
<?php
// Extract all images
$images = [];
$imgNodes = $parser->xpath('//img');

foreach ($imgNodes as $img) {
    $images[] = [
        'src' => $img->getAttribute('src'),
        'alt' => $img->getAttribute('alt')
    ];
}

echo json_encode($images);
?>
Performance Tips
Use built-in cache - Identical HTML is cached automatically

Store parser objects - Don't re-parse, store the object

Use specific queries - xpath('//div[@id="main"]') is faster

Clear cache wisely - Use clearCache() in long-running scripts

Batch operations - Process multiple elements at once

Limitations
Not full XPath 1.0/2.0 (subset of most useful features)

CSS selectors limited to .class and #id

Large documents (>10MB) need sufficient memory

Namespace support is basic but functional

Architecture Notes
Tokenizer Design
State-machine based parser

Handles quoted attributes correctly

Manages <script>, <style>, <textarea> as raw text

Supports XML declarations and DOCTYPEs

Memory Management
Static cache: MD5-based cache for identical HTML

Tree structure: Each node independent, no circular references

Serializable: Pure PHP objects, no resources

License
MIT License - Free to use, modify, and distribute.

Author
Tsiorifinidy Razafindrazaka
Email: tsiorifinidy@yahoo.com
Copyright (c) 2026 tsiorifinidy razafindrazaka

