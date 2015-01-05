core = 7.x
api = 2

; Main makefile containing drupal core
includes[platform][makefile] = 'tests/makefiles/includes-platform.make'
includes[platform][download][type] = "git"
includes[platform][download][url] = "https://github.com/pmatias/drush.git"
includes[platform][download][branch] = "includes-git-support"

; Sub platform makefile 
includes[subplatform][makefile] = 'tests/makefiles/includes-sub-platform.make'
includes[subplatform][download][type] = "git"
includes[subplatform][download][url] = "https://github.com/pmatias/drush.git"
includes[subplatform][download][branch] = "includes-git-support"
