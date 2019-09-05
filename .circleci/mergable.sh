set -e

# No need to check for mergability on commits that are already merged.
if [ "$CIRCLE_BRANCH" == "master" ] || [ "$CIRCLE_BRANCH" == "9.x" ] ; then
	exit 0
fi

# We cannot continue unless we have a pull request.
if [ -z "$CIRCLE_PULL_REQUEST" ] ; then
  echo "No CIRCLE_PULL_REQUEST found - skipping merge test."
  exit 0
fi

# Set up a git user
git config --global user.email "nobody@drush.org"
git config --global user.name "Drush Merge Test Bot"

# CIRCLE_PULL_REQUEST=https://github.com/ORG/PROJECT/pull/NUMBER
PR_NUMBER=$(echo $CIRCLE_PULL_REQUEST | sed -e 's#.*/pull/##')

# Display the API call we are using
echo curl https://api.github.com/repos/$CIRCLE_PROJECT_USERNAME/$CIRCLE_PROJECT_REPONAME/pulls/$PR_NUMBER

# Determine which branch this PR is set to merge into
BASE=$(curl https://api.github.com/repos/$CIRCLE_PROJECT_USERNAME/$CIRCLE_PROJECT_REPONAME/pulls/$PR_NUMBER 2>/dev/null | jq -r .base.ref)
echo "Check to see if this PR can still merge into origin/$BASE"

# Test to see if it is mergable
git merge -q -m 'Merge check' origin/$BASE
