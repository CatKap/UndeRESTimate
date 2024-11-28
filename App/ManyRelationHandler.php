<?php

namespace Aksa;

use Amp\SignalException;

class ManyRelationHandler extends ModelHandler
{
    private array $handlers = [];

    public static function getList(int $thisId, array $otherIds)
    {
        foreach ($otherIds as $id) {
            yield "($thisId, $id)";
        }
    }


    protected function oneToManyRel(ModelHandler $other)
    {
        array_push($this->handlers, $other);
        return parent::oneToManyRel($other);
    }

    public function getAnoterRel(string $relation)
    {
        $keys = array_keys($this->privateFields);
        return $keys[1] == $relation ? $keys[0] : $keys[1];
    }

    public function anoterHandler(ModelHandler $mh)
    {
        if ($mh == $this->handlers[0]) {
            return $this->handlers[1];
        } else {
            return $this->handlers[0];
        }
    }

    function changeRefs(ModelHandler $requestHandler, int $thisId, array $otherIds)
    {
        if (sizeof($otherIds) == 0) {
            return [];
        }
        $rel = self::relName($requestHandler);
        $otherRel = $this->getAnoterRel($rel);
        $otherHandler = $this->anoterHandler($requestHandler);
        $f = new FilterStatement($rel, FilterStatement::EQ, $thisId);
        $f->and($otherRel, FilterStatement::IN, $otherIds);
        $dublicates = iterator_to_array(self::getCol(iterator_to_array($this->get($f, null, true, true)), $otherRel));
        $otherIds = array_diff($otherIds, $dublicates);
        if ($otherIds) {
            $vals = implode(', ', iterator_to_array(self::getList($thisId, $otherIds)));
            $this->pool->query("INSERT INTO $this->tableName ($rel, $otherRel) VALUES $vals");
        }
        $idsFilter = new FilterStatement('id', FilterStatement::IN, $otherIds);
        return $otherHandler->get($idsFilter);
    }

    function checkExsitRefs(ModelHandler $requestHandler, mixed $ids): bool
    {
        $otherHandler = $this->anoterHandler($requestHandler);
        $f = new FilterStatement('id', FilterStatement::IN, $ids);
        $s = $f->getStatement();
        if (!$f->notEmpty()) {
            return true;
        }

        $ret = $otherHandler->pool->query("SELECT (id) FROM $otherHandler->tableName WHERE $s");
        if (sizeof(iterator_to_array($ret)) == sizeof($ids)) {
            return true;
        }
        return false;
    }

    function getRefs(ModelHandler $requstHandler, mixed $ids): array
    {
        $relname = self::relName($requstHandler);
        $retHanlder = $this->anoterHandler($requstHandler);
        $retname = self::relName($retHanlder);
        $f = new FilterStatement($relname, FilterStatement::IN, $ids);
        if (!$statement = $f->getStatement()) {
            return [];
        }
        $retIds = iterator_to_array(self::getCol($this->pool->query("SELECT($retname) FROM $this->tableName WHERE $statement"), $retname));
        if (sizeof($retIds) > 0) {
            $f = new FilterStatement('id', FilterStatement::IN, $retIds);
            return $retHanlder->get($f);
        }
        return [];
    }
}
