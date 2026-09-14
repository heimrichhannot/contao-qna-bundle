<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\View\Model;

final readonly class SessionListItemView
{
    public function __construct(
        public int $id,
        public string $title,
        public string $url,
    ) {
    }
}
