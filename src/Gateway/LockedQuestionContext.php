<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Gateway;

use HeimrichHannot\QnaBundle\Domain\Question;
use HeimrichHannot\QnaBundle\Domain\Session;

final readonly class LockedQuestionContext
{
    public function __construct(
        public Session $session,
        public Question $question,
    ) {
    }
}
