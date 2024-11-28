#!/bin/bash
shopt -s expand_aliases
curl --header "Content-Type: application/json" \
  --request PUT \
  --data '{"address":"localhost","password":"123", "username":"root"}' \
  "http://localhost:8080/agent/?control=WEoewjjwvonvwijfeoeiw&test=1"
curl --header "Content-Type: application/json" \
  --request POST \
  --data '{"address":"mydns.best.ru","password":"123", "username":"root"}' \
  "http://localhost:8080/agent/"

