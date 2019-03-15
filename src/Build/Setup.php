<?php
namespace Centeron\Grids\Build;

use Closure;
use DB;
use LogicException;
use Centeron\Builder\Blueprint;
use Centeron\Builder\BlueprintsCollection;
use Centeron\Builder\Builder;
use Centeron\Builder\Env;
use Centeron\Builder\Instructions\Base\Instruction;
use Centeron\Builder\Instructions\Mapping\Build;
use Centeron\Builder\Instructions\Mapping\BuildChildren;
use Centeron\Builder\Instructions\CustomInstruction;
use Centeron\Builder\Instructions\Mapping\CallMethodWith;
use Centeron\Builder\Instructions\Mapping\CustomMapping;
use Centeron\Builder\Instructions\Mapping\Rename;
use Centeron\Builder\Instructions\SimpleValueAsField;
use Centeron\Builder\Scaffold;
use Centeron\Grids\Build\Instructions\BuildDataProvider;
use Centeron\Grids\EloquentDataProvider;

/**
 * Class Setup
 *
 * This class prepares environment for Centeron/builder package for usage with grids.
 * Integration with Centeron/builder package allows to construct grids from configuration in form of php array.
 *
 * @See \Grids::make
 *
 * @internal
 * @package Centeron\Grids\Build
 */
class Setup
{
    const COLUMN_CLASS = 'Centeron\Grids\FieldConfig';
    const COMPONENT_CLASS = 'Centeron\Grids\Components\Base\ComponentInterface';
    const GRID_CLASS = 'Centeron\Grids\GridConfig';
    const FILTER_CLASS = 'Centeron\Grids\FilterConfig';

    /**
     * @var BlueprintsCollection
     */
    protected $blueprints;

    /**
     * Creates blueprints required to construct grids from configuration.
     *
     * @return Builder
     */
    public function run()
    {
        $this->blueprints = Env::instance()->blueprints();
        $this->blueprints
            ->add($this->makeFilterBlueprint())
            ->add($this->makeFieldBlueprint())
            ->add($this->makeComponentBlueprint())
            ->add($config_blueprint = $this->makeConfigBlueprint());
        return new Builder($config_blueprint);
    }

    /**
     * Creates main blueprint of grid configuration.
     *
     * @return Blueprint
     */
    protected function makeConfigBlueprint()
    {
        $component_blueprint = $this->blueprints->getFor(self::COMPONENT_CLASS);
        if (!$component_blueprint) {
            throw new LogicException(
                'Blueprint for grid components must be created before main blueprint.'
            );
        }

        $column_blueprint = $this->blueprints->getFor(self::COLUMN_CLASS);
        if (!$column_blueprint) {
            throw new LogicException(
                'Blueprint for grid columns must be created before main blueprint.'
            );
        }

        $b = new Blueprint(self::GRID_CLASS, [
            new BuildDataProvider(),
            new CustomInstruction(function (Scaffold $s) {
                /** @var EloquentDataProvider $provider */
                $provider = $s->getInput('data_provider');
                $is_eloquent = $provider  instanceof EloquentDataProvider;

                if ($is_eloquent && !$s->getInput('columns')) {
                    $table = $provider->getBuilder()->getModel()->getTable();
                    $columns = DB
                        ::connection()
                        ->getSchemaBuilder()
                        ->getColumnListing($table);
                    $s->input['columns'] = $columns;

                }
            }, Instruction::PHASE_PRE_INST),
            new BuildChildren(
                'components',
                $component_blueprint
            ),
            new Build('row_component', $component_blueprint),
            new BuildChildren(
                'columns',
                $column_blueprint
            ),
        ]);
        return $b;
    }

    /**
     * Creates blueprint for grid components.
     *
     * @return Blueprint
     */
    protected function makeComponentBlueprint()
    {
        $blueprint = new Blueprint(self::COMPONENT_CLASS, [

            new CustomInstruction(function (Scaffold $s) {
                if ($s->input instanceof Closure) {
                    $s->class = 'Centeron\Grids\Components\RenderFunc';
                    $s->constructor_arguments = [$s->input];
                    $s->input = [];
                } elseif (is_string($s->input)) {
                    $s->class = 'Centeron\Grids\Components\RenderFunc';
                    $out = $s->input;
                    $s->constructor_arguments = [function () use ($out) {
                        return $out;
                    }];
                    $s->input = [];
                }
            }, Instruction::PHASE_PRE_INST),
            new CustomMapping('type', function ($type, Scaffold $s) {
                if (strpos($type, '\\') !== false) {
                    $s->class = $type;
                } else {
                    $s->class = 'Centeron\Grids\Components\\' . str_replace(
                            ' ',
                            '',
                            ucwords(str_replace(array('-', '_'), ' ', $type))
                        );
                }
            }, null, Instruction::PHASE_PRE_INST)
        ]);
        $blueprint->add(new BuildChildren('components', $blueprint));

        $blueprint->add(new Rename('component', 'add_component'));
        $blueprint->add(new Build('add_component', $blueprint));
        $blueprint->add(new CallMethodWith('add_component','addComponent'));

        return $blueprint;
    }

    /**
     * Creates blueprint for filters.
     *
     * @return Blueprint
     */
    protected function makeFilterBlueprint()
    {
        return new Blueprint(self::FILTER_CLASS, [
            new SimpleValueAsField('name'),
            new CustomMapping('type', function ($type, Scaffold $s) {
                switch($type) {
                    case 'select':
                        $s->class = 'Centeron\Grids\SelectFilterConfig';
                        break;
                    default:
                        break;
                }
            }, null, Instruction::PHASE_PRE_INST),
            new Rename(0,'name'),
            new Rename(1,'operator'),
        ]);
    }

    /**
     * Creates blueprint for grid columns.
     *
     * @return Blueprint
     */
    protected function makeFieldBlueprint()
    {
        $filter_blueprint = $this->blueprints->getFor(self::FILTER_CLASS);
        if (!$filter_blueprint) {
            throw new LogicException(
                'Blueprint for grid filters must be created before grid columns blueprint.'
            );
        }
        return new Blueprint(self::COLUMN_CLASS, [
            new SimpleValueAsField('name'),
            new Rename(0,'name'),
            new BuildChildren('filters', $filter_blueprint),

            new Rename('filter', 'add_filter'),
            new Build('add_filter', $filter_blueprint),
            new CallMethodWith('add_filter','addFilter'),
        ]);
    }
}
