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
    public function reader(int $sessionId): Response
    {
        return $this->responseFactory->renderReader($sessionId);
    }

    #[Route(
        '/_qna/stage/{sessionId}/questions',
        name: 'contao_qna_stage_questions',
        requirements: ['sessionId' => '\\d+'],
        methods: ['GET'],
    )]
    public function stage(int $sessionId, Request $request): Response
    {
        return $this->responseFactory->renderStage(
            $sessionId,
            $request->query->getString('sort', 'votes'),
        );
    }
}
