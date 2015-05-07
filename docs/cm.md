# Managing Site Configuration on Drupal 8 with Drush

The Drush config-merge command provides a powerful way to manage updates
between two copies of the same Drupal site.

## Basic Scenario

- The engineering team is updating configuration to support new development.
- Site builders are updating configuration to place blocks, define the data model, and so on.
- These changes need to be combined in a non-destructive way, so that all configuration updates become part of the next deployment.

Drush uses 'git' to merge changes from different sites.  It is a prerequsite
of the config-merge command that the configuration directories be under
git version control.

Imagine that development is done on two branches, 'stage' and 'master'.

           A---B---C stage
          /         \
     D---E---F---G---H master

In this example, 'stage' is what is deployed to the staging server, and
'master' is what the development team is working off of.  In the diagram
above, we have the following commits:

- (E) - The commit that was tagged and deployed to the staging server. This is called the "base" commit.
- (A), (B) and (C) - Configuration commits made on the staging server.
- (F) and (G) - Configuration commits made in development
- (H) - The merge commit combining the work done in development with the work done on the staging server.

## Transport Mechanisms

The `drush config-merge` command provides two ways to transfer
changes from the remote server to the local development server:

- Git branches, using `git push` and `git pull`.
- Raw file copying of all configuration files with `rsync`

### Using Git Branches

Drush config-merge can combine configuration changes by exporting and
committing the latest configuration changes on the staging server, and
then using `git push` to push to the central repository, and `git pull`
on the target machine to pull them in, where they are merged.

**Requirements:**

- In this workflow, the staging server must be able to commit and push to the central repository.
- The working branch must be identified.
- Path to configuration folder must be the same for both sites (Drupal multisites not supported).
- ssh access to the remote server is required.

**Usage:**

    $ drush @dev config-merge @stage --git --branch=stage

## Using rsync to Copy all of the Configuration Files

If the remote server cannot push commits to the central git repository,
then you can use the rsync mechanism (the default).

**Requirements:**

- ssh access to the remote server is required.
- Merged changes should be deployed back to the remote server before more remote configuration changes are made.  Otherwise, the next run of `config-merge` will back out any additional configuration changes made on the dev machine.

**Usage:**

    $ drush @dev config-merge @stage
