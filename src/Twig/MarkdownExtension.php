<?php

namespace App\Twig;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\MarkdownConverter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MarkdownExtension extends AbstractExtension
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $config = [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 100,
            'table' => [
                'wrap' => [
                    'enabled' => true,
                    'tag' => 'div',
                    'attributes' => ['class' => 'table-wrapper overflow-x-auto my-4'],
                ],
                'alignment_attributes' => [
                    'left' => ['class' => 'text-left'],
                    'center' => ['class' => 'text-center'],
                    'right' => ['class' => 'text-right'],
                ],
            ],
        ];

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('markdown_to_html', [$this, 'markdownToHtml'], ['is_safe' => ['html']]),
        ];
    }

    public function markdownToHtml(?string $content): string
    {
        if (empty($content)) {
            return '';
        }

        $html = $this->converter->convert($content)->getContent();

        // Add Tailwind CSS classes to rendered elements
        $html = $this->addTailwindClasses($html);

        return $html;
    }

    private function addTailwindClasses(string $html): string
    {
        // Headers
        $html = preg_replace('/<h1>/', '<h1 class="text-2xl font-bold mt-8 mb-4">', $html);
        $html = preg_replace('/<h2>/', '<h2 class="text-xl font-semibold mt-8 mb-3">', $html);
        $html = preg_replace('/<h3>/', '<h3 class="text-lg font-semibold mt-6 mb-2">', $html);
        $html = preg_replace('/<h4>/', '<h4 class="text-base font-semibold mt-4 mb-2">', $html);

        // Paragraphs
        $html = preg_replace('/<p>/', '<p class="my-3 leading-relaxed">', $html);

        // Lists
        $html = preg_replace('/<ul>/', '<ul class="list-disc list-inside my-4 space-y-1 ml-4">', $html);
        $html = preg_replace('/<ol>/', '<ol class="list-decimal list-inside my-4 space-y-1 ml-4">', $html);
        $html = preg_replace('/<li>/', '<li class="leading-relaxed">', $html);

        // Task lists (checkboxes)
        $html = preg_replace(
            '/<li class="leading-relaxed"><input disabled="" type="checkbox">/',
            '<li class="leading-relaxed flex items-start gap-2"><input disabled type="checkbox" class="mt-1.5 h-4 w-4 rounded border-gray-300">',
            $html
        );
        $html = preg_replace(
            '/<li class="leading-relaxed"><input checked="" disabled="" type="checkbox">/',
            '<li class="leading-relaxed flex items-start gap-2"><input checked disabled type="checkbox" class="mt-1.5 h-4 w-4 rounded border-gray-300 text-green-600">',
            $html
        );

        // Blockquotes
        $html = preg_replace(
            '/<blockquote>/',
            '<blockquote class="border-l-4 border-blue-400 bg-blue-50 pl-4 py-2 my-4 italic text-gray-700">',
            $html
        );

        // Code blocks
        $html = preg_replace(
            '/<pre>/',
            '<pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto my-4 text-sm">',
            $html
        );
        $html = preg_replace('/<code>/', '<code class="font-mono">', $html);

        // Inline code (not in pre)
        $html = preg_replace(
            '/<code class="font-mono">([^<]*)<\/code>(?!<\/pre>)/s',
            '<code class="bg-gray-100 px-1.5 py-0.5 rounded text-sm font-mono text-pink-600">$1</code>',
            $html
        );

        // Tables
        $html = preg_replace(
            '/<table>/',
            '<table class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg overflow-hidden">',
            $html
        );
        $html = preg_replace('/<thead>/', '<thead class="bg-gray-50">', $html);
        $html = preg_replace('/<tbody>/', '<tbody class="bg-white divide-y divide-gray-200">', $html);
        $html = preg_replace(
            '/<th>/',
            '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">',
            $html
        );
        $html = preg_replace(
            '/<th style="text-align: left">/',
            '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">',
            $html
        );
        $html = preg_replace(
            '/<th style="text-align: center">/',
            '<th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">',
            $html
        );
        $html = preg_replace(
            '/<th style="text-align: right">/',
            '<th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">',
            $html
        );
        $html = preg_replace('/<td>/', '<td class="px-4 py-3 text-sm text-gray-700">', $html);
        $html = preg_replace(
            '/<td style="text-align: left">/',
            '<td class="px-4 py-3 text-sm text-gray-700 text-left">',
            $html
        );
        $html = preg_replace(
            '/<td style="text-align: center">/',
            '<td class="px-4 py-3 text-sm text-gray-700 text-center">',
            $html
        );
        $html = preg_replace(
            '/<td style="text-align: right">/',
            '<td class="px-4 py-3 text-sm text-gray-700 text-right">',
            $html
        );
        $html = preg_replace('/<tr>/', '<tr class="hover:bg-gray-50">', $html);

        // Links
        $html = preg_replace('/<a href="/', '<a class="text-blue-600 hover:underline" href="', $html);

        // Horizontal rules
        $html = preg_replace('/<hr \/>/', '<hr class="my-8 border-gray-200">', $html);
        $html = preg_replace('/<hr>/', '<hr class="my-8 border-gray-200">', $html);

        // Strong and emphasis
        $html = preg_replace('/<strong>/', '<strong class="font-semibold">', $html);
        $html = preg_replace('/<em>/', '<em class="italic">', $html);

        // Strikethrough
        $html = preg_replace('/<del>/', '<del class="line-through text-gray-500">', $html);

        return $html;
    }
}
