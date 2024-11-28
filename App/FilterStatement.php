<?php

namespace Aksa;

use Fiber;

class FilterStatement
{
    const EQ = '=';
    const LT = '>';
    const GT = '<';
    const LTE = '>=';
    const GTE = '<=';
    const IN = 'IN';
    const AND = 'AND';
    const OR = 'OR';
    private $statement;

    function __construct($name = null, $op = null, $value = null)
    {
        $this->statement = [];
        if ($name and $op and $value) {
            $this->pushStatement("", $name, $op, $value);
        }
        return $this;
    }

    public function formatValue(mixed $arg)
    {
        if (!is_array($arg)) {
            $args = explode(',', $arg);
        } else {
            $args = $arg;
        }

        if (sizeof($args) > 1) {
             return array_map('self::formatValue', $args);
        }

        if (is_string($arg)) {
            $arg = str_replace("'", '', $arg);
            $arg = str_replace('"', '', $arg);
            $arg = str_replace('[', '', $arg);
            $arg = str_replace(']', '', $arg);
            $arg = "'" . $arg . "'";
        }

        return $arg;
    }

    public function notEmpty()
    {
        return (bool)sizeof($this->statement);
    }

    public function and($name, $op, $value)
    {
        return $this->pushStatement(self::AND, $name, $op, $value);
    }
    public function or($name, $op, $value)
    {
        return $this->pushStatement(self::OR, $name, $op, $value);
    }


    private function pushStatement($logical, $name, $op, $value)
    {
        if (sizeof($this->statement) == 0) {
            $logical = "";
        }

        $value = self::formatValue($value);

        if (is_array($value)) {
            $s = implode(',', $value);
            array_push($this->statement, "$logical $name IN ($s)");
            return $this;
        } else {
            if ($op == FilterStatement::IN) {
                $op = FilterStatement::EQ;
            }
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

    public function fromQuerry(string $querry)
    {
        $args = [];
        parse_str($querry, $args);
        foreach ($args as $name => $value) {
            $this->pushStatement(self::AND, $name, self::EQ, $value);
        }
    }
}
