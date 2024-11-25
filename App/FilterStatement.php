<?php

namespace Aksa;

class FilterStatement
{
    const EQ = "=";
    const LT = ">";
    const GT = "<";
    private $statement;

    function __construct($name, $op, $value)
    {
        $this->statement = [];
        $this->pushStatement("", $name, $op, $value);
        return $this;
    }

    public function and($name, $op, $value)
    {
        return $this->pushStatement("AND", $name, $op, $value);
    }

    public function or($name, $op, $value)
    {
        return $this->pushStatement("OR", $name, $op, $value);
    }

    private function pushStatement($logical, $name, $op, $value)
    {
        if (is_string($value)) {
            $value = "'" . $value . "'";
        }
        array_push($this->statement, "$logical $name $op $value");
        return $this;
    }


    public function andFilter($anotherFilter)
    {
        $s = $anotherFilter->getStatement();
        array_push($this->statement, "AND ($s)");
        return $this;
    }

    public function orFilter($anotherFilter)
    {
        $s = $anotherFilter->getStatement();
        array_push($this->statement, "OR ($s)");
        return $this;
    }

    public function getStatement()
    {
        return implode(" ", $this->statement);
    }

    #public fromQuerry(string $querry){
    #  $s = str_replace("=[", " IN (", $querry);
    #  $s = str_replace("]", ")", $s);
    #}
}
