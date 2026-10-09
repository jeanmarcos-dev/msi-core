<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Model;

use Magento\Framework\Exception\InputException;
use Magento\InventoryAdjustment\Model\AdjustmentInput;
use Magento\InventoryAdjustment\Model\ExplicitAdjustmentResolver;
use PHPUnit\Framework\TestCase;

class ExplicitAdjustmentResolverTest extends TestCase
{
    public function testItemsWithoutAnAdjustmentGiveNothing(): void
    {
        self::assertNull((new ExplicitAdjustmentResolver())->resolve([null, null]));
    }

    public function testASharedAdjustmentBecomesTheMetadata(): void
    {
        $metadata = (new ExplicitAdjustmentResolver())->resolve([
            $this->input('count', 'stocktake', 'ST-9', 'cycle'),
            $this->input('count', 'stocktake', 'ST-9', 'cycle'),
        ]);

        self::assertSame('count', $metadata->getReason()->value);
        self::assertSame('stocktake', $metadata->getReferenceType());
        self::assertSame('ST-9', $metadata->getReferenceId());
        self::assertSame('cycle', $metadata->getNote());
    }

    public function testAReasonOutsideTheListIsRejectedWithTheValidOnes(): void
    {
        $this->expectException(InputException::class);
        $this->expectExceptionMessageMatches('/lost_in_war.*count/');

        (new ExplicitAdjustmentResolver())->resolve([$this->input('lost_in_war')]);
    }

    /**
     * @dataProvider salesDocumentTypes
     */
    public function testASalesDocumentReferenceIsRejected(string $referenceType): void
    {
        $this->expectException(InputException::class);
        $this->expectExceptionMessage($referenceType);

        (new ExplicitAdjustmentResolver())->resolve([$this->input('count', $referenceType, '000000042')]);
    }

    public static function salesDocumentTypes(): array
    {
        return [['order'], ['shipment'], ['invoice'], ['creditmemo']];
    }

    public function testAMissingReasonIsRejected(): void
    {
        $this->expectException(InputException::class);

        (new ExplicitAdjustmentResolver())->resolve([$this->input(null, 'stocktake')]);
    }

    public function testDifferentAdjustmentsInOneRequestAreRejected(): void
    {
        $this->expectException(InputException::class);

        (new ExplicitAdjustmentResolver())->resolve([$this->input('count'), $this->input('damaged')]);
    }

    public function testAnItemWithoutTheAdjustmentAmongOthersIsRejected(): void
    {
        $this->expectException(InputException::class);

        (new ExplicitAdjustmentResolver())->resolve([$this->input('count'), null]);
    }

    public function testValuesLongerThanTheirColumnAreRejected(): void
    {
        $tooLong = [
            $this->input('count', null, null, str_repeat('n', 256)),
            $this->input('count', str_repeat('t', 33)),
            $this->input('count', 'stocktake', str_repeat('i', 65)),
        ];
        foreach ($tooLong as $input) {
            try {
                (new ExplicitAdjustmentResolver())->resolve([$input]);
                self::fail('A value longer than its column was accepted');
            } catch (InputException) {
                self::assertTrue(true);
            }
        }
    }

    public function testValuesThatFillTheirColumnAreAccepted(): void
    {
        $metadata = (new ExplicitAdjustmentResolver())->resolve([
            $this->input('count', str_repeat('t', 32), str_repeat('i', 64), str_repeat('n', 255)),
        ]);

        self::assertSame(255, strlen($metadata->getNote()));
    }

    private function input(
        ?string $reason,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $note = null
    ): AdjustmentInput {
        $input = new AdjustmentInput();
        $input->setReason($reason);
        $input->setReferenceType($referenceType);
        $input->setReferenceId($referenceId);
        $input->setNote($note);
        return $input;
    }
}
