<?php

declare(strict_types=1);

namespace bladeknigt89\dd\Caster;

use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Cloner\Stub;
use yii\base\Model;
use yii\db\BaseActiveRecord;

/**
 * Presents Yii models the way Laravel's dd() presents Eloquent models:
 * attributes and state, without the component event machinery.
 */
final class YiiCaster
{
    /**
     * Collapse noisy framework services when they appear inside another dump.
     *
     * @param array<mixed> $properties
     * @return array<mixed>
     */
    public static function cutWhenNested(object $object, array $properties, Stub $stub, bool $isNested): array
    {
        if ($isNested) {
            $stub->cut += count($properties);

            return [];
        }

        return $properties;
    }

    /**
     * @param array<mixed> $properties
     * @return array<mixed>
     */
    public static function castModel(Model $model, array $properties, Stub $stub, bool $isNested): array
    {
        try {
            return self::presentModel($model, $properties);
        } catch (\Throwable) {
            return $properties;
        }
    }

    /**
     * @param array<mixed> $properties
     * @return array<mixed>
     */
    private static function presentModel(Model $model, array $properties): array
    {
        $attributes = $model->getAttributes();
        $visible = [];

        foreach ($properties as $key => $value) {
            if (!is_string($key) || str_contains($key, "\0")) {
                continue;
            }
            if (array_key_exists($key, $attributes)) {
                continue;
            }
            $visible[$key] = $value;
        }

        if ($model->getScenario() !== Model::SCENARIO_DEFAULT) {
            $visible[Caster::PREFIX_VIRTUAL . 'scenario'] = $model->getScenario();
        }

        $visible[Caster::PREFIX_VIRTUAL . 'attributes'] = $attributes;

        $errors = $model->getErrors();
        if ($errors !== []) {
            $visible[Caster::PREFIX_VIRTUAL . 'errors'] = $errors;
        }

        if (!$model instanceof BaseActiveRecord) {
            return $visible;
        }

        $visible[Caster::PREFIX_VIRTUAL . 'isNew'] = $model->getIsNewRecord();

        $old = $model->getOldAttributes();
        if (is_array($old) && $old !== []) {
            $visible[Caster::PREFIX_VIRTUAL . 'old'] = $old;
        }

        $dirty = $model->getDirtyAttributes();
        if ($dirty !== []) {
            $visible[Caster::PREFIX_VIRTUAL . 'dirty'] = $dirty;
        }

        $related = $model->getRelatedRecords();
        if ($related !== []) {
            $visible[Caster::PREFIX_VIRTUAL . 'related'] = $related;
        }

        return $visible;
    }
}
