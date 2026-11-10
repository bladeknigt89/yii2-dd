<?php

declare(strict_types=1);

namespace bladeknigt89\dd;

use bladeknigt89\dd\Caster\YiiCaster;
use Symfony\Component\VarDumper\Caster\ReflectionCaster;
use Symfony\Component\VarDumper\Caster\ScalarStub;
use Symfony\Component\VarDumper\Cloner\AbstractCloner;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;

/**
 * Laravel-compatible dump implementation used by the global helpers and the Yii component.
 */
final class Dumper
{
    private const EDITORS = [
        'phpstorm' => 'phpstorm://open?file={file}&line={line}',
        'vscode' => 'vscode://file/{file}:{line}',
        'vscode-insiders' => 'vscode-insiders://file/{file}:{line}',
        'sublime' => 'subl://open?url=file://{file}&line={line}',
        'atom' => 'atom://core/open/file?filename={file}&line={line}',
    ];

    /**
     * Framework services that are useless noise when dumped as a nested property.
     * Same idea as Laravel collapsing the container, dispatcher and DB connection.
     */
    private const DEFAULT_COLLAPSE = [
        'yii\base\Application',
        'yii\base\Module',
        'yii\db\Connection',
        'yii\di\Container',
        'yii\log\Dispatcher',
        'yii\log\Logger',
        'yii\caching\Cache',
        'yii\web\Session',
        'yii\mutex\Mutex',
        'yii\rbac\ManagerInterface',
    ];

    /** @var array<string, mixed> */
    private static array $config = [];

    /**
     * @param array<string, mixed> $config
     */
    public static function register(array $config = []): void
    {
        self::$config = array_replace(self::defaults(), $config);
        self::registerCasters();

        if (isset($_SERVER['VAR_DUMPER_FORMAT'])) {
            return;
        }

        self::installHandler();
    }

    /**
     * @param array<int|string, mixed> $vars
     */
    public static function dump(mixed ...$vars): mixed
    {
        if (!$vars) {
            VarDumper::dump(new ScalarStub('🐛'));

            return null;
        }

        if (array_key_exists(0, $vars) && count($vars) === 1) {
            VarDumper::dump($vars[0]);
            $key = 0;
        } else {
            foreach ($vars as $key => $value) {
                VarDumper::dump($value, is_int($key) ? 1 + $key : $key);
            }
        }

        if (count($vars) > 1) {
            return $vars;
        }

        return $vars[$key];
    }

    /**
     * @param array<int|string, mixed> $vars
     */
    public static function dd(mixed ...$vars): never
    {
        if (!in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true) && !headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
        }

        self::dump(...$vars);
        exit(1);
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        return [
            'showSource' => true,
            'editor' => 'phpstorm',
            'basePath' => null,
            'prettyModels' => true,
            'extraCollapse' => [],
            'maxDepth' => null,
            'maxItems' => null,
            'maxString' => null,
            'output' => null,
            'colors' => null,
        ];
    }

    private static function registerCasters(): void
    {
        $extra = self::$config['extraCollapse'] ?? [];
        if (!is_array($extra)) {
            $extra = [];
        }

        foreach (array_merge(self::DEFAULT_COLLAPSE, $extra) as $class) {
            if (!is_string($class) || $class === '') {
                continue;
            }
            AbstractCloner::$defaultCasters[$class] ??= [YiiCaster::class, 'cutWhenNested'];
        }

        if (!empty(self::$config['prettyModels']) && class_exists(\yii\base\Model::class)) {
            AbstractCloner::$defaultCasters[\yii\base\Model::class] ??= [YiiCaster::class, 'castModel'];
        }
    }

    private static function installHandler(): void
    {
        $cloner = new VarCloner();
        $cloner->addCasters(ReflectionCaster::UNSET_CLOSURE_FILE_INFO);

        if (self::$config['maxItems'] !== null) {
            $cloner->setMaxItems((int) self::$config['maxItems']);
        }
        if (self::$config['maxString'] !== null) {
            $cloner->setMaxString((int) self::$config['maxString']);
        }

        $cli = self::isCli();
        $dumper = $cli ? new CliDumper() : new HtmlDumper();
        $colors = self::colors($cli);

        if ($cli) {
            $dumper->setColors($colors);
        }

        $display = [];
        if (self::$config['maxDepth'] !== null) {
            $display['maxDepth'] = (int) self::$config['maxDepth'];
        }

        $editor = self::editorPattern();
        if ($editor !== '') {
            $display['fileLinkFormat'] = strtr($editor, [
                '{file}' => '%f',
                '{filename}' => '%f',
                '{line}' => '%l',
            ]);
        }

        if ($display !== []) {
            $dumper->setDisplayOptions($display);
        }

        VarDumper::setHandler(static function (mixed $var, mixed $label = null) use ($cloner, $dumper, $cli, $colors): void {
            $data = $cloner->cloneVar($var);
            if (is_string($label) || is_int($label)) {
                $data = $data->withContext(['label' => $label]);
            }

            $payload = $dumper->dump($data, true);
            if (!is_string($payload)) {
                return;
            }

            $source = self::sourceSuffix(!$cli, $colors);
            if ($source !== '') {
                if (str_ends_with($payload, "\n")) {
                    $payload = substr($payload, 0, -1) . $source . "\n";
                } else {
                    $payload .= $source;
                }
            }

            self::emit($payload, $cli);
        });
    }

    private static function isCli(): bool
    {
        return in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true);
    }

    private static function colors(bool $cli): bool
    {
        $configured = self::$config['colors'] ?? null;
        if (is_bool($configured)) {
            return $configured;
        }

        if (!$cli) {
            return false;
        }

        $output = self::$config['output'] ?? null;
        if (is_resource($output)) {
            return stream_isatty($output);
        }

        return defined('STDOUT') && stream_isatty(STDOUT);
    }

    private static function editorPattern(): string
    {
        $editor = (string) (self::$config['editor'] ?? 'phpstorm');

        return self::EDITORS[$editor] ?? $editor;
    }

    private static function sourceSuffix(bool $html, bool $colors): string
    {
        if (empty(self::$config['showSource'])) {
            return '';
        }

        $place = self::callSite();
        if ($place === null) {
            return '';
        }

        [$file, $line] = $place;
        $label = self::relativePath($file);
        if ($line !== null) {
            $label .= ':' . $line;
        }

        $href = self::editorHref($file, $line);

        if ($html) {
            $text = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $link = $href === null
                ? $text
                : '<a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" style="color:#a0a0a0">' . $text . '</a>';

            return '<pre class="sf-dump" style="color:#a0a0a0;font:12px Menlo,Consolas,monospace;margin:-8px 0 1.5em">// ' . $link . "</pre>\n";
        }

        $plain = ' // ' . $label;
        if ($colors) {
            $plain = "\033[90m" . $plain . "\033[0m";
        }

        return $plain;
    }

    /**
     * @return array{0: string, 1: int|null}|null
     */
    private static function callSite(): ?array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $sources = str_replace('\\', '/', __DIR__ . '/');

        foreach ($trace as $frame) {
            $file = $frame['file'] ?? null;
            if (!is_string($file) || $file === '') {
                continue;
            }

            $normalized = str_replace('\\', '/', $file);
            if (str_starts_with($normalized, $sources) || str_contains($normalized, '/symfony/var-dumper/')) {
                continue;
            }

            $line = $frame['line'] ?? null;

            return [$file, is_int($line) ? $line : null];
        }

        return null;
    }

    private static function relativePath(string $file): string
    {
        $base = self::$config['basePath'] ?? null;
        if (!is_string($base) || $base === '') {
            $base = getcwd() ?: '';
        }

        $file = str_replace('\\', '/', $file);
        $base = rtrim(str_replace('\\', '/', $base), '/');
        if ($base !== '' && str_starts_with($file, $base . '/')) {
            return substr($file, strlen($base) + 1);
        }

        return $file;
    }

    private static function editorHref(string $file, ?int $line): ?string
    {
        $pattern = self::editorPattern();
        if ($pattern === '') {
            return null;
        }

        return strtr($pattern, [
            '{file}' => $file,
            '{filename}' => $file,
            '{line}' => (string) ($line ?? 1),
            '%f' => $file,
            '%l' => (string) ($line ?? 1),
        ]);
    }

    private static function emit(string $payload, bool $cli): void
    {
        $output = self::$config['output'] ?? null;
        if (is_resource($output)) {
            fwrite($output, $payload);

            return;
        }

        if ($cli && defined('STDOUT')) {
            fwrite(STDOUT, $payload);

            return;
        }

        echo $payload;
    }
}
