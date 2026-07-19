const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..', 'backend');

const FILE_FIXES = {
  'index.php': (code) => code
    .replace(
      /\$path =\(isset\(parse_url\(\(isset\(\$_SERVER\['REQUEST_URI'\]\) \? \$_SERVER\['REQUEST_URI'\] : '\/'\), PHP_URL_PATH\)\) \? parse_url\(\(isset\(\$_SERVER\['REQUEST_URI'\]\) \? \$_SERVER\['REQUEST_URI'\] : '\/'\), PHP_URL_PATH\) : '\/'\);/,
      "$requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';\n$path = parse_url($requestUri, PHP_URL_PATH);\nif ($path === null || $path === false) {\n    $path = '/';\n}"
    ),
  'helpers/Router.php': (code) => code
    .replace(
      /\$path =\(isset\(parse_url\(\$uri, PHP_URL_PATH\)\) \? parse_url\(\$uri, PHP_URL_PATH\) : '\/'\);/,
      "$path = parse_url($uri, PHP_URL_PATH);\n        if ($path === null || $path === false) {\n            $path = '/';\n        }"
    ),
  'models/SimulationModel.php': (code) => code
    .replace(
      /return GameRoomModel::\(isset\(findById\(\(int\) \$room\['id'\]\)\) \? findById\(\(int\) \$room\['id'\]\) : \$room\);/g,
      '$__room = GameRoomModel::findById((int) $room[\'id\']);\n            return $__room ? $__room : $room;'
    )
    .replace(
      /\$summary = \['card' =\(isset\(> \$card\['card_code'\]\) \? > \$card\['card_code'\] : 'AUTO'\), 'effects' => \[\]\];/,
      "$summary = array('card' => (isset($card['card_code']) ? $card['card_code'] : 'AUTO'), 'effects' => array());"
    ),
  'models/GameRoomModel.php': (code) => code
    .replace(
      /return self::\(isset\(findByCode\(\$updated\['room_code'\]\)\) \? findByCode\(\$updated\['room_code'\]\) : \$updated\);/,
      '$__room = self::findByCode($updated[\'room_code\']);\n        return $__room ? $__room : $updated;'
    ),
  'models/EventModel.php': (code) => code
    .replace(
      /: \(self::\(isset\(SPRITE_MAP\[\$code\]\) \? SPRITE_MAP\[\$code\] : 8\)\);/,
      ': (isset(self::SPRITE_MAP[$code]) ? self::SPRITE_MAP[$code] : 8);'
    ),
};

function walk(dir) {
  for (const name of fs.readdirSync(dir)) {
    const full = path.join(dir, name);
    if (fs.statSync(full).isDirectory()) {
      walk(full);
    } else if (name.endsWith('.php')) {
      let code = fs.readFileSync(full, 'utf8');
      const rel = path.relative(ROOT, full).replace(/\\/g, '/');
      const original = code;
      code = code.replace(/=\(isset\(> /g, '=> (isset(');
      code = code.replace(/\? > /g, '? ');
      if (FILE_FIXES[rel]) {
        code = FILE_FIXES[rel](code);
      }
      if (code !== original) {
        fs.writeFileSync(full, code, 'utf8');
        console.log('fixed:', rel);
      }
    }
  }
}

walk(ROOT);
console.log('done');
