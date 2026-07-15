<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Domain\Repository;

use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Repository\ArticleRepository;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ArticleRepositoryTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function findAllFlatReturnsAllArticles(): void
    {
        $articleRepository = $this->get(ArticleRepository::class);
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(self::sharedFixture('shop.csv'));

        $articles = $articleRepository->findAllFlat();
        $this->assertCount(3, $articles);

        $titles = array_map(static fn(Article $a): string => $a->getTitle(), $articles);
        $this->assertContains('Article 1', $titles);
        $this->assertContains('Article 2', $titles);
        $this->assertContains('Article 3', $titles);
    }

    #[Test]
    public function findAllFlatReturnsEmptyWhenNoArticles(): void
    {
        $articleRepository = $this->get(ArticleRepository::class);
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));

        $articles = $articleRepository->findAllFlat();
        $this->assertCount(0, $articles);
    }
}
