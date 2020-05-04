<?php

declare(strict_types=1);

namespace Tests\Jorijn\Bl3pDca\Command;

use Jorijn\Bl3pDca\Command\BuyCommand;
use Jorijn\Bl3pDca\Exception\BuyTimeoutException;
use Jorijn\Bl3pDca\Model\CompletedBuyOrder;
use Jorijn\Bl3pDca\Service\BuyService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @coversDefaultClass \Jorijn\Bl3pDca\Command\BuyCommand
 * @covers ::__construct
 * @covers ::configure
 *
 * @internal
 */
final class BuyCommandTest extends TestCase
{
    public const AMOUNT = 'amount';
    public const COMMAND = 'command';
    /** @var BuyService|MockObject */
    private $buyService;
    /** @var BuyCommand */
    private BuyCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyService = $this->createMock(BuyService::class);
        $this->command = new BuyCommand($this->buyService);
    }

    public function providerOfTags(): array
    {
        return [
            'with tag' => ['tag'.random_int(1000, 2000)],
            'without tag' => [],
        ];
    }

    /**
     * @covers ::execute
     */
    public function testAmountIsNotNumeric(): void
    {
        $commandTester = $this->createCommandTester();
        $commandTester->execute([self::COMMAND => $this->command->getName(), self::AMOUNT => 'string'.random_int(1000, 2000)]);

        static::assertStringContainsString('Amount should be numeric, e.g. 10', $commandTester->getDisplay(true));
        static::assertSame(1, $commandTester->getStatusCode());
    }

    /**
     * @covers ::execute
     */
    public function testNotUnattendedAndNotConfirming(): void
    {
        $amount = random_int(1000, 2000);

        // not buying
        $this->buyService->expects(static::never())->method('buy');

        $commandTester = $this->createCommandTester();
        $commandTester->setInputs(['no']);
        $commandTester->execute([self::COMMAND => $this->command->getName(), self::AMOUNT => $amount]);

        static::assertSame(0, $commandTester->getStatusCode());
    }

    /**
     * @covers ::execute
     * @dataProvider providerOfTags
     */
    public function testNotUnattendedAndConfirmsBuy(string $tag = null): void
    {
        $amount = random_int(1000, 2000);

        $orderInformation = (new CompletedBuyOrder())
            ->setDisplayAmountBought(random_int(1000, 2000).' BTC')
            ->setDisplayAmountSpent(random_int(1000, 2000).' EUR')
            ->setDisplayAveragePrice(random_int(1000, 2000).' EUR')
            ->setDisplayFeesSpent('0.'.random_int(1000, 2000).' BTC')
        ;

        $invocationMocker = $this->buyService
            ->expects(static::once())
            ->method('buy')
            ->willReturn($orderInformation)
        ;

        if (!empty($tag)) {
            $invocationMocker->with($amount, $tag);
        } else {
            $invocationMocker->with($amount);
        }

        $commandTester = $this->createCommandTester();
        $commandTester->setInputs(['yes']);
        $commandTester->execute([
            self::COMMAND => $this->command->getName(),
            self::AMOUNT => $amount,
        ] + (!empty($tag) ? ['--tag' => $tag] : []));

        static::assertSame(0, $commandTester->getStatusCode());
        static::assertStringContainsString(sprintf(
            '[OK] Bought: %s, EUR: %s, price: %s, spent fees: %s',
            $orderInformation->getDisplayAmountBought(),
            $orderInformation->getDisplayAmountSpent(),
            $orderInformation->getDisplayAveragePrice(),
            $orderInformation->getDisplayFeesSpent(),
        ), $commandTester->getDisplay(true));
    }

    public function testUnattendedBuy(string $tag = null): void
    {
        $amount = random_int(1000, 2000);

        $orderInformation = (new CompletedBuyOrder())
            ->setDisplayAmountBought(random_int(1000, 2000).' BTC')
            ->setDisplayAmountSpent(random_int(1000, 2000).' EUR')
            ->setDisplayAveragePrice(random_int(1000, 2000).' EUR')
            ->setDisplayFeesSpent('0.'.random_int(1000, 2000).' BTC')
        ;

        $invocationMocker = $this->buyService
            ->expects(static::once())
            ->method('buy')
            ->willReturn($orderInformation)
        ;

        if (!empty($tag)) {
            $invocationMocker->with($amount, $tag);
        } else {
            $invocationMocker->with($amount);
        }

        $commandTester = $this->createCommandTester();
        $commandTester->execute([
            self::COMMAND => $this->command->getName(),
            self::AMOUNT => $amount,
            '--yes' => null,
        ] + (!empty($tag) ? ['--tag' => $tag] : []));

        static::assertSame(0, $commandTester->getStatusCode());
        static::assertStringContainsString(sprintf(
            '[OK] Bought: %s, EUR: %s, price: %s, spent fees: %s',
            $orderInformation->getDisplayAmountBought(),
            $orderInformation->getDisplayAmountSpent(),
            $orderInformation->getDisplayAveragePrice(),
            $orderInformation->getDisplayFeesSpent(),
        ), $commandTester->getDisplay(true));
    }

    /**
     * @covers ::execute
     */
    public function testBuyingFailsExceptionIsHandled(): void
    {
        $amount = random_int(1000, 2000);
        $exception = new BuyTimeoutException('error'.random_int(1000, 2000));

        $this->buyService
            ->expects(static::once())
            ->method('buy')
            ->with($amount)
            ->willThrowException($exception)
        ;

        $commandTester = $this->createCommandTester();
        $commandTester->execute([self::COMMAND => $this->command->getName(), self::AMOUNT => $amount, '--yes' => null]);

        static::assertSame(1, $commandTester->getStatusCode());
        static::assertStringContainsString('[ERROR] '.$exception->getMessage(), $commandTester->getDisplay(true));
    }

    protected function createCommandTester(): CommandTester
    {
        $application = new Application();
        $application->add($this->command->setName('buy'));

        return new CommandTester($this->command);
    }
}
