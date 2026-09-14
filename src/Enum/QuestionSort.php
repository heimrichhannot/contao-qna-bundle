<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Enum;

enum QuestionSort: string
{
    case VOTES = 'votes';
    case TIME = 'time';

    public static function fromRequestValue(string $value): self
    {
        return self::tryFrom($value) ?? self::VOTES;
    }

    public function orderBySql(): string
    {
        return match ($this) {
            self::VOTES => 'voteCount DESC, q.createdAt ASC',
            self::TIME => 'q.createdAt ASC',
        };
    }
}
