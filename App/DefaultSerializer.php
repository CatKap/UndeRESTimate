<?php

namespace Aksa;

use Amp\Postgres\PostgresConnectionPool;
use Aksa\FilterStatement;
use Amp\Postgres\PostgresTransaction;

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
    public readonly string $tableName;
    private array $localData;
    private bool $isValid;

    private string $colsNames;

    private PostgresConnectionPool $pool;
    private $errorHandler;

    private $updateStatement;
    private $createStatement;
    private $deleteStatement;
    private $getByStatement;

    private $refs;
    private $schema;
    private $privateFields;


    function __construct(
        PostgresConnectionPool $pool,
        $errorHandler,
        string $tableName,
        $schema,
        $privateFields = null
    ) {
        $this->pool = $pool;
        $this->errorHandler = $errorHandler;
        $this->tableName = $tableName;
        $this->isValid = false;
        //$this->getByStatement = $pool->prepare("SELECT $this->colsNames FROM $this->tableName WHERE :statement");
        $this->deleteStatement = $pool->prepare("DELETE FROM $this->tableName WHERE :param  = :value");
        $this->createStatement = $pool->prepare("INSERT INTO $this->tableName (:value_names) VALUES (:values)");
        $this->localData = [];


        $this->schema['id'] = "INT";

        // Had providing to the db names
        foreach ($schema as $name => $type) {
            if (is_string($type)) {
                $this->schema[$name] = 'Aksa\\' . $type;
            } else {
                $this->schema[$name] = $type;
            }
        }

        foreach ($schema as $key => &$field) {
            if (is_array($field)) {
                $this->refs[$key]  = $field;
            }
        }

        $this->colsNames = implode(',', array_keys($this->schema));
    }

    static function nameToValue($name, $value)
    {
        return "$name = $value";
    }

    public function serialize(string $jsonData, bool $set = true, bool $partialUpdate = false)
    {
        $this->isValid = false;
        $decoded = json_decode($jsonData);
        $i = 0;
        foreach ($decoded as $name => $value) {
            echo "$name\n";
            if (isset($this->schema[$name])) {
                $i += 1;
                if ($set) {
                    $this->localData[$name] = call_user_func($this->schema[$name], $value);
                }
            } else {
              // Exeption handing???
                throw new \Exception("The field does not exsist!");
            }
        }

        if ($partialUpdate) {
            $this->isValid = true;
        } else {
            if ($i == sizeof($this->schema)) {
                $this->isValid = true;
            }
        }
        echo "Partial\n";
        if ($this->isValid) {
            return $this;
        }
        return null;
    }


    public function getById(int $id, $set = true, $userid = null, $nested = false)
    {
        $f = new FilterStatement("id", FilterStatement::EQ, $id);
        $res = $this->get($f, $userid);
        if ($res->getRowCount() == 1) {
            $data = iterator_to_array($res)[0];
            if ($set) {
                $localData = $data;
            }

            if ($nested) {
                foreach ($this->refs as $refName => $ref) {
                    $data[$refName] = $ref[0]->getById($data[$refName], $set = false, $userid = $userid, $nested = true);
                }
            }
            return $data;
        }
        return false;
    }

    public function get(FilterStatement $filter = null, $userid = null)
    {
        if ($filter) {
            $s = $filter->getStatement();
            var_dump($this->colsNames);
            $res = $this->pool->execute("SELECT $this->colsNames FROM $this->tableName WHERE ($s)" . ($userid ? "AND userid = $userid" : ""));
        } else {
            $res = $this->pool->execute("SELECT $this->colsNames FROM $this->tableName");
        }

        return $res;
    }

    public function deleteBy(FilterStatement $f, $userid = null)
    {
        $s = $f->getStatement();
        $res = $this->pool->execute("DELETE FROM $this->tableName WHERE ($s)" . ($userid ? "AND userid = $userid" : "") . "RETURNING *");
        return $res;
    }



    public function save(int $id = null, $userid = null) //id using if UPDATE
    {

        $namesAndVals = $this->getSqlInsert();
        $names = $namesAndVals[0];
        $vals = $namesAndVals[1];

        // Create a new instanse
        $provideUserIdName = $userid ? ', user_id' : '';
        $provideUserId = $userid ? ", $userid" : '';
        if ($id === null) {
            $create = $this->pool->query("INSERT INTO $this->tableName ($names $provideUserIdName) VALUES ($vals $provideUserId)");
            return $create;
        }


        $updated = implode(',', array_map('self::nameToValue', $names, $vals));

        // Save a exsisting
        $checkUser = $userid ? "AND user_id = $userid" : '';
        return $this->pool->query("UPDATE $this->tableName SET ($updated) WHERE id = $id $checkUser");
    }

    public function isValid()
    {
        return $this->isValid;
    }

    public function data()
    {
        return $this->localData;
    }

    public function set($name, $value)
    {
        if (isset($this->schema[$name])) {
            $this->localData[$name] = call_user_func($this->schema[$name], $value);
        }
    }

    public function manyRel(ModelHandler $other = null)
    {
        return false;
    }

    private function checkRefs(array $refsData)
    {
        foreach ($refsData as $name => $id) {
            if (!isset($this->refs[$name])) {
                return false;
            }

            if (!$this->refs[$name][0]->getById($id)) {
                return false;
            }
        }
        return true;
    }

    private function getSqlInsert()
    {
        $s = "";
        $n = "";
        $sep = "";
        foreach ($this->localData as $name => $field) {
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
        return [$n, $s];
    }
}
