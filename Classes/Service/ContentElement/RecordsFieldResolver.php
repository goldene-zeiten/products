<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\ContentElement;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class RecordsFieldResolver
{
    /**
     * Resolves the current content element's "records" field to the uids of the given table,
     * respecting whatever "allowed" restriction is configured for this element's CType
     * (see Configuration/TCA/Overrides/tt_content.php columnsOverrides).
     *
     * @return int[]
     */
    public function resolveUids(ServerRequestInterface $request, string $table): array
    {
        $contentObject = $request->getAttribute('currentContentObject');
        $data = $contentObject?->data;
        if (!is_array($data)) {
            return [];
        }
        $rawValue = (string)($data['records'] ?? '');
        if ($rawValue === '') {
            return [];
        }
        $cType = (string)($data['CType'] ?? '');
        $config = $GLOBALS['TCA']['tt_content']['types'][$cType]['columnsOverrides']['records']['config']
            ?? $GLOBALS['TCA']['tt_content']['columns']['records']['config']
            ?? ['allowed' => $table];

        $relationHandler = GeneralUtility::makeInstance(RelationHandler::class);
        $relationHandler->start($rawValue, (string)$config['allowed'], '', 0, 'tt_content', $config);
        return array_map('intval', $relationHandler->tableArray[$table] ?? []);
    }
}
