<?php

namespace App\Tests\Unit\Service\Mercure;

use App\Entity\Decision;
use App\Entity\Document;
use App\Entity\Session;
use App\Service\Mercure\MercurePublisher;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MercurePublisherTest extends TestCase
{
    private HubInterface&MockObject $hub;
    private MercurePublisher $publisher;

    protected function setUp(): void
    {
        $this->hub = $this->createMock(HubInterface::class);
        $this->publisher = new MercurePublisher($this->hub);
    }

    public function testPublishDocumentCreatedCallsHub(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->isInstanceOf(Update::class));

        $this->publisher->publishDocumentCreated('session-id', ['id' => 'doc-id', 'title' => 'Doc']);
    }

    public function testPublishDocumentCreatedUsesCorrectTopic(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                $topics = $update->getTopics();
                return in_array('/sessions/session-123/documents', $topics);
            }));

        $this->publisher->publishDocumentCreated('session-123', ['id' => 'doc-id']);
    }

    public function testPublishDocumentCreatedIncludesType(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                $data = json_decode($update->getData(), true);
                return $data['type'] === 'document.created';
            }));

        $this->publisher->publishDocumentCreated('session-id', ['id' => 'doc-id']);
    }

    public function testPublishDocumentUpdatedCallsHub(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->isInstanceOf(Update::class));

        $this->publisher->publishDocumentUpdated('session-id', 'doc-id', ['id' => 'doc-id']);
    }

    public function testPublishDocumentUpdatedUsesMultipleTopics(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                $topics = $update->getTopics();
                return count($topics) === 2
                    && in_array('/sessions/session-123/documents', $topics)
                    && in_array('/sessions/session-123/documents/doc-456', $topics);
            }));

        $this->publisher->publishDocumentUpdated('session-123', 'doc-456', ['id' => 'doc-456']);
    }

    public function testPublishDocumentDeletedCallsHub(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->isInstanceOf(Update::class));

        $this->publisher->publishDocumentDeleted('session-id', 'doc-id');
    }

    public function testPublishDocumentDeletedIncludesId(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                $data = json_decode($update->getData(), true);
                return $data['data']['id'] === 'doc-123';
            }));

        $this->publisher->publishDocumentDeleted('session-id', 'doc-123');
    }

    public function testPublishAnnotationCreatedCallsHub(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->isInstanceOf(Update::class));

        $this->publisher->publishAnnotationCreated('session-id', 'doc-id', ['id' => 'ann-id']);
    }

    public function testPublishAnnotationCreatedUsesCorrectTopics(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                $topics = $update->getTopics();
                return in_array('/sessions/session-123/annotations', $topics)
                    && in_array('/sessions/session-123/documents/doc-456', $topics);
            }));

        $this->publisher->publishAnnotationCreated('session-123', 'doc-456', ['id' => 'ann-id']);
    }

    public function testPublishAnnotationUpdatedCallsHub(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->isInstanceOf(Update::class));

        $this->publisher->publishAnnotationUpdated('session-id', 'doc-id', ['id' => 'ann-id']);
    }

    public function testPublishAnnotationResolvedCallsHub(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->isInstanceOf(Update::class));

        $this->publisher->publishAnnotationResolved('session-id', 'doc-id', ['id' => 'ann-id']);
    }

    public function testPublishVoteReceivedCallsHub(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->isInstanceOf(Update::class));

        $this->publisher->publishVoteReceived('session-id', 'decision-id', ['option1' => 5]);
    }

    public function testPublishVoteReceivedIncludesStats(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                $data = json_decode($update->getData(), true);
                return isset($data['data']['stats'])
                    && $data['data']['decision_id'] === 'decision-123';
            }));

        $this->publisher->publishVoteReceived('session-id', 'decision-123', ['opt1' => 3, 'opt2' => 2]);
    }

    public function testPublishDecisionStatusChangedCallsHub(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->isInstanceOf(Update::class));

        $this->publisher->publishDecisionStatusChanged('session-id', ['id' => 'dec-id', 'status' => 'valide']);
    }

    public function testPublishDecisionCreatedCallsHub(): void
    {
        $session = new Session();
        $session->setTitle('Test Session');

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test Decision');

        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->isInstanceOf(Update::class));

        $this->publisher->publishDecisionCreated($decision);
    }

    public function testPublishDecisionCreatedIncludesDocumentId(): void
    {
        $session = new Session();
        $session->setTitle('Test Session');

        $document = new Document();
        $document->setSession($session);
        $document->setTitle('Test Doc');
        $document->setSlug('test-doc');

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test Decision');
        $decision->setLinkedDocument($document);

        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) use ($document) {
                $data = json_decode($update->getData(), true);
                return $data['data']['document_id'] === $document->getId()->toString();
            }));

        $this->publisher->publishDecisionCreated($decision);
    }

    public function testPublishDecisionCreatedWithNoLinkedDocument(): void
    {
        $session = new Session();
        $session->setTitle('Test Session');

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test Decision');

        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                $data = json_decode($update->getData(), true);
                return $data['data']['document_id'] === null;
            }));

        $this->publisher->publishDecisionCreated($decision);
    }

    public function testPublishDecisionDeletedCallsHub(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->isInstanceOf(Update::class));

        $this->publisher->publishDecisionDeleted('session-id', 'decision-id', 'doc-id');
    }

    public function testPublishDecisionDeletedWithNullDocumentId(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                $data = json_decode($update->getData(), true);
                return $data['data']['document_id'] === null;
            }));

        $this->publisher->publishDecisionDeleted('session-id', 'decision-id', null);
    }

    public function testPublishDecisionUpdatedCallsHub(): void
    {
        $session = new Session();
        $session->setTitle('Test Session');

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test Decision');
        $decision->addOption('Option A');

        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->isInstanceOf(Update::class));

        $this->publisher->publishDecisionUpdated($decision);
    }

    public function testPublishPresenceUpdateCallsHub(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->isInstanceOf(Update::class));

        $this->publisher->publishPresenceUpdate('session-id', [['id' => 'user-1', 'pseudo' => 'User1']]);
    }

    public function testPublishPresenceUpdateIncludesParticipants(): void
    {
        $participants = [
            ['id' => 'user-1', 'pseudo' => 'User1', 'online' => true],
            ['id' => 'user-2', 'pseudo' => 'User2', 'online' => false],
        ];

        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) use ($participants) {
                $data = json_decode($update->getData(), true);
                return $data['data']['participants'] === $participants;
            }));

        $this->publisher->publishPresenceUpdate('session-id', $participants);
    }

    public function testPublishUserFollowingCallsHub(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->isInstanceOf(Update::class));

        $this->publisher->publishUserFollowing('session-id', 'participant-id', 'doc-id');
    }

    public function testPublishUserFollowingWithNullDocumentId(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                $data = json_decode($update->getData(), true);
                return $data['data']['document_id'] === null;
            }));

        $this->publisher->publishUserFollowing('session-id', 'participant-id', null);
    }

    public function testPublishSessionStatusChangedCallsHub(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->isInstanceOf(Update::class));

        $this->publisher->publishSessionStatusChanged('session-id', 'en_cours');
    }

    public function testPublishIncludesTimestamp(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                $data = json_decode($update->getData(), true);
                return isset($data['timestamp'])
                    && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $data['timestamp']);
            }));

        $this->publisher->publishSessionStatusChanged('session-id', 'en_cours');
    }

    public function testUpdateIncludesEventType(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                // The Update type property is the SSE event name
                return $update->getType() === 'session.status_changed';
            }));

        $this->publisher->publishSessionStatusChanged('session-id', 'en_cours');
    }

    public function testPublishWithEmptyData(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                $data = json_decode($update->getData(), true);
                return isset($data['data']) && $data['data'] === [];
            }));

        $this->publisher->publishDocumentCreated('session-id', []);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAllPublishMethodsUseCorrectEventTypes(): void
    {
        $session = new Session();
        $session->setTitle('Test');
        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');

        $testCases = [
            ['method' => 'publishDocumentCreated', 'args' => ['s', []], 'type' => 'document.created'],
            ['method' => 'publishDocumentUpdated', 'args' => ['s', 'd', []], 'type' => 'document.updated'],
            ['method' => 'publishDocumentDeleted', 'args' => ['s', 'd'], 'type' => 'document.deleted'],
            ['method' => 'publishAnnotationCreated', 'args' => ['s', 'd', []], 'type' => 'annotation.created'],
            ['method' => 'publishAnnotationUpdated', 'args' => ['s', 'd', []], 'type' => 'annotation.updated'],
            ['method' => 'publishAnnotationResolved', 'args' => ['s', 'd', []], 'type' => 'annotation.resolved'],
            ['method' => 'publishVoteReceived', 'args' => ['s', 'd', []], 'type' => 'vote.received'],
            ['method' => 'publishDecisionStatusChanged', 'args' => ['s', []], 'type' => 'decision.status_changed'],
            ['method' => 'publishPresenceUpdate', 'args' => ['s', []], 'type' => 'presence.update'],
            ['method' => 'publishUserFollowing', 'args' => ['s', 'p', null], 'type' => 'presence.following'],
            ['method' => 'publishSessionStatusChanged', 'args' => ['s', 'status'], 'type' => 'session.status_changed'],
        ];

        foreach ($testCases as $testCase) {
            $hub = $this->createMock(HubInterface::class);
            $publisher = new MercurePublisher($hub);

            $expectedType = $testCase['type'];
            $hub->expects($this->once())
                ->method('publish')
                ->with($this->callback(function (Update $update) use ($expectedType) {
                    $data = json_decode($update->getData(), true);
                    return $data['type'] === $expectedType;
                }));

            $publisher->{$testCase['method']}(...$testCase['args']);
        }
    }

    public function testPublishDecisionUpdatedIncludesVoteStats(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->addOption('A');
        $decision->addOption('B');

        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                $data = json_decode($update->getData(), true);
                return isset($data['data']['vote_stats']);
            }));

        $this->publisher->publishDecisionUpdated($decision);
    }

    public function testTopicsAreCorrectlyFormatted(): void
    {
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                $topics = $update->getTopics();
                foreach ($topics as $topic) {
                    if (!str_starts_with($topic, '/sessions/')) {
                        return false;
                    }
                }
                return true;
            }));

        $this->publisher->publishPresenceUpdate('my-session-id', []);
    }
}
