<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\View;

use HeimrichHannot\QnaBundle\Dto\QnaReaderInitialView;
use HeimrichHannot\QnaBundle\Dto\QnaReaderView;
use HeimrichHannot\QnaBundle\Dto\QnaSession;
use HeimrichHannot\QnaBundle\Enum\SessionState;

final class QnaReaderViewFactory
{
    public function createInitial(QnaSession $session): QnaReaderInitialView
    {
        return new QnaReaderInitialView(
            $session->id,
            $session->title,
            $this->frameId($session->id),
            $this->questionsFrameId($session->id),
        );
    }

    public function createDynamic(QnaSession $session, bool $canInteract = true): QnaReaderView
    {
        return match ($session->state) {
            SessionState::WAITING => new QnaReaderView(
                $session->id,
                $this->frameId($session->id),
                $this->questionsFrameId($session->id),
                $this->controlsContentId($session->id),
                $session->state->value,
                'qna.reader.status.waiting',
                false,
                false,
                false,
            ),
            SessionState::OPEN => new QnaReaderView(
                $session->id,
                $this->frameId($session->id),
                $this->questionsFrameId($session->id),
                $this->controlsContentId($session->id),
                $session->state->value,
                'qna.reader.status.open',
                $canInteract,
                true,
                $canInteract,
            ),
            SessionState::CLOSED => new QnaReaderView(
                $session->id,
                $this->frameId($session->id),
                $this->questionsFrameId($session->id),
                $this->controlsContentId($session->id),
                $session->state->value,
                'qna.reader.status.closed',
                false,
                true,
                false,
            ),
        };
    }

    private function frameId(int $sessionId): string
    {
        return \sprintf('qna-session-%d-reader', $sessionId);
    }

    private function questionsFrameId(int $sessionId): string
    {
        return \sprintf('qna-session-%d-questions', $sessionId);
    }

    private function controlsContentId(int $sessionId): string
    {
        return \sprintf('qna-session-%d-controls', $sessionId);
    }
}
