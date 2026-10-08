<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentApi\Model;

enum ActorType: string
{
    case Admin = 'admin';
    case Integration = 'integration';
    case Import = 'import';
    case System = 'system';
    case Customer = 'customer';
}
