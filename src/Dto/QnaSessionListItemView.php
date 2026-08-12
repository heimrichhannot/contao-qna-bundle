<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Dto;

final readonly class QnaSessionListItemView
{
    public function __construct(
        public int $id,
        public string $title,
        public string $url,
    ) {
    }
}
