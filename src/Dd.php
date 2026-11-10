<?php

declare(strict_types=1);

namespace bladeknigt89\dd;

use yii\base\Component;

/**
 * Yii component that installs Laravel's dd()/dump() behavior.
 *
 * The global functions are the everyday API (`dd($model)`, `dump($model)`).
 * Configure this component when the editor link, depth, or model formatting
 * should differ from the defaults. The extension bootstrap instantiates it
 * on its own, so an application config entry is optional.
 */
class Dd extends Component
{
    /**
     * Print the file and line of the dd()/dump() call, as Laravel does.
     */
    public bool $showSource = true;

    /**
     * Editor used for the source link.
     *
     * A short name (`phpstorm`, `vscode`, `vscode-insiders`, `sublime`, `atom`)
     * or a pattern containing `{file}` and `{line}`.
     */
    public string $editor = 'phpstorm';

    /**
     * Project root used to shorten the source path. Defaults to the Yii base path.
     */
    public ?string $basePath = null;

    /**
     * Show Yii model attributes instead of component internals.
     */
    public bool $prettyModels = true;

    /**
     * Extra class names collapsed when they show up nested inside a dump.
     *
     * @var list<class-string>
     */
    public array $extraCollapse = [];

    public ?int $maxDepth = null;

    public ?int $maxItems = null;

    public ?int $maxString = null;

    public function init(): void
    {
        parent::init();

        if ($this->basePath === null && \Yii::$app !== null) {
            $this->basePath = \Yii::$app->basePath;
        }

        Dumper::register([
            'showSource' => $this->showSource,
            'editor' => $this->editor,
            'basePath' => $this->basePath,
            'prettyModels' => $this->prettyModels,
            'extraCollapse' => $this->extraCollapse,
            'maxDepth' => $this->maxDepth,
            'maxItems' => $this->maxItems,
            'maxString' => $this->maxString,
        ]);
    }

    /**
     * Dump the given values and keep running.
     *
     * Same return value as Laravel's dump(): the value itself, or the list of
     * values when more than one was passed.
     */
    public function dump(mixed ...$vars): mixed
    {
        return Dumper::dump(...$vars);
    }

    /**
     * Dump the given values and stop the script.
     */
    public function dd(mixed ...$vars): never
    {
        Dumper::dd(...$vars);
    }
}
