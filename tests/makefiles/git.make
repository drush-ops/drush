core = 6.x
api = 2

; Test that a specific tag can be pulled.
projects[tao][type] = theme
projects[tao][download][type] = git
projects[tao][download][tag] = 6.x-3.2

; Test that a branch can be pulled. We use a super-old "stale" branch in the
; Drupalbin project that we expect not to change.
projects[drupalbin][type] = profile
projects[drupalbin][download][type] = git
projects[drupalbin][download][branch] = 5.x-1.x

; Test that a specific revision can be pulled. Note that provision is not
; actually a module.
projects[visitor][type] = module
projects[visitor][download][type] = git
projects[visitor][download][revision] = 5f256032cd4bcc2db45c962306d12c85131388ef

; Test a non-Drupal.org repository.
projects[geocode][type] = "module"
projects[geocode][download][type] = "git"
projects[geocode][download][url] = "https://github.com/phayes/geocode.git"
projects[geocode][download][revision] = "281c70f86"
