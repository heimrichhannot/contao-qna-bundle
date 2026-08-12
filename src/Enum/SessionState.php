<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Enum;

enum SessionState: string
{
    case WAITING = 'waiting';
    case OPEN = 'open';
    case CLOSED = 'closed';
}
