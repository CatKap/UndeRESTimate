#!/bin/bash
shopt -s expand_aliases
curl --header "Content-Type: application/json" \
  --request POST \
  --data '{"address":"localhost","password":"123", "username":"root"}' \
  http://localhost:8080/agent/

