<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Console\Command;

use Magento\Framework\DataObject\IdentityGeneratorInterface;
use Magento\InventoryAdjustment\Model\OpeningBalance;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class OpeningBalanceCommand extends Command
{
    private const OPTION_BATCH_SIZE = 'batch-size';
    private const DEFAULT_BATCH_SIZE = 1000;

    /**
     * @param OpeningBalance $openingBalance
     * @param IdentityGeneratorInterface $identityGenerator
     * @param string|null $name
     */
    public function __construct(
        private readonly OpeningBalance $openingBalance,
        private readonly IdentityGeneratorInterface $identityGenerator,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('inventory:adjustment:opening-balance');
        $this->setDescription('Write an opening history row for every source item with stock and no history.');
        $this->addOption(
            self::OPTION_BATCH_SIZE,
            null,
            InputOption::VALUE_REQUIRED,
            'Number of source items locked and written per transaction.',
            (string)self::DEFAULT_BATCH_SIZE
        );
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $batchSize = (int)$input->getOption(self::OPTION_BATCH_SIZE);
        if ($batchSize < 1) {
            $output->writeln('<error>--batch-size must be a whole number of at least 1.</error>');
            return Command::INVALID;
        }

        $requestId = $this->identityGenerator->generateId();
        $written = $this->openingBalance->write($requestId, $batchSize);
        $output->writeln(sprintf('Wrote %d opening balance row(s) with request id %s.', $written, $requestId));

        return Command::SUCCESS;
    }
}
