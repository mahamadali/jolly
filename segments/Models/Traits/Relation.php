<?php

namespace Models\Traits;

use Bones\Database;
use Bones\Str;
use Closure;
use Bones\BadMethodException;
use Bones\ModelRelationException;

trait Relation
{
    protected $relation_captions = [];

    public function hasMany($relatedModel, $foreignKey = null, $localKey = null)
    {
        $this->setRelationCaption(__FUNCTION__);

        $foreignKey = (!empty($foreignKey)) ? $foreignKey :  Str::singular($this->table) . '_id';
        $localKey = (!empty($localKey)) ? $localKey : $this->primary_key;

        $relatedModel = (new $relatedModel());

        if (!empty($this->$localKey)) {
            $relatedModel = $relatedModel->where($foreignKey, $this->$localKey);
        }

        return $relatedModel->relationalProps([
            'local_model' => $this->model,
            'related_model' => $relatedModel->model,
            'foreign_key' => $foreignKey,
            'local_key' => $localKey,
            'related_model_value' => $this->$localKey,
            'type' => __FUNCTION__
        ]);
    }

    public function hasOne($relatedModel, $foreignKey = null, $localKey = null)
    {
        $this->setRelationCaption(__FUNCTION__);

        $relatedModel = new $relatedModel();

        $foreignKey = (!empty($foreignKey)) ? $foreignKey :  Str::singular($this->table) . '_id';
        $localKey = (!empty($localKey)) ? $localKey : $this->primary_key;
        
        if (!empty($this->$localKey)) {
            $relatedModel = $relatedModel->where($foreignKey, $this->$localKey);
        }

        return $relatedModel->relationalProps([
            'local_model' => $this->model,
            'related_model' => $relatedModel->model,
            'foreign_key' => $foreignKey,
            'local_key' => $localKey,
            'related_model_value' => $this->$localKey,
            'type' => __FUNCTION__
        ]);
    }

    public function parallelTo($relatedModel, $foreignKey = null, $localKey = null)
    {
        $this->setRelationCaption(__FUNCTION__);

        $relatedModel = new $relatedModel();
        $foreignKey = (!empty($foreignKey)) ? $foreignKey : Str::singular($relatedModel->table) . '_id';
        $localKey = (!empty($localKey)) ? $localKey : $this->primary_key;

        return $relatedModel->relationalProps([
            'local_model' => $this->model,
            'related_model' => $relatedModel->model,
            'foreign_key' => $foreignKey,
            'local_key' => $localKey,
            'related_model_value' => $this->$localKey,
            'type' => __FUNCTION__
        ]);
    }

    public function hasOneVia($finalModel, $interMediateModel, $interMediateForeignKey = null, $finalModelForeignKey = null, $localKey = null, $interMediateLocalKey = null)
    {
        $this->setRelationCaption(__FUNCTION__);

        $finalModel = new $finalModel();
        $interMediateModel = new $interMediateModel();

        $interMediateForeignKey = (!empty($interMediateForeignKey)) ? $interMediateForeignKey : Str::singular($this->table) . '_id';
        $finalModelForeignKey = (!empty($finalModelForeignKey)) ? $finalModelForeignKey : Str::singular($interMediateModel->table) . '_id';

        $localKey = (!empty($localKey)) ? $localKey : $this->primary_key;
        $interMediateLocalKey = (!empty($interMediateLocalKey)) ? $interMediateLocalKey : $interMediateModel->primary_key;

        $interMediateModel = $interMediateModel
                ->select("`".$interMediateModel->table ."`.`". $interMediateLocalKey. "` as has_one_via_intermediate_model_value_" . $interMediateLocalKey)
                ->leftJoin($this->table, $this->table . '.' . $localKey, '=', $interMediateModel->table . '.' . $interMediateForeignKey);

        if (!empty($this->$localKey)) {
            $interMediateModel = $interMediateModel->where($interMediateForeignKey, $this->$localKey);
            $intersectData = $interMediateModel->pluck('has_one_via_intermediate_model_value_'.$interMediateLocalKey);
        } else {
            $intersectData = [];
        }
        
        if (!empty($intersectData)) {
            $finalModel = $finalModel->whereIn($localKey, $intersectData)->model;
        } else {
            $finalModel->whereIn($finalModelForeignKey, [-1]);
        }

        return $finalModel->limit(1)->relationalProps([
            'final_model' => $finalModel->model,
            'intermediate_model' => $interMediateModel->model,
            'intermediate_model_foreign_key' => $interMediateForeignKey,
            'final_model_foreign_key' => $finalModelForeignKey,
            'local_key' => $localKey,
            'intermediate_model_local_key' => $interMediateLocalKey,
            'type' => __FUNCTION__
        ]);
    }

    public function hasManyVia($finalModel, $interMediateModel, $interMediateForeignKey = null, $finalModelForeignKey = null, $localKey = null, $interMediateLocalKey = null)
    {
        $this->setRelationCaption(__FUNCTION__);

        $finalModel = new $finalModel();
        $interMediateModel = new $interMediateModel();

        $interMediateForeignKey = (!empty($interMediateForeignKey)) ? $interMediateForeignKey : Str::singular($this->table) . '_id';
        $finalModelForeignKey = (!empty($finalModelForeignKey)) ? $finalModelForeignKey : Str::singular($interMediateModel->table) . '_id';

        $localKey = (!empty($localKey)) ? $localKey : $this->primary_key;
        $interMediateLocalKey = (!empty($interMediateLocalKey)) ? $interMediateLocalKey : $interMediateModel->primary_key;

        $interMediateModel = $interMediateModel
                ->select("`".$interMediateModel->table ."`.`". $interMediateLocalKey. "` as has_many_via_intermediate_model_value_" . $interMediateLocalKey)
                ->leftJoin($this->table, $this->table . '.' . $localKey, '=' , $interMediateModel->table . '.' . $interMediateForeignKey);

        if (!empty($this->$localKey)) {
            $interMediateModel = $interMediateModel->where($interMediateForeignKey, $this->$localKey);
            $intersectData = $interMediateModel->pluck('has_many_via_intermediate_model_value_'.$interMediateLocalKey);
        } else {
            $intersectData = [];
        }
        
        if (!empty($intersectData)) {
            $finalModel = $finalModel->whereIn($finalModelForeignKey, $intersectData)->model;
        } else {
            $finalModel->whereIn($finalModelForeignKey, [-1]);
        }

        return $finalModel->relationalProps([
            'final_model' => $finalModel->model,
            'intermediate_model' => $interMediateModel->model,
            'intermediate_model_foreign_key' => $interMediateForeignKey,
            'final_model_foreign_key' => $finalModelForeignKey,
            'local_key' => $localKey,
            'intermediate_model_local_key' => $interMediateLocalKey,
            'type' => __FUNCTION__
        ]);
    }

    public function belongsToMany($finalModel, $interMediateTable = null, $primaryForeignKey = null, $secondaryForeignKey =  null, $localKey = null, $finalModelLocalKey = null)
    {
        $this->setRelationCaption(__FUNCTION__);

        $finalModel = new $finalModel();
        
        $interMediateTable = (!empty($interMediateTable)) ? $interMediateTable : strtolower(basename($finalModel->model)) . '_' . strtolower(basename($this->model));
        $primaryForeignKey = (!empty($primaryForeignKey)) ? $primaryForeignKey : Str::singular($this->table) . '_id';
        $secondaryForeignKey = (!empty($secondaryForeignKey)) ? $secondaryForeignKey : Str::singular(strtolower($finalModel->table)) . '_id';
        $localKey = (!empty($localKey)) ? $localKey : $this->primary_key;
        $finalModelLocalKey = (!empty($finalModelLocalKey)) ? $finalModelLocalKey : $finalModel->primary_key;

        if (!empty($this->{$localKey})) {
            $interMediateData = Database::table($interMediateTable);
            $interMediateModel = $interMediateData->where($primaryForeignKey, $this->{$localKey});
            $intersectData = $interMediateModel->pluck($secondaryForeignKey);
        }
        
        if (!empty($intersectData)) {
            $finalModel->whereIn($finalModelLocalKey, $intersectData)->model;
        } else {
            $finalModel->whereIn($finalModelLocalKey, [-1]);
        }

        return $finalModel->relationalProps([
            'final_model' => $finalModel->model,
            'intermediate_table' => $interMediateTable,
            'intermediate_to_primary_foreign_key' => $primaryForeignKey,
            'intermediate_to_secondary_foreign_key' => $secondaryForeignKey,
            'local_key' => $localKey,
            'final_model_local_key' => $finalModelLocalKey,
            'type' => __FUNCTION__
        ]);

    }

    public function prepareWhereHas($with, Closure $closure = null)
    {
        $where_has = $this->$with();
        if (!empty($relationalProps = $where_has->relationalProps) && !empty($where_has->relationalProps['type'])) {
            $relationalProps = (object) $relationalProps;
            if ($relationalProps->type == 'hasMany') {
                $relatedModel = (new $relationalProps->related_model());
                $relatedModel->whereRaw("`".$relatedModel->table."`.`".$relationalProps->foreign_key . '`=' . "`".$this->table."`.`".$relationalProps->local_key."`", []);
                if (!empty($closure))
                    $relatedModel->where($closure);
                $this->db->where_exists[] = 'EXISTS('.$relatedModel->selectRawSQL().')';
            }

            if ($relationalProps->type == 'parallelTo') {
                $relatedModel = (new $relationalProps->related_model());
                if (!empty($closure)) {
                    $relatedModel->where($closure);
                    $relatedModel->select($relationalProps->local_key);
                    $this->db->where_exists[] = "`".$relationalProps->foreign_key . "` IN (".$relatedModel->selectRawSQL().")";
                }
            }

            if ($relationalProps->type == 'hasOne') {
                $relatedModel = (new $relationalProps->related_model());
                $relatedModel->whereRaw("`".$relatedModel->table."`.`".$relationalProps->foreign_key . '`=' . "`".$this->table."`.`".$relationalProps->local_key."`", []);
                if (!empty($closure))
                    $relatedModel->where($closure);
                $this->db->where_exists[] = 'EXISTS('.$relatedModel->selectRawSQL().')';
            }

            if ($relationalProps->type == 'belongsToMany') {
                $final_model_obj = (new $relationalProps->final_model())->select($relationalProps->final_model_local_key);
                $final_model_obj->where($closure);
                $final_model_obj_data = $final_model_obj->pluck($relationalProps->final_model_local_key);

                $this->db->where_exists[] = "EXISTS(SELECT * FROM `".$relationalProps->intermediate_table."` WHERE `".$relationalProps->intermediate_table."`.`".$relationalProps->intermediate_to_primary_foreign_key."` = `".$this->table."`.`".$relationalProps->local_key."` AND `".$relationalProps->intermediate_table."`.`".$relationalProps->intermediate_to_secondary_foreign_key."` IN (".implode(',', $final_model_obj_data)."))";
            }
        }

        return $this;
    }

    public function prop($prop)
    {
        return $this->$prop;
    }

    public function add(...$args)
    {
        if (!empty($relation = $this->getRelation('hasOne'))) {

            $relatedModel = $relation['related_model'];

            $checkRelatedDataExistence = $relatedModel->where($relation['foreign_key'], $relation['related_model_value'])->first();

            if (!empty($checkRelatedDataExistence) && !empty($checkRelatedDataExistence->{$relation['foreign_key']})) {
                throw new ModelRelationException($relation['local_model'] . ' has defined ' . $relation['type'] . '() relation with ' . $relatedModel->model . '() so ' . '{' . $relation['foreign_key'] . '} column with value {' . $relation['related_model_value'] . '} must have only one entry in {' . $relatedModel->table . '} table');
            }

            $args[0][$relation['foreign_key']] = $relation['related_model_value'];

            $this->relationalProps = null;

            return $relatedModel->create($args[0]);
        } else if (!empty($relation = $this->getRelation('hasMany'))) {

            if (is_array($args[0])) {

                $relatedModel = $relation['related_model'];

                if (count($args[0]) == count($args[0], COUNT_RECURSIVE)) {
                    $args[0][$relation['foreign_key']] = $relation['related_model_value'];
                    return $relatedModel->insert($args[0]);
                } else {
                    array_walk($args[0], function (&$insertSet) use ($relation) {
                        $insertSet[$relation['foreign_key']] = $relation['related_model_value'];
                    });
                    return $relatedModel->insertMulti($args[0]);
                }
            }
        } else if (!empty($relation = $this->getRelation('parallelTo'))) {
            throw new ModelRelationException('Bad Usage: parallelTo is one to one bind relational so associated value can be set with update function on model directly');
        }

        throw new BadMethodException('Method {' . __FUNCTION__ . '} not found on ' . $this->model);
    }

    public function remove()
    {
        if (!empty($relation = $this->getRelation('hasOne')) || !empty($relation = $this->getRelation('hasMany'))) {

            $relatedModel = $relation['related_model'];

            return $relatedModel->where($relation['foreign_key'], $relation['related_model_value'])->delete();
        } else if (!empty($relation = $this->getRelation('parallelTo'))) {

            $relatedModel = $relation['related_model'];
            return $relatedModel->delete();
        }

        throw new BadMethodException('Method {' . __FUNCTION__ . '} not found on ' . $this->model);
    }

    public function latestOfSet(string $sortingColumn = null)
    {
        if (empty($sortingColumn)) $sortingColumn = $this->primary_key;

        if (!empty($relation = $this->getRelation('hasOne'))) {
                $relatedModel = $relation['related_model']->orderBy($sortingColumn, 'DESC')->limit(1);

            return $relatedModel;
        }
    }

    public function oldestOfSet(string $sortingColumn = null)
    {
        if (empty($sortingColumn)) $sortingColumn = $this->primary_key;

        if (!empty($relation = $this->getRelation('hasOne'))) {
            if (empty($sortingValue))
                $relatedModel = $relation['related_model']->orderBy($sortingColumn, 'ASC')->limit(1);

            return $relatedModel;
        }
    }

    public function getRelation($relation = '')
    {
        if (empty($relation)) return null;

        if (!empty($this->relationalProps)) {
            $this->relationalProps = (!empty($this->relationalProps[0])) ? $this->relationalProps[0] : $this->relationalProps;
            if (!empty($this->relationalProps['related_model']) && !empty($this->relationalProps['type'])) {
                if ($this->relationalProps['type'] == $relation) {
                    return $this->relationalProps;
                }
            }
        }

        return null;
    }

    public function setRelationCaption($relation)
    {
        $this->relation_captions[debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[2]['function']] = $relation;
    }

    public function callRelated($relationCaption)
    {
        if (empty($this->relation_captions[$relationCaption])) {
            throw new ModelRelationException('{'.$relationCaption . '} Relation not attached to ' . $this->model);
        }

        $relation = $this->relation_captions[$relationCaption];

        switch ($relation) {
            case 'hasMany':
                $retrievalCommand = 'get';
                break;
            case 'hasOne':
                $retrievalCommand = 'first';
                break;
            case 'parallelTo':
                $retrievalCommand = 'first';
                break;
            case 'hasOneVia':
                $retrievalCommand = 'first';
                break;
            case 'hasManyVia':
                $retrievalCommand = 'get';
                break;
            case 'belongsToMany':
                $retrievalCommand = 'get';
                break;
            default:
                $retrievalCommand = null;
                break;
        }

        return $retrievalCommand;
    }
}