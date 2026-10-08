<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Console\Command;

use Magento\InventoryAdjustment\Model\ResourceModel\GetAdjustmentDrift;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class VerifyAdjustmentsCommand extends Command
{
    private const OPTION_LIMIT = 'limit';
    private const DEFAULT_LIMIT = 1000;

    /**
     * @param GetAdjustmentDrift $getAdjustmentDrift
     * @param string|null $name
     */
    public function __construct(
        private readonly GetAdjustmentDrift $getAdjustmentDrift,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('inventory:adjustment:verify');
        $this->setDescription('Report source items whose quantity no longer matches their adjustment history.');
        $this->addOption(
            self::OPTION_LIMIT,
            null,
            InputOption::VALUE_REQUIRED,
            'Maximum number of source items to report.',
            (string)self::DEFAULT_LIMIT
        );
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $drift = $this->getAdjustmentDrift->execute(max(1, (int)$input->getOption(self::OPTION_LIMIT)));
        if ($drift === []) {
            $output->writeln('No drift: every source item matches its last adjustment.');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Source', 'SKU', 'Last adjustment', 'Quantity']);
        foreach ($drift as $row) {
            $table->addRow([$row['source_code'], $row['sku'], $row['quantity_after'], $row['quantity']]);
        }
        $table->render();
        $output->writeln(sprintf(
            '<error>%d source item(s) changed without an adjustment row.</error>',
            count($drift)
        ));

        return Command::FAILURE;
    }
}
