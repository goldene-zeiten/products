<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Guide;

use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use TYPO3\CMS\Core\Settings\SettingsInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * Renders a guide template in the language of the request. The templates and partials come from the site
 * settings "products.mcp.templateRootPaths" and "products.mcp.partialRootPaths", so a site replaces a guide by
 * adding a root path with a template of the same name.
 *
 * Every template gets the variables "locale" (pass it as languageKey to f:translate) and "shopName" next to
 * the ones the caller assigns.
 */
final readonly class GuideRenderer
{
    private const DEFAULT_TEMPLATE_ROOT_PATHS = ['EXT:products_mcp/Resources/Private/Templates/'];
    private const DEFAULT_PARTIAL_ROOT_PATHS = ['EXT:products_mcp/Resources/Private/Partials/'];

    public function __construct(
        private ViewFactoryInterface $viewFactory
    ) {}

    /**
     * @param string $template path below the template root path, without file extension
     * @param array<string, mixed> $variables
     */
    public function render(string $template, array $variables, McpContext $context): string
    {
        $settings = $context->site->getSettings();
        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: $this->paths($settings, 'products.mcp.templateRootPaths', self::DEFAULT_TEMPLATE_ROOT_PATHS),
            partialRootPaths: $this->paths($settings, 'products.mcp.partialRootPaths', self::DEFAULT_PARTIAL_ROOT_PATHS),
            request: $context->request,
            format: 'md',
        ));
        $view->assignMultiple([
            ...$variables,
            'locale' => $this->locale($context),
            'shopName' => $this->shopName($context),
        ]);

        return $view->render($template);
    }

    public function shopName(McpContext $context): string
    {
        $title = (string)($context->site->getConfiguration()['websiteTitle'] ?? '');

        return $title !== '' ? $title : $context->site->getIdentifier();
    }

    /**
     * The locale of the language the request was resolved to, the default language of the site otherwise. A
     * string such as "de-DE" falls back to "de" when translating.
     */
    public function locale(McpContext $context): string
    {
        $language = $context->request->getAttribute('language');
        if (!$language instanceof SiteLanguage) {
            $language = $context->site->getDefaultLanguage();
        }

        return (string)$language->getLocale();
    }

    /**
     * @param array<string> $default
     * @return array<string>
     */
    private function paths(SettingsInterface $settings, string $path, array $default): array
    {
        $configured = $settings->has($path) ? $settings->get($path) : null;
        if (!is_array($configured) || $configured === []) {
            return $default;
        }

        return array_values(array_map(strval(...), $configured));
    }
}
