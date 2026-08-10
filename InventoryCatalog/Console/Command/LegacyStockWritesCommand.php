<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Console\Command;

use Magento\InventoryCatalog\Model\ResourceModel\LegacyStockWriteLog;
use Magento\InventoryCatalog\Model\ResourceModel\LegacyStockWriteTriggers;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Report writes made against the frozen CatalogInventory tables.
 *
 * Exits non-zero while any write is on record, so a pipeline can fail on an extension that still
 * believes it is setting stock through them.
 */
class LegacyStockWritesCommand extends Command
{
    private const OPTION_CLEAR = 'clear';
    private const OPTION_ENABLE = 'enable';
    private const OPTION_DISABLE = 'disable';

    /**
     * @param LegacyStockWriteLog $legacyStockWriteLog
     * @param LegacyStockWriteTriggers $legacyStockWriteTriggers
     * @param string|null $name
     */
    public function __construct(
        private readonly LegacyStockWriteLog $legacyStockWriteLog,
        private readonly LegacyStockWriteTriggers $legacyStockWriteTriggers,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('inventory:legacy-stock:writes');
        $this->setDescription('Report writes made against the frozen cataloginventory_* tables.');
        $this->addOption(self::OPTION_CLEAR, null, InputOption::VALUE_NONE, 'Forget every recorded write.');
        $this->addOption(self::OPTION_ENABLE, null, InputOption::VALUE_NONE, 'Install the detection triggers.');
        $this->addOption(self::OPTION_DISABLE, null, InputOption::VALUE_NONE, 'Drop the detection triggers.');
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption(self::OPTION_ENABLE) && $input->getOption(self::OPTION_DISABLE)) {
            $output->writeln('<error>--enable and --disable are mutually exclusive.</error>');

            return Command::FAILURE;
        }

        if ($input->getOption(self::OPTION_DISABLE)) {
            $this->legacyStockWriteTriggers->remove();
            $output->writeln('<info>Detection triggers dropped.</info>');

            return Command::SUCCESS;
        }

        if ($input->getOption(self::OPTION_ENABLE)) {
            $this->legacyStockWriteTriggers->install();
            $output->writeln('<info>Detection triggers installed.</info>');

            return Command::SUCCESS;
        }

        if ($input->getOption(self::OPTION_CLEAR)) {
            $this->legacyStockWriteLog->clear();
            $output->writeln('<info>Recorded writes cleared.</info>');

            return Command::SUCCESS;
        }

        return $this->report($output);
    }

    /**
     * Print the recorded writes.
     *
     * @param OutputInterface $output
     * @return int
     */
    private function report(OutputInterface $output): int
    {
        if (!$this->legacyStockWriteTriggers->areInstalled()) {
            $output->writeln(
                '<comment>Detection is not active: run this command with --enable to install the triggers.</comment>'
            );
        }

        $detections = $this->legacyStockWriteLog->getDetections();
        if ($detections === []) {
            $output->writeln('<info>No writes recorded against the frozen cataloginventory_* tables.</info>');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Table', 'Operation', 'Writes', 'Sample product id', 'First', 'Last']);
        foreach ($detections as $detection) {
            $table->addRow([
                $detection['table_name'],
                $detection['operation'],
                $detection['write_count'],
                $detection['sample_product_id'],
                $detection['first_detected_at'],
                $detection['last_detected_at'],
            ]);
        }
        $table->render();

        $output->writeln(
            '<error>Those writes changed no stock. Nothing reads the cataloginventory_* tables back.</error>'
        );
        $output->writeln(
            'Point the code that made them at SourceItemsSaveInterface, or at '
            . 'StockRegistryInterface::updateStockItemBySku() which MSI projects onto the default source item.'
        );

        return Command::FAILURE;
    }
}
