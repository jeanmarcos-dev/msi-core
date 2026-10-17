<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Console\Command;

use Magento\InventorySales\Model\SourceReservation\ReconcileReservationsSweep;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reconcile terminal orders that still carry a residual reservation balance.
 * Supports a dry run so the residues can be reviewed before any compensation is
 * written.
 */
class ReconcileReservationsCommand extends Command
{
    private const OPTION_DRY_RUN = 'dry-run';
    private const OPTION_LIMIT = 'limit';
    private const DEFAULT_LIMIT = 500;

    /**
     * @param ReconcileReservationsSweep $reconcileReservationsSweep
     * @param string|null $name
     */
    public function __construct(
        private readonly ReconcileReservationsSweep $reconcileReservationsSweep,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('inventory:reservation:reconcile');
        $this->setDescription('Reconcile terminal orders whose reservations were never released.');
        $this->addOption(
            self::OPTION_DRY_RUN,
            null,
            InputOption::VALUE_NONE,
            'Report the residues without writing any compensation.'
        );
        $this->addOption(
            self::OPTION_LIMIT,
            null,
            InputOption::VALUE_REQUIRED,
            'Maximum number of orders to process.',
            (string)self::DEFAULT_LIMIT
        );
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool)$input->getOption(self::OPTION_DRY_RUN);
        $limit = max(1, (int)$input->getOption(self::OPTION_LIMIT));

        $result = $this->reconcileReservationsSweep->execute($limit, $dryRun);

        $output->writeln(sprintf(
            '%s %d order(s), %d abandoned placement(s), %d compensation(s)%s.',
            $dryRun ? 'Would reconcile' : 'Reconciled',
            $result['orders'],
            $result['abandoned'],
            $result['compensations'],
            $result['stock_ids'] ? ' across stock(s) ' . implode(', ', $result['stock_ids']) : ''
        ));
        if ($result['abandoned_pending'] > 0) {
            $output->writeln(sprintf(
                '<comment>%d abandoned placement(s) are too recent to repair; a later run will repair them.</comment>',
                $result['abandoned_pending']
            ));
        }
        if ($result['limit_reached']) {
            $output->writeln('<comment>Batch limit reached; run again to process the remaining residues.</comment>');
        }

        return Command::SUCCESS;
    }
}
