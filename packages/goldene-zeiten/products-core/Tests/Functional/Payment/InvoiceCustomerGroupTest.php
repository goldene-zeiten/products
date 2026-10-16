<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Payment\InvoicePaymentMethod;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * A shop may grant invoice to regular customers only, i.e. to members of chosen frontend user groups.
 */
final class InvoiceCustomerGroupTest extends AbstractFunctionalTestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    /**
     * @param int[] $customerGroups
     */
    #[Test]
    #[DataProvider('customerProvider')]
    public function invoiceIsOfferedToTheChosenCustomerGroups(string $allowedGroups, array $customerGroups, bool $expectedAvailable): void
    {
        $site = new Site('products', 1, ['settings' => ['products' => ['payment' => ['invoice' => ['frontendUserGroups' => $allowedGroups]]]]]);
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))->withAttribute('site', $site);
        if ($customerGroups !== []) {
            $frontendUser = new FrontendUserAuthentication();
            $frontendUser->user = ['uid' => 7];
            $this->get(Context::class)->setAspect('frontend.user', new UserAspect($frontendUser, $customerGroups));
        }
        $context = new PaymentContext(Money::fromCents(10000), 'EUR', 'DE', $customerGroups !== [] ? 7 : 0);

        $this->assertSame($expectedAvailable, $this->get(InvoicePaymentMethod::class)->isAvailable($context));
    }

    public static function customerProvider(): \Generator
    {
        yield 'no restriction' => [
            'allowedGroups' => '',
            'customerGroups' => [],
            'expectedAvailable' => true,
        ];
        yield 'a guest when restricted' => [
            'allowedGroups' => '3',
            'customerGroups' => [],
            'expectedAvailable' => false,
        ];
        yield 'a customer of another group' => [
            'allowedGroups' => '3, 4',
            'customerGroups' => [1, 2],
            'expectedAvailable' => false,
        ];
        yield 'a regular customer' => [
            'allowedGroups' => '3, 4',
            'customerGroups' => [1, 4],
            'expectedAvailable' => true,
        ];
    }
}
