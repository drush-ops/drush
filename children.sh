# $AUTH_TOKEN is provided via a secure Travis environment variable.
# Secure environment variables are not set for pull requests that
# originated from another repository, so skip child tests if $AUTH_TOKEN
# is empty.
if [ -n "$AUTH_TOKEN" ]
then
  # After a travis build succeeds, run tests from any child repository defined in $TEST_CHILDREN
  for CHILD in $TEST_CHILDREN
  do
    BUILD_NUM=$(curl -s "https://api.travis-ci.org/repos/$CHILD/builds" | grep -o '^\[{"id":[0-9]*,' | grep -o '[0-9]' | tr -d '\n')
    echo "Build number for $CHILD is $BUILD_NUM"
    echo curl -X POST https://api.travis-ci.org/builds/$BUILD_NUM/restart --header "Authorization: token "$AUTH_TOKEN
    curl -X POST https://api.travis-ci.org/builds/$BUILD_NUM/restart --header "Authorization: token "$AUTH_TOKEN
  done
fi
