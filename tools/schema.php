<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$source = file_get_contents($root.'/proto/a2a.proto');
if ($source === false) {
    throw new RuntimeException('Cannot read proto');
}
preg_match_all('/^message (\w+) \{([\s\S]*?)^\}/m', $source, $messages, PREG_SET_ORDER);
$definitions = [];
$camel = static fn (string $name): string => preg_replace_callback('/_([a-z])/', static fn (array $m): string => strtoupper($m[1]), $name);
foreach ($messages as $message) {
    preg_match_all('/^\s*(?:(repeated|optional)\s+)?(map<[^>]+>|[\w.]+)\s+(\w+)\s*=\s*\d+([^;]*);/m', $message[2], $fields, PREG_SET_ORDER);
    $definition = ['fields' => [], 'groups' => []];
    foreach ($fields as $field) {
        $definition['fields'][$camel($field[3])] = ['type' => $field[2], 'repeated' => $field[1] === 'repeated', 'required' => str_contains($field[4], 'REQUIRED')];
    }
    preg_match_all('/oneof \w+ \{([\s\S]*?)\n\s*\}/', $message[2], $groups, PREG_SET_ORDER);
    foreach ($groups as $group) {
        preg_match_all('/^\s*[\w.]+\s+(\w+)\s*=/m', $group[1], $members);
        $definition['groups'][] = array_map($camel, $members[1]);
    }
    $definitions[$message[1]] = $definition;
}
$content = "<?php\ndeclare(strict_types=1);\nnamespace A2A\\Protocol;\nfinal class Schema\n{\n    /** @var array<string, array{fields: array<string, array{type: string, repeated: bool, required: bool}>, groups: list<list<string>>}> */\n    public const MESSAGES = ".var_export($definitions, true).";\n}\n";
if (file_put_contents($argv[1] ?? $root.'/src/Protocol/Schema.php', $content) === false) {
    throw new RuntimeException('Cannot write schema');
}
