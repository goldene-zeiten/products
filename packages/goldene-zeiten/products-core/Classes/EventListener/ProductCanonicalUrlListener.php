<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Seo\Event\ModifyUrlForCanonicalTagEvent;

/**
 * Sets the canonical URL of a product detail page, which EXT:seo would reduce to the bare page: the
 * product's URL, or the article's own URL for an article with texts of its own. EXT:seo builds the canonical
 * before the page content renders, so the product and article are read from the route arguments. Only
 * EXT:seo dispatches the event; without it this listener never runs.
 */
#[AsEventListener('products-core/product-canonical-url')]
final class ProductCanonicalUrlListener
{
    public function __construct(private readonly ConnectionPool $connectionPool) {}

    public function __invoke(ModifyUrlForCanonicalTagEvent $event): void
    {
        $site = $event->getRequest()->getAttribute('site');
        $routing = $event->getRequest()->getAttribute('routing');
        if (!$site instanceof Site || !$routing instanceof PageArguments) {
            return;
        }
        $arguments = $routing->getArguments()['tx_productscore_productdetail'] ?? [];
        $article = is_array($arguments) ? $this->article((int)($arguments['selectedArticle'] ?? 0)) : null;
        $productUid = is_array($arguments) ? (int)($arguments['product'] ?? 0) : 0;
        $productUid = $productUid > 0 ? $productUid : (int)($article['product'] ?? 0);
        if ($productUid === 0) {
            return;
        }
        $canonicalArguments = $article !== null && ($article['seo_title'] !== '' || $article['meta_description'] !== '')
            ? ['selectedArticle' => $article['uid']]
            : ['product' => $productUid];
        $url = $site->getRouter()->generateUri($routing->getPageId(), [
            'tx_productscore_productdetail' => [
                'action' => 'show',
                'controller' => 'Product',
                ...$canonicalArguments,
            ],
        ]);
        if ($url->getHost() === '') {
            $requestUri = $event->getRequest()->getUri();
            $url = $url->withScheme($requestUri->getScheme())->withHost($requestUri->getHost())->withPort($requestUri->getPort());
        }
        $event->setUrl((string)$url);
    }

    /**
     * @return array{uid: int, product: int, seo_title: string, meta_description: string}|null
     */
    private function article(int $articleUid): ?array
    {
        if ($articleUid <= 0) {
            return null;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_article');
        $row = $queryBuilder->select('uid', 'product', 'seo_title', 'meta_description')
            ->from('tx_products_domain_model_article')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($articleUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        if (!is_array($row)) {
            return null;
        }
        return [
            'uid' => (int)$row['uid'],
            'product' => (int)$row['product'],
            'seo_title' => trim((string)$row['seo_title']),
            'meta_description' => trim((string)$row['meta_description']),
        ];
    }
}
