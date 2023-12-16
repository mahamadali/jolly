<?php

namespace Models\Traits;

use Bones\Database;
use Bones\Str;
use Bones\DatabaseException;
use Bones\Skeletons\Database\Builder;
use InvalidArgumentException;
use Models\Base\Model;
use Models\Base\Supporters\Transform;

trait SelfResolve
{
    protected $reserved_props = ['_reserved_model_prop_is_only', '_reserved_model_prop_is_cloned'];

    public function prepareSave()
    {
        $modelData = [];

        foreach ($this->dynamic_attributes as $attribute) {
            if (!in_array($attribute, array_merge($this->attaches, $this->reserved_props)))
                if (isset($this->dynamicProps[$attribute]))
                    $modelData[$attribute] = $this->dynamicProps[$attribute];
        }

        if ($this->isCloned()) {
            $modelData[$this->primary_key] = null;
        }

        if (!empty($modelData[$this->primary_key])) {
            return $this->where($this->primary_key, $modelData[$this->primary_key])->update($this->prepareDataToSave($modelData), true);
        } else {
            return $this->prepareInsert($modelData);
        }

        throw new DatabaseException('Database error occured while saving ' . $this->model);
    }
    
    public function prepareCreate(array $createData = [])
    {
        $elements = (!empty($this->elements)) ? $this->elements : [];
        if (!empty($elements)) {
            foreach ($elements as $element) {
                if (!array_key_exists($element, $createData)) {
                    if (!Str::empty($this->defaults[$element])) {
                        $insertData[$element] = $this->defaults[$element];
                    } else {
                        throw new DatabaseException('{' . $element . '} presents in ' . $this->model . '::elements but not available in arguments Array in method ' . __FUNCTION__ . '(Array)');
                    }
                } else {
                    $insertData[$element] = $createData[$element];
                }
            }
        } else {
            foreach ($createData as $param => $value) {
                $insertData[$param] = $value;
            }
            if (!empty($this->defaults)) {
                foreach ($this->defaults as $element => $default) {
                    if (!isset($insertData[$element]))
                        $insertData[$element] = $default;
                }
            }
        }

        return $this->prepareInsert($insertData);
    }

    public function prepareInsertGetId($insertData)
    {
        return $this->prepareInsert($insertData, true);
    }

    public function prepareInsert($insertData, $return_last_inserted_id = false)
    {
        if ($return_last_inserted_id)
            return $this->db->insertGetId($this->prepareDataToSave($insertData));
        else
            return (new $this->model())->find($this->db->insertGetId($this->prepareDataToSave($insertData)));
    }

    public function prepareUpdate($updateData, $return_model = false)
    {
        return $this->db->update($this->prepareDataToSave($updateData, true), $return_model);
    }

    public function prepareDataToSave($data, $is_update = false)
    {
        if (!empty($this->defaults) && !$is_update) {
            foreach ($this->defaults as $element => $default) {
                if (!array_key_exists($element, $data))
                    $data[$element] = $default;
            }
        }

        if (!empty($this->transforms)) {
            foreach ($data as $elementName => &$elementVal) {
                if (array_key_exists($elementName, $this->transforms)) {
                    $elementVal = $this->transformElement($this->transforms[$elementName], $elementVal);
                }
            }
        }

        return $data;
    }

    public function prepareInsertMulti($multiInsertData)
    {
        if (count($multiInsertData) === count($multiInsertData, COUNT_RECURSIVE))
            throw new InvalidArgumentException('insertMulti() must have multi-dimensional array at argument #1');

        if (!empty($this->defaults)) {
            foreach ($multiInsertData as $insertPairIndex => $insertData) {
                foreach ($this->defaults as $element => $default) {
                    if (!array_key_exists($element, $insertData))
                        $multiInsertData[$insertPairIndex][$element] = $default;
                }
            }
        }

        if (!empty($this->transforms)) {
            foreach ($multiInsertData as $insertPairIndex => &$insertData) {
                foreach ($insertData as $elementName => &$elementVal) {
                    if (array_key_exists($elementName, $this->transforms)) {
                        $elementVal = $this->transformElement($this->transforms[$elementName], $elementVal);
                    }
                }
            }
        }

        return $this->db->insertMulti($multiInsertData);
    }

    public function clone()
    {
        $clone = (new $this->model());

        foreach ($this->dynamic_attributes as $attrName) {
            $clone->$attrName = $this->$attrName;
        }

        return $clone->where($clone->primary_key, $clone->{$clone->primary_key})->setCloned(true)->first();
    }

    public function validateConditionalAction($whereData = [], $additionalData = [])
    {
        if (gettype($whereData) != 'array')
            throw new InvalidArgumentException(__FUNCTION__ . ' must have an array as argument #1');
        if (gettype($additionalData) != 'array')
            throw new InvalidArgumentException(__FUNCTION__ . ' must have an array as argument #2');
    }

    public function matchedFirst($whereData = [], $additionalData = [])
    {
        foreach ($whereData as $attrName => $attrVal) {
            $this->where($attrName, $attrVal);
        }

        if (!empty($first = $this->first()))
            return $first;
        
        return false;
    }

    public function conditionalFirst($whereData = [], $additionalData = [], $action = 'new')
    {
        $this->validateConditionalAction($whereData, $additionalData);

        if ($first = $this->matchedFirst($whereData, $additionalData))
            return $first;
        
        $model = new $this->model();
        
        if ($action == 'new') {
            foreach (array_merge($whereData, $additionalData) as $attrName => $attrVal) {
                $model->dynamicProps[$attrName] = $attrVal;
            }

            return $model;
        } else if ($action == 'create') {
            return $model->prepareInsert(array_merge($whereData, $additionalData));
        }

        return false;
    }

    public function conditionalUpdate($whereData = [], $additionalData = [], $action = 'create')
    {
        $this->validateConditionalAction($whereData, $additionalData);

        $first = $this->matchedFirst($whereData, $additionalData);

        if ($first) {
            $data = $additionalData;
            $model = $first;
        } else {
            $data = array_merge($whereData, $additionalData);
            $model = new $this->model();
        }

        foreach ($data as $attrName => $attrVal) {
            $model->dynamicProps[$attrName] = $attrVal;
        }

        return $model->save();
    }
    
    public function selfBuild($model_obj_skeleton, $attributes, $entries, $is_self_only = false)
    {
        $model = $model_obj_skeleton;
        $model = $this->build($model, $attributes);
        $model->setSelfOnly($is_self_only);
        $model->db->model = $model;
        $model = $this->buildWithBlocks($model, $entries);

        return $model;
    }

    public function build($model, $attributes)
    {
        return $this->attachBehaviour($model, $attributes);
    }

    public function buildWithBlocks($model, $entries)
    {
        if (!$this->skip_relationships) {
            foreach (array_unique($this->with) as $with) {
                $without = $this->circularWiths($this->$with());
                if (in_array($with, $this->without)) continue;
                $this->dynamicProps[$with] = $this->$with()->prepareWithout($without);
                // opd($this->dynamicProps[$with]->relationalProps);
                if (!empty($relationalProps = $this->dynamicProps[$with]->relationalProps) && !empty($this->dynamicProps[$with]->relationalProps['type'])) {
                    $model = $this->buildRelationalData($with, $entries, $model, $relationalProps);
                }
            }
        }

        return $model;
    }

    public function attachBehaviour($model, $attributes)
    {
        foreach ($attributes as $attribute => $value) {
            $model->dynamicProps[$attribute] = $value;
        }

        if (isset($this->skip_attaches) && !$this->skip_attaches) {
            foreach ($this->prop('attaches') as $attach) {
                $model->dynamicProps[$attach] = $model->$attach;
            }
        }

        if (!empty($transforms = $this->prop('transforms'))) {
            foreach ($model->dynamicProps as $element_name => &$element_val) {
                if (array_key_exists($element_name, $transforms) && !in_array($element_name, $this->prop('hidden'))) {
                    $element_val = $this->transformElement($transforms[$element_name], $element_val, 'get');
                }
            }
        }

        foreach ($attributes as $attribute => $value) {
            $attribute_method = 'get'.Str::decamelize($attribute).'Property';
            if (method_exists($this->model, $attribute_method)) {
                $model->dynamicProps[$attribute] = $model->$attribute_method();
            }
        }

        foreach ($this->prop('hidden') as $confidential_attr) {
            if (isset($model->dynamicProps[$confidential_attr])) 
                unset($model->dynamicProps[$confidential_attr]);
        }

        if ($this->isCloned()) {
            $model->setCloned(true);
        }

        return $model;
    }

    public function circularWiths($model)
    {
        if (get_class($model) === Builder::class)
            $model = $model->model;
        else
            $model = $model;

        $circular_withs = [];
        foreach ($model->with as $relative_with) {
            $relative_with_obj = $model->$relative_with();
            if (get_Class($relative_with_obj) === get_class($this))
                $circular_withs[] = $relative_with;
        }

        return $circular_withs;
    }

    public function buildRelationalData($with, $entries, $result, $relational_props)
    {
        if ($this->prop('skip_relationships')) return $result;
        
        if ($relational_props['type'] == 'hasMany') {
            if (is_array($entries)) {
                $forein_key_values_to_map = array_map(function($item) use ($relational_props) {
                    if (isset($item->{$relational_props['local_key']}))
                        return $item->{$relational_props['local_key']};
                }, $entries);
            } else {
                $forein_key_values_to_map = [$entries->{$relational_props['local_key']}];
            }

            if (!empty($forein_key_values_to_map)) {
                $related_model_obj = new $relational_props['related_model']();
                $related_model_obj = $related_model_obj->prepareWithout($this->circularWiths($related_model_obj))->whereIn($relational_props['foreign_key'], array_unique($forein_key_values_to_map));
                
                $relational_data = $related_model_obj->getWithoutSetWrapper();

                if ($relational_data != null) {
                    if (is_array($entries)) {
                        foreach ($result as &$entry) {
                            $entry->dynamicProps[$with] = [];
                            foreach ($relational_data as $withEntry) {
                                if ($entry->{$relational_props['local_key']} == $withEntry->{$relational_props['foreign_key']}) {
                                    $entry->dynamicProps[$with][] = $withEntry;
                                }
                            }
                        }
                    } else {
                        $result->dynamicProps[$with] = $relational_data;
                    }
                } else {
                    $result = $this->setDroplets($result, $with, []);
                }
            }

        } else if ($relational_props['type'] == 'parallelTo') {
            if (is_array($entries)) {
                $forein_key_values_to_map = array_map(function($item) use ($relational_props) {
                    if (isset($item->{$relational_props['foreign_key']}))
                        return $item->{$relational_props['foreign_key']};
                }, $entries);
            } else {
                $forein_key_values_to_map = [$entries->{$relational_props['foreign_key']}];
            }

            if (!empty($forein_key_values_to_map)) {
                $related_model_obj = new $relational_props['related_model']();
                
                $related_model_obj = $related_model_obj->prepareWithout($this->circularWiths($related_model_obj))->whereIn($relational_props['local_key'], array_unique($forein_key_values_to_map));

                $relational_data = $related_model_obj->getWithoutSetWrapper();

                if ($relational_data != null) {
                    if (is_array($entries)) {
                        foreach ($result as &$entry) {
                            $entry->dynamicProps[$with] = null;
                            foreach ($relational_data as $withEntry) {
                                if ($entry->{$relational_props['foreign_key']} == $withEntry->{$relational_props['local_key']}) {
                                    $entry->dynamicProps[$with] = $withEntry;
                                }
                            }
                        }
                    } else {
                        $result->$with = $relational_data[0];
                    }
                } else {
                    $result = $this->setDroplets($result, $with, null);
                }
            }

        } else if ($relational_props['type'] == 'hasOne') {
            if (is_array($entries)) {
                $forein_key_values_to_map = array_map(function($item) use ($relational_props) {
                    if (isset($item->{$relational_props['local_key']}))
                        return $item->{$relational_props['local_key']};
                }, $entries);
            } else {
                $forein_key_values_to_map = [$entries->{$relational_props['local_key']}];
            }

            if (!empty($forein_key_values_to_map)) {
                $related_model_obj = new $relational_props['related_model']();
                
                $related_model_obj = $related_model_obj->prepareWithout($this->circularWiths($related_model_obj))->whereIn($relational_props['foreign_key'], array_unique($forein_key_values_to_map));

                $relational_data = $related_model_obj->getWithoutSetWrapper();

                if ($relational_data != null) {
                    if (is_array($entries)) {
                        foreach ($result as &$entry) {
                            $entry->dynamicProps[$with] = null;
                            foreach ($relational_data as $withEntry) {
                                if ($entry->{$relational_props['local_key']} == $withEntry->{$relational_props['foreign_key']}) {
                                    $entry->dynamicProps[$with] = $withEntry;
                                }
                            }
                        }
                    } else {
                        $result->$with = $relational_data[0];
                    }
                } else {
                    $result = $this->setDroplets($result, $with, null);
                }
            }

        } else if ($relational_props['type'] == 'hasOneVia') {
            
            $final_model = $relational_props['final_model'];
            $intermediate_model = $relational_props['intermediate_model'];
            $intermediate_model_foreign_key = $relational_props['intermediate_model_foreign_key'];
            $final_model_foreign_key = $relational_props['final_model_foreign_key'];
            $local_key = $relational_props['local_key'];
            $intermediate_model_local_key = $relational_props['intermediate_model_local_key'];

            if (is_array($entries)) {
                $intermediate_values_to_map = array_map(function($item) use ($local_key) {
                    if (isset($item->{$local_key}))
                        return $item->{$local_key};
                }, $entries);
            } else {
                $intermediate_values_to_map = [$entries->{$local_key}];
            }

            if (!empty($intermediate_values_to_map)) {
                $intermediate_obj = Database::table($intermediate_model->table);
                $intermediate_data = $intermediate_obj->select($intermediate_model_local_key, $intermediate_model_foreign_key)->whereIn($intermediate_model_foreign_key, array_unique($intermediate_values_to_map))->getArray();
                
                if (!empty($intermediate_data)) {
                    $final_model_obj = new $final_model();
                    $final_model_obj = $final_model_obj->whereIn($final_model_foreign_key, array_column($intermediate_data, $intermediate_model_foreign_key));

                    $final_data = $final_model_obj->getWithoutSetWrapper();

                    if (!empty($final_data)) {
                        if (is_array($entries)) {
                            foreach ($result as &$entry) {
                                $entry->dynamicProps[$with] = null;
                                foreach ($intermediate_data as $intermediate) {
                                    $intermediate = (array) $intermediate;
                                    if ($intermediate[$intermediate_model_foreign_key] == $entry->{$local_key}) {
                                        $loop = true;
                                        foreach ($final_data as $final) {
                                            if ($loop && $final->{$final_model_foreign_key} == $intermediate[$intermediate_model_local_key]) {
                                                $entry->dynamicProps[$with] = $final;
                                                $loop = false;
                                            }
                                        }
                                    }
                                }    
                            }
                        } else {
                            $result->$with = $final_data[0];
                        }
                    } else {
                        $result = $this->setDroplets($result, $with, null);
                    }

                }

            }

        } else if ($relational_props['type'] == 'hasManyVia') {
            $final_model = $relational_props['final_model'];
            $intermediate_model = $relational_props['intermediate_model'];
            $intermediate_model_foreign_key = $relational_props['intermediate_model_foreign_key'];
            $final_model_foreign_key = $relational_props['final_model_foreign_key'];
            $local_key = $relational_props['local_key'];
            $intermediate_model_local_key = $relational_props['intermediate_model_local_key'];

            if (is_array($entries)) {
                $intermediate_values_to_map = array_map(function($item) use ($local_key) {
                    if (isset($item->{$local_key}))
                        return $item->{$local_key};
                }, $entries);
            } else {
                $intermediate_values_to_map = [$entries->{$local_key}];
            }

            if (!empty($intermediate_values_to_map)) {
                $intermediate_obj = Database::table($intermediate_model->table);
                $intermediate_data = $intermediate_obj->select($intermediate_model_local_key, $intermediate_model_foreign_key)->whereIn($intermediate_model_foreign_key, array_unique($intermediate_values_to_map))->getArray();
                
                if (!empty($intermediate_data)) {
                    $final_model_obj = new $final_model();
                    $final_model_obj = $final_model_obj->prepareWithout($this->circularWiths($final_model_obj))->whereIn($final_model_foreign_key, array_column($intermediate_data, $intermediate_model_foreign_key));

                    $final_data = $final_model_obj->getWithoutSetWrapper();

                    if (!empty($final_data)) {
                        if (is_array($entries)) {
                            foreach ($result as &$entry) {
                                $entry->dynamicProps[$with] = [];
                                foreach ($intermediate_data as $intermediate) {
                                    $intermediate = (array) $intermediate;
                                    if ($intermediate[$intermediate_model_foreign_key] == $entry->{$local_key}) {
                                        $loop = true;
                                        foreach ($final_data as $final) {
                                            if ($loop && $final->{$final_model_foreign_key} == $intermediate[$intermediate_model_local_key]) {
                                                $entry->dynamicProps[$with][] = $final;
                                            }
                                        }
                                    }
                                }    
                            }
                        } else {
                            $result->$with = $final_data;
                        }
                    } else {
                        $result = $this->setDroplets($result, $with, []);
                    }

                }

            }

        } else if ($relational_props['type'] == 'belongsToMany') {
            $final_model = $relational_props['final_model'];
            $intermediate_table = $relational_props['intermediate_table'];
            $intermediate_to_primary_foreign_key = $relational_props['intermediate_to_primary_foreign_key'];
            $intermediate_to_secondary_foreign_key = $relational_props['intermediate_to_secondary_foreign_key'];
            $local_key = $relational_props['local_key'];
            $final_model_local_key = $relational_props['final_model_local_key'];

            if (is_array($entries)) {
                $forein_key_values_to_map = array_map(function($item) use ($local_key) {
                    if (isset($item->{$local_key}))
                        return $item->{$local_key};
                }, $entries);
            } else {
                $forein_key_values_to_map = [$entries->{$local_key}];
            }

            if (!empty($forein_key_values_to_map)) {
                
                $intermediate_data = Database::table($intermediate_table);
                $intermediate_data = $intermediate_data->whereIn($intermediate_to_primary_foreign_key, array_unique($forein_key_values_to_map))->pluckAssoc([$intermediate_to_primary_foreign_key, $intermediate_to_secondary_foreign_key]);
                
                if (!empty($intermediate_data)) {
                    $final_model_obj = new $final_model();
                    $final_model_obj = $final_model_obj->prepareWithout($this->circularWiths($final_model_obj))->whereIn($final_model_local_key, array_unique(array_column($intermediate_data, $intermediate_to_secondary_foreign_key)));

                    $final_data = $final_model_obj->getWithoutSetWrapper();

                    if (!empty($final_data)) {
                        if (is_array($entries)) {
                            foreach ($result as &$entry) {
                                $entry->dynamicProps[$with] = [];
                                foreach ($intermediate_data as $intermediate) {
                                    if ($intermediate->$intermediate_to_primary_foreign_key == $entry->{$local_key}) {
                                        $loop = true;
                                        foreach ($final_data as $final) {
                                            if ($loop && $final->{$final_model_local_key} == $intermediate->$intermediate_to_secondary_foreign_key) {
                                                $entry->dynamicProps[$with][] = $final;
                                            }
                                        }
                                    }
                                }    
                            }
                        } else {
                            $result->$with = $final_data;
                        }
                    } else {
                        $result = $this->setDroplets($result, $with, []);
                    }
                    
                }
            }

        }

        return $result;
    }

    public function setDroplets($set, $to, $droplet)
    {
        if (!$this->prop('skip_relationships')) {
            if ($set instanceof Model) 
                $set->dynamicProps[$to] = $droplet;
            else
                foreach ($set as &$entry) {
                    if (gettype($entry) == 'object') {
                        $entry->$to = $droplet;
                    }
                }
        }

        return $set;
    }

    public function transformElement($transformType, $value, $operation = 'set')
    {
        return (new Transform($transformType, $value, $operation))->mutate();
    }

    public function isSelfOnly()
    {
        return (!empty($this->_reserved_model_prop_is_only) && $this->_reserved_model_prop_is_only);
    }

    public function setSelfOnly($isOnly = false)
    {
        $this->_reserved_model_prop_is_only = $isOnly;
        return $this;
    }

    public function isCloned()
    {
        return (!empty($this->_reserved_model_prop_is_cloned) && $this->_reserved_model_prop_is_cloned);
    }

    public function setCloned($isCloned = false)
    {
        $this->_reserved_model_prop_is_cloned = $isCloned;
        return $this;
    }
    
}