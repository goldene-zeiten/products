<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Personalization\EventListener;

use GoldeneZeiten\Products\Core\Event\ResolveLineItemOptionsEvent;
use GoldeneZeiten\Products\Personalization\Domain\Repository\OptionRepository;
use GoldeneZeiten\Products\Personalization\Service\Exception\InvalidOptionSelectionException;
use GoldeneZeiten\Products\Personalization\Service\OptionResolver;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Sells a basket line with the options defined on its product and article; a selection that breaks an
 * option's rules refuses the line with a message in the customer's language.
 */
#[AsEventListener('products-personalization/resolve-options')]
final class ResolvePersonalizationOptions
{
    public function __construct(
        private readonly OptionRepository $optionRepository,
        private readonly OptionResolver $optionResolver,
        private readonly LanguageServiceFactory $languageServiceFactory
    ) {}

    public function __invoke(ResolveLineItemOptionsEvent $event): void
    {
        $definitions = $this->optionRepository->findForLine((int)$event->getProduct()->getUid(), $event->getArticle()?->getUid());
        $selections = $event->getSelections();
        try {
            foreach ($definitions as $identifier => $definition) {
                $option = $this->optionResolver->resolve($definition, $selections[$identifier] ?? '');
                if ($option !== null) {
                    $event->addOption($option);
                }
            }
        } catch (InvalidOptionSelectionException $exception) {
            $event->reject($this->reason($exception, $event));
        }
    }

    private function reason(InvalidOptionSelectionException $exception, ResolveLineItemOptionsEvent $event): string
    {
        $language = $event->getRequest()->getAttribute('language');
        $languageService = $language instanceof SiteLanguage
            ? $this->languageServiceFactory->createFromSiteLanguage($language)
            : $this->languageServiceFactory->create('default');
        $label = match ($exception->getReasonKey()) {
            'required' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang.xlf:selection.required',
            'too_long' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang.xlf:selection.too_long',
            'invalid_characters' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang.xlf:selection.invalid_characters',
            default => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang.xlf:selection.unknown_choice',
        };
        return sprintf($languageService->sL($label), $exception->getOptionTitle());
    }
}
