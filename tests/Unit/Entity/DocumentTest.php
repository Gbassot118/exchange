<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Annotation;
use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Entity\Session;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class DocumentTest extends TestCase
{
    private Document $document;

    protected function setUp(): void
    {
        $this->document = new Document();
    }

    public function testConstructorGeneratesUuidV7(): void
    {
        $id = $this->document->getId();

        $this->assertInstanceOf(Uuid::class, $id);
        // UUIDv7 has version 7 in the 13th character position
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id->toString());
    }

    public function testConstructorInitializesCollections(): void
    {
        $this->assertCount(0, $this->document->getChildren());
        $this->assertCount(0, $this->document->getAnnotations());
        $this->assertCount(0, $this->document->getVersions());
    }

    public function testDefaultTypeIsGeneral(): void
    {
        $this->assertSame(Document::TYPE_GENERAL, $this->document->getType());
    }

    public function testDefaultContentIsEmptyString(): void
    {
        $this->assertSame('', $this->document->getContent());
    }

    public function testDefaultCurrentVersionIs1(): void
    {
        $this->assertSame(1, $this->document->getCurrentVersion());
    }

    public function testDefaultSortOrderIs0(): void
    {
        $this->assertSame(0, $this->document->getSortOrder());
    }

    public function testConstructorSetsCreatedAt(): void
    {
        $createdAt = $this->document->getCreatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $createdAt);
        $this->assertEqualsWithDelta(new \DateTimeImmutable(), $createdAt, 1);
    }

    public function testConstructorSetsUpdatedAt(): void
    {
        $updatedAt = $this->document->getUpdatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $updatedAt);
        $this->assertEqualsWithDelta(new \DateTimeImmutable(), $updatedAt, 1);
    }

    public function testSetAndGetTitle(): void
    {
        $this->document->setTitle('Test Document');

        $this->assertSame('Test Document', $this->document->getTitle());
    }

    public function testSetAndGetSlug(): void
    {
        $this->document->setSlug('test-document');

        $this->assertSame('test-document', $this->document->getSlug());
    }

    public function testSetAndGetContent(): void
    {
        $content = 'This is the document content';

        $this->document->setContent($content);

        $this->assertSame($content, $this->document->getContent());
    }

    public function testSetAndGetType(): void
    {
        $this->document->setType(Document::TYPE_SYNTHESIS);

        $this->assertSame(Document::TYPE_SYNTHESIS, $this->document->getType());
    }

    public function testSetAndGetSession(): void
    {
        $session = new Session();
        $session->setTitle('Test Session');

        $this->document->setSession($session);

        $this->assertSame($session, $this->document->getSession());
    }

    public function testSetAndGetParent(): void
    {
        $parent = new Document();
        $parent->setTitle('Parent');
        $parent->setSlug('parent');

        $this->document->setParent($parent);

        $this->assertSame($parent, $this->document->getParent());
    }

    public function testSetParentToNull(): void
    {
        $parent = new Document();
        $this->document->setParent($parent);
        $this->document->setParent(null);

        $this->assertNull($this->document->getParent());
    }

    public function testDefaultParentIsNull(): void
    {
        $this->assertNull($this->document->getParent());
    }

    public function testAddChildAddsToCollection(): void
    {
        $child = new Document();
        $child->setTitle('Child');
        $child->setSlug('child');

        $this->document->addChild($child);

        $this->assertCount(1, $this->document->getChildren());
        $this->assertTrue($this->document->getChildren()->contains($child));
    }

    public function testAddChildSetsParentOnChild(): void
    {
        $child = new Document();
        $child->setTitle('Child');
        $child->setSlug('child');

        $this->document->addChild($child);

        $this->assertSame($this->document, $child->getParent());
    }

    public function testAddChildDoesNotAddDuplicate(): void
    {
        $child = new Document();
        $child->setTitle('Child');
        $child->setSlug('child');

        $this->document->addChild($child);
        $this->document->addChild($child);

        $this->assertCount(1, $this->document->getChildren());
    }

    public function testRemoveChildRemovesFromCollection(): void
    {
        $child = new Document();
        $child->setTitle('Child');
        $child->setSlug('child');

        $this->document->addChild($child);
        $this->document->removeChild($child);

        $this->assertCount(0, $this->document->getChildren());
    }

    public function testRemoveChildUnsetsParentOnChild(): void
    {
        $child = new Document();
        $child->setTitle('Child');
        $child->setSlug('child');

        $this->document->addChild($child);
        $this->document->removeChild($child);

        $this->assertNull($child->getParent());
    }

    public function testRemoveChildOnlyUnsetsIfParentMatches(): void
    {
        $parent1 = new Document();
        $parent1->setTitle('Parent 1');
        $parent1->setSlug('parent-1');

        $parent2 = new Document();
        $parent2->setTitle('Parent 2');
        $parent2->setSlug('parent-2');

        $child = new Document();
        $child->setTitle('Child');
        $child->setSlug('child');

        $parent1->addChild($child);
        // Now child.parent = parent1
        // If we call parent2.removeChild(child), it shouldn't unset the parent
        // because child.parent !== parent2
        $child->setParent($parent2);
        $parent1->removeChild($child);

        // Parent should still be parent2 since it didn't match parent1
        $this->assertSame($parent2, $child->getParent());
    }

    public function testIncrementVersionIncreasesByOne(): void
    {
        $initialVersion = $this->document->getCurrentVersion();

        $this->document->incrementVersion();

        $this->assertSame($initialVersion + 1, $this->document->getCurrentVersion());
    }

    public function testIncrementVersionMultipleTimes(): void
    {
        $this->document->incrementVersion();
        $this->document->incrementVersion();
        $this->document->incrementVersion();

        $this->assertSame(4, $this->document->getCurrentVersion());
    }

    public function testAddVersionAddsToCollection(): void
    {
        $version = new DocumentVersion();
        $version->setVersion(1);
        $version->setContent('content');

        $this->document->addVersion($version);

        $this->assertCount(1, $this->document->getVersions());
        $this->assertTrue($this->document->getVersions()->contains($version));
    }

    public function testAddVersionSetsDocumentOnVersion(): void
    {
        $version = new DocumentVersion();
        $version->setVersion(1);
        $version->setContent('content');

        $this->document->addVersion($version);

        $this->assertSame($this->document, $version->getDocument());
    }

    public function testAddAnnotationAddsToCollection(): void
    {
        $annotation = new Annotation();
        $annotation->setContent('Test annotation');

        $this->document->addAnnotation($annotation);

        $this->assertCount(1, $this->document->getAnnotations());
        $this->assertTrue($this->document->getAnnotations()->contains($annotation));
    }

    public function testAddAnnotationSetsDocumentOnAnnotation(): void
    {
        $annotation = new Annotation();
        $annotation->setContent('Test annotation');

        $this->document->addAnnotation($annotation);

        $this->assertSame($this->document, $annotation->getDocument());
    }

    public function testRemoveAnnotationRemovesFromCollection(): void
    {
        $annotation = new Annotation();
        $annotation->setContent('Test annotation');

        $this->document->addAnnotation($annotation);
        $this->document->removeAnnotation($annotation);

        $this->assertCount(0, $this->document->getAnnotations());
    }

    public function testGetBreadcrumbsReturnsOnlySelfForRootDocument(): void
    {
        $this->document->setTitle('Root');
        $this->document->setSlug('root');

        $breadcrumbs = $this->document->getBreadcrumbs();

        $this->assertCount(1, $breadcrumbs);
        $this->assertSame($this->document, $breadcrumbs[0]);
    }

    public function testGetBreadcrumbsReturnsFullPath(): void
    {
        $root = new Document();
        $root->setTitle('Root');
        $root->setSlug('root');

        $parent = new Document();
        $parent->setTitle('Parent');
        $parent->setSlug('parent');
        $parent->setParent($root);

        $child = new Document();
        $child->setTitle('Child');
        $child->setSlug('child');
        $child->setParent($parent);

        $breadcrumbs = $child->getBreadcrumbs();

        $this->assertCount(3, $breadcrumbs);
        $this->assertSame($root, $breadcrumbs[0]);
        $this->assertSame($parent, $breadcrumbs[1]);
        $this->assertSame($child, $breadcrumbs[2]);
    }

    public function testGetBreadcrumbsOrderIsCorrect(): void
    {
        $root = new Document();
        $root->setTitle('Root');
        $root->setSlug('root');

        $child = new Document();
        $child->setTitle('Child');
        $child->setSlug('child');
        $child->setParent($root);

        $breadcrumbs = $child->getBreadcrumbs();

        $this->assertSame('Root', $breadcrumbs[0]->getTitle());
        $this->assertSame('Child', $breadcrumbs[1]->getTitle());
    }

    public function testDeepHierarchyBreadcrumbs(): void
    {
        $documents = [];
        $previous = null;

        for ($i = 0; $i < 6; $i++) {
            $doc = new Document();
            $doc->setTitle("Level $i");
            $doc->setSlug("level-$i");
            if ($previous !== null) {
                $doc->setParent($previous);
            }
            $documents[] = $doc;
            $previous = $doc;
        }

        $deepest = $documents[5];
        $breadcrumbs = $deepest->getBreadcrumbs();

        $this->assertCount(6, $breadcrumbs);
        for ($i = 0; $i < 6; $i++) {
            $this->assertSame("Level $i", $breadcrumbs[$i]->getTitle());
        }
    }

    public function testSetAndGetMetadata(): void
    {
        $metadata = ['key' => 'value', 'count' => 42];

        $this->document->setMetadata($metadata);

        $this->assertSame($metadata, $this->document->getMetadata());
    }

    public function testMetadataCanBeNull(): void
    {
        $this->document->setMetadata(['key' => 'value']);
        $this->document->setMetadata(null);

        $this->assertNull($this->document->getMetadata());
    }

    public function testMetadataDefaultIsNull(): void
    {
        $this->assertNull($this->document->getMetadata());
    }

    public function testMetadataCanBeComplexArray(): void
    {
        $metadata = [
            'author' => 'John',
            'tags' => ['tag1', 'tag2'],
            'settings' => ['nested' => ['value' => true]],
        ];

        $this->document->setMetadata($metadata);

        $this->assertSame($metadata, $this->document->getMetadata());
    }

    public function testSetAndGetSortOrder(): void
    {
        $this->document->setSortOrder(5);

        $this->assertSame(5, $this->document->getSortOrder());
    }

    public function testSetAndGetCurrentVersion(): void
    {
        $this->document->setCurrentVersion(10);

        $this->assertSame(10, $this->document->getCurrentVersion());
    }

    public function testTypeConstants(): void
    {
        $this->assertSame('synthesis', Document::TYPE_SYNTHESIS);
        $this->assertSame('question', Document::TYPE_QUESTION);
        $this->assertSame('comparison', Document::TYPE_COMPARISON);
        $this->assertSame('annexe', Document::TYPE_ANNEXE);
        $this->assertSame('compte_rendu', Document::TYPE_COMPTE_RENDU);
        $this->assertSame('general', Document::TYPE_GENERAL);
    }

    public function testSetValidType(): void
    {
        $types = [
            Document::TYPE_SYNTHESIS,
            Document::TYPE_QUESTION,
            Document::TYPE_COMPARISON,
            Document::TYPE_ANNEXE,
            Document::TYPE_COMPTE_RENDU,
            Document::TYPE_GENERAL,
        ];

        foreach ($types as $type) {
            $this->document->setType($type);
            $this->assertSame($type, $this->document->getType());
        }
    }

    public function testSlugCanContainSpecialCharacters(): void
    {
        $slug = 'document-with-numbers-123-and-underscores_test';

        $this->document->setSlug($slug);

        $this->assertSame($slug, $this->document->getSlug());
    }

    public function testContentCanBeVeryLong(): void
    {
        $longContent = str_repeat('Lorem ipsum dolor sit amet. ', 1000);

        $this->document->setContent($longContent);

        $this->assertSame($longContent, $this->document->getContent());
    }

    public function testSetTitleReturnsSelf(): void
    {
        $result = $this->document->setTitle('Test');

        $this->assertSame($this->document, $result);
    }

    public function testSetSlugReturnsSelf(): void
    {
        $result = $this->document->setSlug('test');

        $this->assertSame($this->document, $result);
    }

    public function testSetContentReturnsSelf(): void
    {
        $result = $this->document->setContent('content');

        $this->assertSame($this->document, $result);
    }

    public function testSetTypeReturnsSelf(): void
    {
        $result = $this->document->setType(Document::TYPE_GENERAL);

        $this->assertSame($this->document, $result);
    }

    public function testSetMetadataReturnsSelf(): void
    {
        $result = $this->document->setMetadata([]);

        $this->assertSame($this->document, $result);
    }

    public function testSetSessionReturnsSelf(): void
    {
        $session = new Session();

        $result = $this->document->setSession($session);

        $this->assertSame($this->document, $result);
    }

    public function testSetParentReturnsSelf(): void
    {
        $result = $this->document->setParent(null);

        $this->assertSame($this->document, $result);
    }

    public function testAddChildReturnsSelf(): void
    {
        $child = new Document();

        $result = $this->document->addChild($child);

        $this->assertSame($this->document, $result);
    }

    public function testRemoveChildReturnsSelf(): void
    {
        $child = new Document();
        $this->document->addChild($child);

        $result = $this->document->removeChild($child);

        $this->assertSame($this->document, $result);
    }

    public function testSetSortOrderReturnsSelf(): void
    {
        $result = $this->document->setSortOrder(1);

        $this->assertSame($this->document, $result);
    }

    public function testAddAnnotationReturnsSelf(): void
    {
        $annotation = new Annotation();

        $result = $this->document->addAnnotation($annotation);

        $this->assertSame($this->document, $result);
    }

    public function testRemoveAnnotationReturnsSelf(): void
    {
        $annotation = new Annotation();
        $this->document->addAnnotation($annotation);

        $result = $this->document->removeAnnotation($annotation);

        $this->assertSame($this->document, $result);
    }

    public function testAddVersionReturnsSelf(): void
    {
        $version = new DocumentVersion();

        $result = $this->document->addVersion($version);

        $this->assertSame($this->document, $result);
    }

    public function testSetCurrentVersionReturnsSelf(): void
    {
        $result = $this->document->setCurrentVersion(5);

        $this->assertSame($this->document, $result);
    }

    public function testIncrementVersionReturnsSelf(): void
    {
        $result = $this->document->incrementVersion();

        $this->assertSame($this->document, $result);
    }

    public function testMultipleChildrenOrdering(): void
    {
        $child1 = new Document();
        $child1->setTitle('Child 1');
        $child1->setSlug('child-1');
        $child1->setSortOrder(2);

        $child2 = new Document();
        $child2->setTitle('Child 2');
        $child2->setSlug('child-2');
        $child2->setSortOrder(1);

        $child3 = new Document();
        $child3->setTitle('Child 3');
        $child3->setSlug('child-3');
        $child3->setSortOrder(3);

        $this->document->addChild($child1);
        $this->document->addChild($child2);
        $this->document->addChild($child3);

        $this->assertCount(3, $this->document->getChildren());
    }

    public function testMultipleDocumentsHaveUniqueIds(): void
    {
        $doc1 = new Document();
        $doc2 = new Document();

        $this->assertFalse($doc1->getId()->equals($doc2->getId()));
    }
}
