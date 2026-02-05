<?php
declare(strict_types=1);
namespace Reno\core;
/**
 * HTMLParser - A robust, session-safe PHP HTML/XML parser.
 *
 * This class provides a serializable tree structure for HTML/XML documents,
 * solving critical issues with PHP's DOMDocument in sessions and debugging.
 * It includes XPath-like query capabilities and an internal parsing cache.
 *
 * @copyright Copyright (c) 2026 Tsiorifinidy Razafindrazaka
 * @license https://opensource.org/licenses/MIT MIT License
 * @author Tsiorifinidy Razafindrazaka <tsiorifinidy@yahoo.com>
 */

class HTMLParser {
    private string $type;
    private ?string $name;
    private ?string $nameSpace;
    private array $attributes;
    private ?string $content;
    private array $children;
    private int $depth;
    
    // Cache as static to avoid the same html reparsing
    private static array $cache = [];
    
    /**
     * Private constructor to force factory methods.
     */
    private function __construct(
        string $type,
        ?string $name = null,
        ?string $nameSpace = null,
        array $attributes = [],
        ?string $content = null,
        array $children = [],
        int $depth = 0
    ) {
        $this->type = $type;
        $this->name = $name;
        $this->nameSpace = $nameSpace;
        $this->attributes = $attributes;
        $this->content = $content;
        $this->children = $children;
        $this->depth = $depth;
    }
    
    /**
     * Main factory method.
     * @throws \InvalidArgumentException If XML declaration is malformed
     */
    public static function parse(string $html): HTMLParser 
    {
        $cacheKey = md5($html);
        
        // Cache to avoid re-parsing the same HTML
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        
        // Check for XML declaration and validate it
        self::validateXmlDeclaration($html);
        
        $tokens = self::tokenize($html);
        $root = self::buildObjectTree($tokens);
        
        self::$cache[$cacheKey] = $root;
        return $root;
    }
    
    /**
     * Validates XML declaration if present
     * @throws \InvalidArgumentException If XML declaration is malformed
     */
    private static function validateXmlDeclaration(string $html): void
    {
        // Remove leading whitespace
        $trimmed = ltrim($html);
        
        // Check if it starts with XML declaration
        if (str_starts_with($trimmed, '<?xml')) {
            // Find the end of the declaration
            $endPos = strpos($trimmed, '?>');
            if ($endPos === false) {
                throw new \InvalidArgumentException('Invalid XML declaration: missing closing ?>');
            }
            
            $declaration = substr($trimmed, 0, $endPos + 2);
            
            // Basic validation of XML declaration
            if (!preg_match('/^<\?xml\s+version\s*=\s*["\'][0-9.]+["\']/', $declaration)) {
                throw new \InvalidArgumentException('Invalid XML declaration: version attribute required');
            }
            
            // Check for valid attributes
            if (preg_match('/encoding\s*=\s*["\']/', $declaration)) {
                if (!preg_match('/encoding\s*=\s*["\'][^"\']+["\']/', $declaration)) {
                    throw new \InvalidArgumentException('Invalid XML declaration: encoding attribute malformed');
                }
            }
            
            // Check for standalone attribute
            if (preg_match('/standalone\s*=\s*["\']/', $declaration)) {
                if (!preg_match('/standalone\s*=\s*["\'](yes|no)["\']/', $declaration)) {
                    throw new \InvalidArgumentException('Invalid XML declaration: standalone must be "yes" or "no"');
                }
            }
            
            // Check for common errors like double question marks
            if (preg_match('/\?\?\>/', $declaration) || preg_match('/<\?\?xml/', $declaration)) {
                throw new \InvalidArgumentException('Invalid XML declaration: malformed processing instruction');
            }
        }
    }
    
    /**
     * Builds object tree from tokens
     */
    private static function buildObjectTree(array $tokens): HTMLParser 
    {
        $root = new HTMLParser('root', null, null, [], null, [], 0);
        $stack = [&$root];
        
        foreach ($tokens as $token) {
            $currentNode = &$stack[count($stack) - 1];
            
            switch ($token['type']) {
                case 'open_tag':
                    $newNode = new HTMLParser(
                        'element',
                        $token['name'],
                        $token['name_space'] ?? null,
                        $token['attributes'],
                        null,
                        [],
                        count($stack) - 1
                    );
                    
                    $currentNode->children[] = $newNode;
                    $lastIndex = count($currentNode->children) - 1;
                    
                    if (!self::isSelfClosing($token['name'])) {
                        $stack[] = &$currentNode->children[$lastIndex];
                    }
                    break;
                    
                case 'close_tag':
                    if (count($stack) > 1) {
                        for ($i = count($stack) - 1; $i > 0; $i--) {
                            if ($stack[$i]->name === $token['name']) {
                                $stack = array_slice($stack, 0, $i);
                                break;
                            }
                        }
                    }
                    break;
                    
                case 'text':
                    $content = trim($token['content']);
                    if ($content !== '') {
                        $textNode = new HTMLParser(
                            'text',
                            null,
                            null,
                            [],
                            $content,
                            [],
                            count($stack) - 1
                        );
                        $currentNode->children[] = $textNode;
                    }
                    break;
                    
                case 'comment':
                    $commentContent = $token['content'];
                    $commentNode = new HTMLParser(
                        'comment',
                        null,
                        null,
                        [],
                        $commentContent,
                        [],
                        count($stack) - 1
                    );
                    $currentNode->children[] = $commentNode;
                    break;
                    
                case 'doctype':
                    $doctypeNode = new HTMLParser(
                        'doctype',
                        null,
                        null,
                        [],
                        $token['content'],
                        [],
                        count($stack) - 1
                    );
                    $currentNode->children[] = $doctypeNode;
                    break;
            }
        }
        
        return $root;
    }
    
    /**
     * Get all text content from this node and its children
     */
    public function getText(): string 
    {
        if ($this->type === 'text') {
            return $this->content ?? '';
        }
        
        $text = '';
        foreach ($this->children as $child) {
            $text .= $child->getText();
        }
        
        return $text;
    }
    
    /**
     * Get text nodes that are direct children
     * @return HTMLParser[]
     */
    public function getTextChildren(): array 
    {
        return array_filter($this->children, fn($child) => $child->getType() === 'text');
    }
    
    public function getType(): string 
    { 
        return $this->type; 
    }
    
    public function getName(): ?string 
    { 
        return $this->name; 
    }
    
    public function getNameSpace(): ?string 
    { 
        return $this->nameSpace; 
    }
    
    public function getAttributes(): array 
    { 
        return $this->attributes; 
    }
    
    /**
     * Gets the content of the element
     */
    public function getContent(): ?string 
    { 
        return $this->content; 
    }
    
    public function getChildren(): array 
    { 
        return $this->children; 
    }
    
    public function getDepth(): int 
    { 
        return $this->depth; 
    }
    
    /**
     * Get a specific attribute
     */
    public function getAttribute(string $name): ?string {
        return $this->attributes[$name] ?? null;
    }
    
    /**
     * Check if the node has children
     */
    public function hasChildren(): bool {
        return !empty($this->children);
    }
    
    /**
     * Return the number of children
     */
    public function countChildren(): int {
        return count($this->children);
    }
    
    /**
     * Recursive search for elements by tag name
     * @return HTMLParser[]
     */
    public function find(string $tagName): array {
        $results = [];
        
        if ($this->type === 'element' && $this->name === $tagName) {
            $results[] = $this;
        }
        
        foreach ($this->children as $child) {
            $results = array_merge($results, $child->find($tagName));
        }
        
        return $results;
    }
    
    /**
     * Filter children with a callback function
     * @return HTMLParser[]
     */
    public function filter(callable $callback): array {
        $results = [];
        
        if ($callback($this)) {
            $results[] = $this;
        }
        
        foreach ($this->children as $child) {
            $results = array_merge($results, $child->filter($callback));
        }
        
        return $results;
    }
    
    /**
     * Return only direct children (non-recursive)
     * @return HTMLParser[]
     */
    public function getDirectChildren(): array {
        return $this->children;
    }
    
    /**
     * Return direct children of a specific type
     * @return HTMLParser[]
     */
    public function getChildrenByType(string $type): array {
        return array_filter($this->children, fn($child) => $child->getType() === $type);
    }

    /**
     * Get comment nodes that are direct children
     * @return HTMLParser[]
     */
    public function getCommentChildren(): array 
    {
        return array_filter($this->children, fn($child) => $child->getType() === 'comment');
    }

    /**
     * Get doctype nodes that are direct children
     * @return HTMLParser[]
     */
    public function getDoctypeChildren(): array 
    {
        return array_filter($this->children, fn($child) => $child->getType() === 'doctype');
    }

    /**
     * Debugging method
     */
    public function toDebugString(): string {
        return sprintf(
            "%s{name: %s, children: %d, depth: %d}",
            $this->type,
            $this->name ?? 'null',
            count($this->children),
            $this->depth
        );
    }
    
    /**
     * Utility method to display the tree
     */
    public function printTree(int $indent = 0): void {
        $spaces = str_repeat('  ', $indent);
        
        if ($this->type === 'root') {
            echo "{$spaces}ROOT (children: " . count($this->children) . ")\n";
            foreach ($this->children as $child) {
                $child->printTree($indent + 1);
            }
        } elseif ($this->type === 'element') {
            $attrs = '';
            if (!empty($this->attributes)) {
                $attrs = ' [' . implode(', ', array_map(
                    fn($k, $v) => "$k=\"$v\"", 
                    array_keys($this->attributes), 
                    $this->attributes
                )) . ']';
            }
            echo "{$spaces}<{$this->name}{$attrs}> (depth: {$this->depth}, children: " . count($this->children) . ")\n";
            foreach ($this->children as $child) {
                $child->printTree($indent + 1);
            }
            echo "{$spaces}</{$this->name}>\n";
        } elseif ($this->type === 'text') {
            echo "{$spaces}TEXT: \"{$this->content}\" (depth: {$this->depth})\n";
        } elseif ($this->type === 'comment') {
            echo "{$spaces}COMMENT: \"{$this->content}\" (depth: {$this->depth})\n";
        } elseif ($this->type === 'doctype') {
            echo "{$spaces}DOCTYPE: \"{$this->content}\" (depth: {$this->depth})\n";
        }
    }
    
    /**
     * Convert to array (for compatibility or serialization)
     */
    public function toArray(): array {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'name_space' => $this->nameSpace,
            'attributes' => $this->attributes,
            'content' => $this->content,
            'children' => array_map(fn($child) => $child->toArray(), $this->children),
            'depth' => $this->depth
        ];
    }
    
    /**
     * Magic method for string conversion
     */
    public function __toString(): string 
    {
        return $this->toString();
    }
    
    /**
     * Explicit method for precise control
     */
    public function toString(): string {
        if (in_array($this->type, ['text', 'comment', 'doctype'])) {
            return $this->content ?? '';
        }
        
        if ($this->type === 'element') {
            $attrs = '';
            if (!empty($this->attributes)) {
                $attrs = ' [' . implode(', ', $this->attributes) . ']';
            }
            return "<{$this->name}{$attrs}>";
        }
        
        return $this->type . ($this->name ? ":{$this->name}" : '');
    }

    public function toHTML(): string {
        if ($this->type === 'text') {
            return htmlspecialchars($this->content ?? '');
        }
        
        if ($this->type === 'comment') {
            return "<!--" . $this->content . "-->";
        }
        
        if ($this->type === 'doctype') {
            return "<!" . $this->content . ">";
        }
        
        if ($this->type === 'element') {
            $attrs = '';
            foreach ($this->attributes as $key => $value) {
                $attrs .= " $key=\"" . htmlspecialchars($value) . "\"";
            }
            
            $content = '';
            foreach ($this->children as $child) {
                $content .= $child->toHTML();
            }
            
            if (self::isSelfClosing($this->name)) {
                return "<{$this->name}{$attrs} />";
            }
            
            return "<{$this->name}{$this->writeNameSpace($this->nameSpace)}{$attrs}>$content</{$this->name}>";
        }
        
        // For root, concatenate children
        $content = '';
        foreach ($this->children as $child) {
            $content .= $child->toHTML();
        }
        return $content;
    }

    /**
     * For namespace reconstruction
     */
    private function writeNameSpace(string $ns): string
    {
        if ($ns !== "") {
            return ":$ns";
        }
        return "";
    }




/**
 * Tokenizes HTML/XML string into structured tokens using state-based parsing.
 * Handles quoted content properly, treating '<' and '>' inside quotes as text.
 * Special handling for <script> , <style> and other elements  to treat their content as raw text.
 *
 * @param string $html The HTML/XML string to tokenize
 * @return array Array of tokens with type and content
 * @throws \InvalidArgumentException If malformed structure is detected
 */
private static function tokenize(string $html): array
{
    $tokens = [];
    $length = strlen($html);
    $position = 0;
    $state = 'text'; // Possible states: 'text', 'tag', 'comment', 'doctype', 'xml_decl', 'quote', 'cdata'
    $currentText = '';
    $quoteChar = '';
    $tagBuffer = '';
    $rawTextElement = null; // Tracks if we're inside <script> or <style> element
    $rawTextEndTag = ''; // The closing tag we're looking for in raw text mode
    $rawTextContent = ''; // Accumulates raw text content
    
    while ($position < $length) {
        $char = $html[$position];
        
        // If we're inside a raw text element (script/style), handle specially
        if ($rawTextElement !== null) {
            // Check if we're at the start of the closing tag
            $remaining = substr($html, $position);
            
            if (str_starts_with($remaining, '</' . $rawTextElement)) {
                // Found the closing tag, exit raw text mode
                if ($rawTextContent !== '') {
                    $tokens[] = [
                        'type' => 'text',
                        'content' => $rawTextContent
                    ];
                    $rawTextContent = '';
                }
                
                $rawTextElement = null;
                // Continue processing from this position (the < of closing tag)
                continue;
            }
            
            // Still inside raw text, accumulate character
            $rawTextContent .= $char;
            $position++;
            continue;
        }
        
        switch ($state) {
            case 'text':
                if ($char === '<') {
                    // Save any accumulated text
                    if ($currentText !== '') {
                        $tokens[] = [
                            'type' => 'text',
                            'content' => $currentText
                        ];
                        $currentText = '';
                    }
                    
                    // Check what type of tag starts
                    $nextChars = substr($html, $position, 10);
                    
                    if (str_starts_with($nextChars, '<!--')) {
                        $state = 'comment';
                        $position += 3; // We'll process the '!' in the comment state
                    } elseif (str_starts_with($nextChars, '<?xml')) {
                        $state = 'xml_decl';
                        $position += 1; // We'll process the '?' in the xml_decl state
                    } elseif (str_starts_with($nextChars, '<!DOCTYPE') || 
                               str_starts_with($nextChars, '<!doctype')) {
                        $state = 'doctype';
                        $position += 1; // We'll process the '!' in the doctype state
                    } elseif (str_starts_with($nextChars, '<![CDATA[')) {
                        $state = 'cdata';
                        $position += 8; // Skip '<![CDATA['
                    } else {
                        $state = 'tag';
                        $tagBuffer = '<';
                    }
                } else {
                    $currentText .= $char;
                }
                $position++;
                break;
                
            case 'tag':
                $tagBuffer .= $char;
                
                // Check for quote start within tag
                if ($char === '"' || $char === "'") {
                    $state = 'quote';
                    $quoteChar = $char;
                }
                // Check for tag end
                elseif ($char === '>') {
                    // Parse the complete tag
                    $tagInfo = self::parseTagBuffer($tagBuffer);
                    $tokens[] = $tagInfo;
                    
                    // Check if this is a script or style opening tag
                    if ($tagInfo['type'] === 'open_tag' && 
                        !$tagInfo['self_closing'] && 
                        in_array($tagInfo['name'], ['iframe', 'noscript', 'script', 'style', 'textarea'])) {
                        $rawTextElement = $tagInfo['name'];
                        $rawTextContent = '';
                    }
                    
                    $state = 'text';
                    $tagBuffer = '';
                    $quoteChar = '';
                }
                $position++;
                break;
                
            case 'quote':
                $tagBuffer .= $char;
                
                // Check for quote end (not escaped)
                if ($char === $quoteChar && $position > 0 && $html[$position - 1] !== '\\') {
                    $state = 'tag';
                }
                $position++;
                break;
                
            case 'comment':
                // Find comment end
                $commentEnd = strpos($html, '-->', $position);
                if ($commentEnd === false) {
                    throw new \InvalidArgumentException('Unclosed comment at position ' . $position);
                }
                
                $commentContent = substr($html, $position + 1, $commentEnd - $position - 1);
                $tokens[] = [
                    'type' => 'comment',
                    'content' => trim($commentContent)
                ];
                
                $position = $commentEnd + 3;
                $state = 'text';
                break;
                
            case 'cdata':
                // Find CDATA end
                $cdataEnd = strpos($html, ']]>', $position);
                if ($cdataEnd === false) {
                    throw new \InvalidArgumentException('Unclosed CDATA at position ' . $position);
                }
                
                $cdataContent = substr($html, $position, $cdataEnd - $position);
                $tokens[] = [
                    'type' => 'cdata',
                    'content' => $cdataContent
                ];
                
                $position = $cdataEnd + 3;
                $state = 'text';
                break;
                
            case 'xml_decl':
                // Find XML declaration end
                $declEnd = strpos($html, '?>', $position);
                if ($declEnd === false) {
                    throw new \InvalidArgumentException('Unclosed XML declaration at position ' . $position);
                }
                
                $declContent = substr($html, $position + 1, $declEnd - $position - 1);
                $tokens[] = [
                    'type' => 'xml_declaration',
                    'content' => trim($declContent)
                ];
                
                $position = $declEnd + 2;
                $state = 'text';
                break;
                
            case 'doctype':
                // Find DOCTYPE end
                $doctypeEnd = strpos($html, '>', $position);
                if ($doctypeEnd === false) {
                    throw new \InvalidArgumentException('Unclosed DOCTYPE at position ' . $position);
                }
                // Extract the complete DOCTYPE declaration without '<!' and '>'
    $fullDoctype = substr($html, $position - 1, $doctypeEnd - $position + 2);
    
    // Extract content after "DOCTYPE" keyword (case-insensitive)
    if (preg_match('/!DOCTYPE\s+([^>]*)/i', $fullDoctype, $matches)) {
        $doctypeContent = trim($matches[1]);
    } else {
        $doctypeContent = '';
    }
                
                $tokens[] = [
                    'type' => 'doctype',
                    'content' => trim($doctypeContent)
                ];
                
                $position = $doctypeEnd + 1;
                $state = 'text';
                break;
        }
    }
    
    // Add any remaining text
    if ($rawTextElement !== null) {
        // We never found the closing tag for script/style
        throw new \InvalidArgumentException('Unclosed ' . $rawTextElement . ' element');
    }
    
    if ($state === 'text' && $currentText !== '') {
        $tokens[] = [
            'type' => 'text',
            'content' => $currentText
        ];
    } elseif ($state !== 'text') {
        throw new \InvalidArgumentException('Unclosed ' . $state . ' at end of input');
    }
    
    return $tokens;
}

/**
 * Parses a complete tag buffer and adds appropriate tokens.
 *
 * @param string $tagBuffer The complete tag string including '<' and '>'
 * @return array The parsed tag token
 */
private static function parseTagBuffer(string $tagBuffer): array
{
    // Remove < and >
    $tagContent = substr($tagBuffer, 1, -1);
    
    // Check if it's a closing tag
    if (str_starts_with($tagContent, '/')) {
        $tagName = trim(substr($tagContent, 1));
        
        // Handle namespace
        $nameSpace = '';
        if (strpos($tagName, ':') !== false) {
            list($nameSpace, $tagName) = explode(':', $tagName, 2);
        }
        
        return [
            'type' => 'close_tag',
            'name' => $tagName,
            'name_space' => $nameSpace
        ];
    }
    
    // Check if it's a self-closing tag
    $isSelfClosing = false;
    if (str_ends_with($tagContent, '/')) {
        $isSelfClosing = true;
        $tagContent = substr($tagContent, 0, -1);
    }
    
    // Split tag name from attributes
    $parts = preg_split('/\s+/', $tagContent, 2);
    $fullTagName = $parts[0];
    $attrString = $parts[1] ?? '';
    
    // Handle namespace
    $nameSpace = '';
    $tagName = $fullTagName;
    if (strpos($fullTagName, ':') !== false) {
        list($nameSpace, $tagName) = explode(':', $fullTagName, 2);
    }
    
    // Parse attributes
    $attributes = self::parseAttributes($attrString);
    
    // Check if it's a known self-closing tag
    if (!$isSelfClosing && self::isSelfClosing($tagName)) {
        $isSelfClosing = true;
    }
    
    return [
        'type' => 'open_tag',
        'name' => $tagName,
        'name_space' => $nameSpace,
        'attributes' => $attributes,
        'self_closing' => $isSelfClosing
    ];
}
/**
 * Parses HTML attributes from a string, including namespaced attributes.
 *
 * @param string $attrString The attribute string to parse
 * @return array Associative array of attribute names and values
 */
private static function parseAttributes(string $attrString): array
{
    $attributes = [];
    $attrString = trim($attrString);
    
    if ($attrString === '') {
        return $attributes;
    }
    
    // Pattern to match attributes with optional namespace prefix
    // Supports: name, name="value", name='value', name=value
    // Also supports namespaced attributes like ns:name, xmlns:ns
    $pattern = '/([a-zA-Z_][a-zA-Z0-9_\-:]*(?::[a-zA-Z_][a-zA-Z0-9_\-:]*)?)' . // Attribute name with optional namespace
               '(?:\s*=\s*' .
               '(?:"([^"]*)"' .        // Double quoted value
               '|\'([^\']*)\'' .       // Single quoted value  
               '|([^>\s]+)' .          // Unquoted value
               '))?/';
    
    preg_match_all($pattern, $attrString, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $attrName = $match[1];
        $attrValue = '';
        
        // Determine which capture group contains the value
        if (isset($match[2]) && $match[2] !== '') {
            $attrValue = $match[2];
        } elseif (isset($match[3]) && $match[3] !== '') {
            $attrValue = $match[3];
        } elseif (isset($match[4]) && $match[4] !== '') {
            $attrValue = $match[4];
        }
        // If no value provided (boolean attribute), set to empty string
        // or you could set to the attribute name: $attrValue = $attrName;
        
        $attributes[$attrName] = $attrValue;
    }
    
    return $attributes;
}
    /**
     * Checks if tag is self-closing
     */
    private static function isSelfClosing(string $tagName): bool {
        $selfClosingTags = ['img', 'br', 'hr', 'input', 'meta', 'link', 'area', 'base', 'col', 'embed', 'source', 'track', 'wbr'];
        return in_array(strtolower($tagName), $selfClosingTags, true);
    }
    
    /**
     * Clears the cache (useful for tests or if memory is limited)
     */
    public static function clearCache(): void {
        self::$cache = [];
    }

    /**
     * XPath query execution
     */
    public function xpath(string $query, bool $returnSingle = false): HTMLParser|array|null {
        $results = $this->executeXPathQuery($query);
        
        if ($returnSingle) {
            return $results[0] ?? null;
        }
        
        return $results;
    }
    
    /**
     * Recursive search in the entire tree
     */
    private function findRecursive(string $tagName, array &$results = []): array {
        if ($this->type === 'element' && $this->name === $tagName) {
            $results[] = $this;
        }
        
        foreach ($this->children as $child) {
            $child->findRecursive($tagName, $results);
        }
        
        return $results;
    }
    
    /**
     * Search by absolute path (e.g., /html/body/div)
     */
    private function findByAbsolutePath(array $pathSegments, int $index = 0): array {
        if ($index >= count($pathSegments)) {
            return [$this];
        }
        
        $currentSegment = $pathSegments[$index];
        $results = [];
        
        foreach ($this->children as $child) {
            if ($child->type === 'element' && $child->name === $currentSegment) {
                $results = array_merge($results, $child->findByAbsolutePath($pathSegments, $index + 1));
            }
        }
        
        return $results;
    }
    
    /**
     * Search by attribute (e.g., @class, @id, @data-*)
     */
    private function findByAttribute(string $attribute, array &$results = []): array {
        if ($this->type === 'element' && isset($this->attributes[$attribute])) {
            $results[] = $this;
        }
        
        foreach ($this->children as $child) {
            $child->findByAttribute($attribute, $results);
        }
        
        return $results;
    }
    
    /**
     * Executes XPath query and always returns an array
     */
    private function executeXPathQuery(string $query): array {
        // Clean and normalize query
        $query = trim($query);
        
        // Automatic detection of query type
        if (str_starts_with($query, '//')) {
            return $this->handleRecursiveSearch($query);
        } elseif (str_starts_with($query, '/')) {
            return $this->handleAbsolutePath($query);
        } elseif (str_starts_with($query, '@')) {
            return $this->handleAttributeSearch($query);
        } elseif (str_starts_with($query, '.')) {
            return $this->handleClassSearch($query);
        } elseif (str_starts_with($query, '#')) {
            return $this->handleIdSearch($query);
        } elseif (str_contains($query, '[') && str_contains($query, ']')) {
            return $this->handleAdvancedQuery($query);
        } else {
            // Simple search by tag name
            return $this->findRecursive($query);
        }
    }
    
    /**
     * Handler for recursive search (//div, //p, etc.)
     */
    private function handleRecursiveSearch(string $query): array {
        $tagName = substr($query, 2);
        return $this->findRecursive($tagName);
    }
    
    /**
     * Handler for absolute path (/html/body/div)
     */
    private function handleAbsolutePath(string $query): array {
        $path = explode('/', substr($query, 1));
        return $this->findByAbsolutePath(array_filter($path));
    }
    
    /**
     * Handler for attribute search (@class, @href, etc.)
     */
    private function handleAttributeSearch(string $query): array {
        $attribute = substr($query, 1);
        return $this->findByAttribute($attribute);
    }
    
    /**
     * Handler for class search (.my-class)
     */
    private function handleClassSearch(string $query): array {
        $className = substr($query, 1);
        return $this->findByClass($className);
    }
    
    /**
     * Handler for ID search (#my-id)
     */
    private function handleIdSearch(string $query): array {
        $id = substr($query, 1);
        return $this->findById($id);
    }
    
    /**
     * Handler for advanced queries with predicates
     */
    private function handleAdvancedQuery(string $query): array {
        // Support for: //div[@class="test"], //a[1], //p[@data-info]
        
        // Extract base selector and predicates
        preg_match('/^(.*?)\[(.*?)\]$/', $query, $matches);
        $baseSelector = $matches[1] ?? $query;
        $predicate = $matches[2] ?? '';
        
        $elements = $this->executeXPathQuery($baseSelector);
        
        if (empty($predicate)) {
            return $elements;
        }
        
        // Apply predicates
        return $this->applyPredicate($elements, $predicate);
    }
    
    /**
     * Applies predicate to a set of elements
     */
    private function applyPredicate(array $elements, string $predicate): array {
        // Position predicate [1], [2], etc.
        if (is_numeric($predicate)) {
            $index = (int)$predicate - 1;
            return isset($elements[$index]) ? [$elements[$index]] : [];
        }
        
        // Attribute predicate with value [@class="test"]
        if (preg_match('/^@(\w+)=["\']([^"\']*)["\']$/', $predicate, $matches)) {
            $attribute = $matches[1];
            $value = $matches[2];
            return array_filter($elements, function($element) use ($attribute, $value) {
                return $element->getAttribute($attribute) === $value;
            });
        }
        
        // Attribute presence predicate [@href]
        if (preg_match('/^@(\w+)$/', $predicate, $matches)) {
            $attribute = $matches[1];
            return array_filter($elements, function($element) use ($attribute) {
                return $element->getAttribute($attribute) !== null;
            });
        }
        
        // Advanced position predicate [position()=1]
        if (preg_match('/^position\(\)\s*=\s*(\d+)$/', $predicate, $matches)) {
            $position = (int)$matches[1] - 1;
            return isset($elements[$position]) ? [$elements[$position]] : [];
        }
        
        return $elements;
    }
    
    /**
     * Search by CSS class (handles multiple classes)
     */
    private function findByClass(string $className): array {
        return $this->findRecursiveByCallback(function($element) use ($className) {
            if ($element->type !== 'element') {
                return false;
            }
            
            $classAttr = $element->getAttribute('class');
            if (!$classAttr) {
                return false;
            }
            
            $classes = array_map('trim', explode(' ', $classAttr));
            return in_array($className, $classes);
        });
    }
    
    /**
     * Search by ID (returns max 1 element)
     */
    private function findById(string $id): array {
        $results = $this->findRecursiveByCallback(function($element) use ($id) {
            return $element->type === 'element' && $element->getAttribute('id') === $id;
        });
        
        return array_slice($results, 0, 1); // Maximum 1 element for ID
    }
    
    /**
     * Recursive search by callback
     */
    private function findRecursiveByCallback(callable $callback, array &$results = []): array {
        if ($callback($this)) {
            $results[] = $this;
        }
        
        foreach ($this->children as $child) {
            $child->findRecursiveByCallback($callback, $results);
        }
        
        return $results;
    }
    
    /**
     * Convenience shortcuts (optional but useful)
     */
    
    /**
     * Gets the first matching element (or null)
     */
    public function first(string $query): ?HTMLParser {
        return $this->xpath($query, true);
    }
    
    /**
     * Gets the text of the first matching element
     */
    public function text(string $query): ?string {
        $element = $this->first($query);
        return $element ? $element->getTextContent() : null;
    }
    
    /**
     * Gets an attribute of the first matching element
     */
    public function attr(string $query, string $attribute): ?string {
        $element = $this->first($query);
        return $element ? $element->getAttribute($attribute) : null;
    }
    
    /**
     * Checks if at least one element exists
     */
    public function exists(string $query): bool {
        return !empty($this->xpath($query));
    }
    
    /**
     * Counts the number of matching elements
     */
    public function count(string $query): int {
        return count($this->xpath($query));
    }
    
    /**
     * Advanced XPath with predicate support
     */
    public function xpathAdvanced(string $query): array {
        // Detection of predicates [@attribute="value"]
        if (preg_match('/^(.*)\[(@\w+)=["\']([^"\']*)["\']\]$/', $query, $matches)) {
            $baseQuery = $matches[1];
            $attribute = substr($matches[2], 1); // Remove @
            $value = $matches[3];
            
            $candidates = $this->xpath($baseQuery);
            return array_filter($candidates, function($node) use ($attribute, $value) {
                return $node->getAttribute($attribute) === $value;
            });
        }
        
        // Detection of indices [1], [2], etc.
        if (preg_match('/^(.*)\[(\d+)\]$/', $query, $matches)) {
            $baseQuery = $matches[1];
            $index = (int)$matches[2] - 1; // XPath starts at 1
            
            $candidates = $this->xpath($baseQuery);
            return isset($candidates[$index]) ? [$candidates[$index]] : [];
        }
        
        // Detection of attribute presence [@attribute]
        if (preg_match('/^(.*)\[(@\w+)\]$/', $query, $matches)) {
            $baseQuery = $matches[1];
            $attribute = substr($matches[2], 1);
            
            $candidates = $this->xpath($baseQuery);
            return array_filter($candidates, function($node) use ($attribute) {
                return $node->getAttribute($attribute) !== null;
            });
        }
        
        // Fallback to simple XPath
        return $this->xpath($query);
    }
    
    /**
     * Gets text from all matching elements
     */
    public function xpathText(string $query): array {
        $elements = $this->xpathAdvanced($query);
        return array_map(function($element) {
            return $element->getTextContent();
        }, $elements);
    }
    
    /**
     * Gets the text content of an element (including children)
     */
    public function getTextContent(): string {
        if ($this->type === 'text') {
            return $this->content ?? '';
        }
        
        $text = '';
        foreach ($this->children as $child) {
            $text .= $child->getTextContent();
        }
        
        return $text;
    }
    
    /**
     * Gets attribute values
     */
    public function xpathAttribute(string $query, string $attribute): array {
        $elements = $this->xpathAdvanced($query);
        return array_map(function($element) use ($attribute) {
            return $element->getAttribute($attribute);
        }, $elements);
    }
}
