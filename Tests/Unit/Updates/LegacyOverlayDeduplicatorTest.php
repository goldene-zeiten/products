<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Unit\Updates;

use GoldeneZeiten\Products\Updates\LegacyOverlayDeduplicator;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class LegacyOverlayDeduplicatorTest extends UnitTestCase
{
    private LegacyOverlayDeduplicator $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new LegacyOverlayDeduplicator();
    }

    #[Test]
    public function distinctParentsAndLanguagesAllWin(): void
    {
        $rows = [
            ['uid' => 1, 'cat_uid' => 10, 'sys_language_uid' => 1, 'hidden' => 0, 'deleted' => 0],
            ['uid' => 2, 'cat_uid' => 10, 'sys_language_uid' => 2, 'hidden' => 0, 'deleted' => 0],
            ['uid' => 3, 'cat_uid' => 11, 'sys_language_uid' => 1, 'hidden' => 0, 'deleted' => 0],
        ];

        $result = $this->subject->deduplicate($rows, 'cat_uid');

        self::assertCount(3, $result['winners']);
        self::assertSame([], $result['losers']);
    }

    #[Test]
    public function deletedRowsAreExcludedEntirely(): void
    {
        $rows = [
            ['uid' => 1, 'cat_uid' => 10, 'sys_language_uid' => 1, 'hidden' => 0, 'deleted' => 1],
        ];

        $result = $this->subject->deduplicate($rows, 'cat_uid');

        self::assertSame([], $result['winners']);
        self::assertSame([], $result['losers']);
    }

    #[Test]
    public function visibleCandidateWinsOverHiddenRegardlessOfUid(): void
    {
        $rows = [
            ['uid' => 5, 'cat_uid' => 10, 'sys_language_uid' => 1, 'hidden' => 0, 'deleted' => 0],
            ['uid' => 9, 'cat_uid' => 10, 'sys_language_uid' => 1, 'hidden' => 1, 'deleted' => 0],
        ];

        $result = $this->subject->deduplicate($rows, 'cat_uid');

        self::assertCount(1, $result['winners']);
        self::assertSame(5, $result['winners'][0]['uid']);
        self::assertCount(1, $result['losers']);
        self::assertSame(9, $result['losers'][0]['uid']);
    }

    #[Test]
    public function highestUidWinsAmongEquallyVisibleCandidates(): void
    {
        $rows = [
            ['uid' => 3, 'cat_uid' => 10, 'sys_language_uid' => 1, 'hidden' => 0, 'deleted' => 0],
            ['uid' => 7, 'cat_uid' => 10, 'sys_language_uid' => 1, 'hidden' => 0, 'deleted' => 0],
            ['uid' => 4, 'cat_uid' => 10, 'sys_language_uid' => 1, 'hidden' => 0, 'deleted' => 0],
        ];

        $result = $this->subject->deduplicate($rows, 'cat_uid');

        self::assertCount(1, $result['winners']);
        self::assertSame(7, $result['winners'][0]['uid']);
        self::assertCount(2, $result['losers']);
    }

    #[Test]
    public function highestUidHiddenRowWinsWhenAllCandidatesAreHidden(): void
    {
        $rows = [
            ['uid' => 3, 'cat_uid' => 10, 'sys_language_uid' => 1, 'hidden' => 1, 'deleted' => 0],
            ['uid' => 8, 'cat_uid' => 10, 'sys_language_uid' => 1, 'hidden' => 1, 'deleted' => 0],
        ];

        $result = $this->subject->deduplicate($rows, 'cat_uid');

        self::assertCount(1, $result['winners']);
        self::assertSame(8, $result['winners'][0]['uid']);
        self::assertSame(1, $result['winners'][0]['hidden']);
    }
}
