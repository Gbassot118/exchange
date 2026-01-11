<?php

namespace App\Tests\Unit\Domain\Document\ValueObject;

use App\Domain\Document\Exception\InvalidDocumentTypeException;
use App\Domain\Document\ValueObject\DocumentType;
use PHPUnit\Framework\TestCase;

class DocumentTypeTest extends TestCase
{
    public function testSynthesisType(): void
    {
        $type = DocumentType::synthesis();

        $this->assertTrue($type->isSynthesis());
        $this->assertFalse($type->isQuestion());
        $this->assertFalse($type->isGeneral());
        $this->assertSame('synthesis', $type->toString());
    }

    public function testQuestionType(): void
    {
        $type = DocumentType::question();

        $this->assertTrue($type->isQuestion());
        $this->assertFalse($type->isSynthesis());
        $this->assertSame('question', $type->toString());
    }

    public function testComparisonType(): void
    {
        $type = DocumentType::comparison();

        $this->assertTrue($type->isComparison());
        $this->assertSame('comparison', $type->toString());
    }

    public function testAnnexeType(): void
    {
        $type = DocumentType::annexe();

        $this->assertTrue($type->isAnnexe());
        $this->assertSame('annexe', $type->toString());
    }

    public function testCompteRenduType(): void
    {
        $type = DocumentType::compteRendu();

        $this->assertTrue($type->isCompteRendu());
        $this->assertSame('compte_rendu', $type->toString());
    }

    public function testGeneralType(): void
    {
        $type = DocumentType::general();

        $this->assertTrue($type->isGeneral());
        $this->assertSame('general', $type->toString());
    }

    public function testFromStringWithValidValues(): void
    {
        $this->assertTrue(DocumentType::fromString('synthesis')->isSynthesis());
        $this->assertTrue(DocumentType::fromString('question')->isQuestion());
        $this->assertTrue(DocumentType::fromString('comparison')->isComparison());
        $this->assertTrue(DocumentType::fromString('annexe')->isAnnexe());
        $this->assertTrue(DocumentType::fromString('compte_rendu')->isCompteRendu());
        $this->assertTrue(DocumentType::fromString('general')->isGeneral());
    }

    public function testFromStringWithInvalidValue(): void
    {
        $this->expectException(InvalidDocumentTypeException::class);

        DocumentType::fromString('invalid');
    }

    public function testEquals(): void
    {
        $type1 = DocumentType::synthesis();
        $type2 = DocumentType::synthesis();
        $type3 = DocumentType::question();

        $this->assertTrue($type1->equals($type2));
        $this->assertFalse($type1->equals($type3));
    }

    public function testAllTypes(): void
    {
        $types = DocumentType::allTypes();

        $this->assertCount(6, $types);
        $this->assertContains('synthesis', $types);
        $this->assertContains('question', $types);
        $this->assertContains('comparison', $types);
        $this->assertContains('annexe', $types);
        $this->assertContains('compte_rendu', $types);
        $this->assertContains('general', $types);
    }

    public function testToString(): void
    {
        $type = DocumentType::synthesis();

        $this->assertSame('synthesis', (string) $type);
    }
}
