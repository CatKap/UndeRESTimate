#!/bin/bash

curl --header "Content-Type: application/json" \
  --request POST \
  --data '{"name":"add_host", "deploy_script":"neofetch", "run_script":"shutdown -now"}' \
  "http://localhost:8080/build/"

curl --header "Content-Type: application/json" \
  --request POST \
  --data '{"name":"bestGroup", "builds":["1", "2", "3", "4"]}' \
  "http://localhost:8080/group/"

curl --header "Content-Type: application/json" \
  --request PATCH \
  --data '{"name":"first_build", "builds":["1","2","3","4","5"]}' \
  "http://localhost:8080/group/24/"
