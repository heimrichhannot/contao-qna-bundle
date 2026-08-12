<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\EventListener\DataContainer\Session;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Slug\Slug;
use Contao\DC_Table;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCallback(table: 'tl_qna_session', target: 'fields.alias.save')]
final readonly class FieldsAliasSaveListener
{
    public function __construct(
        private Slug $slug,
        private Connection $connection,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(string $value, DC_Table $dataContainer): string
    {
        $record = $dataContainer->getActiveRecord() ?? [];
        $recordId = $record['id'] ?? 0;

        if (!\is_int($recordId) && !\is_string($recordId)) {
            throw new \UnexpectedValueException('The active record ID is not an integer value.');
        }

        $recordId = (int) $recordId;
        $aliasExists = fn (string $alias): bool => (bool) $this->connection->fetchOne(
            <<<'SQL'
                SELECT 1
                FROM tl_qna_session
                WHERE alias = :alias AND id != :id
                SQL,
            ['alias' => $alias, 'id' => $recordId],
            ['alias' => ParameterType::STRING, 'id' => ParameterType::INTEGER],
        );

        if ('' === $value) {
            $title = $record['title'] ?? '';

            if (!\is_string($title)) {
                throw new \UnexpectedValueException('The active record title is not a string value.');
            }

            return $this->slug->generate($title, [], $aliasExists);
        }

        if (preg_match('/^[1-9]\d*$/', $value)) {
            throw new \RuntimeException($this->translator->trans('ERR.aliasNumeric', [], 'contao_default'));
        }

        if ($aliasExists($value)) {
            throw new \RuntimeException($this->translator->trans('ERR.aliasExists', [$value], 'contao_default'));
        }

        return $value;
    }
}
