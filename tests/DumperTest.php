<?php

declare(strict_types=1);

namespace bladeknigt89\dd\tests;

use bladeknigt89\dd\Bootstrap;
use bladeknigt89\dd\Dd;
use bladeknigt89\dd\Dumper;
use PHPUnit\Framework\TestCase;
use yii\base\DynamicModel;
use yii\base\Model;
use yii\console\Application;

final class DumperTest extends TestCase
{
    private string $autoload;

    protected function setUp(): void
    {
        $this->autoload = dirname(__DIR__) . '/vendor/autoload.php';
    }

    protected function tearDown(): void
    {
        if (class_exists(\Yii::class, false)) {
            \Yii::$app = null;
        }
        restore_error_handler();
        restore_exception_handler();
    }

    public function testDumpReturnsTheValueAndPrintsItWithCallSite(): void
    {
        $stream = $this->capture();

        $value = dump('hello-dd');

        self::assertSame('hello-dd', $value);
        $output = $this->read($stream);
        self::assertStringContainsString('hello-dd', $output);
        self::assertStringContainsString('tests/DumperTest.php', $output);
    }

    public function testDumpReturnsEveryArgumentAndChainsOnOne(): void
    {
        $object = new \stdClass();
        $object->name = 'Ada';

        $stream = $this->capture();

        self::assertSame($object, dump($object));
        self::assertSame('Ada', dump($object)->name);
        self::assertSame(['alpha', 'beta'], dump('alpha', 'beta'));

        $output = $this->read($stream);
        self::assertStringContainsString('Ada', $output);
        self::assertStringContainsString('alpha', $output);
        self::assertStringContainsString('beta', $output);
    }

    public function testEmptyDumpReturnsNull(): void
    {
        $this->capture();

        self::assertNull(dump());
    }

    public function testYiiModelDumpShowsAttributesWithoutComponentInternals(): void
    {
        $model = new DynamicModel(['name' => null, 'city' => null]);
        $model->name = 'Ada';
        $model->city = 'Pécs';
        $model->addError('name', 'Required');

        $stream = $this->capture();
        dump($model);
        $output = $this->read($stream);

        self::assertStringContainsString('Ada', $output);
        self::assertStringContainsString('Pécs', $output);
        self::assertStringContainsString('attributes', $output);
        self::assertStringContainsString('Required', $output);
        self::assertStringNotContainsString('_events', $output);
        self::assertStringNotContainsString('_behaviors', $output);
    }

    public function testCustomScenarioIsVisible(): void
    {
        $model = new DynamicModel(['name' => 'Ada']);
        $model->setScenario('create');

        $stream = $this->capture();
        dump($model);

        self::assertStringContainsString('create', $this->read($stream));
    }

    public function testBootstrapInstallsTheComponent(): void
    {
        $this->loadYii();
        $app = new Application([
            'id' => 'yii2-dd-test',
            'basePath' => dirname(__DIR__),
        ]);

        (new Bootstrap())->bootstrap($app);

        $component = $app->get('dd');
        self::assertInstanceOf(Dd::class, $component);
        self::assertSame(dirname(__DIR__), $component->basePath);

        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        Dumper::register([
            'output' => $stream,
            'colors' => false,
            'showSource' => false,
            'prettyModels' => true,
            'basePath' => dirname(__DIR__),
        ]);

        $model = new class(['title' => 'Posted']) extends Model {
            public ?string $title = null;

            public function rules()
            {
                return [[['title'], 'safe']];
            }
        };

        self::assertSame($model, $app->dd->dump($model));
        self::assertStringContainsString('Posted', $this->read($stream));
    }

    public function testConfiguredComponentWinsOverTheDefault(): void
    {
        $this->loadYii();
        $app = new Application([
            'id' => 'yii2-dd-test',
            'basePath' => dirname(__DIR__),
            'components' => [
                'dd' => [
                    'class' => Dd::class,
                    'editor' => 'vscode',
                    'showSource' => false,
                ],
            ],
        ]);

        (new Bootstrap())->bootstrap($app);

        $component = $app->get('dd');
        self::assertInstanceOf(Dd::class, $component);
        self::assertSame('vscode', $component->editor);
        self::assertFalse($component->showSource);
    }

    public function testGlobalDdStopsTheProcessWithStatusOne(): void
    {
        [$status, $stdout, $stderr] = $this->runScript(<<<PHP
        require {$this->export($this->autoload)};
        dd('stop-marker');
        fwrite(STDERR, 'SHOULD_NOT_RUN');
        PHP);

        self::assertSame(1, $status);
        self::assertStringContainsString('stop-marker', $stdout);
        self::assertStringNotContainsString('SHOULD_NOT_RUN', $stdout);
        self::assertStringNotContainsString('SHOULD_NOT_RUN', $stderr);
    }

    public function testGlobalDdPrintsEveryArgument(): void
    {
        [$status, $stdout] = $this->runScript(<<<PHP
        require {$this->export($this->autoload)};
        dd('alpha-marker', 'beta-marker');
        PHP);

        self::assertSame(1, $status);
        self::assertStringContainsString('alpha-marker', $stdout);
        self::assertStringContainsString('beta-marker', $stdout);
    }

    public function testDumpDoesNotStopTheProcess(): void
    {
        [$status, $stdout, $stderr] = $this->runScript(<<<PHP
        require {$this->export($this->autoload)};
        dump('kept-marker');
        echo 'AFTER';
        PHP);

        self::assertSame(0, $status, $stderr);
        self::assertStringContainsString('kept-marker', $stdout);
        self::assertStringContainsString('AFTER', $stdout);
    }

    public function testComponentDdStopsTheProcess(): void
    {
        $base = $this->export(dirname(__DIR__));
        $yii = $this->export(dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php');
        [$status, $stdout, $stderr] = $this->runScript(<<<PHP
        require {$this->export($this->autoload)};
        require {$yii};
        new yii\console\Application([
            'id' => 'yii2-dd-test',
            'basePath' => {$base},
        ]);
        (new bladeknigt89\dd\Bootstrap())->bootstrap(Yii::\$app);
        Yii::\$app->dd->dd('component-marker');
        fwrite(STDERR, 'SHOULD_NOT_RUN');
        PHP);

        self::assertSame(1, $status, $stderr);
        self::assertStringContainsString('component-marker', $stdout);
        self::assertStringNotContainsString('SHOULD_NOT_RUN', $stderr);
    }

    public function testWebRequestReturns500AndTheDump(): void
    {
        $script = tempnam(sys_get_temp_dir(), 'yii2dd');
        self::assertIsString($script);
        file_put_contents($script, <<<PHP
        <?php
        require {$this->export($this->autoload)};
        dd(['web' => 'browser-marker']);
        PHP);

        $port = $this->freePort();
        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, $script],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            sys_get_temp_dir()
        );
        self::assertIsResource($process);

        try {
            self::assertTrue($this->waitForPort($port), 'The test server did not start.');

            $context = stream_context_create([
                'http' => ['ignore_errors' => true, 'timeout' => 5],
            ]);
            $body = file_get_contents('http://127.0.0.1:' . $port . '/', false, $context);
            $headers = $http_response_header ?? [];

            self::assertIsString($body);
            self::assertNotEmpty($headers);
            self::assertStringContainsString('500', (string) $headers[0]);
            self::assertStringContainsString('browser-marker', $body);
            self::assertStringContainsString('sf-dump', $body);
            self::assertStringContainsString(basename($script), $body);
        } finally {
            proc_terminate($process, 9);
            proc_close($process);
            @unlink($script);
        }
    }

    /**
     * @return resource
     */
    private function capture()
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        Dumper::register([
            'output' => $stream,
            'colors' => false,
            'showSource' => true,
            'prettyModels' => true,
            'basePath' => dirname(__DIR__),
        ]);

        return $stream;
    }

    /**
     * @param resource $stream
     */
    private function read($stream): string
    {
        rewind($stream);
        $contents = stream_get_contents($stream);
        self::assertIsString($contents);

        return $contents;
    }

    private function loadYii(): void
    {
        require_once dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
    }

    private function export(string $value): string
    {
        return var_export($value, true);
    }

    /**
     * @return array{0: int, 1: string, 2: string}
     */
    private function runScript(string $code): array
    {
        $script = tempnam(sys_get_temp_dir(), 'yii2dd');
        self::assertIsString($script);
        file_put_contents($script, "<?php\ndeclare(strict_types=1);\n" . $code);

        $process = proc_open(
            [PHP_BINARY, $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        @unlink($script);

        return [$status, (string) $stdout, (string) $stderr];
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        self::assertIsResource($socket, $error);
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        self::assertIsString($name);

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    private function waitForPort(int $port): bool
    {
        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.2);
            if (is_resource($socket)) {
                fclose($socket);

                return true;
            }
            usleep(100000);
        }

        return false;
    }
}
