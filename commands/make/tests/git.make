core = 6.x
api = 2

; Test that a specific tag can be pulled.
projects[tao][type] = theme
projects[tao][download][type] = git
projects[tao][download][tag] = 6.x-3.2

; Test that a branch can be pulled. We use a super-old "stale" branch in the
; Aegir project that we expect not to change.
projects[hostmaster][type] = profile
projects[hostmaster][download][type] = git
projects[hostmaster][download][branch] = 5.x

; Test that a specific revision can be pulled. Note that provision is not
; actually a module.
projects[provision][type] = module
projects[provision][download][type] = git
projects[provision][download][revision] = 23ccccd074b0c5c92df7c3a2a298907250525421

