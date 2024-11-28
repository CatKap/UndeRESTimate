<?php

namespace Aksa;

require "../../App/FilterStatement.php";

$f = new FilterStatement("id", '=', 5);
$fo = new FilterStatement("name", '<=', 'England');

$fo->or("field", FilterStatement::LT, 5);

echo $f->and("id", FilterStatement::LT, 5)->or("test", FilterStatement::EQ, "20")->andFilter($fo)->getStatement();

$q = new FilterStatement();
$q->fromQuerry("");
echo "\n\n\n";
if ($q->notEmpty()) {
    echo "NOT notEmpty";
} else {
    echo "EMPTY";
}
