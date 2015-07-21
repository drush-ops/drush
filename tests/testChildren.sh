# See https://github.com/drush-ops/drush/pull/1426 for background information.
#
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
    echo "Restarting build $BUILD_NUM for $CHILD"
    curl -X POST https://api.travis-ci.org/builds/$BUILD_NUM/restart --header "Authorization: token "$AUTH_TOKEN
  done
fi
