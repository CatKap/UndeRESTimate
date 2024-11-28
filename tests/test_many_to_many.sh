#!/bin/bash

#echo " Create build"
#
#curl --header "Content-Type: application/json" \
#  --request POST \
#  --data '{"name":"nginx", "deploy_script":"apt install nginx", "run_script":"systemctl start nginx"}' \
#  "http://localhost:8080/build/"
#
#echo " Create file"
#
#curl --header "Content-Type: application/json" \
#  --request POST 5/\
#  --data '{"link":"my.ftp.com/some/uri/nginx.conf"}'\
#  "http://localhost:8080/file/"
#
#echo "link file to the some build"

echo "Put data to the 7th file"
curl --header "Content-Type: application/json" \
  --request PATCH \
  --data '{"link":"mynfs.local/testfile.txt"}'\
  "http://localhost:8080/file/7/"

echo "Adding files to the 5th build\n"
curl --header "Content-Type: application/json" \
  --request PATCH \
  --data '{"files":["1", "2", "7", "6"]}'\
  "http://localhost:8080/build/6/"

curl http://localhost:8080/build/6/

echo "Adding  files to the 2th build\n"
curl --header "Content-Type: application/json" \
  --request PATCH \
  --data '{"name":"testSecond", "files":["1", "3", "7"]}'\
  "http://localhost:8080/build/2/"


curl http://localhost:8080/build/6/
echo "Deleting file 7"
curl -X DELETE http://localhost:8080/files/7/
curl http://localhost:8080/build/6/
curl http://localhost:8080/build/2/

