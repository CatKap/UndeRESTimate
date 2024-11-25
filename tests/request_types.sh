#!/bin/bash
shopt -s expand_aliases
alias test_cmd='curl -H "Accept: application/json" -H "Content-Type: application/json" -H "X-transaction-control: sipadjpvawj34245"'

type test_cmd
echo $1
test_cmd -i  -X GET $1 > ./logs/GET.log
test_cmd -i  -X POST $1 > ./logs/POST.log
test_cmd -i  -X DELETE $1 > ./logs/DELETE.log
