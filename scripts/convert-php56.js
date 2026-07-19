/**
 * Convert FarmSim backend PHP 7.4+ syntax to PHP 5.6 compatible code.
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..', 'backend');

const ARROW_REPLACEMENTS = [
  [/static fn \(\$c\) => \$c\['card_code'\] === 'PLANT'/g,
    "function ($c) { return $c['card_code'] === 'PLANT'; }"],
  [/static fn \(\$t\) => \$t\['event_type'\] === 'disaster'/g,
    "function ($t) { return $t['event_type'] === 'disaster'; }"],
  [/static fn \(\$t\) => \$t\['event_type'\] === 'government_policy'/g,
    "function ($t) { return $t['event_type'] === 'government_policy'; }"],
  [/static fn \(\$m\) => \$labels\[\$m\] \?\? \(string\) \$m/g,
    "function ($m) use ($labels) { return isset($labels[$m]) ? $labels[$m] : (string) $m; }"],
  [/static fn \(\$a, \$b\) => \$b\['total'\] <=> \$a\['total'\]/g,
    "function ($a, $b) { if ($b['total'] == $a['total']) { return 0; } return ($b['total'] < $a['total']) ? -1 : 1; }"],
];

const FETCH_NULL_COALESCE = [
  [/\(\s*int\s*\)\s*\(\s*\$check->fetch\(\)\['cnt'\]\s*\?\?\s*0\s*\)/g,
    "(int) (($__row = $check->fetch()) && isset($__row['cnt']) ? $__row['cnt'] : 0)"],
  [/\(\s*int\s*\)\s*\(\s*\$countStmt->fetch\(\)\['cnt'\]\s*\?\?\s*0\s*\)/g,
    "(int) (($__row = $countStmt->fetch()) && isset($__row['cnt']) ? $__row['cnt'] : 0)"],
  [/\(\s*int\s*\)\s*\(\s*\$eventStmt->fetch\(\)\['cnt'\]\s*\?\?\s*0\s*\)/g,
    "(int) (($__row = $eventStmt->fetch()) && isset($__row['cnt']) ? $__row['cnt'] : 0)"],
  [/\(\s*int\s*\)\s*\(\s*\$stmt->fetch\(\)\['grand_total'\]\s*\?\?\s*0\s*\)/g,
    "(int) (($__row = $stmt->fetch()) && isset($__row['grand_total']) ? $__row['grand_total'] : 0)"],
  [/return \(bool\) \(\$stmt->fetch\(\)\['due'\]\s*\?\?\s*false\);/g,
    "$__row = $stmt->fetch(); return (bool) (isset($__row['due']) ? $__row['due'] : false);"],
  [/\$remaining = \(int\) \(\$stmt->fetch\(\)\['remaining'\]\s*\?\?\s*0\);/g,
    "$__row = $stmt->fetch(); $remaining = (int) (isset($__row['remaining']) ? $__row['remaining'] : 0);"],
  [/return \(int\) \(\$stmt->fetch\(\)\['remaining'\]\s*\?\?\s*0\);/g,
    "$__row = $stmt->fetch(); return (int) (isset($__row['remaining']) ? $__row['remaining'] : 0);"],
];

function findExprStart(s, end) {
  let depthParen = 0;
  let depthBracket = 0;
  let i = end - 1;
  while (i >= 0 && /\s/.test(s[i])) i--;
  end = i + 1;
  while (i >= 0) {
    const ch = s[i];
    if (ch === ')' || ch === ']' || ch === '}') {
      if (ch === ')') depthParen++;
      else if (ch === ']') depthBracket++;
      i--;
      continue;
    }
    if (ch === '(') {
      if (depthParen > 0) { depthParen--; i--; continue; }
      break;
    }
    if (ch === '[') {
      if (depthBracket > 0) { depthBracket--; i--; continue; }
      break;
    }
    if (';,=?:&|^'.includes(ch) && depthParen === 0 && depthBracket === 0) break;
    if ('+-*/%'.includes(ch) && depthParen === 0 && depthBracket === 0) {
      const prev = i > 0 ? s[i - 1] : '';
      if (ch === '-' && (/[a-zA-Z0-9_)\]>]/.test(prev))) { i--; continue; }
      if ('*/%'.includes(ch)) break;
    }
    i--;
  }
  return i + 1;
}

function findExprEnd(s, start) {
  let depthParen = 0;
  let depthBracket = 0;
  let i = start;
  while (i < s.length && /\s/.test(s[i])) i++;
  while (i < s.length) {
    const ch = s[i];
    if (ch === '(') depthParen++;
    else if (ch === ')') {
      if (depthParen === 0) break;
      depthParen--;
    } else if (ch === '[') depthBracket++;
    else if (ch === ']') depthBracket--;
    else if (',;)]}'.includes(ch) && depthParen === 0 && depthBracket === 0) break;
    i++;
  }
  return i;
}

function replaceNullCoalesce(s) {
  while (true) {
    const idx = s.indexOf('??');
    if (idx === -1) break;
    const leftStart = findExprStart(s, idx);
    const left = s.slice(leftStart, idx).trim();
    const rightStart = idx + 2;
    const rightEnd = findExprEnd(s, rightStart);
    const right = s.slice(rightStart, rightEnd).trim();
    const replacement = `(isset(${left}) ? ${left} : ${right})`;
    s = s.slice(0, leftStart) + replacement + s.slice(rightEnd);
  }
  return s;
}

function stripPhp56Incompatible(code) {
  code = code.replace(/Throwable/g, 'Exception');
  code = code.replace(/\bpublic const\b/g, 'const');
  code = code.replace(/\bprivate const\b/g, 'const');
  code = code.replace(/\bprotected const\b/g, 'const');

  for (const [pattern, repl] of ARROW_REPLACEMENTS) {
    code = code.replace(pattern, repl);
  }

  code = code.replace(/static function\s*\(([^)]*)\)\s*:\s*array/g, 'static function($1)');
  code = code.replace(/\?(int|string|array|float|bool)\s+(\$\w+)(\s*=\s*[^,)]+)?/g, '$2$3');
  code = code.replace(/\b(int|string|float|bool)\s+(\$\w+)/g, '$2');
  code = code.replace(/\)\s*:\s*\?array\b/g, ')');
  code = code.replace(/\)\s*:\s*\?string\b/g, ')');
  code = code.replace(/\)\s*:\s*\?int\b/g, ')');
  code = code.replace(/\)\s*:\s*void\b/g, ')');
  code = code.replace(/\)\s*:\s*array\b/g, ')');
  code = code.replace(/\)\s*:\s*string\b/g, ')');
  code = code.replace(/\)\s*:\s*int\b/g, ')');
  code = code.replace(/\)\s*:\s*bool\b/g, ')');
  code = code.replace(/\)\s*:\s*float\b/g, ')');
  code = code.replace(/\)\s*:\s*PDO\b/g, ')');

  for (const [pattern, repl] of FETCH_NULL_COALESCE) {
    code = code.replace(pattern, repl);
  }

  return replaceNullCoalesce(code);
}

function walk(dir) {
  let changed = 0;
  for (const name of fs.readdirSync(dir)) {
    const full = path.join(dir, name);
    const stat = fs.statSync(full);
    if (stat.isDirectory()) {
      changed += walk(full);
    } else if (name.endsWith('.php')) {
      const original = fs.readFileSync(full, 'utf8');
      const converted = stripPhp56Incompatible(original);
      if (converted !== original) {
        fs.writeFileSync(full, converted, 'utf8');
        console.log('updated:', path.relative(ROOT, full));
        changed++;
      }
    }
  }
  return changed;
}

const n = walk(ROOT);
console.log('done, %d files updated', n);
