<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Gateway;

use HeimrichHannot\QnaBundle\Model\Question;
use HeimrichHannot\QnaBundle\Model\Session;

final readonly class LockedQuestionContext
{
    public function __construct(
        public Session $session,
        public Question $question,
    ) {
    }
}
