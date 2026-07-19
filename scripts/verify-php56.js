/**
 * ตรวจ syntax ที่ PHP 5.6 ใช้ไม่ได้
 * รัน: node scripts/verify-php56.js
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..', 'backend');

const PATTERNS = [
  { name: 'null coalesce ??', re: /\?\?/ },
  { name: 'arrow function static fn', re: /static fn / },
  { name: 'static closure (PHP 7.4+)', re: /array_map\(static function/ },
  { name: 'isset on class const array', re: /isset\(self::[A-Z_]+\[/ },
  { name: 'spaceship <=>', re: /<=>\s*/ },
  { name: 'spread ...$', re: /\.\.\.\$/ },
  { name: 'Throwable', re: /\bThrowable\b/ },
  { name: 'return type : void', re: /\)\s*:\s*void\b/ },
  { name: 'return type : array', re: /\)\s*:\s*array\b/ },
  { name: 'return type : string', re: /\)\s*:\s*string\b/ },
  { name: 'return type : int', re: /\)\s*:\s*int\b/ },
  { name: 'return type : bool', re: /\)\s*:\s*bool\b/ },
  { name: 'return type : float', re: /\)\s*:\s*float\b/ },
  { name: 'nullable ?array', re: /\?array\b/ },
  { name: 'nullable ?string', re: /\?string\b/ },
  { name: 'nullable ?int', re: /\?int\b/ },
  { name: 'public const', re: /\bpublic const\b/ },
  { name: 'JSON_INVALID without defined()', re: /JSON_INVALID_UTF8_SUBSTITUTE(?!\s*\))/ },
];

const EAGER_CONTROLLER_REQUIRES = [
  { file: 'controllers/PlayerController.php', re: /^require_once.*models/m },
  { file: 'controllers/CountryController.php', re: /^require_once.*models/m },
  { file: 'controllers/DashboardController.php', re: /^require_once.*models/m },
  { file: 'controllers/RoomController.php', re: /^require_once.*SimulationModel/m },
  { file: 'controllers/SimulationController.php', re: /^require_once.*SimulationModel/m },
];

let issues = 0;

function walk(dir) {
  for (const name of fs.readdirSync(dir)) {
    const full = path.join(dir, name);
    if (fs.statSync(full).isDirectory()) {
      walk(full);
    } else if (name.endsWith('.php')) {
      const rel = path.relative(ROOT, full).replace(/\\/g, '/');
      const content = fs.readFileSync(full, 'utf8');
      const lines = content.split('\n');

      for (const pat of PATTERNS) {
        lines.forEach((line, i) => {
          const trimmed = line.trim();
          if (trimmed.startsWith('*') || trimmed.startsWith('//')) {
            return;
          }
          if (pat.name === 'JSON_INVALID without defined()') {
            if (rel === 'helpers/Response.php') return;
            if (line.includes('defined(')) return;
          }
          if (pat.re.test(line)) {
            console.log(`FAIL [${pat.name}] ${rel}:${i + 1}`);
            issues++;
          }
        });
      }
    }
  }
}

walk(ROOT);

for (const rule of EAGER_CONTROLLER_REQUIRES) {
  const full = path.join(ROOT, rule.file);
  if (!fs.existsSync(full)) continue;
  const content = fs.readFileSync(full, 'utf8');
  if (rule.re.test(content)) {
    console.log(`WARN [eager model require] ${rule.file}`);
    issues++;
  }
}

if (issues === 0) {
  console.log('OK — PHP 5.6 compatibility check passed');
} else {
  console.log(`\n${issues} issue(s) found`);
  process.exit(1);
}
