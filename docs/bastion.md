# Remote Operations on Drupal Sites via a Bastion Server

Wikipedia defines a [bastion server](http://en.wikipedia.org/wiki/Bastion_host) as "a special purpose computer on a network specifically designed and configured to withstand attacks." For the purposes of this documentation, though, any server that you can ssh through to reach other servers will do. Using standard ssh and Drush techniques, it is possible to make a two-hop remote command look and act as if the destination machine is on the same network as the source machine.

## Recap of Remote Site Aliases

Site aliases can refer to Drupal sites that are running on remote machines simply including 'remote-host' and 'remote-user' attributes:

    $aliases['internal'] = array(
        'remote-host' => 'internal.company.com',
        'remote-user' => 'wwwadmin',
        'uri' => 'http://internal.company.com',
        'root' => '/path/to/remote/drupal/root',
    );

With this alias defintion, you may use commands such as `drush @internal status`, `drush ssh @internal` and `drush rsync @internal @dev` to operate remotely on the internal machine. What if you cannot reach the server that site is on from your current network? Enter the bastion server.

## Setting up a Bastion server in .ssh/config

If you have access to a server, bastion.company.com, which you can ssh to from the open internet, and if the bastion server can in turn reach the internal server, then it is possible to configure ssh to route all traffic to the internal server through the bastion. The .ssh configuration file would look something like this:

In **.ssh/config:**

    Host internal.company.com
        ProxyCommand ssh user@bastion.company.com nc %h %p

That is all that is necessary; however, if the dev machine you are configuring is a laptop that might sometimes be inside the company intranet, you might want to optimize this setup so that the bastion is not used when the internal server can be reached directly. You could do this by changing the contents of your .ssh/config file when your network settings change -- or you could use Drush.

# Setting up a Bastion server via Drush configuration

First, make sure that you do not have any configuration options for the internal machine in your .ssh/config file. The next step after that is to identify when you are connected to your company intranet. I like to determine this by using the `route` command to find my network gateway address, since this is always the same on my intranet, and unlikely to be encountered in other places.

In **drushrc.php:**

    # Figure out if we are inside our company intranet by testing our gateway address against a known value
    exec("route -n | grep '^0\.0\.0\.0' | awk '{ print $2; }' 2> /dev/null", $output);
    if ($output[0] == '172.30.10.1') {
      drush_set_context('MY_INTRANET', TRUE);
    }

After this code runs, the 'MY\_INTRANET' context will be set if our gateway IP address matches the expected value, and unset otherwise. We can make use of this in our alias files.

In **aliases.drushrc.php:**

    if (drush_get_context('MY_INTRANET', FALSE) === FALSE) {
      $aliases['intranet-proxy'] = array(
        'ssh-options' => ' -o "ProxyCommand ssh user@bastion.company.com nc %h %p"',
      );
    }
    $aliases['internal-server'] = array(
      'parent' => '@intranet-proxy',
      'remote-host' => 'internal.company.com',
      'remote-user' => 'wwwadmin',
    );
    $aliases['internal'] = array(
      'parent' => '@internal-server',
      'uri' => 'http://internal.company.com',
      'root' => '/path/to/remote/drupal/root',
    );

The 'parent' term of the internal-server alias record is ignored if the alias it references ('@intranet-proxy') is not defined; the result is that 'ssh-options' will only be defined when outside of the intranet, and the ssh ProxyCommand to the bastion server will only be included when it is needed.

With this setup, you will be able to use your site alias '@internal' to remotely operate on your internal intranet Drupal site seemlessly, regardless of your location -- a handy trick indeed.

