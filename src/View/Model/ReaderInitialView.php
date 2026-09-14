<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\View\Model;

final readonly class ReaderInitialView
{
    public function __construct(
        public int $sessionId,
        public string $title,
        public string $frameId,
        public string $questionsFrameId,
    ) {
    }
}
