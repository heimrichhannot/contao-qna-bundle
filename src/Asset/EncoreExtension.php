<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Asset;

use HeimrichHannot\EncoreContracts\EncoreEntry;
use HeimrichHannot\EncoreContracts\EncoreExtensionInterface;
use HeimrichHannot\QnaBundle\HeimrichHannotQnaBundle;

class EncoreExtension implements EncoreExtensionInterface
{
    public const string ENTRY = 'huh_qna';

    public function getBundle(): string
    {
        return HeimrichHannotQnaBundle::class;
    }

    /**
     * @return list<EncoreEntry>
     */
    public function getEntries(): array
    {
        return [
            EncoreEntry::create(self::ENTRY, 'assets/js/qna.js')
                ->setRequiresCss(true),
        ];
    }
}
