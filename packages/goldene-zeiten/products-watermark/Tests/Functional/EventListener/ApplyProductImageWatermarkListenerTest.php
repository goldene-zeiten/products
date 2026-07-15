<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Watermark\Tests\Functional\EventListener;

use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\FileProcessingAspect;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Drives the listener through the real file-processing pipeline: an actual image is processed, which
 * dispatches {@see \TYPO3\CMS\Core\Resource\Event\AfterFileProcessingEvent} through the container and
 * runs the listener exactly as it would in the frontend.
 */
final class ApplyProductImageWatermarkListenerTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-watermark',
    ];

    private ResourceStorage $storage;
    private string $watermarkPath = '';

    protected function setUp(): void
    {
        parent::setUp();
        $storageRepository = $this->get(StorageRepository::class);
        $this->storage = $storageRepository->getStorageObject(
            $storageRepository->createLocalStorage('watermark test', 'fileadmin/', 'relative', '', true),
        );
        $this->watermarkPath = Environment::getPublicPath() . '/typo3temp/assets/watermark_test.png';
        GeneralUtility::mkdir_deep(dirname($this->watermarkPath));
        $this->createSolidImage($this->watermarkPath, 40, 40, [255, 0, 0]);
        // Image processing is deferred in the backend by default; force immediate generation so every
        // test can inspect the produced file, whichever application type it simulates.
        GeneralUtility::makeInstance(Context::class)->setAspect('fileProcessing', new FileProcessingAspect(false));
    }

    #[Test]
    public function aProductImageIsWatermarkedInTheFrontend(): void
    {
        $file = $this->indexImage('product_frontend.png');
        $this->referenceAsProductImage($file);
        $this->activateFrontendRequestWithWatermark(100);

        $pixel = $this->processAndReadPixel($file, 5, 5);

        $this->assertGreaterThan(200, $pixel[0]);
        $this->assertLessThan(60, $pixel[1]);
    }

    #[Test]
    public function anImageThatIsNotAProductImageStaysUntouched(): void
    {
        $file = $this->indexImage('banner.png');
        $this->activateFrontendRequestWithWatermark(100);

        $pixel = $this->processAndReadPixel($file, 5, 5);

        $this->assertGreaterThan(240, $pixel[0]);
        $this->assertGreaterThan(240, $pixel[1]);
        $this->assertGreaterThan(240, $pixel[2]);
    }

    #[Test]
    public function aBackendRequestLeavesTheImageUntouched(): void
    {
        $file = $this->indexImage('product_backend.png');
        $this->referenceAsProductImage($file);
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/typo3/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('site', $this->siteWithWatermark(100));

        $pixel = $this->processAndReadPixel($file, 5, 5);

        $this->assertGreaterThan(240, $pixel[0]);
        $this->assertGreaterThan(240, $pixel[1]);
    }

    #[Test]
    public function aCachedVariantIsNotWatermarkedAgain(): void
    {
        $file = $this->indexImage('product_cached.png');
        $this->referenceAsProductImage($file);
        $this->activateFrontendRequestWithWatermark(50);

        $firstPass = $this->processAndReadPixel($file, 5, 5);
        $secondPass = $this->processAndReadPixel($file, 5, 5);

        $this->assertSame($firstPass, $secondPass, 'Re-processing a cached variant must not stack the watermark.');
        $this->assertGreaterThan(90, $firstPass[1], 'A single 50% red-on-white blend keeps green well above a doubled blend.');
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function processAndReadPixel(File $file, int $x, int $y): array
    {
        $processed = $file->process(ProcessedFile::CONTEXT_IMAGECROPSCALEMASK, ['width' => 150, 'height' => 150]);

        return $this->pixel($processed->getForLocalProcessing(false), $x, $y);
    }

    private function indexImage(string $name): File
    {
        $path = Environment::getPublicPath() . '/fileadmin/' . $name;
        $this->createSolidImage($path, 200, 200, [255, 255, 255]);

        $file = $this->storage->getFile('/' . $name);
        \assert($file instanceof File);

        return $file;
    }

    private function referenceAsProductImage(File $file): void
    {
        $this->getConnectionPool()->getConnectionForTable('sys_file_reference')->insert(
            'sys_file_reference',
            [
                'pid' => 1,
                'uid_local' => $file->getUid(),
                'uid_foreign' => 1,
                'tablenames' => 'tx_products_domain_model_product',
                'fieldname' => 'images',
                'deleted' => 0,
            ],
            [
                'pid' => Connection::PARAM_INT,
                'uid_local' => Connection::PARAM_INT,
                'uid_foreign' => Connection::PARAM_INT,
                'tablenames' => Connection::PARAM_STR,
                'fieldname' => Connection::PARAM_STR,
                'deleted' => Connection::PARAM_INT,
            ],
        );
    }

    private function activateFrontendRequestWithWatermark(int $opacity): void
    {
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('site', $this->siteWithWatermark($opacity));
    }

    private function siteWithWatermark(int $opacity): Site
    {
        return new Site('products', 1, ['settings' => ['products' => ['watermark' => [
            'file' => $this->watermarkPath,
            'position' => 'top-left',
            'opacity' => $opacity,
            'scale' => 0,
            'minWidth' => 0,
            'margin' => 0,
        ]]]]);
    }

    /**
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private function createSolidImage(string $path, int $width, int $height, array $rgb): void
    {
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));
        \assert($image instanceof \GdImage);
        $color = imagecolorallocate($image, max(0, min(255, $rgb[0])), max(0, min(255, $rgb[1])), max(0, min(255, $rgb[2])));
        imagefilledrectangle($image, 0, 0, $width, $height, (int)$color);
        imagepng($image, $path);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function pixel(string $path, int $x, int $y): array
    {
        $image = imagecreatefrompng($path);
        \assert($image instanceof \GdImage);
        $rgba = imagecolorat($image, $x, $y);

        return [($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF];
    }
}
