# Remote environments
:octicons-tag-24: 15.0+

Drush 15 has no site aliases and does not run commands on remote hosts. [Task](https://taskfile.dev) can fill that gap. A Taskfile holds the connection details for each environment and builds the `ssh` command for you:

```shell
task drush:prod -- cache:rebuild
```

## Setup

1. [Install Task](https://taskfile.dev/docs/installation). This page was tested with Task 3.54.
2. Add a `Taskfile.yml` to your project root. It lists your environments and includes the Taskfile that ships with Drush:

```yaml
version: '3'

vars:
  ENVIRONMENTS:
    map:
      dev:
        host: dev.example.com
        user: deploy
        root: /var/www/dev
        url: https://dev.example.com
      prod:
        host: prod.example.com
        user: deploy
        root: /var/www/prod
        url: https://example.com

includes:
  drush:
    taskfile: vendor/drush/drush/examples/Taskfile.remote.yml
    flatten: true
```

Each environment needs all four keys:

| Key | Purpose |
| --- | --- |
| `host` | Host name to ssh to. |
| `user` | User to ssh as. |
| `root` | Project root on the remote host, the directory that contains `vendor/`. |
| `url` | Site URL, passed to `dr` as `--url`. |

## Usage

| Command | Result |
| --- | --- |
| `task drush:prod -- cache:rebuild` | Run `dr cache:rebuild` on prod. |
| `task drush TARGET=prod -- cache:rebuild` | The same, naming the environment in a variable. |
| `task site:alias` | Print every environment and its details. `task sa` is the short form. |
| `task --dry drush:prod -- cache:rebuild` | Print the `ssh` command without running it. |

Everything after `--` goes to `dr` on the remote host. Each argument is quoted for the remote shell, so this reaches `php:eval` as one argument:

```shell
task drush:dev -- php:eval 'echo "a b";'
```

An unknown environment fails before anything runs, and the error lists the allowed names.

A few Task behaviors to know about:

- **Exit codes.** When the remote command fails, Task exits with its own code. Pass `-x` to get the remote command's exit code instead: `task -x drush:prod -- cache:rebuild`. Scripts and CI want this.
- **Echo.** Task prints the `ssh` command to stderr before running it. Pass `-s` to hide it.
- **Terminals.** A remote terminal is requested only when stdout is a terminal, so `task drush:prod -- sql:dump > dump.sql` writes a clean file.

## The Taskfile

```yaml
--8<-- "examples/Taskfile.remote.yml"
```

Including the file from `vendor` keeps it current with Drush. Copy it into your project instead if you need to change it, for example to add ssh options or to reach a container with `docker exec` or `kubectl exec`.

## Limitations

- Commands that named two sites, such as `sql:sync` and `core:rsync` in earlier versions of Drush, have no equivalent here. Write a task for each such workflow, for example one that pipes a remote `sql:dump` into a local `sql:cli`.
- The environments exist only in the Taskfile. Drush commands and PHP code cannot look them up.
- Every developer and CI image needs Task installed. Composer does not install it.
