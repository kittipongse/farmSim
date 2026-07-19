#!/usr/bin/env python3
"""Convert FarmSim backend PHP 7.4+ syntax to PHP 5.6 compatible code."""

from __future__ import print_function

import os
import re
import sys

ROOT = os.path.join(os.path.dirname(__file__), '..', 'backend')

ARROW_REPLACEMENTS = [
    (
        r"static fn \(\$c\) => \$c\['card_code'\] === 'PLANT'",
        "function ($c) { return $c['card_code'] === 'PLANT'; }",
    ),
    (
        r"static fn \(\$t\) => \$t\['event_type'\] === 'disaster'",
        "function ($t) { return $t['event_type'] === 'disaster'; }",
    ),
    (
        r"static fn \(\$t\) => \$t\['event_type'\] === 'government_policy'",
        "function ($t) { return $t['event_type'] === 'government_policy'; }",
    ),
    (
        r"static fn \(\$m\) => \$labels\[\$m\] \?\? \(string\) \$m",
        "function ($m) use ($labels) { return isset($labels[$m]) ? $labels[$m] : (string) $m; }",
    ),
    (
        r"static fn \(\$a, \$b\) => \$b\['total'\] <=> \$a\['total'\]",
        "function ($a, $b) { if ($b['total'] == $a['total']) { return 0; } return ($b['total'] < $a['total']) ? -1 : 1; }",
    ),
]

FETCH_NULL_COALESCE = [
    (
        r"\(\s*int\s*\)\s*\(\s*\$check->fetch\(\)\['cnt'\]\s*\?\?\s*0\s*\)",
        "(int) (($__row = $check->fetch()) && isset($__row['cnt']) ? $__row['cnt'] : 0)",
    ),
    (
        r"\(\s*int\s*\)\s*\(\s*\$countStmt->fetch\(\)\['cnt'\]\s*\?\?\s*0\s*\)",
        "(int) (($__row = $countStmt->fetch()) && isset($__row['cnt']) ? $__row['cnt'] : 0)",
    ),
    (
        r"\(\s*int\s*\)\s*\(\s*\$eventStmt->fetch\(\)\['cnt'\]\s*\?\?\s*0\s*\)",
        "(int) (($__row = $eventStmt->fetch()) && isset($__row['cnt']) ? $__row['cnt'] : 0)",
    ),
    (
        r"\(\s*int\s*\)\s*\(\s*\$stmt->fetch\(\)\['grand_total'\]\s*\?\?\s*0\s*\)",
        "(int) (($__row = $stmt->fetch()) && isset($__row['grand_total']) ? $__row['grand_total'] : 0)",
    ),
    (
        r"return \(bool\) \(\$stmt->fetch\(\)\['due'\]\s*\?\?\s*false\);",
        "$__row = $stmt->fetch(); return (bool) (isset($__row['due']) ? $__row['due'] : false);",
    ),
    (
        r"\$remaining = \(int\) \(\$stmt->fetch\(\)\['remaining'\]\s*\?\?\s*0\);",
        "$__row = $stmt->fetch(); $remaining = (int) (isset($__row['remaining']) ? $__row['remaining'] : 0);",
    ),
    (
        r"return \(int\) \(\$stmt->fetch\(\)\['remaining'\]\s*\?\?\s*0\);",
        "$__row = $stmt->fetch(); return (int) (isset($__row['remaining']) ? $__row['remaining'] : 0);",
    ),
]


def find_expr_start(s, end):
    depth_paren = depth_bracket = 0
    i = end - 1
    while i >= 0 and s[i].isspace():
        i -= 1
    end = i + 1
    while i >= 0:
        ch = s[i]
        if ch in ')]}':
            if ch == ')':
                depth_paren += 1
            elif ch == ']':
                depth_bracket += 1
            elif ch == '}':
                pass
            i -= 1
            continue
        if ch in '([{':
            if ch == '(':
                if depth_paren > 0:
                    depth_paren -= 1
                    i -= 1
                    continue
                break
            if ch == '[':
                if depth_bracket > 0:
                    depth_bracket -= 1
                    i -= 1
                    continue
            break
        if ch in ';,=?:&|^' and depth_paren == 0 and depth_bracket == 0:
            break
        if ch in '+-*/%' and depth_paren == 0 and depth_bracket == 0:
            prev = s[i - 1] if i > 0 else ''
            if ch == '-' and (prev.isalnum() or prev in ')])>'):
                i -= 1
                continue
            if ch in '*/%':
                break
        i -= 1
    return i + 1


def find_expr_end(s, start):
    depth_paren = depth_bracket = 0
    i = start
    while i < len(s) and s[i].isspace():
        i += 1
    while i < len(s):
        ch = s[i]
        if ch == '(':
            depth_paren += 1
        elif ch == ')':
            if depth_paren == 0:
                break
            depth_paren -= 1
        elif ch == '[':
            depth_bracket += 1
        elif ch == ']':
            depth_bracket -= 1
        elif ch in ',;)]}' and depth_paren == 0 and depth_bracket == 0:
            break
        i += 1
    return i


def replace_null_coalesce(s):
    while True:
        idx = s.find('??')
        if idx == -1:
            break
        left_start = find_expr_start(s, idx)
        left = s[left_start:idx].strip()
        right_start = idx + 2
        right_end = find_expr_end(s, right_start)
        right = s[right_start:right_end].strip()
        replacement = '(isset(%s) ? %s : %s)' % (left, left, right)
        s = s[:left_start] + replacement + s[right_end:]
    return s


def strip_php56_incompatible(code):
    code = code.replace('Throwable', 'Exception')
    code = re.sub(r'\bpublic const\b', 'const', code)
    code = re.sub(r'\bprivate const\b', 'const', code)
    code = re.sub(r'\bprotected const\b', 'const', code)

    for pattern, repl in ARROW_REPLACEMENTS:
        code = re.sub(pattern, repl, code)

    code = re.sub(
        r'static function\s*\(([^)]*)\)\s*:\s*array',
        r'static function(\1)',
        code,
    )

    # nullable + typed params: ?string $x = null -> $x = null
    code = re.sub(
        r'\?(int|string|array|float|bool)\s+(\$\w+)(\s*=\s*[^,\)]+)?',
        r'\2\3',
        code,
    )
    # scalar param types
    code = re.sub(
        r'\b(int|string|float|bool)\s+(\$\w+)',
        r'\2',
        code,
    )
    # return types
    code = re.sub(r'\)\s*:\s*\?array\b', ')', code)
    code = re.sub(r'\)\s*:\s*\?string\b', ')', code)
    code = re.sub(r'\)\s*:\s*\?int\b', ')', code)
    code = re.sub(r'\)\s*:\s*void\b', ')', code)
    code = re.sub(r'\)\s*:\s*array\b', ')', code)
    code = re.sub(r'\)\s*:\s*string\b', ')', code)
    code = re.sub(r'\)\s*:\s*int\b', ')', code)
    code = re.sub(r'\)\s*:\s*bool\b', ')', code)
    code = re.sub(r'\)\s*:\s*float\b', ')', code)
    code = re.sub(r'\)\s*:\s*PDO\b', ')', code)

    for pattern, repl in FETCH_NULL_COALESCE:
        code = re.sub(pattern, repl, code)

    code = replace_null_coalesce(code)
    return code


def process_file(path):
    with open(path, 'r', encoding='utf-8') as f:
        original = f.read()
    converted = strip_php56_incompatible(original)
    if converted != original:
        with open(path, 'w', encoding='utf-8', newline='\n') as f:
            f.write(converted)
        return True
    return False


def main():
    changed = 0
    for dirpath, _, filenames in os.walk(ROOT):
        for name in filenames:
            if not name.endswith('.php'):
                continue
            path = os.path.join(dirpath, name)
            if process_file(path):
                changed += 1
                print('updated:', os.path.relpath(path, ROOT))
    print('done, %d files updated' % changed)


if __name__ == '__main__':
    main()
