<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Personalization\Tests\Functional\EventListener;

use GoldeneZeiten\Products\Core\Domain\Enum\WithdrawalExclusion;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\Basket\Exception\LineItemOptionsRejectedException;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * A basket line is sold with the options defined on its product and article, and refused when a selection
 * breaks an option's rules.
 */
final class ResolvePersonalizationOptionsTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-personalization',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/options.csv');
    }

    #[Test]
    public function aLineIsSoldWithTheChosenOptionsAndTheirSurcharges(): void
    {
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        $basketService->add($request, 1, null, 1, ['size' => '64', 'engraving' => 'Anna & Ben', 'unknown' => 'x']);
        $item = $basketService->getBasketViewModel($request)->getItems()[0];

        $options = $item->getLineOptions();
        $this->assertCount(2, $options);
        $this->assertSame('Ring size', $options[0]->getLabel());
        $this->assertSame('64 (oversize)', $options[0]->getValueLabel());
        $this->assertSame(1500, $options[0]->getSurcharge()->getCents());
        $this->assertSame('Anna & Ben', $options[1]->getValueLabel());
        $this->assertSame(12500, $item->getUnitPriceGross()->getCents());
        $this->assertSame(WithdrawalExclusion::PERSONALIZED, $item->getWithdrawalExclusion());
    }

    #[Test]
    public function aSelectionThatPersonalizesNothingKeepsTheWithdrawalRight(): void
    {
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        $basketService->add($request, 1, null, 1, ['size' => '52']);
        $item = $basketService->getBasketViewModel($request)->getItems()[0];

        $this->assertCount(1, $item->getLineOptions());
        $this->assertSame(WithdrawalExclusion::NONE, $item->getWithdrawalExclusion());
    }

    #[Test]
    #[DataProvider('choiceProvider')]
    public function onlyAPersonalizingChoiceExcludesTheLineFromWithdrawal(string $font, WithdrawalExclusion $expectedExclusion): void
    {
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        $basketService->add($request, 1, null, 1, ['size' => '52', 'font' => $font]);
        $item = $basketService->getBasketViewModel($request)->getItems()[0];

        $this->assertCount(2, $item->getLineOptions());
        $this->assertSame($expectedExclusion, $item->getWithdrawalExclusion());
    }

    public static function choiceProvider(): \Generator
    {
        yield 'the choice for no engraving' => [
            'font' => 'none',
            'expectedExclusion' => WithdrawalExclusion::NONE,
        ];
        yield 'an engraving font' => [
            'font' => 'script',
            'expectedExclusion' => WithdrawalExclusion::PERSONALIZED,
        ];
    }

    /**
     * @param array<string, string> $selections
     */
    #[Test]
    #[DataProvider('refusedSelectionProvider')]
    public function aSelectionBreakingTheRulesIsRefused(array $selections, string $expectedMessage): void
    {
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        try {
            $basketService->add($request, 1, null, 1, $selections);
            $this->fail('The selection must be refused.');
        } catch (LineItemOptionsRejectedException $exception) {
            $this->assertSame(1792173011, $exception->getCode());
            $this->assertSame($expectedMessage, $exception->getMessage());
        }
        $this->assertSame([], $basketService->getBasketViewModel($request)->getItems());
    }

    public static function refusedSelectionProvider(): \Generator
    {
        yield 'required option missing' => [
            'selections' => ['engraving' => 'Anna'],
            'expectedMessage' => 'Please fill in "Ring size".',
        ];
        yield 'required option blank' => [
            'selections' => ['size' => '  '],
            'expectedMessage' => 'Please fill in "Ring size".',
        ];
        yield 'choice not on the list' => [
            'selections' => ['size' => '99'],
            'expectedMessage' => 'The choice for "Ring size" is not available. Please choose again.',
        ];
        yield 'text longer than allowed' => [
            'selections' => ['size' => '52', 'engraving' => 'Thirteen char'],
            'expectedMessage' => '"Engraving" is too long.',
        ];
        yield 'text with characters an engraver cannot cut' => [
            'selections' => ['size' => '52', 'engraving' => 'Anna <3'],
            'expectedMessage' => '"Engraving" contains characters that cannot be engraved. Please use letters, digits, spaces and . , - & \' ! ? + only.',
        ];
    }

    #[Test]
    public function aHiddenOptionIsNotOffered(): void
    {
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        $basketService->add($request, 1, null, 1, ['size' => '52', 'hidden' => 'x']);

        $this->assertCount(1, $basketService->getBasketViewModel($request)->getItems()[0]->getLineOptions());
    }

    #[Test]
    public function anArticleOptionReplacesTheProductOptionOfTheSameIdentifier(): void
    {
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        $basketService->add($request, 2, 21, 1, ['engraving' => 'A longer platinum engraving', 'finish' => 'polished']);
        $item = $basketService->getBasketViewModel($request)->getItems()[0];

        $options = $item->getLineOptions();
        $this->assertCount(2, $options);
        $this->assertSame(2500, $options[0]->getSurcharge()->getCents());
        $this->assertSame('Polished', $options[1]->getValueLabel());
        $this->assertSame(32500, $item->getUnitPriceGross()->getCents());
    }

    #[Test]
    public function aProductWithoutOptionsIsSoldAsBefore(): void
    {
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        $basketService->add($request, 3, null, 2, ['engraving' => 'Anna']);
        $item = $basketService->getBasketViewModel($request)->getItems()[0];

        $this->assertSame([], $item->getLineOptions());
        $this->assertSame(10000, $item->getLineTotalGross()->getCents());
    }

    private function anonymousSessionRequest(): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('frontend.user', $frontendUser);
    }
}
