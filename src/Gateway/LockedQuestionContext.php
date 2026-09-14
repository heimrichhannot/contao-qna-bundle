<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Gateway;

use HeimrichHannot\QnaBundle\Dto\QnaQuestion;
use HeimrichHannot\QnaBundle\Dto\QnaSession;

final readonly class LockedQuestionContext
{
    public function __construct(
        public QnaSession $session,
        public QnaQuestion $question,
    ) {
    }
}
