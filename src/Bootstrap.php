<?php

declare(strict_types=1);

namespace bladeknigt89\dd;

use yii\base\Application;
use yii\base\BootstrapInterface;

/**
 * Registers the dd component during application bootstrap.
 */
class Bootstrap implements BootstrapInterface
{
    /**
     * @param Application $app
     */
    public function bootstrap($app): void
    {
        if (!$app instanceof Application) {
            return;
        }

        if (!$app->has('dd', false)) {
            $app->set('dd', ['class' => Dd::class]);
        }

        $component = $app->get('dd');
        if (!$component instanceof Dd) {
            return;
        }

        if ($component->basePath === null) {
            $component->basePath = $app->basePath;
            Dumper::register([
                'showSource' => $component->showSource,
                'editor' => $component->editor,
                'basePath' => $component->basePath,
                'prettyModels' => $component->prettyModels,
                'extraCollapse' => $component->extraCollapse,
                'maxDepth' => $component->maxDepth,
                'maxItems' => $component->maxItems,
                'maxString' => $component->maxString,
            ]);
        }
    }
}
