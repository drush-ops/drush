echo "Auth Token is $AUTH_TOKEN"
for CHILD in $@
do
  BUILD_NUM=$(curl -s 'https://api.travis-ci.org/repos/$CHILD/builds' | grep -o '^\[{"id":[0-9]*,' | grep -o '[0-9]' | tr -d '\n')
  echo "Build number for $CHILD is $BUILD_NUM"
  echo curl -X POST https://api.travis-ci.org/builds/$BUILD_NUM/restart --header "Authorization: token "$AUTH_TOKEN"
done
