<?php
/**
 * Scan Website: clean Markdown for docs, blog posts and other article types.
 *
 * Pages are rewritten into Q&A by AI, because page-builder layouts are too
 * fragmented to search well. Docs and posts are already written as articles
 * with headings, so they are uploaded as they are: converted to Markdown with
 * a short header (title, link, type, dates, categories). No AI request is made,
 * and steps, code and tables keep their exact wording.
 *
 * @package WPiko_Chatbot_Pro
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * How Scan Website turns an item into knowledge.
 *
 * Pages become AI-written Q&A; every other content type becomes Markdown.
 *
 * @param WP_Post|int $post Post object or ID.
 * @return string 'qa' or 'markdown'.
 */
function wpiko_chatbot_pro_scan_mode($post) {
    $post = get_post($post);
    if (!$post) {
        return 'qa';
    }
    $mode = $post->post_type === 'page' ? 'qa' : 'markdown';

    /**
     * Filter how Scan Website processes an item.
     *
     * Return 'qa' to have AI rewrite it into questions and answers, or
     * 'markdown' to upload its content as clean Markdown with no AI request.
     *
     * @param string  $mode 'qa' for pages, 'markdown' for everything else.
     * @param WP_Post $post The item being scanned.
     */
    $mode = apply_filters('wpiko_chatbot_pro_scan_mode', $mode, $post);
    return $mode === 'markdown' ? 'markdown' : 'qa';
}

/**
 * Rendered HTML of a post, the way visitors see its main content.
 *
 * @param WP_Post $post Post.
 * @return string HTML.
 */
function wpiko_chatbot_pro_scan_post_html($post) {
    $html = '';

    // Elementor keeps its content outside post_content.
    if (did_action('elementor/loaded') && class_exists('\Elementor\Plugin') && get_post_meta($post->ID, '_elementor_edit_mode', true) === 'builder') {
        $elementor = \Elementor\Plugin::instance();
        if (isset($elementor->frontend) && method_exists($elementor->frontend, 'get_builder_content_for_display')) {
            $html = (string) $elementor->frontend->get_builder_content_for_display($post->ID);
        }
    }

    if ($html === '') {
        $html = (string) $post->post_content;
        if (function_exists('has_blocks') && has_blocks($html)) {
            $html = do_blocks($html);
        } else {
            // Classic editor content keeps paragraphs as blank lines.
            $html = wpautop($html);
        }
        // Do not put the chatbot inside its own knowledge.
        $html = str_replace('[wpiko_chatbot]', '', $html);
        $html = do_shortcode($html);
    }

    return $html;
}

/**
 * Build the Markdown knowledge file for a post: header plus content.
 *
 * @param int $post_id Post ID.
 * @return array|false {title, url, id, content} or false when the post cannot be scanned.
 */
function wpiko_chatbot_pro_build_markdown($post_id) {
    $post = get_post($post_id);
    if (!$post || !in_array($post->post_type, wpiko_chatbot_pro_scan_post_types(), true)) {
        return false;
    }

    $body = wpiko_chatbot_pro_html_to_markdown(wpiko_chatbot_pro_scan_post_html($post));
    if (trim($body) === '') {
        return array(
            'title' => $post->post_title,
            'url' => get_permalink($post),
            'id' => (int) $post->ID,
            'content' => '',
        );
    }

    return array(
        'title' => $post->post_title,
        'url' => get_permalink($post),
        'id' => (int) $post->ID,
        'content' => wpiko_chatbot_pro_markdown_header($post) . "\n\n" . $body . "\n",
    );
}

/**
 * Short header that keeps the source, type and dates next to the content.
 *
 * @param WP_Post $post Post.
 * @return string Markdown.
 */
function wpiko_chatbot_pro_markdown_header($post) {
    $title = html_entity_decode(wp_strip_all_tags(get_the_title($post)), ENT_QUOTES, 'UTF-8');
    if ($title === '') {
        $title = '(no title)';
    }

    $type_object = get_post_type_object($post->post_type);
    $type_label = $type_object ? $type_object->labels->singular_name : $post->post_type;

    $is_live = in_array($post->post_status, array('publish', 'private'), true);
    $published = $is_live ? get_the_date('Y-m-d', $post) : '';
    $updated = get_the_modified_date('Y-m-d', $post);

    $lines = array('# ' . $title, '');
    $lines[] = 'Source: ' . get_permalink($post);
    $lines[] = 'Type: ' . $type_label;
    if ($published) {
        $lines[] = 'Published: ' . $published;
    }
    if ($updated && $updated !== $published) {
        $lines[] = 'Last updated: ' . $updated;
    }

    // Categories, tags and the taxonomies docs plugins register.
    foreach (get_object_taxonomies($post, 'objects') as $taxonomy) {
        if (empty($taxonomy->public) || empty($taxonomy->show_ui) || $taxonomy->name === 'post_format') {
            continue;
        }
        $terms = get_the_terms($post, $taxonomy->name);
        if (!$terms || is_wp_error($terms)) {
            continue;
        }
        $names = array();
        foreach ($terms as $term) {
            // "Uncategorized" says nothing about the content.
            if ($taxonomy->name === 'category' && (int) $term->term_id === (int) get_option('default_category')) {
                continue;
            }
            $names[] = html_entity_decode($term->name, ENT_QUOTES, 'UTF-8');
        }
        if ($names) {
            $lines[] = $taxonomy->labels->name . ': ' . implode(', ', $names);
        }
    }

    // Blog posts describe a moment in time; say so, so answers can mention the date.
    if ($post->post_type === 'post' && $published) {
        $lines[] = 'Note: This blog post was published on ' . $published . '. Details may have changed since then.';
    }

    $lines[] = '';
    $lines[] = '---';

    /**
     * Filter the header lines of a Scan Website Markdown file.
     *
     * @param string[] $lines Header lines (title, source, type, dates, terms).
     * @param WP_Post  $post  The scanned item.
     */
    $lines = (array) apply_filters('wpiko_chatbot_pro_markdown_header', $lines, $post);
    return implode("\n", $lines);
}

/**
 * Convert rendered HTML to Markdown.
 *
 * Keeps headings, paragraphs, lists (nested), links, emphasis, code blocks,
 * tables, quotes and accordions (details/summary). Drops scripts, styles,
 * forms, embeds and layout-only markup.
 *
 * @param string $html HTML.
 * @return string Markdown.
 */
function wpiko_chatbot_pro_html_to_markdown($html) {
    $html = trim((string) $html);
    if ($html === '') {
        return '';
    }
    if (!class_exists('DOMDocument')) {
        // Rare hosts without the DOM extension: plain text keeps the content.
        return wpiko_chatbot_pro_html_to_text($html);
    }
    $converter = new WPiko_Chatbot_Pro_Html_To_Markdown();
    return $converter->convert($html);
}

/**
 * DOM-based HTML to Markdown converter.
 */
class WPiko_Chatbot_Pro_Html_To_Markdown {

    /** @var string[] Code blocks, kept out of whitespace clean-up until the end. */
    private $blocks = array();

    /** @var string Unique token prefix for protected blocks. */
    private $token;

    /** @var string Site origin for relative links, e.g. https://example.com */
    private $origin = '';

    /** Elements whose content is never knowledge. */
    private static $skip = array(
        'script', 'style', 'noscript', 'template', 'svg', 'canvas', 'iframe', 'object', 'embed',
        'video', 'audio', 'source', 'track', 'map', 'form', 'input', 'select', 'option', 'textarea',
        'button', 'label', 'nav', 'head', 'meta', 'link', 'title',
    );

    /** Block-level containers: their content is separated from what is around it. */
    private static $block = array(
        'div', 'section', 'article', 'main', 'header', 'footer', 'aside', 'address', 'figure',
        'center', 'body', 'html', 'fieldset', 'hgroup',
    );

    public function __construct() {
        $this->token = 'WPIKOMD' . substr(md5(uniqid('', true)), 0, 8) . 'X';
        if (function_exists('home_url')) {
            $parts = wp_parse_url(home_url('/'));
            if (!empty($parts['scheme']) && !empty($parts['host'])) {
                $this->origin = $parts['scheme'] . '://' . $parts['host'] . (!empty($parts['port']) ? ':' . $parts['port'] : '');
            }
        }
    }

    /**
     * @param string $html HTML fragment.
     * @return string Markdown.
     */
    public function convert($html) {
        $this->blocks = array();

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        // The XML declaration makes libxml read the fragment as UTF-8.
        $doc->loadHTML('<?xml encoding="UTF-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = null;
        foreach ($doc->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $root = $child;
                break;
            }
        }
        if (!$root) {
            return '';
        }

        $markdown = $this->children($root);
        return $this->finish($markdown);
    }

    /* ------------------------------------------------------------------ */

    private function children(DOMNode $node) {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= $this->node($child);
        }
        return $out;
    }

    private function node(DOMNode $node) {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            // Collapse HTML whitespace (including non-breaking spaces) to one space.
            return preg_replace('/[\s\x{00A0}]+/u', ' ', $node->nodeValue);
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }
        /** @var DOMElement $node */
        $tag = strtolower($node->nodeName);

        if (in_array($tag, self::$skip, true) || $this->is_hidden($node)) {
            return '';
        }

        switch ($tag) {
            case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
                // The file title is the only level-1 heading.
                $level = max(2, (int) substr($tag, 1));
                $text = $this->inline_text($node);
                return $text === '' ? '' : "\n\n" . str_repeat('#', $level) . ' ' . $text . "\n\n";

            case 'p':
                return "\n\n" . $this->children($node) . "\n\n";

            case 'br':
                return "\n";

            case 'hr':
                return "\n\n---\n\n";

            case 'strong': case 'b':
                return $this->wrap($this->children($node), '**');

            case 'em': case 'i': case 'cite':
                return $this->wrap($this->children($node), '*');

            case 'del': case 's': case 'strike':
                return $this->wrap($this->children($node), '~~');

            case 'code': case 'kbd': case 'samp': case 'tt':
                return $this->inline_code($node->textContent);

            case 'pre':
                return $this->pre($node);

            case 'a':
                return $this->link($node);

            case 'img':
                return $this->image($node);

            case 'ul': case 'ol':
                return "\n\n" . $this->list_block($node) . "\n\n";

            case 'li':
                // A list item outside a list: treat as a bullet.
                return "\n- " . trim($this->children($node)) . "\n";

            case 'blockquote':
                return $this->blockquote($node);

            case 'table':
                return $this->table($node);

            case 'details':
                return $this->details($node);

            case 'summary':
                $text = $this->inline_text($node);
                return $text === '' ? '' : "\n\n**" . $text . "**\n\n";

            case 'figcaption':
                $text = $this->inline_text($node);
                return $text === '' ? '' : "\n\n*" . $text . "*\n\n";

            case 'dl':
                return "\n\n" . $this->children($node) . "\n\n";

            case 'dt':
                $text = $this->inline_text($node);
                return $text === '' ? '' : "\n\n**" . $text . "**\n";

            case 'dd':
                return "\n" . trim($this->children($node)) . "\n\n";

            case 'sup':
                return '^' . trim($this->children($node));

            case 'sub':
                return '~' . trim($this->children($node));
        }

        if (in_array($tag, self::$block, true)) {
            return "\n\n" . $this->children($node) . "\n\n";
        }

        // span, mark, abbr, small, time, unknown tags: keep the text.
        return $this->children($node);
    }

    /* ------------------------------------------------------------------ */

    private function is_hidden(DOMElement $node) {
        if ($node->hasAttribute('hidden') || $node->getAttribute('aria-hidden') === 'true') {
            return true;
        }
        $style = strtolower(str_replace(' ', '', $node->getAttribute('style')));
        if ($style !== '' && (strpos($style, 'display:none') !== false || strpos($style, 'visibility:hidden') !== false)) {
            return true;
        }
        $class = ' ' . $node->getAttribute('class') . ' ';
        return strpos($class, ' screen-reader-text ') !== false || strpos($class, ' sr-only ') !== false;
    }

    /** Inline content of an element on one line. */
    private function inline_text(DOMNode $node) {
        $text = $this->children($node);
        $text = preg_replace('/\s*\n+\s*/', ' ', $text);
        return trim(preg_replace('/ {2,}/', ' ', $text));
    }

    private function wrap($text, $marker) {
        // Keep surrounding spaces outside the markers: "**bold** text", not "**bold **text".
        if (trim($text) === '') {
            return $text;
        }
        preg_match('/^(\s*)(.*?)(\s*)$/s', $text, $m);
        return $m[1] . $marker . $m[2] . $marker . $m[3];
    }

    private function inline_code($code) {
        $code = preg_replace('/[\s\x{00A0}]+/u', ' ', $code);
        if (trim($code) === '') {
            return '';
        }
        $fence = strpos($code, '`') !== false ? '``' : '`';
        $pad = $fence === '``' ? ' ' : '';
        return $fence . $pad . $code . $pad . $fence;
    }

    private function pre(DOMElement $node) {
        $code = rtrim($node->textContent, "\r\n");
        $code = ltrim($code, "\r\n");
        if (trim($code) === '') {
            return '';
        }
        $language = $this->code_language($node);
        if ($language === '') {
            foreach ($node->getElementsByTagName('code') as $inner) {
                $language = $this->code_language($inner);
                break;
            }
        }
        $fence = '```';
        while (strpos($code, $fence) !== false) {
            $fence .= '`';
        }
        return "\n\n" . $this->protect($fence . $language . "\n" . $code . "\n" . $fence) . "\n\n";
    }

    private function code_language(DOMElement $node) {
        if (preg_match('/(?:^|\s)(?:language|lang)-([A-Za-z0-9_+#.-]+)/', $node->getAttribute('class'), $m)) {
            return strtolower($m[1]);
        }
        $lang = $node->getAttribute('data-lang') ?: $node->getAttribute('data-language');
        return preg_match('/^[A-Za-z0-9_+#.-]+$/', $lang) ? strtolower($lang) : '';
    }

    private function link(DOMElement $node) {
        $text = $this->inline_text($node);
        $href = $this->absolute_url(trim($node->getAttribute('href')));

        if ($href === '' || $href[0] === '#' || preg_match('/^(javascript|data|vbscript):/i', $href)) {
            return $text;
        }
        if ($text === '') {
            // Image-only links: the image's alt text names the link.
            return '';
        }
        if ($text === $href && preg_match('#^https?://#i', $href)) {
            return '<' . $href . '>';
        }
        return '[' . str_replace(array('[', ']'), array('\[', '\]'), $text) . '](' . $this->escape_url($href) . ')';
    }

    private function image(DOMElement $node) {
        $alt = trim(preg_replace('/\s+/', ' ', $node->getAttribute('alt')));
        $src = $this->absolute_url(trim($node->getAttribute('src')));
        // Decorative images (no alt text) carry no knowledge.
        if ($alt === '' || $src === '' || stripos($src, 'data:') === 0) {
            return '';
        }
        return '![' . str_replace(array('[', ']'), '', $alt) . '](' . $this->escape_url($src) . ')';
    }

    private function absolute_url($url) {
        if ($url === '' || $this->origin === '') {
            return $url;
        }
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }
        if ($url[0] === '/') {
            return $this->origin . $url;
        }
        return $url;
    }

    private function escape_url($url) {
        return str_replace(array(' ', '(', ')'), array('%20', '%28', '%29'), $url);
    }

    private function list_block(DOMElement $list, $depth = 0) {
        $ordered = strtolower($list->nodeName) === 'ol';
        $number = $ordered && $list->hasAttribute('start') ? (int) $list->getAttribute('start') : 1;
        $lines = array();

        foreach ($list->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $tag = strtolower($child->nodeName);
            if ($tag === 'ul' || $tag === 'ol') {
                // Invalid but common: a list directly inside a list.
                $lines[] = $this->indent($this->list_block($child, $depth + 1), '  ');
                continue;
            }
            if ($tag !== 'li' || $this->is_hidden($child)) {
                continue;
            }

            $marker = $ordered ? $number . '. ' : '- ';
            $number++;

            $content = '';
            foreach ($child->childNodes as $part) {
                $part_tag = $part->nodeType === XML_ELEMENT_NODE ? strtolower($part->nodeName) : '';
                if ($part_tag === 'ul' || $part_tag === 'ol') {
                    $content .= "\n" . $this->list_block($part, $depth + 1) . "\n";
                } else {
                    $content .= $this->node($part);
                }
            }
            // Items stay tight: one line per paragraph inside the item.
            $content = trim(preg_replace("/[ \t]*\n[ \t]*\n+[ \t]*/", "\n", $content));
            $content = preg_replace('/[ \t]{2,}/', ' ', $content);

            $lines[] = $marker . $this->indent($content, str_repeat(' ', strlen($marker)), false);
        }

        return implode("\n", $lines);
    }

    /** Indent every line (or every line after the first). */
    private function indent($text, $pad, $first = true) {
        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            if (($i > 0 || $first) && $line !== '') {
                $lines[$i] = $pad . $line;
            }
        }
        return implode("\n", $lines);
    }

    private function blockquote(DOMElement $node) {
        $content = trim($this->tidy($this->children($node)));
        if ($content === '') {
            return '';
        }
        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            $lines[$i] = $line === '' ? '>' : '> ' . $line;
        }
        return "\n\n" . implode("\n", $lines) . "\n\n";
    }

    private function details(DOMElement $node) {
        $summary = '';
        $body = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->nodeName) === 'summary' && $summary === '') {
                $summary = $this->inline_text($child);
            } else {
                $body .= $this->node($child);
            }
        }
        $out = "\n\n";
        if ($summary !== '') {
            $out .= '**' . $summary . "**\n\n";
        }
        return $out . $body . "\n\n";
    }

    private function table(DOMElement $table) {
        $rows = array();
        $header_row = null;
        $width = 0;

        foreach ($this->table_rows($table) as $tr) {
            $cells = array();
            $all_th = true;
            foreach ($tr->childNodes as $cell) {
                if ($cell->nodeType !== XML_ELEMENT_NODE) {
                    continue;
                }
                $cell_tag = strtolower($cell->nodeName);
                if ($cell_tag !== 'td' && $cell_tag !== 'th') {
                    continue;
                }
                if ($cell_tag === 'td') {
                    $all_th = false;
                }
                $text = $this->tidy($this->children($cell));
                $text = trim(preg_replace('/\s*\n+\s*/', ' ', $text));
                $text = str_replace('|', '\|', $text);
                $cells[] = $text;
                $span = (int) $cell->getAttribute('colspan');
                for ($s = 1; $s < $span && $s < 20; $s++) {
                    $cells[] = '';
                }
            }
            if (!$cells) {
                continue;
            }
            $width = max($width, count($cells));
            $in_head = strtolower($tr->parentNode->nodeName) === 'thead';
            if ($header_row === null && !$rows && ($in_head || $all_th)) {
                $header_row = $cells;
            } else {
                $rows[] = $cells;
            }
        }

        if ($width === 0) {
            return '';
        }

        // Layout tables with a single column read better as paragraphs.
        if ($width === 1) {
            $lines = array();
            foreach (array_merge($header_row ? array($header_row) : array(), $rows) as $cells) {
                if ($cells[0] !== '') {
                    $lines[] = $cells[0];
                }
            }
            return "\n\n" . implode("\n\n", $lines) . "\n\n";
        }

        if ($header_row === null) {
            // Markdown tables need a header row: use the first row.
            $header_row = array_shift($rows);
        }

        $out = array();
        $out[] = $this->table_line($header_row, $width);
        $out[] = '|' . str_repeat(' --- |', $width);
        foreach ($rows as $cells) {
            $out[] = $this->table_line($cells, $width);
        }
        $caption = '';
        foreach ($table->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->nodeName) === 'caption') {
                $caption = $this->inline_text($child);
            }
        }
        return "\n\n" . ($caption !== '' ? '**' . $caption . "**\n\n" : '') . $this->protect(implode("\n", $out)) . "\n\n";
    }

    private function table_rows(DOMElement $table) {
        $rows = array();
        foreach ($table->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $tag = strtolower($child->nodeName);
            if ($tag === 'tr') {
                $rows[] = $child;
            } elseif (in_array($tag, array('thead', 'tbody', 'tfoot'), true)) {
                foreach ($child->childNodes as $tr) {
                    if ($tr->nodeType === XML_ELEMENT_NODE && strtolower($tr->nodeName) === 'tr') {
                        $rows[] = $tr;
                    }
                }
            }
        }
        return $rows;
    }

    private function table_line($cells, $width) {
        $cells = array_pad($cells, $width, '');
        return '| ' . implode(' | ', $cells) . ' |';
    }

    /* ------------------------------------------------------------------ */

    /** Keep a finished block (code, table) away from whitespace clean-up. */
    private function protect($text) {
        $key = $this->token . count($this->blocks);
        $this->blocks[$key] = $text;
        return $key;
    }

    /** Whitespace clean-up of a fragment. */
    private function tidy($text) {
        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            $line = preg_replace('/(?<=\S)[ \t]{2,}/', ' ', $line);
            // Keep the indentation of list items and their continuation lines
            // (two or more spaces); drop the single spaces left by HTML whitespace.
            $lines[$i] = preg_match('/^ {2,}\S/', $line) ? rtrim($line) : trim($line);
        }
        $text = implode("\n", $lines);
        return preg_replace("/\n{3,}/", "\n\n", $text);
    }

    private function finish($markdown) {
        $markdown = $this->tidy($markdown);

        // Lines that are only emphasis markers left by empty elements.
        $markdown = preg_replace('/^(\*\*|\*|~~)\s*\1$/m', '', $markdown);
        $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown);

        // Restore protected blocks, newest first (they can nest).
        for ($i = count($this->blocks) - 1; $i >= 0; $i--) {
            $key = $this->token . $i;
            $markdown = str_replace($key, $this->blocks[$key], $markdown);
        }

        return trim($markdown);
    }
}
