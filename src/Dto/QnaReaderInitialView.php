<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Dto;

final readonly class QnaReaderInitialView
{
    public function __construct(
        public int $sessionId,
        public string $title,
        public string $frameId,
        public string $questionsFrameId,
    ) {
    }
}
