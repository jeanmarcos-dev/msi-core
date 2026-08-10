<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Model\System\Message;

use Magento\Framework\Notification\MessageInterface;
use Magento\InventoryCatalog\Model\ResourceModel\LegacyStockWriteLog;
use Magento\InventoryCatalog\Model\System\Message\LegacyStockWriteDetected;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LegacyStockWriteDetectedTest extends TestCase
{
    /**
     * @var LegacyStockWriteLog|MockObject
     */
    private $legacyStockWriteLog;

    /**
     * @var LegacyStockWriteDetected
     */
    private $message;

    protected function setUp(): void
    {
        $this->legacyStockWriteLog = $this->createMock(LegacyStockWriteLog::class);
        $this->message = new LegacyStockWriteDetected($this->legacyStockWriteLog);
    }

    public function testIsNotDisplayedWhenNothingWasDetected(): void
    {
        $this->legacyStockWriteLog->method('getDetections')->willReturn([]);

        self::assertFalse($this->message->isDisplayed());
    }

    public function testIsDisplayedWhenAWriteWasDetected(): void
    {
        $this->legacyStockWriteLog->method('getDetections')->willReturn([$this->detection('update', 3)]);

        self::assertTrue($this->message->isDisplayed());
    }

    public function testTextNamesTheTableAndTheReplacementApi(): void
    {
        $this->legacyStockWriteLog->method('getDetections')->willReturn([$this->detection('update', 3)]);

        $text = (string)$this->message->getText();

        self::assertStringContainsString('3 write(s)', $text);
        self::assertStringContainsString('cataloginventory_stock_item', $text);
        self::assertStringContainsString('SourceItemsSaveInterface', $text);
    }

    public function testTextSumsEveryDetection(): void
    {
        $this->legacyStockWriteLog->method('getDetections')->willReturn(
            [$this->detection('update', 3), $this->detection('insert', 4)]
        );

        self::assertStringContainsString('7 write(s)', (string)$this->message->getText());
    }

    public function testIdentityChangesWhenNewWritesArrive(): void
    {
        $this->legacyStockWriteLog->method('getDetections')->willReturn([$this->detection('update', 3)]);
        $first = $this->message->getIdentity();

        $otherLog = $this->createMock(LegacyStockWriteLog::class);
        $otherLog->method('getDetections')->willReturn([$this->detection('update', 4)]);

        self::assertNotSame($first, (new LegacyStockWriteDetected($otherLog))->getIdentity());
    }

    public function testSeverityIsMajor(): void
    {
        self::assertSame(MessageInterface::SEVERITY_MAJOR, $this->message->getSeverity());
    }

    public function testABrokenLogNeverBreaksTheAdmin(): void
    {
        $this->legacyStockWriteLog->method('getDetections')->willThrowException(new RuntimeException('no table'));

        self::assertFalse($this->message->isDisplayed());
    }

    public function testTheLogIsQueriedOncePerRequest(): void
    {
        $this->legacyStockWriteLog->expects(self::once())->method('getDetections')->willReturn([]);

        $this->message->isDisplayed();
        $this->message->getIdentity();
        $this->message->isDisplayed();
    }

    /**
     * Build a detection row.
     *
     * @param string $operation
     * @param int $writeCount
     * @return array
     */
    private function detection(string $operation, int $writeCount): array
    {
        return [
            'table_name' => 'cataloginventory_stock_item',
            'operation' => $operation,
            'write_count' => (string)$writeCount,
            'sample_product_id' => '15',
            'first_detected_at' => '2026-08-09 10:00:00',
            'last_detected_at' => '2026-08-09 11:00:00',
        ];
    }
}
