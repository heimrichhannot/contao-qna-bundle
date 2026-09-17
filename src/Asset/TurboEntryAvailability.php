<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Asset;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\LayoutModel;
use Contao\PageModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use HeimrichHannot\ContaoUxTurboEncore\EncoreExtension as TurboEncoreExtension;

final readonly class TurboEntryAvailability
{
    public function __construct(private ContaoFramework $framework, private Connection $connection)
    {
    }

    public function isActiveForPage(PageModel $page): bool
    {
        $page->loadDetails();
        $layout = $this->framework->getAdapter(LayoutModel::class)->findById($page->layout);

        if (null === $layout || !($layout->row()['addEncore'] ?? false)) {
            return false;
        }

        if ($this->containsActiveTurboEntry($layout->row()['encoreEntries'] ?? null)) {
            return true;
        }

        foreach ($page->trail as $id) {
            if ((!\is_int($id) && !\is_string($id)) || (int) $id <= 0 || (int) $id === (int) $page->id) {
                continue;
            }

            $parent = $this->framework->getAdapter(PageModel::class)->findById($id);
            if (null !== $parent && $this->containsActiveTurboEntry($parent->row()['encoreEntries'] ?? null)) {
                return true;
            }
        }

        return $this->containsActiveTurboEntry($page->row()['encoreEntries'] ?? null);
    }

    public function isActiveAnywhere(): bool
    {
        $schema = $this->connection->createSchemaManager();
        $layoutColumns = $schema->listTableColumns('tl_layout');
        $pageColumns = $schema->listTableColumns('tl_page');

        if (!isset($layoutColumns['encoreentries'], $layoutColumns['addencore'], $pageColumns['encoreentries'])) {
            return false;
        }

        foreach (["SELECT encoreEntries FROM tl_layout WHERE addEncore = '1'", 'SELECT encoreEntries FROM tl_page WHERE encoreEntries IS NOT NULL'] as $sql) {
            foreach ($this->connection->iterateColumn($sql) as $blob) {
                if ($this->containsActiveTurboEntry($blob)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function containsActiveTurboEntry(mixed $blob): bool
    {
        foreach ($this->framework->getAdapter(StringUtil::class)->deserialize($blob, true) as $row) {
            if (\is_array($row) && \in_array($row['entry'] ?? null, [TurboEncoreExtension::DEFAULT, TurboEncoreExtension::NO_DRIVER], true) && ($row['active'] ?? true)) {
                return true;
            }
        }

        return false;
    }
}
