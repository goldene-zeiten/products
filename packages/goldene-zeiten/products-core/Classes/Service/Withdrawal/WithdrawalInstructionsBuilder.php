<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Withdrawal;

use GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal\TraderIdentity;
use GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal\WithdrawalInstructions;
use GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal\WithdrawalModelForm;
use GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal\WithdrawalSettings;
use GoldeneZeiten\Products\Core\Domain\Enum\WithdrawalExclusion;
use GoldeneZeiten\Products\Core\Domain\Enum\WithdrawalInstructionVariant;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Builds the model instructions on withdrawal and the model withdrawal form (Annex I(A) and I(B) of
 * Directive 2011/83/EU) from the official wording in the language files, with the trader data of the
 * site filled in. Used wherever the consumer has to be informed: the withdrawal page, the terms page
 * content element and the order confirmation mail.
 */
final class WithdrawalInstructionsBuilder
{
    private const LANGUAGE_FILE = 'LLL:EXT:products_core/Resources/Private/Language/locallang_legal.xlf:';

    /**
     * @param string $functionUrl address of the online withdrawal function; empty if there is none
     * @param string|null $languageKey language of the texts; null for the current language
     */
    public function build(WithdrawalSettings $settings, WithdrawalInstructionVariant $variant, string $functionUrl = '', ?string $languageKey = null): WithdrawalInstructions
    {
        $trader = $settings->trader;
        $isGoods = $variant->isGoods();
        return new WithdrawalInstructions(
            heading: $this->translate('instructions.heading', [], $languageKey),
            rightHeading: $this->translate('instructions.right_heading', [], $languageKey),
            right: $this->translate('instructions.right', [], $languageKey),
            period: $this->translate('instructions.period.' . $variant->value, [], $languageKey),
            exercise: $this->translate('instructions.exercise', [$this->traderLine($trader, true, $languageKey)], $languageKey),
            onlineFunction: $functionUrl !== '' ? $this->translate('instructions.online_function', [$functionUrl], $languageKey) : null,
            onlineForm: $functionUrl !== '' ? $this->translate('instructions.online_form', [$functionUrl], $languageKey) : null,
            deadline: $this->translate('instructions.deadline', [], $languageKey),
            effectsHeading: $this->translate('instructions.effects_heading', [], $languageKey),
            effects: $this->translate('instructions.effects', [], $languageKey),
            withholding: $isGoods ? $this->translate('instructions.withholding', [], $languageKey) : null,
            returnInstruction: $isGoods ? $this->translate('instructions.return', [$this->returnAddressLine($trader)], $languageKey) : null,
            returnCost: $isGoods ? $this->translate($settings->returnCostBorneByTrader ? 'instructions.return_cost_trader' : 'instructions.return_cost_customer', [], $languageKey) : null,
            diminishedValue: $isGoods ? $this->translate('instructions.diminished_value', [], $languageKey) : null,
            exceptionsHeading: $this->translate('instructions.exceptions_heading', [], $languageKey),
            exceptions: $this->translate('instructions.exceptions', [], $languageKey),
            form: $this->buildForm($trader, $languageKey),
        );
    }

    /**
     * The start of the period to state for an order: its goods when it has any (several goods in one
     * order may be delivered separately, so the last one counts), else the conclusion of the contract.
     */
    public function variantFor(Order $order): WithdrawalInstructionVariant
    {
        $goods = 0;
        $digital = 0;
        foreach ($order->getItems()->toArray() as $item) {
            $item->getWithdrawalExclusion() === WithdrawalExclusion::DIGITAL_CONTENT ? $digital++ : $goods++;
        }
        if ($goods === 0 && $digital > 0) {
            return WithdrawalInstructionVariant::DIGITAL_CONTENT;
        }
        return $goods > 1 ? WithdrawalInstructionVariant::MULTIPLE_GOODS : WithdrawalInstructionVariant::GOODS;
    }

    private function buildForm(TraderIdentity $trader, ?string $languageKey): WithdrawalModelForm
    {
        return new WithdrawalModelForm(
            heading: $this->translate('form.heading', [], $languageKey),
            lead: $this->translate('form.lead', [], $languageKey),
            to: $this->translate('form.to', [$this->traderLine($trader, false, $languageKey)], $languageKey),
            notice: $this->translate('form.notice', [], $languageKey),
            ordered: $this->translate('form.ordered', [], $languageKey),
            name: $this->translate('form.name', [], $languageKey),
            address: $this->translate('form.address', [], $languageKey),
            signature: $this->translate('form.signature', [], $languageKey),
            date: $this->translate('form.date', [], $languageKey),
            delete: $this->translate('form.delete', [], $languageKey),
        );
    }

    /**
     * Name, geographical address, and where available telephone number (not on the model form, which
     * asks for the address and email only) and email address.
     */
    private function traderLine(TraderIdentity $trader, bool $withPhone, ?string $languageKey): string
    {
        $parts = array_filter([$trader->name, implode(', ', $trader->getAddressLines())]);
        if ($withPhone && $trader->phone !== '') {
            $parts[] = $this->translate('trader.phone', [$trader->phone], $languageKey);
        }
        if ($trader->email !== '') {
            $parts[] = $this->translate('trader.email', [$trader->email], $languageKey);
        }
        return $parts === [] ? $this->translate('trader.missing', [], $languageKey) : implode(', ', $parts);
    }

    private function returnAddressLine(TraderIdentity $trader): string
    {
        $parts = array_filter([$trader->name, implode(', ', $trader->getReturnAddressLines())]);
        return implode(', ', $parts);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function translate(string $key, array $arguments, ?string $languageKey): string
    {
        return (string)LocalizationUtility::translate(self::LANGUAGE_FILE . 'legal.' . $key, 'ProductsCore', $arguments === [] ? null : $arguments, $languageKey);
    }
}
