<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Fixtures;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * Test double for {@see ConfigurationManagerInterface} that returns a caller-chosen fixed
 * configuration array - used where a test needs to construct its subject with a specific
 * setting (e.g. pricing.discountFieldMode) without the real DI-wired configuration manager's
 * eager Extbase settings resolution getting in the way.
 */
final class FixtureConfigurationManager implements ConfigurationManagerInterface
{
    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(
        private readonly array $configuration
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getConfiguration(string $configurationType, ?string $extensionName = null, ?string $pluginName = null): array
    {
        return $this->configuration;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public function setConfiguration(array $configuration = []): void {}

    public function setRequest(ServerRequestInterface $request): void {}
}
