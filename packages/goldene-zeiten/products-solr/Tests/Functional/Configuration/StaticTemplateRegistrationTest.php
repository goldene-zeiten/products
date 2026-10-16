<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Solr\Tests\Functional\Configuration;

use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Installations without site sets include the TypoScript through a sys_template record, whose
 * "Include static" selector stores the value registered here.
 */
final class StaticTemplateRegistrationTest extends AbstractFunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'reports',
        'scheduler',
        'tstemplate',
    ];

    protected array $testExtensionsToLoad = [
        'apache-solr-for-typo3/solr',
        'goldene-zeiten/products-solr',
    ];

    #[Test]
    public function staticTemplateIsSelectableUnderTheExtensionKey(): void
    {
        $values = array_column($GLOBALS['TCA']['sys_template']['columns']['include_static_file']['config']['items'], 'value');

        $this->assertContains('EXT:products_solr/Configuration/TypoScript', $values);
    }
}
