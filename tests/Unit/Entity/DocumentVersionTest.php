<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Entity\Participant;
use App\Entity\Session;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class DocumentVersionTest extends TestCase
{
    private DocumentVersion $version;

    protected function setUp(): void
    {
        $this->version = new DocumentVersion();
    }

    public function testConstructorGeneratesUuidV7(): void
    {
        $id = $this->version->getId();

        $this->assertInstanceOf(Uuid::class, $id);
        // UUIDv7 has version 7 in the 13th character position
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id->toString());
    }

    public function testConstructorSetsCreatedAt(): void
    {
        $createdAt = $this->version->getCreatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $createdAt);
        $this->assertEqualsWithDelta(new \DateTimeImmutable(), $createdAt, 1);
    }

    public function testSetAndGetDocument(): void
    {
        $session = new Session();
        $session->setTitle('Test Session');

        $document = new Document();
        $document->setSession($session);
        $document->setTitle('Test Document');
        $document->setSlug('test-document');

        $this->version->setDocument($document);

        $this->assertSame($document, $this->version->getDocument());
    }

    public function testSetAndGetVersion(): void
    {
        $this->version->setVersion(5);

        $this->assertSame(5, $this->version->getVersion());
    }

    public function testSetAndGetContent(): void
    {
        $content = 'This is the document content';

        $this->version->setContent($content);

        $this->assertSame($content, $this->version->getContent());
    }

    public function testContentCanBeEmpty(): void
    {
        $this->version->setContent('');

        $this->assertSame('', $this->version->getContent());
    }

    public function testContentCanBeLongText(): void
    {
        $longContent = str_repeat('Lorem ipsum dolor sit amet. ', 500);

        $this->version->setContent($longContent);

        $this->assertSame($longContent, $this->version->getContent());
        $this->assertGreaterThan(10000, strlen($this->version->getContent()));
    }

    public function testSetAndGetMetadata(): void
    {
        $metadata = ['key' => 'value', 'count' => 42];

        $this->version->setMetadata($metadata);

        $this->assertSame($metadata, $this->version->getMetadata());
    }

    public function testMetadataCanBeNull(): void
    {
        $this->version->setMetadata(['key' => 'value']);
        $this->version->setMetadata(null);

        $this->assertNull($this->version->getMetadata());
    }

    public function testMetadataDefaultIsNull(): void
    {
        $this->assertNull($this->version->getMetadata());
    }

    public function testMetadataCanBeNestedArray(): void
    {
        $metadata = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'value' => 'deep nested',
                        'array' => [1, 2, 3],
                    ],
                ],
            ],
            'tags' => ['tag1', 'tag2', 'tag3'],
        ];

        $this->version->setMetadata($metadata);

        $this->assertSame($metadata, $this->version->getMetadata());
        $this->assertSame('deep nested', $this->version->getMetadata()['level1']['level2']['level3']['value']);
    }

    public function testSetAndGetAuthor(): void
    {
        $session = new Session();
        $session->setTitle('Test Session');

        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo('Author');

        $this->version->setAuthor($participant);

        $this->assertSame($participant, $this->version->getAuthor());
    }

    public function testAuthorCanBeNull(): void
    {
        $participant = new Participant();
        $this->version->setAuthor($participant);
        $this->version->setAuthor(null);

        $this->assertNull($this->version->getAuthor());
    }

    public function testAuthorDefaultIsNull(): void
    {
        $this->assertNull($this->version->getAuthor());
    }

    public function testSetAndGetChangeDescription(): void
    {
        $description = 'Fixed typos and updated formatting';

        $this->version->setChangeDescription($description);

        $this->assertSame($description, $this->version->getChangeDescription());
    }

    public function testChangeDescriptionCanBeNull(): void
    {
        $this->version->setChangeDescription('Some description');
        $this->version->setChangeDescription(null);

        $this->assertNull($this->version->getChangeDescription());
    }

    public function testChangeDescriptionDefaultIsNull(): void
    {
        $this->assertNull($this->version->getChangeDescription());
    }

    public function testSetDocumentReturnsSelf(): void
    {
        $document = new Document();

        $result = $this->version->setDocument($document);

        $this->assertSame($this->version, $result);
    }

    public function testSetVersionReturnsSelf(): void
    {
        $result = $this->version->setVersion(1);

        $this->assertSame($this->version, $result);
    }

    public function testSetContentReturnsSelf(): void
    {
        $result = $this->version->setContent('content');

        $this->assertSame($this->version, $result);
    }

    public function testSetMetadataReturnsSelf(): void
    {
        $result = $this->version->setMetadata(['key' => 'value']);

        $this->assertSame($this->version, $result);
    }

    public function testSetAuthorReturnsSelf(): void
    {
        $result = $this->version->setAuthor(null);

        $this->assertSame($this->version, $result);
    }

    public function testSetChangeDescriptionReturnsSelf(): void
    {
        $result = $this->version->setChangeDescription('desc');

        $this->assertSame($this->version, $result);
    }

    public function testVersionNumberCanBeZero(): void
    {
        $this->version->setVersion(0);

        $this->assertSame(0, $this->version->getVersion());
    }

    public function testVersionNumberCanBeLarge(): void
    {
        $this->version->setVersion(999999);

        $this->assertSame(999999, $this->version->getVersion());
    }

    public function testContentCanContainSpecialCharacters(): void
    {
        $content = "# Markdown content\n\n- Item 1\n- Item 2\n\n```php\n<?php echo 'test';\n```\n\nEmoji: 📝";

        $this->version->setContent($content);

        $this->assertSame($content, $this->version->getContent());
    }

    public function testMultipleVersionsHaveUniqueIds(): void
    {
        $version1 = new DocumentVersion();
        $version2 = new DocumentVersion();

        $this->assertFalse($version1->getId()->equals($version2->getId()));
    }
}
