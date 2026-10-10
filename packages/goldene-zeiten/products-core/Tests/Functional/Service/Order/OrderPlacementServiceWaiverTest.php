<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutChoices;
use GoldeneZeiten\Products\Core\Domain\Enum\WithdrawalExclusion;
use GoldeneZeiten\Products\Core\Domain\Model\OrderItem;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Payment\Exception\PaymentMethodNotAvailableException;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\Order\Exception\IncompleteAddressException;
use GoldeneZeiten\Products\Core\Service\Order\Exception\WithdrawalWaiverNotAcceptedException;
use GoldeneZeiten\Products\Core\Service\Order\OrderPlacementService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Server-side enforcement of the Art. 16(m) digital-content waiver: the review checkbox is a
 * browser detail - an order containing digital content must not come into existence without the
 * express consent, and the consent must be recorded on the order when given.
 */
final class OrderPlacementServiceWaiverTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderPlacementServiceWaiverTest/digital_and_ordinary_products.csv');
    }

    #[Test]
    public function placeRejectsADigitalBasketWithoutTheWaiverAndCreatesNoOrder(): void
    {
        $request = $this->requestFor();
        $this->get(BasketService::class)->add($request, 1, null, 1);

        try {
            $this->get(OrderPlacementService::class)->place($request, $this->address(), 'invoice', new CheckoutChoices(termsAccepted: true));
            $this->fail('Expected WithdrawalWaiverNotAcceptedException was not thrown.');
        } catch (WithdrawalWaiverNotAcceptedException $exception) {
            $this->assertSame(1784937600, $exception->getCode());
        }

        $this->assertSame(0, $this->get(OrderRepository::class)->findAll()->count());
    }

    #[Test]
    public function placeRecordsTheWaiverAndTheItemExemptionForADigitalBasket(): void
    {
        $request = $this->requestFor();
        $this->get(BasketService::class)->add($request, 1, null, 1);

        $order = $this->get(OrderPlacementService::class)
            ->place($request, $this->address(), 'invoice', new CheckoutChoices(termsAccepted: true, withdrawalWaiverAccepted: true))
            ->getOrder();

        $this->assertNotNull($order->getWithdrawalWaiverAcceptedAt());
        $items = $order->getItems()->toArray();
        $this->assertCount(1, $items);
        $this->assertInstanceOf(OrderItem::class, $items[0]);
        $this->assertSame(WithdrawalExclusion::DIGITAL_CONTENT, $items[0]->getWithdrawalExclusion());
    }

    #[Test]
    public function theWaiverDoesNotExemptADigitalBasketFromTheAddressCheck(): void
    {
        $request = $this->requestFor();
        $this->get(BasketService::class)->add($request, 1, null, 1);

        try {
            $this->get(OrderPlacementService::class)->place($request, new Address(email: 'buyer@example.com', country: 'DE'), 'invoice', new CheckoutChoices(termsAccepted: true, withdrawalWaiverAccepted: true));
            $this->fail('Expected IncompleteAddressException was not thrown.');
        } catch (IncompleteAddressException $exception) {
            $this->assertSame(1791663303, $exception->getCode());
        }

        $this->assertSame(0, $this->get(OrderRepository::class)->findAll()->count());
    }

    #[Test]
    public function theWaiverDoesNotExemptADigitalBasketFromThePaymentCheck(): void
    {
        $request = $this->requestFor();
        $this->get(BasketService::class)->add($request, 1, null, 1);

        try {
            $this->get(OrderPlacementService::class)->place($request, $this->address(), 'no-such-method', new CheckoutChoices(termsAccepted: true, withdrawalWaiverAccepted: true));
            $this->fail('Expected PaymentMethodNotAvailableException was not thrown.');
        } catch (PaymentMethodNotAvailableException $exception) {
            $this->assertSame(1791662739, $exception->getCode());
        }

        $this->assertSame(0, $this->get(OrderRepository::class)->findAll()->count());
    }

    #[Test]
    public function placeIgnoresTheWaiverFlagForAnOrdinaryBasket(): void
    {
        $request = $this->requestFor();
        $this->get(BasketService::class)->add($request, 2, null, 1);

        $order = $this->get(OrderPlacementService::class)
            ->place($request, $this->address(), 'invoice', new CheckoutChoices(termsAccepted: true, withdrawalWaiverAccepted: true))
            ->getOrder();

        $this->assertNull($order->getWithdrawalWaiverAcceptedAt());
        $items = $order->getItems()->toArray();
        $this->assertInstanceOf(OrderItem::class, $items[0]);
        $this->assertSame(WithdrawalExclusion::NONE, $items[0]->getWithdrawalExclusion());
    }

    private function requestFor(): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        $frontendUser->user = ['uid' => 0];
        $site = new Site('products', 1, ['settings' => ['products' => []]]);
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('site', $site);
    }

    private function address(): Address
    {
        return new Address(email: 'buyer@example.com', firstName: 'Jane', lastName: 'Doe', street: 'Main Street 1', zip: '10115', city: 'Berlin', country: 'DE');
    }
}
