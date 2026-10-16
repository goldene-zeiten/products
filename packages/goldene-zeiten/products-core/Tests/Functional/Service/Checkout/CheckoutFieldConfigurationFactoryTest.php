<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Checkout;

use GoldeneZeiten\Products\Core\Domain\Enum\FieldRequirement;
use GoldeneZeiten\Products\Core\Service\Checkout\CheckoutFieldConfigurationFactory;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Site\SiteFinder;

final class CheckoutFieldConfigurationFactoryTest extends AbstractFrontendTestCase
{
    #[Test]
    public function defaultsKeepTheCheckoutLean(): void
    {
        $subject = $this->get(CheckoutFieldConfigurationFactory::class);

        $fields = $subject->fromSite($this->get(SiteFinder::class)->getSiteByIdentifier('products'));

        $this->assertSame(FieldRequirement::Optional, $fields->requirementOf('salutation'));
        $this->assertSame(FieldRequirement::Optional, $fields->requirementOf('company'));
        $this->assertSame(FieldRequirement::Optional, $fields->requirementOf('telephone'));
        $this->assertSame(FieldRequirement::Hidden, $fields->requirementOf('vatId'));
        $this->assertSame([], $fields->getAllowedCountries());
        $this->assertSame(['mr', 'ms', 'diverse'], $fields->getSalutations());
        $this->assertFalse($fields->isDesiredDeliveryDateEnabled());
    }

    #[Test]
    public function siteSettingsConfigureTheFields(): void
    {
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'dependencies' => $this->siteSetDependencies(),
                'settings' => [
                    'products' => [
                        'checkout' => [
                            'fields' => [
                                'telephone' => 'required',
                                'vatId' => 'optional',
                                'company' => 'nonsense',
                            ],
                            'allowedCountries' => 'de, fr ,it',
                            'salutations' => 'mr,ms,diverse,company',
                            'desiredDeliveryDate' => true,
                        ],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $subject = $this->get(CheckoutFieldConfigurationFactory::class);

        $fields = $subject->fromSite($this->get(SiteFinder::class)->getSiteByIdentifier('products'));

        $this->assertSame(FieldRequirement::Required, $fields->requirementOf('telephone'));
        $this->assertSame(FieldRequirement::Optional, $fields->requirementOf('vatId'));
        $this->assertSame(FieldRequirement::Optional, $fields->requirementOf('company'));
        $this->assertSame(['DE', 'FR', 'IT'], $fields->getAllowedCountries());
        $this->assertSame(['mr', 'ms', 'diverse', 'company'], $fields->getSalutations());
        $this->assertTrue($fields->isDesiredDeliveryDateEnabled());
    }
}
