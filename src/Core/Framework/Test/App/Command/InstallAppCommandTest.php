<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Command\AppPrinter;
use Shopware\Core\Framework\App\Command\InstallAppCommand;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\App\Validation\ManifestValidator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\App\StorefrontPluginRegistryTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class InstallAppCommandTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPluginRegistryTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    public function setUp(): void
    {
        $this->appRepository = $this->getContainer()->get('app.repository');
    }

    public function testInstallWithoutPermissions(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->setInputs(['yes']);

        $commandTester->execute(['name' => 'withoutPermissions']);

        static::assertEquals(Command::SUCCESS, $commandTester->getStatusCode());

        static::assertStringContainsString('[OK] App withoutPermissions has been successfully installed.', $commandTester->getDisplay());
    }

    public function testInstallWithForce(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));

        $commandTester->execute(['name' => 'withPermissions', '-f' => true]);

        static::assertEquals(Command::SUCCESS, $commandTester->getStatusCode());

        static::assertStringContainsString('[OK] App withPermissions has been successfully installed.', $commandTester->getDisplay());
    }

    public function testInstallWithPermissions(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->setInputs(['yes']);

        $commandTester->execute(['name' => 'withPermissions']);

        static::assertEquals(0, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        // header permissions
        static::assertMatchesRegularExpression('/.*Resource\s+Privileges\s+\n.*/', $display);
        // content permissions
        static::assertMatchesRegularExpression('/.*product\s+write, delete\s+\n.*/', $display);
        static::assertMatchesRegularExpression('/.*category\s+write\s+\n.*/', $display);
        static::assertMatchesRegularExpression('/.*order\s+read\s+\n.*/', $display);

        static::assertStringContainsString('[OK] App withPermissions has been successfully installed.', $display);
    }

    public function testInstallWithPermissionsCancel(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->setInputs(['no']);

        $commandTester->execute(['name' => 'withPermissions']);

        static::assertEquals(1, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        // header permissions
        static::assertMatchesRegularExpression('/.*Resource\s+Privileges\s+\n.*/', $display);
        // content permissions
        static::assertMatchesRegularExpression('/.*product\s+write, delete\s+\n.*/', $display);
        static::assertMatchesRegularExpression('/.*category\s+write\s+\n.*/', $display);
        static::assertMatchesRegularExpression('/.*order\s+read\s+\n.*/', $display);

        static::assertStringContainsString('Aborting due to user input.', $commandTester->getDisplay());
    }

    public function testInstallWithActivation(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->setInputs(['yes']);

        $commandTester->execute(['name' => 'withoutPermissions', '-a' => true]);

        static::assertEquals(0, $commandTester->getStatusCode());

        static::assertStringContainsString('[OK] App withoutPermissions has been successfully installed.', $commandTester->getDisplay());
    }

    public function testInstallWithNotFoundApp(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));

        $commandTester->execute(['name' => 'Test']);

        static::assertEquals(Command::SUCCESS, $commandTester->getStatusCode());

        static::assertStringContainsString('[INFO] Could not find any app with this name', $commandTester->getDisplay());
    }

    public function testInstallFailsIfAppIsAlreadyInstalled(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->setInputs(['yes']);

        $commandTester->execute(['name' => 'withoutPermissions']);
        static::assertEquals(Command::SUCCESS, $commandTester->getStatusCode());

        $commandTester->execute(['name' => 'withoutPermissions']);
        static::assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('[INFO] App withoutPermissions is already installed', $commandTester->getDisplay());
    }

    public function testInstallFailsIfAppHasValidations(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/../Manifest/_fixtures'));
        $commandTester->setInputs(['yes']);
        $commandTester->execute(['name' => 'invalidWebhooks']);

        static::assertEquals(1, $commandTester->getStatusCode());
        static::assertStringContainsString('App installation of invalidWebhooks failed due: ', $commandTester->getDisplay());
    }

    public function testInstallInvalidAppWithNoValidate(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/../Manifest/_fixtures'));
        $commandTester->setInputs(['yes']);
        $commandTester->execute(['name' => 'invalidWebhooks', '--no-validate' => true]);

        static::assertEquals(0, $commandTester->getStatusCode());
        static::assertStringContainsString('App invalidWebhooks has been successfully installed.', $commandTester->getDisplay());
    }

    public function testInstallMultipleAppsAtOnceForced(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->setInputs(['yes']);

        $commandTester->execute(['name' => ['withoutPermissions', 'withPermissions'], '-a' => true, '-f' => true]);

        static::assertEquals(Command::SUCCESS, $commandTester->getStatusCode());

        static::assertStringContainsString('[OK] App withoutPermissions has been successfully installed.', $commandTester->getDisplay());
        static::assertStringContainsString('[OK] App withPermissions has been successfully installed.', $commandTester->getDisplay());
    }

    private function createCommand(string $appFolder): InstallAppCommand
    {
        return new InstallAppCommand(
            new AppLoader($appFolder, '', $this->createMock(ConfigReader::class)),
            $this->getContainer()->get(AppLifecycle::class),
            new AppPrinter($this->appRepository),
            $this->getContainer()->get(ManifestValidator::class)
        );
    }
}
