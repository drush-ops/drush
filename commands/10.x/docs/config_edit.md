# config:edit

Open a config file in a text editor. Edits are imported after closing editor.

#### Examples

- <code>drush config:edit image.style.large</code>. Edit the image style configurations.
- <code>drush config:edit</code>. Choose a config file to edit.
- <code>drush --bg config-edit image.style.large</code>. Return to shell prompt as soon as the editor window opens.

#### Arguments

- **config_name**. The config object name, for example "system.site".

#### Aliases

- cedit
- config-edit

