<?php

namespace Aksa;

use Amp\Postgres\PostgresConnectionPool;
use Aksa\FilterStatement;

function INT(mixed $value)
{
    return (int) $value;
}

function STRING(mixed $value)
{
    return (string) $value;
}

class ModelHandler
{
    const INT = 'INT';
    const STRING = 'STRING';

    public readonly string $tableName;
    private array $localData;
    private array $privateData;
    private bool $isValid;
    private string $colsNames;
    protected PostgresConnectionPool $pool;
    protected array $privateFields;
    protected array $schema;
    protected array $refs;


    function __construct(
        PostgresConnectionPool $pool,
        string $tableName,
        $schema,
        $privateFields = null
    ) {
        $this->pool = $pool;
        $this->tableName = $tableName;
        $this->isValid = false;
        $this->localData = [];
        $this->privateData = [];
        $this->refs = [];

        $schema['id'] = 'INT';

        // Had providing to the db names
        foreach ($schema as $name => $type) {
            if (is_array($type)) {
              // Relation handling
                $this->refs[$name] = call_user_func($type, $this);
                unset($schema[$name]);
                continue;
            }
            $this->schema[$name] = 'Aksa\\' . $type;
        }

        $this->colsNames = implode(',', array_keys($this->schema));
    }

    public static function getCol(mixed $queryArray, string $colName)
    {
        foreach ($queryArray as $row) {
            yield $row[$colName];
        }
    }

    static function relName(ModelHandler $md)
    {
        return strtolower($md->tableName) . '_id';
    }


    static function getSqlInsert(array $arrays)
    {
        $s = "";
        $n = "";
        $sep = "";
        foreach ($arrays as $data) {
            foreach ($data as $name => $field) {
                if (is_array($field)) {
                    continue;
                }

                if (is_string($field)) {
                    $s = $s . $sep . "'" . $field . "'";
                } else {
                    $s = $s . (string) $field;
                }
                $n = $n . $sep . $name;
                if ($sep == "") {
                    $sep = ", ";
                }
            }
        }
        return [$n, $s];
    }

    public function nameToValue($name, $value)
    {
        if (!isset($this->refs[$name])) {
            return "$name = '$value'";
        }
    }

    public function checkRelExsist($relname)
    {
        if (!$this->privateFields[$relname]) {
            throw \Exeption("No such relation $relname!");
            return false;
        }
        return true;
    }

    public function serialize(string $jsonData, bool $set = true, bool $partialUpdate = false)
    {
        $this->isValid = false;
        $decoded = json_decode($jsonData);
        $i = 0;
        $refValid = true;
        foreach ($decoded as $name => $value) {
            if (isset($this->refs[$name]) and $set) {
                $refValid = false;
                if ($this->refs[$name]->checkExsitRefs($this, $value)) {
                    $this->localData[$name] = $value;
                    $refValid = true;
                }
                continue;
            }
            if (isset($this->schema[$name])) {
                $i += 1;
                if ($set) {
                    $this->localData[$name] = call_user_func($this->schema[$name], $value);
                }
            } else {
                throw new \Exception("The field does not exsist!");
            }
        }
        var_dump($refValid, $partialUpdate);
        if ($refValid) {
            if ($partialUpdate) {
                $this->isValid = true;
            } else {
                if ($i == sizeof($this->schema)) {
                    $this->isValid = true;
                }
            }
        }

        if ($this->isValid) {
            return $this;
        }
        return null;
    }


    public function getById(int $id, $set = true, $userid = null, $nested = false, $getPrivate = false)
    {
        $f = new FilterStatement("id", FilterStatement::EQ, $id);
        $res = $this->get($f, $userid, $getPrivate);
        if (sizeof($res) == 1) {
            $data = iterator_to_array($res)[0];
            if ($set) {
                foreach ($data as $key => $value) {
                    if (isset($this->privateFields[$key])) {
                        $this->privateData[$key] = call_user_func($this->privateFields[$key], $value);
                    } else {
                        $localData[$key] = call_user_func($this->schema[$key], $value);
                    }
                }
            }
            if ($nested) {
                foreach ($this->refs as $refName => $ref) {
                    $data[$refName] = $ref->getRefs($this, $id);
                }
            }
            return $data;
        }
        return false;
    }

    public function get(FilterStatement $filter = null, $userid = null, $nested = true, $getPrivate = false)
    {
        $colnames = $getPrivate ? "*" : $this->colsNames;
        if (!$getPrivate) {
            $this->privateData = [];
        }

        if ($filter ? $filter->notEmpty() : false) {
            $s = $filter->getStatement();
            $res = $this->pool->execute("SELECT $colnames FROM $this->tableName WHERE ($s)" . ($userid ? "AND userid = $userid" : ""));
        } else {
            $res = $this->pool->execute("SELECT $colnames FROM $this->tableName");
        }

        $data = iterator_to_array($res);
        if ($nested) {
            foreach ($data as &$row) {
                foreach ($this->refs as $refName => $ref) {
                    $row[$refName] = $ref->getRefs($this, $row['id']);
                }
            }
        }
        return $data;
    }

    public function deleteBy(FilterStatement $f, $userid = null)
    {
        $s = $f->getStatement();
        $res = $this->pool->execute("DELETE FROM $this->tableName WHERE ($s)" . ($userid ? "AND userid = $userid" : "") . "RETURNING *");
        return $res;
    }

    public function save(int $id = null, $userid = null) //id using if UPDATE
    {

        $namesAndVals = self::getSqlInsert([$this->localData, $this->privateData]);
        $names = $namesAndVals[0];
        $vals = $namesAndVals[1];

        $provideUserIdName = $userid ? ', user_id' : '';
        $provideUserId = $userid ? ", $userid" : '';
        if ($id === null) {
            // Create a new instanse
            $created = $this->pool->query("INSERT INTO $this->tableName ($names $provideUserIdName) VALUES ($vals $provideUserId) RETURNING id");
        } else {
            $updated = implode(
                ', ',
                array_filter(array_map([$this, 'nameToValue'], array_keys($this->localData), array_values($this->localData)), static function ($var) {
                    return $var !== null;
                })
            );
            if ($updated) {
        // Save a exsisting
                $checkUser = $userid ? "AND user_id = $userid" : '';
                $created = $this->pool->query("UPDATE 
                $this->tableName SET $updated WHERE id = $id $checkUser RETURNING id");
            }
        }
        if ($created) {
            $arr = iterator_to_array($created);
            var_dump($arr);
            if (sizeof($arr) == 1) {
                $id = $arr[0]['id'];
            } else {
                return null;
            }
        }

        foreach ($this->refs as $refName => $refModel) {
            if (isset($this->localData[$refName])) {
                $refModel->changeRefs($this, $id, $this->localData[$refName]);
            }
        }
        return $id;
    }

    public function isValid()
    {
        return $this->isValid;
    }

    public function data()
    {
        return $this->localData;
    }

    /* Only id is acceptable */
    public function changeRefs(ModelHandler $otherHandler, int $thisId, array $otherIds)
    {
        $relname = self::relName($otherHandler);
        $this->checkRelExsist($relname);
        $idStr = implode(',', $otherIds);

        return $this->pool->query("UPDATE $this->tableName SET $relname = $thisId WHERE id IN ($idStr) RETURNING id");
    }

    public function getRefs(ModelHandler $otherHandler, mixed $ids): array
    {
        $relname = self::relName($otherHandler);
        $this->checkRelExsist($relname);
        $f = new FilterStatement($relname, FilterStatement::IN, $ids);
        return iterator_to_array($this->get($f));
    }

    public function checkExsitRefs(ModelHandler $otherHandler, mixed $ids): bool
    {
        $relname = self::relName($otherHandler);
        $this->checkRelExsist($relname);
        $idsFilter = new FilterStatement("id", FilterStatement::IN, $ids);
        if (sizeof($ids) == sizeof(iterator_to_array($this->get($idsFilter)))) {
            return true;
        }
        return false;
    }

    public function set($name, $value)
    {
        /* Relation handling */
        if (isset($this->refs[$name])) {
            if (!$this->refs[$name]->setRefs($this, $value)) {
                throw \Exeption("Wrong refs to $name with $value");
                return $this;
            }
        }

        if (isset($this->schema[$name])) {
            $this->localData[$name] = call_user_func($this->schema[$name], $value);
            return $this;
        } else {
            throw \Exception("No field $name in model $this->tableName!\n");
        }
        return null;
    }

    public function setPrivate($name, $value)
    {
        if (isset($this->privateFields[$name])) {
            $this->privateData[$name] = call_user_func($this->privateFields[$name], $value);
        } else {
            throw \Exception("No field $name in model $this->tableName!\n");
        }
    }

    // For adding manyToMany, for example
    public function addRelDirectly(string $name, ModelHandler $relation)
    {
        $this->refs[$name] = $relation;
    }


    public function getRel($name)
    {
        return $this->refs[$name];
    }

    /* Schema creation fucntions
    It will be create link to this object from other */
    protected function oneToManyRel(ModelHandler $other)
    {
        $relname = strtolower($other->tableName) . "_id";
        $this->privateFields[$relname] = self::INT;
        return $this;
    }

    protected function manyToManyRel(ModelHandler $other)
    {
        $nameFirst = strtoupper($this->tableName);
        $nameSecond = strtoupper($other->tableName);
        $tableName = $nameFirst . '_TO_' . $nameSecond;

        $manyToManyHandler = new ManyRelationHandler(
            $this->pool,
            $tableName,
            []
        );
        $manyToManyHandler->oneToManyRel($this);
        $manyToManyHandler->oneToManyRel($other);

        return $manyToManyHandler;
    }
}
