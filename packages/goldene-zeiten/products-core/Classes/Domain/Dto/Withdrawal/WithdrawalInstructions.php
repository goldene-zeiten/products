<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The model instructions on withdrawal of Annex I(A) of Directive 2011/83/EU, ready to render: every
 * paragraph is final text in the language it was built for. The goods paragraphs (withholding, return,
 * return cost, diminished value) and the online-function paragraphs are null where they do not apply.
 */
#[Exclude]
final readonly class WithdrawalInstructions
{
    public function __construct(
        public string $heading,
        public string $rightHeading,
        public string $right,
        public string $period,
        public string $exercise,
        public ?string $onlineFunction,
        public ?string $onlineForm,
        public string $deadline,
        public string $effectsHeading,
        public string $effects,
        public ?string $withholding,
        public ?string $returnInstruction,
        public ?string $returnCost,
        public ?string $diminishedValue,
        public string $exceptionsHeading,
        public string $exceptions,
        public WithdrawalModelForm $form,
    ) {}
}
