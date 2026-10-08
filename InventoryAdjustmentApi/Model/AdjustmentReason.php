<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentApi\Model;

enum AdjustmentReason: string
{
    case Correction = 'correction';
    case Count = 'count';
    case Received = 'received';
    case ReturnRestock = 'return_restock';
    case Damaged = 'damaged';
    case TheftOrLoss = 'theft_or_loss';
    case PromotionOrDonation = 'promotion_or_donation';
    case Shipment = 'shipment';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case TransitLoss = 'transit_loss';
    case IncomingDeclared = 'incoming_declared';
    case StateMove = 'state_move';
    case Import = 'import';
    case LegacyBridge = 'legacy_bridge';
    case Other = 'other';
}
