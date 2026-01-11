<?php

namespace App\Controller;

use App\Application\Command\Annotation\CreateAnnotationCommand;
use App\Application\Command\Annotation\CreateAnnotationHandler;
use App\Application\Command\Decision\ValidateDecisionCommand;
use App\Application\Command\Decision\ValidateDecisionHandler;
use App\Application\Command\Decision\VoteCommand;
use App\Application\Command\Decision\VoteHandler;
use App\Application\Command\Document\CreateDocumentCommand;
use App\Application\Command\Document\CreateDocumentHandler;
use App\Application\Command\Document\UpdateDocumentCommand;
use App\Application\Command\Document\UpdateDocumentHandler;
use App\Application\Command\Session\CreateSessionCommand;
use App\Application\Command\Session\CreateSessionHandler;
use App\Application\Command\Session\JoinSessionCommand;
use App\Application\Command\Session\JoinSessionHandler;
use App\Domain\Session\Exception\SessionArchivedException;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Entity\Session;
use App\Repository\AnnotationRepository;
use App\Repository\DecisionRepository;
use App\Repository\DocumentRepository;
use App\Repository\ParticipantRepository;
use App\Repository\SessionRepository;
use App\Service\Export\ExportService;
use App\Service\Session\SessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class SessionWebController extends AbstractController
{
    public function __construct(
        private readonly CreateSessionHandler $createSessionHandler,
        private readonly JoinSessionHandler $joinSessionHandler,
        private readonly CreateDocumentHandler $createDocumentHandler,
        private readonly UpdateDocumentHandler $updateDocumentHandler,
        private readonly CreateAnnotationHandler $createAnnotationHandler,
        private readonly VoteHandler $voteHandler,
        private readonly ValidateDecisionHandler $validateDecisionHandler,
        private readonly SessionService $sessionService,
        private readonly SessionRepository $sessionRepository,
        private readonly DocumentRepository $documentRepository,
        private readonly ParticipantRepository $participantRepository,
        private readonly AnnotationRepository $annotationRepository,
        private readonly DecisionRepository $decisionRepository,
        private readonly ExportService $exportService,
    ) {}

    #[Route('/', name: 'home')]
    public function home(Request $request): Response
    {
        $inviteCode = $request->query->get('code');

        return $this->render('home.html.twig', [
            'invite_code' => $inviteCode,
        ]);
    }

    #[Route('/session/create', name: 'session_create', methods: ['POST'])]
    public function create(Request $request, SessionInterface $httpSession): Response
    {
        $title = $request->request->get('title');
        $pseudo = $request->request->get('pseudo');

        if (empty($title) || empty($pseudo)) {
            $this->addFlash('error', 'Le titre et le pseudo sont requis');
            return $this->redirectToRoute('home');
        }

        $command = new CreateSessionCommand(
            title: $title,
            description: null,
            creatorPseudo: $pseudo,
            isAgent: false,
        );

        $result = ($this->createSessionHandler)($command);
        $session = $result['session'];
        $participant = $result['participant'];

        $httpSession->set('participant_id', $participant->id);
        $httpSession->set('session_id', $session->id);

        return $this->redirectToRoute('session_view', ['id' => $session->id]);
    }

    #[Route('/session/join', name: 'session_join', methods: ['POST'])]
    public function join(Request $request, SessionInterface $httpSession): Response
    {
        $inviteCode = $request->request->get('invite_code');
        $pseudo = $request->request->get('pseudo');

        if (empty($inviteCode) || empty($pseudo)) {
            $this->addFlash('error', 'Le code d\'invitation et le pseudo sont requis');
            return $this->redirectToRoute('home');
        }

        try {
            $command = new JoinSessionCommand(
                inviteCode: $inviteCode,
                pseudo: $pseudo,
                color: null,
                isAgent: false,
            );

            $result = ($this->joinSessionHandler)($command);
            $session = $result['session'];
            $participant = $result['participant'];

            $httpSession->set('participant_id', $participant->id);
            $httpSession->set('session_id', $session->id);

            return $this->redirectToRoute('session_view', ['id' => $session->id]);
        } catch (SessionNotFoundException $e) {
            $this->addFlash('error', 'Code d\'invitation invalide');
            return $this->redirectToRoute('home');
        } catch (SessionArchivedException $e) {
            $this->addFlash('error', 'Cette session est archivée');
            return $this->redirectToRoute('home');
        }
    }

    #[Route('/session/{id}', name: 'session_view')]
    public function view(string $id, Request $request, SessionInterface $httpSession): Response
    {
        $session = $this->getSessionOrFail($id);

        $participantOrRedirect = $this->getParticipantOrRedirect($httpSession, $session);
        if ($participantOrRedirect instanceof Response) {
            return $participantOrRedirect;
        }
        $participant = $participantOrRedirect;

        $documents = $this->documentRepository->findRootDocuments($session);
        $onlineParticipants = $this->sessionService->getOnlineParticipants($session);

        $mercureUrl = $this->getParameter('mercure.public_url') ?? 'https://localhost/.well-known/mercure';

        // Si c'est une requête HTMX, retourner seulement le contenu partiel (écran d'accueil)
        if ($request->headers->has('HX-Request')) {
            return $this->render('session/_welcome.html.twig', [
                'session' => $session,
            ]);
        }

        return $this->render('session/view.html.twig', [
            'session' => $session,
            'participant' => $participant,
            'documents' => $documents,
            'onlineParticipants' => $onlineParticipants,
            'currentDocument' => null,
            'annotations' => [],
            'mercure_url' => $mercureUrl,
        ]);
    }

    #[Route('/session/{id}/document/new', name: 'session_document_new', methods: ['GET', 'POST'])]
    public function newDocument(string $id, Request $request, SessionInterface $httpSession): Response
    {
        $session = $this->getSessionOrFail($id);
        $participantOrRedirect = $this->getParticipantOrRedirect($httpSession, $session);
        if ($participantOrRedirect instanceof Response) {
            return $participantOrRedirect;
        }
        $participant = $participantOrRedirect;

        if ($request->isMethod('POST')) {
            $command = new CreateDocumentCommand(
                sessionId: $id,
                title: $request->request->get('title'),
                type: $request->request->get('type', 'general'),
                content: $request->request->get('content', ''),
                metadata: null,
                parentId: $request->request->get('parent_id') ?: null,
                sortOrder: 0,
                authorParticipantId: $participant->getId()->toString(),
            );

            $document = ($this->createDocumentHandler)($command);

            return $this->redirectToRoute('session_document_view', [
                'id' => $id,
                'slug' => $document->slug,
            ]);
        }

        $allDocuments = $this->documentRepository->findBySession($session, null, null);
        $mercureUrl = $this->getParameter('mercure.public_url') ?? 'https://localhost/.well-known/mercure';

        return $this->render('document/_form.html.twig', [
            'session' => $session,
            'participant' => $participant,
            'allDocuments' => $allDocuments,
            'mercure_url' => $mercureUrl,
        ]);
    }

    #[Route('/session/{id}/document/{slug}', name: 'session_document_view')]
    public function viewDocument(string $id, string $slug, SessionInterface $httpSession): Response
    {
        $session = $this->getSessionOrFail($id);
        $participantOrRedirect = $this->getParticipantOrRedirect($httpSession, $session);
        if ($participantOrRedirect instanceof Response) {
            return $participantOrRedirect;
        }
        $participant = $participantOrRedirect;

        $document = $this->documentRepository->findOneBySessionAndSlug($session, $slug);
        if ($document === null) {
            throw $this->createNotFoundException('Document non trouvé');
        }

        $this->sessionService->updateParticipantPresence($participant, $document->getId()->toString());

        $documents = $this->documentRepository->findRootDocuments($session);
        $annotations = $this->annotationRepository->findByDocumentWithFilters($document);
        $onlineParticipants = $this->sessionService->getOnlineParticipants($session);

        // Get all decisions linked to this document
        $decisions = $this->decisionRepository->findBy(['linkedDocument' => $document], ['createdAt' => 'ASC']);

        $mercureUrl = $this->getParameter('mercure.public_url') ?? 'https://localhost/.well-known/mercure';

        if ($this->isHtmxRequest($httpSession)) {
            return $this->render('document/_view.html.twig', [
                'session' => $session,
                'participant' => $participant,
                'document' => $document,
                'annotations' => $annotations,
                'decisions' => $decisions,
                'mercure_url' => $mercureUrl,
            ]);
        }

        return $this->render('session/view.html.twig', [
            'session' => $session,
            'participant' => $participant,
            'documents' => $documents,
            'currentDocument' => $document,
            'annotations' => $annotations,
            'onlineParticipants' => $onlineParticipants,
            'decisions' => $decisions,
            'mercure_url' => $mercureUrl,
        ]);
    }

    #[Route('/session/{id}/document/{slug}/edit', name: 'session_document_edit', methods: ['GET', 'POST'])]
    public function editDocument(string $id, string $slug, Request $request, SessionInterface $httpSession): Response
    {
        $session = $this->getSessionOrFail($id);
        $participantOrRedirect = $this->getParticipantOrRedirect($httpSession, $session);
        if ($participantOrRedirect instanceof Response) {
            return $participantOrRedirect;
        }
        $participant = $participantOrRedirect;

        $document = $this->documentRepository->findOneBySessionAndSlug($session, $slug);
        if ($document === null) {
            throw $this->createNotFoundException('Document non trouvé');
        }

        if ($request->isMethod('POST')) {
            $command = new UpdateDocumentCommand(
                documentId: $document->getId()->toString(),
                title: $request->request->get('title'),
                content: $request->request->get('content'),
                metadata: null,
                parentId: null,
                sortOrder: null,
                changeDescription: null,
                authorParticipantId: $participant->getId()->toString(),
            );

            $updatedDocument = ($this->updateDocumentHandler)($command);

            return $this->redirectToRoute('session_document_view', [
                'id' => $id,
                'slug' => $updatedDocument->slug,
            ]);
        }

        $allDocuments = $this->documentRepository->findBySession($session, null, null);
        $mercureUrl = $this->getParameter('mercure.public_url') ?? 'https://localhost/.well-known/mercure';

        return $this->render('document/_form.html.twig', [
            'session' => $session,
            'participant' => $participant,
            'document' => $document,
            'allDocuments' => $allDocuments,
            'mercure_url' => $mercureUrl,
        ]);
    }

    #[Route('/session/{id}/annotation/create', name: 'session_annotation_create', methods: ['POST'])]
    public function createAnnotation(string $id, Request $request, SessionInterface $httpSession): Response
    {
        $session = $this->getSessionOrFail($id);
        $participantOrRedirect = $this->getParticipantOrRedirect($httpSession, $session);
        if ($participantOrRedirect instanceof Response) {
            return $participantOrRedirect;
        }
        $participant = $participantOrRedirect;

        $documentId = $request->request->get('document_id');
        $content = $request->request->get('content');
        $type = $request->request->get('type', 'comment');

        if (empty($documentId) || empty($content)) {
            return new Response('Document et contenu requis', Response::HTTP_BAD_REQUEST);
        }

        $document = $this->documentRepository->find(Uuid::fromString($documentId));
        if ($document === null || $document->getSession()->getId()->toString() !== $id) {
            return new Response('Document non trouvé', Response::HTTP_NOT_FOUND);
        }

        $command = new CreateAnnotationCommand(
            documentId: $documentId,
            authorParticipantId: $participant->getId()->toString(),
            content: $content,
            type: $type,
            anchor: null,
        );

        ($this->createAnnotationHandler)($command);

        // Refresh annotation from repository for Twig template
        $annotations = $this->annotationRepository->findByDocumentWithFilters($document);
        $annotation = end($annotations); // Get the last one (just created)

        return $this->render('annotation/_item.html.twig', [
            'annotation' => $annotation,
            'session' => $session,
            'participant' => $participant,
        ]);
    }

    #[Route('/session/{id}/annotations', name: 'session_annotations')]
    public function annotations(string $id, Request $request, SessionInterface $httpSession): Response
    {
        $session = $this->getSessionOrFail($id);
        $participantOrRedirect = $this->getParticipantOrRedirect($httpSession, $session);
        if ($participantOrRedirect instanceof Response) {
            return $participantOrRedirect;
        }
        $participant = $participantOrRedirect;

        $documentId = $request->query->get('document_id');
        $type = $request->query->get('type');

        $filters = array_filter([
            'type' => $type,
        ]);

        if ($documentId) {
            $document = $this->documentRepository->find(Uuid::fromString($documentId));
            if ($document) {
                $annotations = $this->annotationRepository->findByDocumentWithFilters($document, $filters);
            } else {
                $annotations = [];
            }
        } else {
            $annotations = $this->annotationRepository->findBySessionWithFilters($session, $filters);
        }

        return $this->render('annotation/_list.html.twig', [
            'session' => $session,
            'participant' => $participant,
            'annotations' => $annotations,
            'current_filter' => $type ?? 'all',
        ]);
    }

    #[Route('/session/{id}/sidebar', name: 'session_sidebar')]
    public function sidebar(string $id, SessionInterface $httpSession): Response
    {
        $session = $this->getSessionOrFail($id);
        $participantOrRedirect = $this->getParticipantOrRedirect($httpSession, $session);
        if ($participantOrRedirect instanceof Response) {
            return $participantOrRedirect;
        }

        $documents = $this->documentRepository->findRootDocuments($session);

        return $this->render('session/_document_tree.html.twig', [
            'session' => $session,
            'documents' => $documents,
        ]);
    }

    #[Route('/session/{id}/header', name: 'session_header')]
    public function header(string $id, Request $request, SessionInterface $httpSession): Response
    {
        $session = $this->getSessionOrFail($id);
        $participantOrRedirect = $this->getParticipantOrRedirect($httpSession, $session);
        if ($participantOrRedirect instanceof Response) {
            return $participantOrRedirect;
        }

        $currentDocument = null;
        $documentSlug = $request->query->get('document');
        if ($documentSlug) {
            $currentDocument = $this->documentRepository->findOneBySessionAndSlug($session, $documentSlug);
        }

        $onlineParticipants = $this->sessionService->getOnlineParticipants($session);

        return $this->render('session/_header.html.twig', [
            'session' => $session,
            'currentDocument' => $currentDocument,
            'onlineParticipants' => $onlineParticipants,
        ]);
    }

    #[Route('/session/{id}/decisions', name: 'session_decisions')]
    public function decisions(string $id, Request $request, SessionInterface $httpSession): Response
    {
        $session = $this->getSessionOrFail($id);
        $participantOrRedirect = $this->getParticipantOrRedirect($httpSession, $session);
        if ($participantOrRedirect instanceof Response) {
            return $participantOrRedirect;
        }
        $participant = $participantOrRedirect;

        $documentId = $request->query->get('document_id');

        $criteria = ['session' => $session];
        if ($documentId) {
            $document = $this->documentRepository->find(Uuid::fromString($documentId));
            if ($document) {
                $criteria['linkedDocument'] = $document;
            }
        }

        $decisions = $this->decisionRepository->findBy($criteria, ['createdAt' => 'ASC']);

        return $this->render('decision/_list.html.twig', [
            'session' => $session,
            'participant' => $participant,
            'decisions' => $decisions,
        ]);
    }

    #[Route('/session/{id}/decision/{decisionId}/vote', name: 'session_decision_vote', methods: ['POST'])]
    public function voteDecision(string $id, string $decisionId, Request $request, SessionInterface $httpSession): Response
    {
        $session = $this->getSessionOrFail($id);
        $participantOrRedirect = $this->getParticipantOrRedirect($httpSession, $session);
        if ($participantOrRedirect instanceof Response) {
            return $participantOrRedirect;
        }
        $participant = $participantOrRedirect;

        $decision = $this->decisionRepository->find(Uuid::fromString($decisionId));
        if ($decision === null || $decision->getSession()->getId()->toString() !== $id) {
            throw $this->createNotFoundException('Décision non trouvée');
        }

        $optionId = $request->request->get('option_id');
        if (empty($optionId)) {
            throw $this->createNotFoundException('Option requise');
        }

        $command = new VoteCommand(
            decisionId: $decisionId,
            participantId: $participant->getId()->toString(),
            optionId: $optionId,
            comment: null,
        );

        ($this->voteHandler)($command);

        // Refresh the decision entity
        $decision = $this->decisionRepository->find(Uuid::fromString($decisionId));

        return $this->render('decision/_card.html.twig', [
            'decision' => $decision,
            'participant' => $participant,
            'session' => $session,
        ]);
    }

    #[Route('/session/{id}/decision/{decisionId}/validate', name: 'session_decision_validate', methods: ['POST'])]
    public function validateDecision(string $id, string $decisionId, Request $request, SessionInterface $httpSession): Response
    {
        $session = $this->getSessionOrFail($id);
        $participantOrRedirect = $this->getParticipantOrRedirect($httpSession, $session);
        if ($participantOrRedirect instanceof Response) {
            return $participantOrRedirect;
        }
        $participant = $participantOrRedirect;

        $decision = $this->decisionRepository->find(Uuid::fromString($decisionId));
        if ($decision === null || $decision->getSession()->getId()->toString() !== $id) {
            throw $this->createNotFoundException('Décision non trouvée');
        }

        $optionId = $request->request->get('option_id');
        if (empty($optionId)) {
            throw $this->createNotFoundException('Option requise');
        }

        $command = new ValidateDecisionCommand(
            decisionId: $decisionId,
            selectedOptionId: $optionId,
        );

        ($this->validateDecisionHandler)($command);

        // Refresh the decision entity
        $decision = $this->decisionRepository->find(Uuid::fromString($decisionId));

        return $this->render('decision/_card.html.twig', [
            'decision' => $decision,
            'participant' => $participant,
            'session' => $session,
        ]);
    }

    #[Route('/session/{id}/export/markdown', name: 'session_export_markdown')]
    public function exportMarkdown(string $id, SessionInterface $httpSession): Response
    {
        $session = $this->getSessionOrFail($id);
        $participantOrRedirect = $this->getParticipantOrRedirect($httpSession, $session);
        if ($participantOrRedirect instanceof Response) {
            return $participantOrRedirect;
        }

        $markdown = $this->exportService->exportToMarkdown($session);

        $filename = $this->sanitizeFilename($session->getTitle()) . '.md';

        $response = new Response($markdown);
        $response->headers->set('Content-Type', 'text/markdown; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    #[Route('/session/{id}/export/html', name: 'session_export_html')]
    public function exportHtml(string $id, SessionInterface $httpSession): Response
    {
        $session = $this->getSessionOrFail($id);
        $participantOrRedirect = $this->getParticipantOrRedirect($httpSession, $session);
        if ($participantOrRedirect instanceof Response) {
            return $participantOrRedirect;
        }

        $html = $this->exportService->exportToHtml($session);

        $filename = $this->sanitizeFilename($session->getTitle()) . '.html';

        $response = new Response($html);
        $response->headers->set('Content-Type', 'text/html; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $filename);
        $filename = preg_replace('/\s+/', '_', $filename);
        return mb_substr($filename, 0, 50);
    }

    private function getSessionOrFail(string $id): Session
    {
        try {
            $session = $this->sessionRepository->find(Uuid::fromString($id));
        } catch (\InvalidArgumentException $e) {
            throw $this->createNotFoundException('Session non trouvée');
        }

        if ($session === null) {
            throw $this->createNotFoundException('Session non trouvée');
        }

        return $session;
    }

    private function tryGetParticipant(SessionInterface $httpSession, Session $session): ?\App\Entity\Participant
    {
        $participantId = $httpSession->get('participant_id');
        if (empty($participantId)) {
            return null;
        }

        try {
            $participant = $this->participantRepository->find(Uuid::fromString($participantId));
        } catch (\InvalidArgumentException $e) {
            return null;
        }

        if ($participant === null || $participant->getSession()->getId()->toString() !== $session->getId()->toString()) {
            return null;
        }

        return $participant;
    }

    private function getParticipantOrRedirect(SessionInterface $httpSession, Session $session): \App\Entity\Participant|Response
    {
        $participant = $this->tryGetParticipant($httpSession, $session);
        if ($participant === null) {
            $httpSession->remove('participant_id');
            $httpSession->remove('session_id');
            return $this->redirectToRoute('home', ['code' => $session->getInviteCode()]);
        }
        return $participant;
    }

    private function isHtmxRequest(SessionInterface $httpSession): bool
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        return $request && $request->headers->has('HX-Request');
    }
}
