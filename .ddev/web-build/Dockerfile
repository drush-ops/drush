ARG BASE_IMAGE
FROM $BASE_IMAGE

# https://asciinema.org/docs/installation
RUN pip3 install asciinema

# Install Autocast https://github.com/k9withabone/autocast/tree/main#installation
# https://stackoverflow.com/questions/67092242/how-can-i-add-to-the-path-in-the-ddev-web-container-for-drush-for-example
RUN echo 'export PATH="$PATH:$HOME/.cargo/bin"' >/etc/bashrc/commandline-addons.bashrc
# https://ddev.readthedocs.io/en/latest/users/extend/customizing-images/#adding-extra-dockerfiles-for-webimage-and-dbimage
USER $uid
RUN curl -L --proto '=https' --tlsv1.2 -sSf https://raw.githubusercontent.com/cargo-bins/cargo-binstall/main/install-from-binstall-release.sh | bash
RUN cargo-binstall --no-confirm autocast
USER root
