<?php

/**
 * Phase 5 crops v2 migration — PHP 5.6 compatible (no DELIMITER/stored procedures).
 */
class CropsMigration
{
    public static function tableExists($pdo, $table)
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute(array($table));
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function dropForeignKeysTo($pdo, $referencedTable)
    {
        $stmt = $pdo->prepare(
            'SELECT TABLE_NAME, CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND REFERENCED_TABLE_NAME = ?'
        );
        $stmt->execute(array($referencedTable));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $sql = 'ALTER TABLE `' . $row['TABLE_NAME'] . '` DROP FOREIGN KEY `'
                . $row['CONSTRAINT_NAME'] . '`';
            $pdo->exec($sql);
        }
    }

    public static function addFkIfMissing($pdo, $table, $constraint, $ddl)
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?'
        );
        $stmt->execute(array($table, $constraint));
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec($ddl);
        }
    }

    public static function isApplied($pdo)
    {
        if (!self::tableExists($pdo, 'crops')) {
            return false;
        }
        $count = (int) $pdo->query('SELECT COUNT(*) FROM crops')->fetchColumn();
        return $count >= 80;
    }

    public static function migrate($pdo, $sqlFile)
    {
        if (self::isApplied($pdo)) {
            return array('skipped' => true, 'message' => 'crops v2 already applied');
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS crops (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                country_id INT UNSIGNED NOT NULL,
                code VARCHAR(50) NOT NULL,
                name_th VARCHAR(100) NOT NULL,
                name_en VARCHAR(100) NOT NULL,
                category ENUM('field_crop','vegetable','fruit','herb','economic') NOT NULL,
                growth_months TINYINT UNSIGNED NOT NULL DEFAULT 4,
                crop_category ENUM('short','medium','long') NOT NULL DEFAULT 'medium',
                base_coin INT UNSIGNED NOT NULL DEFAULT 100,
                base_buy_price INT NOT NULL DEFAULT 15,
                base_sell_price INT NOT NULL DEFAULT 30,
                capability_bonus TINYINT UNSIGNED NOT NULL DEFAULT 2,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (country_id) REFERENCES countries(id),
                UNIQUE KEY uk_crop_country_code (country_id, code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS crop_region_rates (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                crop_id INT UNSIGNED NOT NULL,
                region_id INT UNSIGNED NOT NULL,
                suitability_level ENUM('excellent','good','moderate','poor','unsuitable') NOT NULL,
                coin_multiplier DECIMAL(4,2) NOT NULL DEFAULT 1.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_crop_region (crop_id, region_id),
                FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE CASCADE,
                FOREIGN KEY (region_id) REFERENCES country_regions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS crop_seasons (
                crop_id INT UNSIGNED NOT NULL,
                season_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (crop_id, season_id),
                FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE CASCADE,
                FOREIGN KEY (season_id) REFERENCES agricultural_seasons(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        if (self::tableExists($pdo, 'region_crops')) {
            self::dropForeignKeysTo($pdo, 'region_crops');
        }

        $pdo->exec('DELETE FROM market_transactions');
        $pdo->exec('DELETE FROM market_prices');
        $pdo->exec('UPDATE player_crop_plans SET crop_id = NULL');

        if (self::tableExists($pdo, 'region_crop_seasons')) {
            $pdo->exec('DROP TABLE region_crop_seasons');
        }
        if (self::tableExists($pdo, 'region_crops')) {
            $pdo->exec('DROP TABLE region_crops');
        }

        $pdo->exec('DELETE FROM crop_seasons');
        $pdo->exec('DELETE FROM crop_region_rates');
        $pdo->exec('DELETE FROM crops');

        if (!is_readable($sqlFile)) {
            throw new Exception('Migration SQL not found: ' . $sqlFile);
        }

        $sql = file_get_contents($sqlFile);
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/DROP PROCEDURE.*?DROP PROCEDURE farmsim_drop_crop_fks;/s', '', $sql);
        $sql = preg_replace('/SET @[^;]+;/', '', $sql);
        $sql = preg_replace('/PREPARE stmt.*?DEALLOCATE PREPARE stmt;/s', '', $sql);

        $parts = explode(';', $sql);
        foreach ($parts as $part) {
            $stmt = trim($part);
            if ($stmt === '') {
                continue;
            }
            $upper = strtoupper(ltrim($stmt));
            if (strpos($upper, 'USE ') === 0) {
                continue;
            }
            if (strpos($upper, 'SET NAMES') === 0) {
                continue;
            }
            if (strpos($upper, 'CREATE TABLE') === 0) {
                continue;
            }
            if (strpos($upper, 'DELETE FROM MARKET') === 0) {
                continue;
            }
            if (strpos($upper, 'UPDATE PLAYER_CROP_PLANS') === 0) {
                continue;
            }
            if (strpos($upper, 'DROP TABLE') === 0) {
                continue;
            }
            $pdo->exec($stmt);
        }

        self::addFkIfMissing(
            $pdo,
            'market_prices',
            'market_prices_crop_fk',
            'ALTER TABLE market_prices ADD CONSTRAINT market_prices_crop_fk
             FOREIGN KEY (crop_id) REFERENCES crops(id)'
        );
        self::addFkIfMissing(
            $pdo,
            'player_crop_plans',
            'player_crop_plans_crop_fk',
            'ALTER TABLE player_crop_plans ADD CONSTRAINT player_crop_plans_crop_fk
             FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE SET NULL'
        );
        self::addFkIfMissing(
            $pdo,
            'market_transactions',
            'market_transactions_crop_fk',
            'ALTER TABLE market_transactions ADD CONSTRAINT market_transactions_crop_fk
             FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE SET NULL'
        );

        $count = (int) $pdo->query('SELECT COUNT(*) FROM crops')->fetchColumn();
        return array(
            'skipped' => false,
            'crops' => $count,
            'rates' => (int) $pdo->query('SELECT COUNT(*) FROM crop_region_rates')->fetchColumn(),
        );
    }

    /**
     * Phase 9: upsert short crops that grow well across Thai regions.
     * Safe to re-run on production (updates rates + inserts missing crops).
     */
    public static function upsertUniversalShortCrops($pdo)
    {
        if (!self::tableExists($pdo, 'crops') || !self::tableExists($pdo, 'crop_region_rates')) {
            return array('skipped' => true, 'reason' => 'crops tables missing');
        }

        // PHP 5.6 + native prepares: บังคับ buffered เพื่อไม่ชน query ค้างจาก migration ก่อนหน้า
        try {
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        } catch (Exception $e) {
            // ignore if unsupported
        }

        // [code, name_th, name_en, category, growth, crop_category, base_coin, rates by region_id]
        // region: 1=north, 2=central, 3=south, 4=isan
        $crops = array(
            array('morning_glory', 'ผักบุ้ง', 'Morning Glory', 'vegetable', 2, 'short', 60, array(1 => 0.85, 2 => 1.00, 4 => 0.85, 3 => 0.85)),
            array('cucumber', 'แตงกวา', 'Cucumber', 'vegetable', 2, 'short', 70, array(1 => 0.85, 2 => 1.00, 4 => 0.85, 3 => 0.85)),
            array('long_bean', 'ถั่วฝักยาว', 'Long Bean', 'vegetable', 2, 'short', 65, array(1 => 0.85, 2 => 1.00, 4 => 0.85, 3 => 1.00)),
            array('chili', 'พริก', 'Chili', 'vegetable', 3, 'short', 85, array(1 => 0.85, 2 => 1.00, 4 => 1.00, 3 => 0.85)),
            array('lemongrass', 'ตะไคร้', 'Lemongrass', 'herb', 3, 'short', 75, array(1 => 0.85, 2 => 1.00, 4 => 0.85, 3 => 1.00)),
            array('kale', 'คะน้า', 'Kale', 'vegetable', 2, 'short', 65, array(1 => 1.00, 2 => 1.00, 4 => 0.85, 3 => 0.85)),
            array('bok_choy', 'กวางตุ้ง', 'Bok Choy', 'vegetable', 2, 'short', 65, array(1 => 1.00, 2 => 1.00, 4 => 0.85, 3 => 0.85)),
            array('napa_cabbage', 'ผักกาดขาว', 'Napa Cabbage', 'vegetable', 2, 'short', 65, array(1 => 1.00, 2 => 0.85, 4 => 0.85, 3 => 0.85)),
            array('coriander', 'ผักชี', 'Coriander', 'herb', 2, 'short', 60, array(1 => 1.00, 2 => 0.85, 4 => 0.85, 3 => 0.85)),
            array('spring_onion', 'ต้นหอม', 'Spring Onion', 'herb', 2, 'short', 60, array(1 => 1.00, 2 => 0.85, 4 => 0.85, 3 => 0.85)),
            array('sweet_basil', 'โหระพา', 'Sweet Basil', 'herb', 2, 'short', 65, array(1 => 0.85, 2 => 1.00, 4 => 1.00, 3 => 1.00)),
            array('holy_basil', 'กะเพรา', 'Holy Basil', 'herb', 2, 'short', 65, array(1 => 0.85, 2 => 1.00, 4 => 1.00, 3 => 1.00)),
            array('lemon_basil', 'แมงลัก', 'Lemon Basil', 'herb', 2, 'short', 65, array(1 => 0.85, 2 => 1.00, 4 => 0.85, 3 => 1.00)),
            array('sweet_corn', 'ข้าวโพดหวาน', 'Sweet Corn', 'field_crop', 3, 'short', 100, array(1 => 1.00, 2 => 1.00, 4 => 1.00, 3 => 0.85)),
            array('mung_bean', 'ถั่วเขียว', 'Mung Bean', 'field_crop', 3, 'short', 80, array(1 => 0.85, 2 => 0.85, 4 => 1.00, 3 => 0.85)),
            array('peanut', 'ถั่วลิสง', 'Peanut', 'field_crop', 3, 'short', 90, array(1 => 0.85, 2 => 0.85, 4 => 1.00, 3 => 0.85)),
            array('sesame', 'งา', 'Sesame', 'field_crop', 3, 'short', 85, array(1 => 0.85, 2 => 0.85, 4 => 1.00, 3 => 0.85)),
            array('pumpkin', 'ฟักทอง', 'Pumpkin', 'vegetable', 3, 'short', 75, array(1 => 1.00, 2 => 1.00, 4 => 0.85, 3 => 0.85)),
        );

        $find = $pdo->prepare(
            'SELECT id FROM crops WHERE country_id = 1 AND code = ? LIMIT 1'
        );
        $insertCrop = $pdo->prepare(
            'INSERT INTO crops
             (country_id, code, name_th, name_en, category, growth_months, crop_category,
              base_coin, base_buy_price, base_sell_price, capability_bonus, is_active)
             VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)'
        );
        $updateCrop = $pdo->prepare(
            'UPDATE crops SET name_th = ?, name_en = ?, category = ?, growth_months = ?,
             crop_category = ?, base_coin = ?, base_buy_price = ?, base_sell_price = ?,
             capability_bonus = 1, is_active = 1
             WHERE id = ?'
        );
        $upsertRate = $pdo->prepare(
            'INSERT INTO crop_region_rates (crop_id, region_id, suitability_level, coin_multiplier)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE suitability_level = VALUES(suitability_level),
                                     coin_multiplier = VALUES(coin_multiplier)'
        );
        $linkSeason = $pdo->prepare(
            'INSERT IGNORE INTO crop_seasons (crop_id, season_id) VALUES (?, ?)'
        );

        $inserted = 0;
        $updated = 0;

        foreach ($crops as $row) {
            $code = $row[0];
            $nameTh = $row[1];
            $nameEn = $row[2];
            $category = $row[3];
            $growth = (int) $row[4];
            $cropCat = $row[5];
            $baseCoin = (int) $row[6];
            $rates = $row[7];
            $buy = (int) max(1, round($baseCoin * 0.15));
            $sell = (int) max(1, round($baseCoin * 0.30));

            $find->execute(array($code));
            $existing = $find->fetch(PDO::FETCH_ASSOC);
            $find->closeCursor();
            if ($existing) {
                $cropId = (int) $existing['id'];
                $updateCrop->execute(array(
                    $nameTh, $nameEn, $category, $growth, $cropCat,
                    $baseCoin, $buy, $sell, $cropId
                ));
                $updateCrop->closeCursor();
                $updated++;
            } else {
                $insertCrop->execute(array(
                    $code, $nameTh, $nameEn, $category, $growth, $cropCat,
                    $baseCoin, $buy, $sell
                ));
                $insertCrop->closeCursor();
                $cropId = (int) $pdo->lastInsertId();
                $inserted++;
            }

            foreach ($rates as $regionId => $mult) {
                $upsertRate->execute(array(
                    $cropId,
                    (int) $regionId,
                    self::suitabilityFromMultiplier($mult),
                    number_format((float) $mult, 2, '.', ''),
                ));
                $upsertRate->closeCursor();
            }

            // vegetables/herbs: winter+summer; field_crop: rainy+summer
            if ($category === 'field_crop') {
                $linkSeason->execute(array($cropId, 1));
                $linkSeason->closeCursor();
                $linkSeason->execute(array($cropId, 3));
                $linkSeason->closeCursor();
            } else {
                $linkSeason->execute(array($cropId, 2));
                $linkSeason->closeCursor();
                $linkSeason->execute(array($cropId, 3));
                $linkSeason->closeCursor();
            }
        }

        return array(
            'skipped' => false,
            'inserted' => $inserted,
            'updated' => $updated,
            'total' => count($crops),
        );
    }

    public static function suitabilityFromMultiplier($mult)
    {
        $m = (float) $mult;
        if ($m >= 1.0) {
            return 'excellent';
        }
        if ($m >= 0.85) {
            return 'good';
        }
        if ($m >= 0.65) {
            return 'moderate';
        }
        if ($m >= 0.45) {
            return 'poor';
        }
        return 'unsuitable';
    }

    public static function hasUniversalShortCrops($pdo)
    {
        if (!self::tableExists($pdo, 'crops')) {
            return false;
        }
        $count = (int) $pdo->query(
            "SELECT COUNT(*) FROM crops
             WHERE country_id = 1 AND code IN ('napa_cabbage','coriander','holy_basil','lemon_basil')"
        )->fetchColumn();
        return $count >= 4;
    }
}
