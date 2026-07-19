<?php

require_once __DIR__ . '/../config/Db.php';
require_once __DIR__ . '/PlayerModel.php';

class MarketModel
{
    public static function initRoomMarket($roomId, $countryId)
    {
        $pdo = Db::connection();
        $check = $pdo->prepare('SELECT COUNT(*) AS cnt FROM market_prices WHERE room_id = ?');
        $check->execute([$roomId]);
        if ((int) (($__row = $check->fetch()) && isset($__row['cnt']) ? $__row['cnt'] : 0) > 0) {
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT c.id, c.base_buy_price, c.base_sell_price
             FROM crops c
             WHERE c.country_id = ? AND c.is_active = 1'
        );
        $stmt->execute([$countryId]);
        $crops = $stmt->fetchAll();

        $insert = $pdo->prepare(
            'INSERT INTO market_prices (room_id, crop_id, buy_price, sell_price, supply, demand)
             VALUES (?, ?, ?, ?, 0, 100)'
        );
        foreach ($crops as $crop) {
            $insert->execute([
                $roomId,
                (int) $crop['id'],
                (float) $crop['base_buy_price'],
                (float) $crop['base_sell_price'],
            ]);
        }
    }

    public static function listPrices($roomId)
    {
        $stmt = Db::connection()->prepare(
            'SELECT mp.*, c.name_th, c.name_en
             FROM market_prices mp
             JOIN crops c ON c.id = mp.crop_id
             WHERE mp.room_id = ?
             ORDER BY c.name_th'
        );
        $stmt->execute([$roomId]);
        return array_map(function (array $row) {
            $modifier = self::priceModifier((int) $row['supply'], (int) $row['demand']);
            return [
                'crop_id' => (int) $row['crop_id'],
                'name_th' => $row['name_th'],
                'name_en' => $row['name_en'],
                'buy_price' => round((float) $row['buy_price'] * $modifier, 2),
                'sell_price' => round((float) $row['sell_price'] * $modifier, 2),
                'supply' => (int) $row['supply'],
                'demand' => (int) $row['demand'],
            ];
        }, $stmt->fetchAll());
    }

    public static function sellPlayerStock(
        $playerId,
        $roomId,
        $year,
        $month,
        $cropId = null
    ) {
        $resources = PlayerModel::getResources($playerId);
        if (!$resources || (int) $resources['stock_amount'] <= 0) {
            return ['sold' => 0, 'coins' => 0];
        }

        $stock = (int) $resources['stock_amount'];
        $pdo = Db::connection();

        if ($cropId) {
            $priceStmt = $pdo->prepare(
                'SELECT sell_price, supply, demand FROM market_prices WHERE room_id = ? AND crop_id = ?'
            );
            $priceStmt->execute([$roomId, $cropId]);
        } else {
            $plan = $pdo->prepare(
                'SELECT crop_id FROM player_crop_plans WHERE player_id = ? AND year = ? ORDER BY id DESC LIMIT 1'
            );
            $plan->execute([$playerId, $year]);
            $planRow = $plan->fetch();
            $cropId = $planRow ? (int) $planRow['crop_id'] : null;

            if (!$cropId) {
                $priceStmt = $pdo->prepare(
                    'SELECT sell_price, supply, demand, crop_id FROM market_prices WHERE room_id = ? LIMIT 1'
                );
                $priceStmt->execute([$roomId]);
            } else {
                $priceStmt = $pdo->prepare(
                    'SELECT sell_price, supply, demand, crop_id FROM market_prices WHERE room_id = ? AND crop_id = ?'
                );
                $priceStmt->execute([$roomId, $cropId]);
            }
        }

        $priceRow = $priceStmt->fetch();
        if (!$priceRow) {
            return ['sold' => 0, 'coins' => 0];
        }

        $cropId = (int) $priceRow['crop_id'];
        $modifier = self::priceModifier((int) $priceRow['supply'], (int) $priceRow['demand']);
        $unitPrice = (float) $priceRow['sell_price'] * $modifier;
        $coins = (int) round($stock * $unitPrice);

        PlayerModel::setResourceField($playerId, 'stock_amount', 0);
        PlayerModel::adjustCoins($playerId, $coins);

        $pdo->prepare(
            'UPDATE market_prices SET supply = supply + ?, updated_at = NOW() WHERE room_id = ? AND crop_id = ?'
        )->execute([$stock, $roomId, $cropId]);

        $pdo->prepare(
            'INSERT INTO market_transactions (room_id, player_id, crop_id, type, amount, price, year, month)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$roomId, $playerId, $cropId, 'sell', $stock, $unitPrice, $year, $month]);

        $bonus = 2;
        $planStmt = $pdo->prepare(
            'SELECT c.capability_bonus FROM player_crop_plans pcp
             JOIN crops c ON c.id = pcp.crop_id
             WHERE pcp.player_id = ? AND pcp.year = ? LIMIT 1'
        );
        $planStmt->execute([$playerId, $year]);
        $planData = $planStmt->fetch();
        if ($planData) {
            $bonus = (int) $planData['capability_bonus'];
        }
        PlayerModel::adjustCapability($playerId, $bonus);

        return ['sold' => $stock, 'coins' => $coins, 'capability_bonus' => $bonus];
    }

    private static function priceModifier($supply, $demand)
    {
        if ($demand <= 0) {
            return 1.0;
        }
        $ratio = $supply / max(1, $demand);
        return max(0.6, min(1.5, 1.2 - ($ratio * 0.3)));
    }
}
