<?php
declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';
$root = dirname(__DIR__);
$expected = explode(' ', trim(file_get_contents($root.'/proto/SHA256SUMS')))[0];
if (!hash_equals($expected, hash_file('sha256', $root.'/proto/a2a.proto'))) {
    throw new RuntimeException('Protocol source checksum mismatch');
}
foreach (A2A\Protocol\Operation::cases() as $operation) {
    foreach ([$operation->requestClass(), $operation->responseClass()] as $class) {
        if (!is_subclass_of($class, Google\Protobuf\Internal\Message::class)) {
            throw new RuntimeException('Missing generated protobuf class: '.$class);
        }
    }
}
$temporary = sys_get_temp_dir().'/a2a-generated-'.bin2hex(random_bytes(8));
mkdir($temporary, 0700, true);
foreach ([
    ['protoc', '-I', $root.'/proto', '--php_out='.$temporary, $root.'/proto/a2a.proto', $root.'/proto/storage.proto'],
    [PHP_BINARY, $root.'/tools/schema.php', $temporary.'/Schema.php'],
] as $command) {
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
    if (!is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('Cannot regenerate protocol sources for verification');
    }
}
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS)) as $file) {
    $relative = substr($file->getPathname(), strlen($temporary) + 1);
    $target = $relative === 'Schema.php' ? $root.'/src/Protocol/Schema.php' : $root.'/generated/'.$relative;
    if (!is_file($target) || hash_file('sha256', $target) !== hash_file('sha256', $file->getPathname())) {
        throw new RuntimeException('Generated source differs from proto: '.$relative);
    }
}
fwrite(STDOUT, "Protocol checksum and reproducible generated sources verified.\n");
