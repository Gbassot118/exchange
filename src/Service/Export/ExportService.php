<?php

namespace App\Service\Export;

use App\Entity\Annotation;
use App\Entity\Decision;
use App\Entity\Document;
use App\Entity\Session;
use App\Repository\AnnotationRepository;
use App\Repository\DecisionRepository;
use App\Repository\DocumentRepository;

final class ExportService
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly AnnotationRepository $annotationRepository,
        private readonly DecisionRepository $decisionRepository,
    ) {}

    public function exportToMarkdown(Session $session): string
    {
        $output = [];

        // Header
        $output[] = '# ' . $session->getTitle();
        $output[] = '';

        if ($session->getDescription()) {
            $output[] = $session->getDescription();
            $output[] = '';
        }

        $output[] = '**Statut:** ' . $this->translateStatus($session->getStatus());
        $output[] = '**Créé le:** ' . $session->getCreatedAt()->format('d/m/Y H:i');
        $output[] = '**Dernière mise à jour:** ' . $session->getUpdatedAt()->format('d/m/Y H:i');
        $output[] = '';
        $output[] = '---';
        $output[] = '';

        // Table of contents
        $documents = $this->documentRepository->findRootDocuments($session);
        if (!empty($documents)) {
            $output[] = '## Table des matières';
            $output[] = '';
            $output = array_merge($output, $this->generateTableOfContents($documents, 0));
            $output[] = '';
            $output[] = '---';
            $output[] = '';
        }

        // Documents
        $output[] = '## Documents';
        $output[] = '';
        foreach ($documents as $document) {
            $output = array_merge($output, $this->exportDocumentToMarkdown($document, 2));
        }

        // Decisions
        $decisions = $this->decisionRepository->findBySession($session);
        if (!empty($decisions)) {
            $output[] = '---';
            $output[] = '';
            $output[] = '## Décisions';
            $output[] = '';
            foreach ($decisions as $decision) {
                $output = array_merge($output, $this->exportDecisionToMarkdown($decision));
            }
        }

        return implode("\n", $output);
    }

    public function exportToHtml(Session $session): string
    {
        $markdown = $this->exportToMarkdown($session);

        // Convert markdown to HTML
        $html = $this->markdownToHtml($markdown);

        // Wrap in HTML document
        return $this->wrapInHtmlDocument($session->getTitle(), $html);
    }

    /**
     * @param Document[] $documents
     * @return string[]
     */
    private function generateTableOfContents(array $documents, int $level): array
    {
        $output = [];
        $indent = str_repeat('  ', $level);

        foreach ($documents as $document) {
            $anchor = $this->generateAnchor($document->getTitle());
            $output[] = $indent . '- [' . $document->getTitle() . '](#' . $anchor . ')';

            $children = $document->getChildren()->toArray();
            if (!empty($children)) {
                $output = array_merge($output, $this->generateTableOfContents($children, $level + 1));
            }
        }

        return $output;
    }

    /**
     * @return string[]
     */
    private function exportDocumentToMarkdown(Document $document, int $headingLevel): array
    {
        $output = [];
        $heading = str_repeat('#', $headingLevel);

        $anchor = $this->generateAnchor($document->getTitle());
        $output[] = '<a id="' . $anchor . '"></a>';
        $output[] = $heading . ' ' . $document->getTitle();
        $output[] = '';

        // Document metadata
        $output[] = '*Type: ' . $this->translateDocumentType($document->getType()) . ' | ';
        $output[] = 'Version: ' . $document->getCurrentVersion() . ' | ';
        $output[] = 'Mis à jour: ' . $document->getUpdatedAt()->format('d/m/Y H:i') . '*';
        $output[] = '';

        // Content
        if (!empty($document->getContent())) {
            $output[] = $document->getContent();
            $output[] = '';
        }

        // Annotations
        $annotations = $this->annotationRepository->findByDocumentWithFilters($document);
        $rootAnnotations = array_filter($annotations, fn(Annotation $a) => !$a->isReply());

        if (!empty($rootAnnotations)) {
            $output[] = '#### Annotations';
            $output[] = '';
            foreach ($rootAnnotations as $annotation) {
                $output = array_merge($output, $this->exportAnnotationToMarkdown($annotation, 0));
            }
            $output[] = '';
        }

        // Children documents
        $children = $document->getChildren()->toArray();
        foreach ($children as $child) {
            $output = array_merge($output, $this->exportDocumentToMarkdown($child, min($headingLevel + 1, 6)));
        }

        return $output;
    }

    /**
     * @return string[]
     */
    private function exportAnnotationToMarkdown(Annotation $annotation, int $depth): array
    {
        $output = [];
        $indent = str_repeat('  ', $depth);

        $typeIcon = $this->getAnnotationTypeIcon($annotation->getType());
        $statusBadge = $annotation->getStatus() === 'resolved' ? ' ✅' : '';

        $output[] = $indent . '- ' . $typeIcon . ' **' . $annotation->getAuthor()->getPseudo() . '** ';
        $output[] = $indent . '  *(' . $annotation->getCreatedAt()->format('d/m/Y H:i') . ')*' . $statusBadge;
        $output[] = $indent . '  ';
        $output[] = $indent . '  ' . str_replace("\n", "\n" . $indent . '  ', $annotation->getContent());

        // Replies
        foreach ($annotation->getReplies() as $reply) {
            $output = array_merge($output, $this->exportAnnotationToMarkdown($reply, $depth + 1));
        }

        return $output;
    }

    /**
     * @return string[]
     */
    private function exportDecisionToMarkdown(Decision $decision): array
    {
        $output = [];

        $statusIcon = $this->getDecisionStatusIcon($decision->getStatus());
        $output[] = '### ' . $statusIcon . ' ' . $decision->getTitle();
        $output[] = '';

        $output[] = '**Statut:** ' . $this->translateDecisionStatus($decision->getStatus());

        if ($decision->getLinkedDocument()) {
            $output[] = '**Document lié:** ' . $decision->getLinkedDocument()->getTitle();
        }
        $output[] = '';

        if ($decision->getDescription()) {
            $output[] = $decision->getDescription();
            $output[] = '';
        }

        // Options and votes
        $output[] = '**Options:**';
        $output[] = '';

        $voteStats = $decision->getVoteStats();
        $selectedOptionId = $decision->getSelectedOptionId()?->toString();

        foreach ($decision->getOptions() as $option) {
            $isSelected = ($option['id'] === $selectedOptionId);
            $voteCount = $voteStats[$option['id']] ?? 0;
            $selectedMark = $isSelected ? ' ✓ **VALIDÉ**' : '';

            $output[] = '- **' . $option['label'] . '** (' . $voteCount . ' vote(s))' . $selectedMark;
            if (!empty($option['description'])) {
                $output[] = '  ' . $option['description'];
            }
        }
        $output[] = '';

        return $output;
    }

    private function translateStatus(string $status): string
    {
        return match ($status) {
            'preparation' => 'Préparation',
            'en_cours' => 'En cours',
            'termine' => 'Terminé',
            'archive' => 'Archivé',
            default => $status,
        };
    }

    private function translateDocumentType(string $type): string
    {
        return match ($type) {
            'synthesis' => 'Synthèse',
            'question' => 'Question',
            'comparison' => 'Comparaison',
            'annexe' => 'Annexe',
            'compte_rendu' => 'Compte-rendu',
            'general' => 'Général',
            default => $type,
        };
    }

    private function translateDecisionStatus(string $status): string
    {
        return match ($status) {
            'ouvert' => 'Ouvert',
            'en_discussion' => 'En discussion',
            'consensus' => 'Consensus',
            'valide' => 'Validé',
            'reporte' => 'Reporté',
            default => $status,
        };
    }

    private function getAnnotationTypeIcon(string $type): string
    {
        return match ($type) {
            'comment' => '💬',
            'question' => '❓',
            'suggestion' => '💡',
            'objection' => '⚠️',
            'validation' => '✅',
            default => '📝',
        };
    }

    private function getDecisionStatusIcon(string $status): string
    {
        return match ($status) {
            'ouvert' => '🔵',
            'en_discussion' => '🟡',
            'consensus' => '🟢',
            'valide' => '✅',
            'reporte' => '🔴',
            default => '⚪',
        };
    }

    private function generateAnchor(string $title): string
    {
        $anchor = mb_strtolower($title);
        $anchor = preg_replace('/[^a-z0-9\s-]/u', '', $anchor);
        $anchor = preg_replace('/[\s]+/', '-', $anchor);
        return trim($anchor, '-');
    }

    private function markdownToHtml(string $markdown): string
    {
        // Basic markdown to HTML conversion
        $html = $markdown;

        // Escape HTML
        $html = htmlspecialchars($html, ENT_NOQUOTES, 'UTF-8');

        // Headers
        $html = preg_replace('/^###### (.+)$/m', '<h6>$1</h6>', $html);
        $html = preg_replace('/^##### (.+)$/m', '<h5>$1</h5>', $html);
        $html = preg_replace('/^#### (.+)$/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);

        // Bold and italic
        $html = preg_replace('/\*\*\*(.+?)\*\*\*/s', '<strong><em>$1</em></strong>', $html);
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $html);

        // Links
        $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html);

        // Horizontal rules
        $html = preg_replace('/^---$/m', '<hr>', $html);

        // Code blocks
        $html = preg_replace('/```(\w*)\n(.*?)```/s', '<pre><code class="language-$1">$2</code></pre>', $html);
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);

        // Lists (simple handling)
        $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*<\/li>\n)+/s', '<ul>$0</ul>', $html);

        // Anchors (preserve them)
        $html = preg_replace('/&lt;a id=&quot;([^&]+)&quot;&gt;&lt;\/a&gt;/', '<a id="$1"></a>', $html);

        // Paragraphs
        $html = preg_replace('/\n\n+/', '</p><p>', $html);
        $html = '<p>' . $html . '</p>';

        // Clean up empty paragraphs
        $html = preg_replace('/<p>\s*<\/p>/', '', $html);
        $html = preg_replace('/<p>\s*(<h[1-6]>)/', '$1', $html);
        $html = preg_replace('/(<\/h[1-6]>)\s*<\/p>/', '$1', $html);
        $html = preg_replace('/<p>\s*<hr>\s*<\/p>/', '<hr>', $html);
        $html = preg_replace('/<p>\s*(<ul>)/', '$1', $html);
        $html = preg_replace('/(<\/ul>)\s*<\/p>/', '$1', $html);
        $html = preg_replace('/<p>\s*(<pre>)/', '$1', $html);
        $html = preg_replace('/(<\/pre>)\s*<\/p>/', '$1', $html);
        $html = preg_replace('/<p>\s*(<a id=)/', '$1', $html);

        return $html;
    }

    private function wrapInHtmlDocument(string $title, string $content): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - Export Documentation</title>
    <style>
        :root {
            --primary-color: #2563eb;
            --text-color: #1f2937;
            --muted-color: #6b7280;
            --border-color: #e5e7eb;
            --bg-color: #ffffff;
            --code-bg: #f3f4f6;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem;
            background: var(--bg-color);
        }

        h1 {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 0.5rem;
            margin-top: 2rem;
        }

        h2 {
            color: var(--text-color);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.3rem;
            margin-top: 2rem;
        }

        h3, h4, h5, h6 {
            color: var(--text-color);
            margin-top: 1.5rem;
        }

        hr {
            border: none;
            border-top: 1px solid var(--border-color);
            margin: 2rem 0;
        }

        a {
            color: var(--primary-color);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        code {
            background: var(--code-bg);
            padding: 0.2em 0.4em;
            border-radius: 3px;
            font-size: 0.9em;
        }

        pre {
            background: var(--code-bg);
            padding: 1rem;
            border-radius: 6px;
            overflow-x: auto;
        }

        pre code {
            background: none;
            padding: 0;
        }

        ul {
            padding-left: 1.5rem;
        }

        li {
            margin-bottom: 0.5rem;
        }

        em {
            color: var(--muted-color);
        }

        strong {
            color: var(--text-color);
        }

        blockquote {
            border-left: 4px solid var(--primary-color);
            margin: 1rem 0;
            padding-left: 1rem;
            color: var(--muted-color);
        }

        .annotation {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 0.5rem 1rem;
            margin: 0.5rem 0;
        }

        .decision {
            background: #dbeafe;
            border-left: 4px solid #2563eb;
            padding: 0.5rem 1rem;
            margin: 0.5rem 0;
        }

        @media print {
            body {
                max-width: none;
                padding: 1rem;
            }

            a {
                color: var(--text-color);
            }

            h1, h2 {
                page-break-after: avoid;
            }

            pre, blockquote {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    {$content}

    <footer style="margin-top: 3rem; padding-top: 1rem; border-top: 1px solid var(--border-color); color: var(--muted-color); font-size: 0.875rem;">
        <p>Exporté depuis Documentation Collaborative le {$this->getCurrentDate()}</p>
    </footer>
</body>
</html>
HTML;
    }

    private function getCurrentDate(): string
    {
        return (new \DateTimeImmutable())->format('d/m/Y à H:i');
    }
}
