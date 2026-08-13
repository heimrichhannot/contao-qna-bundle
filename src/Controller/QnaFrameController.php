<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Controller;

use HeimrichHannot\QnaBundle\View\QnaFrameResponseFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class QnaFrameController
{
    public function __construct(private QnaFrameResponseFactory $responseFactory)
    {
    }

    #[Route(
        '/_qna/reader/{sessionId}',
        name: 'contao_qna_reader_frame',
        requirements: ['sessionId' => '\\d+'],
        methods: ['GET'],
    )]
    public function reader(int $sessionId, Request $request): Response
    {
        if ($this->acceptsTurboStream($request)) {
            return $this->responseFactory->renderReaderUpdate(
                $sessionId,
                $request->query->getBoolean('resetQuestionForm'),
            );
        }

        return $this->responseFactory->renderReaderQuestions($sessionId);
    }

    #[Route(
        '/_qna/reader/{sessionId}/controls',
        name: 'contao_qna_reader_controls',
        requirements: ['sessionId' => '\\d+'],
        methods: ['GET'],
    )]
    public function readerControls(int $sessionId): Response
    {
        return $this->responseFactory->renderReaderControls($sessionId);
    }

    #[Route(
        '/_qna/stage/{sessionId}/questions',
        name: 'contao_qna_stage_questions',
        requirements: ['sessionId' => '\\d+'],
        methods: ['GET'],
    )]
    public function stage(int $sessionId, Request $request): Response
    {
        $sort = $request->query->getString('sort', 'votes');

        return $this->acceptsTurboStream($request)
            ? $this->responseFactory->renderStageUpdate($sessionId, $sort)
            : $this->responseFactory->renderStage($sessionId, $sort);
    }

    private function acceptsTurboStream(Request $request): bool
    {
        return str_contains(
            $request->headers->get('Accept', ''),
            QnaFrameResponseFactory::TURBO_STREAM_CONTENT_TYPE,
        );
    }
}
