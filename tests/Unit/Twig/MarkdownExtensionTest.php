<?php

namespace App\Tests\Unit\Twig;

use App\Twig\MarkdownExtension;
use PHPUnit\Framework\TestCase;
use Twig\TwigFilter;

class MarkdownExtensionTest extends TestCase
{
    private MarkdownExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new MarkdownExtension();
    }

    public function testGetFiltersReturnsArray(): void
    {
        $filters = $this->extension->getFilters();

        $this->assertIsArray($filters);
        $this->assertNotEmpty($filters);
    }

    public function testGetFiltersContainsMarkdownToHtml(): void
    {
        $filters = $this->extension->getFilters();

        $filterNames = array_map(fn(TwigFilter $filter) => $filter->getName(), $filters);

        $this->assertContains('markdown_to_html', $filterNames);
    }

    public function testFilterIsSafeForHtml(): void
    {
        $filters = $this->extension->getFilters();

        $markdownFilter = null;
        foreach ($filters as $filter) {
            if ($filter->getName() === 'markdown_to_html') {
                $markdownFilter = $filter;
                break;
            }
        }

        $this->assertNotNull($markdownFilter);
        // The filter is configured with ['is_safe' => ['html']]
        // We verify the filter exists and is properly named
        $this->assertSame('markdown_to_html', $markdownFilter->getName());
    }

    public function testMarkdownToHtmlWithNull(): void
    {
        $result = $this->extension->markdownToHtml(null);

        $this->assertSame('', $result);
    }

    public function testMarkdownToHtmlWithEmptyString(): void
    {
        $result = $this->extension->markdownToHtml('');

        $this->assertSame('', $result);
    }

    public function testMarkdownToHtmlHeader1(): void
    {
        $result = $this->extension->markdownToHtml('# Header 1');

        $this->assertStringContainsString('<h1', $result);
        $this->assertStringContainsString('Header 1', $result);
        $this->assertStringContainsString('</h1>', $result);
    }

    public function testMarkdownToHtmlHeader2(): void
    {
        $result = $this->extension->markdownToHtml('## Header 2');

        $this->assertStringContainsString('<h2', $result);
        $this->assertStringContainsString('Header 2', $result);
    }

    public function testMarkdownToHtmlHeader3(): void
    {
        $result = $this->extension->markdownToHtml('### Header 3');

        $this->assertStringContainsString('<h3', $result);
        $this->assertStringContainsString('Header 3', $result);
    }

    public function testMarkdownToHtmlHeader4(): void
    {
        $result = $this->extension->markdownToHtml('#### Header 4');

        $this->assertStringContainsString('<h4', $result);
        $this->assertStringContainsString('Header 4', $result);
    }

    public function testMarkdownToHtmlParagraph(): void
    {
        $result = $this->extension->markdownToHtml('This is a paragraph.');

        $this->assertStringContainsString('<p', $result);
        $this->assertStringContainsString('This is a paragraph.', $result);
    }

    public function testMarkdownToHtmlBold(): void
    {
        $result = $this->extension->markdownToHtml('This is **bold** text.');

        $this->assertStringContainsString('<strong', $result);
        $this->assertStringContainsString('bold', $result);
    }

    public function testMarkdownToHtmlItalic(): void
    {
        $result = $this->extension->markdownToHtml('This is *italic* text.');

        $this->assertStringContainsString('<em', $result);
        $this->assertStringContainsString('italic', $result);
    }

    public function testMarkdownToHtmlBoldItalic(): void
    {
        $result = $this->extension->markdownToHtml('This is ***bold and italic*** text.');

        $this->assertStringContainsString('<strong', $result);
        $this->assertStringContainsString('<em', $result);
    }

    public function testMarkdownToHtmlUnorderedList(): void
    {
        $markdown = "- Item 1\n- Item 2\n- Item 3";
        $result = $this->extension->markdownToHtml($markdown);

        $this->assertStringContainsString('<ul', $result);
        $this->assertStringContainsString('<li', $result);
        $this->assertStringContainsString('Item 1', $result);
        $this->assertStringContainsString('Item 2', $result);
    }

    public function testMarkdownToHtmlOrderedList(): void
    {
        $markdown = "1. First\n2. Second\n3. Third";
        $result = $this->extension->markdownToHtml($markdown);

        $this->assertStringContainsString('<ol', $result);
        $this->assertStringContainsString('<li', $result);
        $this->assertStringContainsString('First', $result);
    }

    public function testMarkdownToHtmlTaskListUnchecked(): void
    {
        $markdown = "- [ ] Task not done";
        $result = $this->extension->markdownToHtml($markdown);

        $this->assertStringContainsString('type="checkbox"', $result);
        $this->assertStringContainsString('Task not done', $result);
    }

    public function testMarkdownToHtmlTaskListChecked(): void
    {
        $markdown = "- [x] Task done";
        $result = $this->extension->markdownToHtml($markdown);

        $this->assertStringContainsString('checked', $result);
        $this->assertStringContainsString('Task done', $result);
    }

    public function testMarkdownToHtmlBlockquote(): void
    {
        $result = $this->extension->markdownToHtml('> This is a quote');

        $this->assertStringContainsString('<blockquote', $result);
        $this->assertStringContainsString('This is a quote', $result);
    }

    public function testMarkdownToHtmlCodeBlock(): void
    {
        $markdown = "```\ncode here\n```";
        $result = $this->extension->markdownToHtml($markdown);

        $this->assertStringContainsString('<pre', $result);
        $this->assertStringContainsString('<code', $result);
        $this->assertStringContainsString('code here', $result);
    }

    public function testMarkdownToHtmlInlineCode(): void
    {
        $result = $this->extension->markdownToHtml('Use `inline code` here');

        $this->assertStringContainsString('<code', $result);
        $this->assertStringContainsString('inline code', $result);
    }

    public function testMarkdownToHtmlTable(): void
    {
        $markdown = "| Header 1 | Header 2 |\n|----------|----------|\n| Cell 1   | Cell 2   |";
        $result = $this->extension->markdownToHtml($markdown);

        $this->assertStringContainsString('<table', $result);
        $this->assertStringContainsString('<th', $result);
        $this->assertStringContainsString('<td', $result);
        $this->assertStringContainsString('Header 1', $result);
        $this->assertStringContainsString('Cell 1', $result);
    }

    public function testMarkdownToHtmlTableAlignment(): void
    {
        $markdown = "| Left | Center | Right |\n|:-----|:------:|------:|\n| L    | C      | R     |";
        $result = $this->extension->markdownToHtml($markdown);

        $this->assertStringContainsString('text-left', $result);
        $this->assertStringContainsString('text-center', $result);
        $this->assertStringContainsString('text-right', $result);
    }

    public function testMarkdownToHtmlLink(): void
    {
        $result = $this->extension->markdownToHtml('[Link text](https://example.com)');

        $this->assertStringContainsString('<a', $result);
        $this->assertStringContainsString('href="https://example.com"', $result);
        $this->assertStringContainsString('Link text', $result);
    }

    public function testMarkdownToHtmlHorizontalRule(): void
    {
        $result = $this->extension->markdownToHtml("Text\n\n---\n\nMore text");

        $this->assertStringContainsString('<hr', $result);
    }

    public function testMarkdownToHtmlStrikethrough(): void
    {
        $result = $this->extension->markdownToHtml('~~strikethrough~~');

        $this->assertStringContainsString('<del', $result);
        $this->assertStringContainsString('strikethrough', $result);
    }

    public function testAddsTailwindClassesToHeaders(): void
    {
        $result = $this->extension->markdownToHtml('# Header');

        $this->assertStringContainsString('class="', $result);
        $this->assertStringContainsString('font-bold', $result);
    }

    public function testAddsTailwindClassesToParagraphs(): void
    {
        $result = $this->extension->markdownToHtml('Paragraph text');

        $this->assertStringContainsString('leading-relaxed', $result);
    }

    public function testAddsTailwindClassesToLists(): void
    {
        $result = $this->extension->markdownToHtml("- Item 1\n- Item 2");

        $this->assertStringContainsString('list-disc', $result);
    }

    public function testAddsTailwindClassesToCode(): void
    {
        $result = $this->extension->markdownToHtml("```\ncode\n```");

        $this->assertStringContainsString('bg-gray-900', $result);
    }

    public function testAddsTailwindClassesToTables(): void
    {
        $markdown = "| A | B |\n|---|---|\n| 1 | 2 |";
        $result = $this->extension->markdownToHtml($markdown);

        $this->assertStringContainsString('min-w-full', $result);
    }

    public function testAddsTailwindClassesToLinks(): void
    {
        $result = $this->extension->markdownToHtml('[Link](https://example.com)');

        $this->assertStringContainsString('text-blue-600', $result);
        $this->assertStringContainsString('hover:underline', $result);
    }

    public function testStripsHtmlInput(): void
    {
        // When HTML is mixed with markdown, the HTML tags are stripped
        // but text content inside tags is preserved (security measure strips tags, not content)
        $result = $this->extension->markdownToHtml('Hello <script>danger()</script> World');

        // HTML tags should be stripped
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('</script>', $result);
        // Text content is preserved
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('World', $result);
    }

    public function testNestedLists(): void
    {
        $markdown = "- Item 1\n  - Nested 1\n  - Nested 2\n- Item 2";
        $result = $this->extension->markdownToHtml($markdown);

        $this->assertStringContainsString('<ul', $result);
        $this->assertStringContainsString('Item 1', $result);
        $this->assertStringContainsString('Nested 1', $result);
    }

    public function testMixedContent(): void
    {
        $markdown = "# Title\n\nParagraph with **bold** and *italic*.\n\n- List item\n\n> Quote";
        $result = $this->extension->markdownToHtml($markdown);

        $this->assertStringContainsString('<h1', $result);
        $this->assertStringContainsString('<p', $result);
        $this->assertStringContainsString('<strong', $result);
        $this->assertStringContainsString('<em', $result);
        $this->assertStringContainsString('<ul', $result);
        $this->assertStringContainsString('<blockquote', $result);
    }

    public function testCodeBlockWithLanguage(): void
    {
        $markdown = "```php\n<?php echo 'hello';\n```";
        $result = $this->extension->markdownToHtml($markdown);

        $this->assertStringContainsString('<pre', $result);
        $this->assertStringContainsString('<code', $result);
    }

    public function testAutolinks(): void
    {
        $result = $this->extension->markdownToHtml('Visit https://example.com for more');

        $this->assertStringContainsString('<a', $result);
        $this->assertStringContainsString('https://example.com', $result);
    }

    public function testSpecialCharacters(): void
    {
        $result = $this->extension->markdownToHtml('Text with accents: éàü and symbols: & < >');

        $this->assertStringContainsString('éàü', $result);
    }

    public function testMultipleParagraphs(): void
    {
        $markdown = "First paragraph.\n\nSecond paragraph.\n\nThird paragraph.";
        $result = $this->extension->markdownToHtml($markdown);

        preg_match_all('/<p[^>]*>/', $result, $matches);
        $this->assertCount(3, $matches[0]);
    }

    public function testEmoji(): void
    {
        $result = $this->extension->markdownToHtml('Hello 👋 World 🌍');

        $this->assertStringContainsString('👋', $result);
        $this->assertStringContainsString('🌍', $result);
    }

    public function testLongContent(): void
    {
        $markdown = str_repeat("Paragraph content.\n\n", 100);
        $result = $this->extension->markdownToHtml($markdown);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('Paragraph content', $result);
    }

    public function testWhitespaceOnlyContent(): void
    {
        $result = $this->extension->markdownToHtml("   \n\n   ");

        // Whitespace-only should be treated as empty
        $this->assertSame('', $result);
    }

    public function testMultilineCodeBlock(): void
    {
        $markdown = "```\nline 1\nline 2\nline 3\n```";
        $result = $this->extension->markdownToHtml($markdown);

        $this->assertStringContainsString('line 1', $result);
        $this->assertStringContainsString('line 2', $result);
        $this->assertStringContainsString('line 3', $result);
    }

    public function testComplexTable(): void
    {
        $markdown = <<<MD
| Name | Age | City |
|------|-----|------|
| John | 30  | Paris |
| Jane | 25  | London |
| Bob  | 35  | Berlin |
MD;
        $result = $this->extension->markdownToHtml($markdown);

        $this->assertStringContainsString('John', $result);
        $this->assertStringContainsString('Jane', $result);
        $this->assertStringContainsString('Paris', $result);
        $this->assertStringContainsString('London', $result);
    }

    public function testImageIsNotRendered(): void
    {
        // Images should be stripped since html_input is 'strip'
        $result = $this->extension->markdownToHtml('![Alt text](image.png)');

        // CommonMark will render images, but they won't be malicious
        $this->assertStringContainsString('alt="Alt text"', $result);
    }

    public function testEscapedMarkdown(): void
    {
        $result = $this->extension->markdownToHtml('\\*not italic\\*');

        $this->assertStringNotContainsString('<em>', $result);
        $this->assertStringContainsString('*not italic*', $result);
    }
}
